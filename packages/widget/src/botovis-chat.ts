// ─────────────────────────────────────────────────
//  Botovis Widget — Main Custom Element (v2 Drawer)
// ─────────────────────────────────────────────────

import { BotovisApi, BotovisApiError } from './api';
import { icons, actionIcon } from './icons';
import { t, type Locale } from './i18n';
import { styles } from './styles';
import type {
  BotovisConfig,
  ChatMessage,
  ApiResponse,
  ResolvedIntent,
  ActionResult,
  SchemaTable,
  SuggestedAction,
  ConversationSummary,
} from './types';

export class BotovisChat extends HTMLElement {

  // ── Observed attributes ────────────────────────
  static observedAttributes = [
    'endpoint', 'lang', 'theme', 'position',
    'title', 'placeholder', 'csrf-token', 'sounds', 'streaming',
  ];

  // ── State ──────────────────────────────────────
  private shadow: ShadowRoot;
  private api!: BotovisApi;
  private messages: ChatMessage[] = [];
  private conversationId: string | null = null;
  private isOpen = false;
  private isLoading = false;
  private hasPending = false;
  private unreadCount = 0;
  private schemaTables: SchemaTable[] = [];
  private darkMediaQuery: MediaQueryList | null = null;
  private boundKeyHandler: ((e: KeyboardEvent) => void) | null = null;

  // History
  private isHistoryOpen = false;
  private conversations: ConversationSummary[] = [];
  private isLoadingHistory = false;

  // Streaming
  private streamController: AbortController | null = null;
  private currentStreamingMessageId: string | null = null;
  private streamingSteps: Array<{step: number; thought: string; action?: string; observation?: string}> = [];

  // Resize
  private drawerWidth = 420;

  // Timeline expand state
  private expandedTimelines = new Set<string>();

  // Current theme state
  private isDarkTheme = false;

  constructor() {
    super();
    this.shadow = this.attachShadow({ mode: 'open' });
  }

  // ── Lifecycle ──────────────────────────────────

  connectedCallback(): void {
    this.api = new BotovisApi(this.cfg.endpoint, this.cfg.csrfToken);
    this.render();
    this.bindEvents();
    this.setupKeyboard();
    this.setupTheme();
    this.setupResize();
    this.fetchSchema();
  }

  disconnectedCallback(): void {
    if (this.boundKeyHandler) {
      document.removeEventListener('keydown', this.boundKeyHandler);
    }
    this.darkMediaQuery?.removeEventListener('change', this.handleMediaChange);
  }

  attributeChangedCallback(name: string, _old: string | null, val: string | null): void {
    if (name === 'endpoint' && val) this.api?.updateEndpoint(val);
    if (name === 'csrf-token' && val) this.api?.updateCsrfToken(val);
    if (name === 'theme') this.applyTheme();
  }

  // ── Config ─────────────────────────────────────

  get cfg(): BotovisConfig {
    return {
      endpoint: this.getAttribute('endpoint') || '/botovis',
      lang: (this.getAttribute('lang') as Locale) || 'tr',
      theme: (this.getAttribute('theme') as 'light' | 'dark' | 'auto') || 'auto',
      position: (this.getAttribute('position') as 'bottom-right' | 'bottom-left') || 'bottom-right',
      title: this.getAttribute('title') || t('title', this.locale),
      placeholder: this.getAttribute('placeholder') || t('placeholder', this.locale),
      csrfToken: this.getAttribute('csrf-token') || this.detectCsrf(),
      sounds: this.getAttribute('sounds') !== 'false',
      streaming: this.getAttribute('streaming') !== 'false',
    };
  }

  private get locale(): Locale { return (this.getAttribute('lang') as Locale) || 'tr'; }

  private i(key: string, params?: Record<string, string | number>): string {
    return t(key, this.locale, params);
  }

  // ── Public API ─────────────────────────────────

  open(): void  { if (!this.isOpen) this.toggle(); }
  close(): void { if (this.isOpen) this.toggle(); }

  toggle(): void {
    this.isOpen = !this.isOpen;
    this.$('drawer')?.classList.toggle('bv-open', this.isOpen);
    this.$('fab')?.classList.toggle('bv-hidden', this.isOpen);

    if (this.isOpen) {
      this.unreadCount = 0;
      this.updateBadge();
      setTimeout(() => this.$('input')?.focus(), 100);
    }

    this.dispatchEvent(new CustomEvent(this.isOpen ? 'botovis:open' : 'botovis:close'));
  }

  // ── Send ───────────────────────────────────────

  async send(text: string): Promise<void> {
    if (!text.trim() || this.isLoading) return;

    this.addUserMessage(text);

    if (this.cfg.streaming) {
      // For streaming: just disable send button, don't add loading dots
      // (the streaming placeholder message handles the loading UI)
      this.isLoading = true;
      const sendBtn = this.$('btn-send') as HTMLButtonElement | null;
      if (sendBtn) sendBtn.disabled = true;
      this.sendWithStreaming(text);
      return;
    }

    this.setLoading(true);

    try {
      const response = await this.api.chat(text, this.conversationId ?? undefined);
      this.processResponse(response);
    } catch (err) {
      this.handleError(err);
    } finally {
      this.setLoading(false);
    }
  }

  private sendWithStreaming(text: string): void {
    this.streamingSteps = [];
    this.currentStreamingMessageId = this.uid();

    this.addMessage({
      id: this.currentStreamingMessageId,
      role: 'assistant',
      type: 'loading',
      content: '',
      timestamp: new Date(),
    });

    this.streamController = this.api.streamChat(
      text,
      this.conversationId ?? undefined,
      {
        onInit: (conversationId) => {
          this.conversationId = conversationId;
        },

        onStep: (step) => {
          this.dispatchEvent(new CustomEvent('botovis:step', { detail: step }));

          this.streamingSteps.push({
            step: step.step,
            thought: step.thought,
            action: step.action ?? undefined,
            observation: step.observation ?? undefined,
          });

          this.updateStreamingStepsUI();
        },

        onMessage: (content) => {
          this.finalizeStreamingMessage(content, 'text');
        },

        onConfirmation: (action, params, description) => {
          this.hasPending = true;
          this.finalizeStreamingMessage(description, 'confirmation', {
            type: 'operation',
            action,
            table: params.table as string || null,
            data: params,
            where: {},
            select: [],
            message: description,
            confidence: 1,
            auto_continue: false,
          });
        },

        onError: (error) => {
          this.finalizeStreamingMessage(error.message, 'error');
        },

        onDone: () => {
          this.setLoading(false);
          this.streamController = null;
          if (!this.isOpen && this.cfg.sounds) this.playSound();
        },

        onAbort: () => {
          this.setLoading(false);
          this.streamController = null;
        },
      }
    );
  }

  private updateStreamingStepsUI(): void {
    if (!this.currentStreamingMessageId) return;

    const msgIndex = this.messages.findIndex(m => m.id === this.currentStreamingMessageId);
    if (msgIndex === -1) return;

    const steps = this.streamingSteps;
    const currentStep = steps[steps.length - 1];
    if (!currentStep) return;

    const completedCount = steps.filter(s => s.observation).length;
    const isRunningTool = currentStep.action && !currentStep.observation;

    // Build timeline steps
    let timelineHtml = '';
    for (const s of steps) {
      const isDone = !!s.observation;
      const isActive = s === currentStep && !isDone;
      const cls = isDone ? 'bv-done' : (isActive ? 'bv-running' : '');

      timelineHtml += `<div class="bv-tl-step ${cls}">`;
      timelineHtml += `<div class="bv-tl-thought">${this.escapeHtml(s.thought)}</div>`;
      if (s.action) {
        timelineHtml += `<div class="bv-tl-action">${this.escapeHtml(this.formatToolName(s.action))}</div>`;
      }
      timelineHtml += `</div>`;
    }

    const thinkingText = isRunningTool
      ? `${this.i('running')}: ${this.formatToolName(currentStep.action!)}`
      : currentStep.thought;

    const html = `
      <div class="bv-thinking-line">
        <div class="bv-spinner"></div>
        <span class="bv-thinking-label">${this.escapeHtml(thinkingText)}</span>
        ${completedCount > 0 ? `<span class="bv-thinking-count">${completedCount} araç</span>` : ''}
      </div>
      <div class="bv-timeline bv-visible">${timelineHtml}</div>
    `;

    this.messages[msgIndex].type = 'streaming';
    this.messages[msgIndex].content = html;
    this.renderMessages();
  }

  private formatToolName(action: string): string {
    const match = action.match(/^(\w+)/);
    if (match) {
      const name = match[1];
      const names: Record<string, string> = {
        'search_records': 'arama',
        'count_records': 'sayım',
        'get_sample_data': 'veri çekme',
        'get_column_stats': 'istatistik',
        'aggregate': 'hesaplama',
        'create_record': 'oluşturma',
        'update_record': 'güncelleme',
        'delete_record': 'silme',
      };
      return names[name] || name.replace(/_/g, ' ');
    }
    return action.substring(0, 15);
  }

  private escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  private finalizeStreamingMessage(
    content: string,
    type: ChatMessage['type'],
    intent?: ResolvedIntent,
  ): void {
    if (!this.currentStreamingMessageId) return;

    const msgIndex = this.messages.findIndex(m => m.id === this.currentStreamingMessageId);
    if (msgIndex !== -1) {
      // Save timeline steps with the message for later rendering
      const stepsSnapshot = [...this.streamingSteps];

      this.messages[msgIndex] = {
        ...this.messages[msgIndex],
        type,
        content,
        intent: intent || null,
        _steps: stepsSnapshot,
      };
      this.renderMessages();
    }
    this.currentStreamingMessageId = null;
  }

  cancelStream(): void {
    if (this.streamController) {
      this.streamController.abort();
      this.streamController = null;
      this.setLoading(false);
    }
  }

  // ── Initial Render ─────────────────────────────

  private render(): void {
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(styles);
    this.shadow.adoptedStyleSheets = [sheet];

    const pos = this.cfg.position;
    const root = document.createElement('div');
    root.className = `bv-root${pos === 'bottom-left' ? ' bv-left' : ''}`;
    root.id = 'root';
    root.innerHTML = `
      ${this.renderDrawer()}
      <button class="bv-fab" id="fab" aria-label="${this.i('shortcutToggle')}">
        <span class="bv-fab-icon" id="fab-icon">${icons.chat}</span>
        <span class="bv-badge" id="badge"></span>
      </button>
      <div class="bv-toasts" id="toasts"></div>
    `;
    this.shadow.appendChild(root);
    this.applyTheme();
  }

  private renderDrawer(): string {
    return `
      <div class="bv-drawer" id="drawer" style="width:${this.drawerWidth}px">
        <div class="bv-resize-handle" id="resize-handle"></div>

        <div class="bv-header">
          <div class="bv-header-logo">
            ${icons.logo}
          </div>
          <button class="bv-hbtn" id="btn-new" title="${this.i('newConversation')}">${icons.plus}</button>
          <button class="bv-hbtn" id="btn-history" title="${this.i('conversations')}">${icons.clock}</button>
          <span class="bv-hsep"></span>
          <button class="bv-hbtn" id="btn-theme" title="${this.i('themeLight')}">${icons.sun}</button>
          <button class="bv-hbtn" id="btn-close" title="${this.i('close')}">${icons.close}</button>
        </div>

        <div class="bv-content">
          <!-- History overlay -->
          <div class="bv-history-panel" id="history-panel">
            <div class="bv-history-header">
              <button class="bv-hbtn" id="btn-history-back">${icons.arrowLeft}</button>
              <span class="bv-history-title">${this.i('conversations')}</span>
            </div>
            <div class="bv-history-list" id="history-list">
              ${this.renderHistoryContent()}
            </div>
          </div>

          <!-- Messages -->
          <div class="bv-messages" id="messages">
            ${this.renderEmptyState()}
          </div>
        </div>

        <div class="bv-input-area">
          <div class="bv-input-row">
            <textarea class="bv-textarea" id="input"
              placeholder="${this.esc(this.cfg.placeholder)}"
              rows="1"
              aria-label="${this.i('placeholder')}"
            ></textarea>
            <button class="bv-send-btn" id="btn-send" title="${this.i('send')}">${icons.send}</button>
          </div>
          <div class="bv-input-suggestions" id="input-suggestions"></div>
        </div>
        <div class="bv-input-hint">
          <span class="bv-kbd">Enter</span> ${this.i('shortcutSend')} · <span class="bv-kbd">Esc</span> ${this.i('shortcutClose')}
        </div>
      </div>
    `;
  }

  private renderHistoryContent(): string {
    if (this.isLoadingHistory) {
      return `<div class="bv-history-empty">${this.i('loadingConversations')}</div>`;
    }

    if (this.conversations.length === 0) {
      return `
        <div class="bv-history-empty">
          ${icons.messageSquare}
          <span>${this.i('noConversations')}</span>
        </div>
      `;
    }

    return this.conversations.map(conv => `
      <div class="bv-history-item" data-action="select-conversation" data-id="${conv.id}">
        <div class="bv-history-item-content">
          <div class="bv-history-item-title">${this.esc(conv.title)}</div>
          <div class="bv-history-item-meta">${this.formatDate(conv.updated_at)} · ${conv.message_count} mesaj</div>
        </div>
        <button class="bv-history-item-delete" data-action="delete-conversation" data-id="${conv.id}" title="${this.i('deleteConversation')}">
          ${icons.trash}
        </button>
      </div>
    `).join('');
  }

  private formatDate(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) {
      return date.toLocaleTimeString(this.locale, { hour: '2-digit', minute: '2-digit' });
    } else if (days === 1) {
      return this.locale === 'tr' ? 'Dün' : 'Yesterday';
    } else if (days < 7) {
      return date.toLocaleDateString(this.locale, { weekday: 'short' });
    } else {
      return date.toLocaleDateString(this.locale, { day: 'numeric', month: 'short' });
    }
  }

  private renderEmptyState(): string {
    const suggestions = this.generateSuggestions();
    const suggestHtml = suggestions.length > 0
      ? `<div class="bv-suggestions">
           ${suggestions.map(s => `
             <button class="bv-suggestion" data-action="suggest" data-message="${this.esc(s.message)}">
               <span class="bv-suggestion-icon">${s.icon}</span>
               ${this.esc(s.label)}
             </button>
           `).join('')}
         </div>`
      : '';

    return `
      <div class="bv-empty" id="empty-state">
        <div class="bv-empty-icon">${icons.command}</div>
        <div class="bv-empty-text">${this.i('emptyState')}</div>
        ${suggestHtml}
      </div>
    `;
  }

  // ── Event Binding ──────────────────────────────

  private bindEvents(): void {
    this.shadow.addEventListener('click', (e) => {
      const target = e.target as HTMLElement;

      // FAB
      const fab = target.closest('#fab') as HTMLElement | null;
      if (fab) { this.toggle(); return; }

      const btn = target.closest('[id], [data-action]') as HTMLElement | null;
      if (!btn) return;

      const id = btn.id;
      const action = btn.dataset.action;

      if (id === 'btn-close') this.close();
      if (id === 'btn-send') this.handleSend();
      if (id === 'btn-new') this.startNewConversation();
      if (id === 'btn-history') this.toggleHistory();
      if (id === 'btn-history-back') this.toggleHistory();
      if (id === 'btn-theme') this.toggleTheme();

      if (action === 'confirm') this.handleConfirm();
      if (action === 'reject') this.handleReject();
      if (action === 'toggle-details') {
        const details = btn.closest('.bv-confirm-card')?.querySelector('.bv-confirm-details') as HTMLElement | null;
        if (details) {
          const isOpen = details.classList.toggle('bv-open');
          const label = btn.querySelector('.bv-detail-label');
          if (label) label.textContent = isOpen ? this.i('hideDetails') : this.i('details');
          btn.classList.toggle('bv-open', isOpen);
        }
      }
      if (action === 'suggest') {
        const msg = btn.dataset.message;
        if (msg) this.send(msg);
      }
      if (action === 'select-conversation') {
        const convId = btn.dataset.id;
        if (convId) this.loadConversation(convId);
      }
      if (action === 'delete-conversation') {
        e.stopPropagation();
        const convId = btn.dataset.id;
        if (convId) this.deleteConversation(convId);
      }
      if (action === 'toggle-timeline') {
        const msgId = btn.dataset.msgId;
        if (msgId) this.toggleTimeline(msgId);
      }
    });

    // Textarea
    const textarea = this.$('input') as HTMLTextAreaElement | null;
    if (textarea) {
      textarea.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.handleSend();
        }
      });

      textarea.addEventListener('input', () => {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
      });
    }
  }

  private setupKeyboard(): void {
    this.boundKeyHandler = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        this.toggle();
      }
      if (e.key === 'Escape' && this.isOpen) {
        if (this.isHistoryOpen) {
          this.toggleHistory();
        } else {
          this.close();
        }
      }
    };
    document.addEventListener('keydown', this.boundKeyHandler);
  }

  // ── Resize ─────────────────────────────────────

  private setupResize(): void {
    const handle = this.$('resize-handle');
    if (!handle) return;

    const isLeft = this.cfg.position === 'bottom-left';
    let startX = 0;
    let startWidth = 0;

    const onMouseMove = (e: MouseEvent) => {
      const diff = isLeft ? (e.clientX - startX) : (startX - e.clientX);
      const newWidth = Math.max(320, Math.min(startWidth + diff, window.innerWidth * 0.9));
      this.drawerWidth = newWidth;
      const drawer = this.$('drawer') as HTMLElement;
      if (drawer) drawer.style.width = newWidth + 'px';
    };

    const onMouseUp = () => {
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      handle.classList.remove('bv-active');
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
    };

    handle.addEventListener('mousedown', (e: MouseEvent) => {
      e.preventDefault();
      startX = e.clientX;
      startWidth = this.drawerWidth;
      handle.classList.add('bv-active');
      document.body.style.cursor = 'ew-resize';
      document.body.style.userSelect = 'none';
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    });
  }

  // ── Theme ──────────────────────────────────────

  private handleMediaChange = (): void => { this.applyTheme(); };

  private setupTheme(): void {
    this.darkMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    this.darkMediaQuery.addEventListener('change', this.handleMediaChange);
    this.applyTheme();
  }

  private applyTheme(): void {
    const root = this.$('root');
    if (!root) return;

    const theme = this.cfg.theme;
    if (theme === 'dark') this.isDarkTheme = true;
    else if (theme === 'light') this.isDarkTheme = false;
    else this.isDarkTheme = !!this.darkMediaQuery?.matches;

    root.classList.toggle('bv-dark', this.isDarkTheme);
    this.updateThemeButton();
  }

  private toggleTheme(): void {
    this.isDarkTheme = !this.isDarkTheme;
    const root = this.$('root');
    if (root) root.classList.toggle('bv-dark', this.isDarkTheme);
    this.updateThemeButton();
  }

  private updateThemeButton(): void {
    const btn = this.$('btn-theme');
    if (btn) {
      btn.innerHTML = this.isDarkTheme ? icons.moon : icons.sun;
      btn.title = this.isDarkTheme ? this.i('themeDark') : this.i('themeLight');
    }
  }

  // ── Timeline Toggle ────────────────────────────

  private toggleTimeline(msgId: string): void {
    const el = this.shadow.querySelector(`[data-timeline-id="${msgId}"]`) as HTMLElement;
    const btn = this.shadow.querySelector(`[data-msg-id="${msgId}"]`) as HTMLElement;
    if (!el) return;

    if (this.expandedTimelines.has(msgId)) {
      this.expandedTimelines.delete(msgId);
      el.classList.remove('bv-visible');
      btn?.classList.remove('bv-expanded');
    } else {
      this.expandedTimelines.add(msgId);
      el.classList.add('bv-visible');
      btn?.classList.add('bv-expanded');
    }
  }

  // ── Message Flow ───────────────────────────────

  private async handleSend(): Promise<void> {
    const textarea = this.$('input') as HTMLTextAreaElement | null;
    if (!textarea) return;
    const text = textarea.value.trim();
    if (!text) return;
    textarea.value = '';
    textarea.style.height = 'auto';
    await this.send(text);
  }

  private async handleConfirm(): Promise<void> {
    if (!this.conversationId || !this.hasPending) return;
    this.hasPending = false;
    this.disableConfirmButtons();

    if (this.cfg.streaming) {
      this.confirmWithStreaming();
      return;
    }

    this.setLoading(true);
    try {
      const response = await this.api.confirm(this.conversationId);
      this.processResponse(response);
    } catch (err) {
      this.handleError(err);
    } finally {
      this.setLoading(false);
    }
  }

  private confirmWithStreaming(): void {
    this.streamingSteps = [];
    this.currentStreamingMessageId = this.uid();

    this.addMessage({
      id: this.currentStreamingMessageId,
      role: 'assistant',
      type: 'loading',
      content: '',
      timestamp: new Date(),
    });

    this.isLoading = true;
    const sendBtn = this.$('btn-send') as HTMLButtonElement | null;
    if (sendBtn) sendBtn.disabled = true;

    this.streamController = this.api.streamConfirm(
      this.conversationId!,
      {
        onStep: (step) => {
          this.streamingSteps.push({
            step: step.step,
            thought: step.thought,
            action: step.action ?? undefined,
            observation: step.observation ?? undefined,
          });
          this.updateStreamingStepsUI();
        },

        onConfirmation: (action, params, description) => {
          this.hasPending = true;
          this.finalizeStreamingMessage(description, 'confirmation', {
            type: 'operation',
            action,
            table: params.table as string || null,
            data: params,
            where: {},
            select: [],
            message: description,
            confidence: 1,
            auto_continue: false,
          });
        },

        onMessage: (content) => {
          this.finalizeStreamingMessage(content, 'text');
        },

        onError: (error) => {
          this.finalizeStreamingMessage(error.message, 'error');
        },

        onDone: () => {
          this.isLoading = false;
          if (sendBtn) sendBtn.disabled = false;
          this.streamController = null;
        },

        onAbort: () => {
          this.isLoading = false;
          if (sendBtn) sendBtn.disabled = false;
          this.streamController = null;
        },
      }
    );
  }

  private async handleReject(): Promise<void> {
    if (!this.conversationId || !this.hasPending) return;
    this.hasPending = false;
    this.disableConfirmButtons('rejected');
    this.setLoading(true);

    try {
      const response = await this.api.reject(this.conversationId);
      this.processResponse(response);
    } catch (err) {
      this.handleError(err);
    } finally {
      this.setLoading(false);
    }
  }

  private async handleReset(): Promise<void> {
    if (this.conversationId) {
      try { await this.api.reset(this.conversationId); } catch { /* ignore */ }
    }
    this.messages = [];
    this.conversationId = null;
    this.hasPending = false;
    const container = this.$('messages');
    if (container) container.innerHTML = this.renderEmptyState();
    this.toast(this.i('resetDone'), 'info');
  }

  private processResponse(response: ApiResponse): void {
    this.conversationId = response.conversation_id;

    if (response.steps?.length) {
      for (const step of response.steps) {
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'action',
          content: '', timestamp: new Date(),
          intent: step.intent, result: step.result,
        });
      }
    }

    switch (response.type) {
      case 'message':
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'text',
          content: response.message, timestamp: new Date(),
          intent: response.intent,
        });
        break;

      case 'confirmation':
        this.hasPending = true;
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'confirmation',
          content: response.message, timestamp: new Date(),
          intent: response.intent,
        });
        break;

      case 'executed':
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'executed',
          content: response.message, timestamp: new Date(),
          intent: response.intent, result: response.result,
        });
        break;

      case 'rejected':
        this.hasPending = false;
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'rejected',
          content: response.message, timestamp: new Date(),
        });
        break;

      case 'error':
        this.addMessage({
          id: this.uid(), role: 'assistant', type: 'error',
          content: response.message, timestamp: new Date(),
        });
        break;
    }

    if (!this.isOpen && this.cfg.sounds) this.playSound();
  }

  private handleError(err: unknown): void {
    let msg = this.i('error');

    if (err instanceof BotovisApiError) {
      if (err.status === 401 || err.status === 403) msg = this.i('sessionExpired');
      else if (err.status === 429) msg = this.i('tooManyRequests');
      else if (err.status === 419) msg = this.i('sessionExpired');
    } else if (err instanceof TypeError) {
      msg = this.i('connectionError');
    }

    this.addMessage({
      id: this.uid(), role: 'assistant', type: 'error',
      content: msg, timestamp: new Date(),
    });
    this.toast(msg, 'error');
  }

  // ── DOM Updates ────────────────────────────────

  private addUserMessage(text: string): void {
    this.addMessage({
      id: this.uid(), role: 'user', type: 'text',
      content: text, timestamp: new Date(),
    });
  }

  private addMessage(msg: ChatMessage): void {
    this.messages.push(msg);
    const container = this.$('messages')!;

    const empty = container.querySelector('.bv-empty');
    if (empty) empty.remove();

    const wrapper = document.createElement('div');
    wrapper.innerHTML = this.renderMessage(msg);
    const el = wrapper.firstElementChild;
    if (el) container.appendChild(el);

    this.scrollToBottom();

    if (!this.isOpen && msg.role === 'assistant') {
      this.unreadCount++;
      this.updateBadge();
    }
  }

  private setLoading(loading: boolean): void {
    this.isLoading = loading;
    const container = this.$('messages');
    const sendBtn = this.$('btn-send') as HTMLButtonElement | null;

    if (sendBtn) sendBtn.disabled = loading;

    if (loading) {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = this.renderLoading();
      const el = wrapper.firstElementChild;
      if (el) container?.appendChild(el);
      this.scrollToBottom();
    } else {
      container?.querySelector('.bv-msg-loading')?.remove();
    }
  }

  private updateBadge(): void {
    const badge = this.$('badge');
    if (!badge) return;
    badge.textContent = this.unreadCount > 0 ? String(this.unreadCount) : '';
    badge.classList.toggle('bv-visible', this.unreadCount > 0);
  }

  private disableConfirmButtons(state: 'confirmed' | 'rejected' = 'confirmed'): void {
    // Persist the state in the message data so re-renders preserve it
    const confirmMsg = this.messages.find(m => m.type === 'confirmation' && !m._confirmState);
    if (confirmMsg) {
      confirmMsg._confirmState = state;
    }

    // Re-render all messages so the correct card updates from data
    this.renderMessages();
  }

  private scrollToBottom(): void {
    const container = this.$('messages');
    if (container) {
      requestAnimationFrame(() => {
        container.scrollTop = container.scrollHeight;
      });
    }
  }

  private renderMessages(): void {
    const container = this.$('messages');
    if (!container) return;

    if (this.messages.length === 0) {
      container.innerHTML = this.renderEmptyState();
      return;
    }

    container.innerHTML = this.messages.map(msg => this.renderMessage(msg)).join('');
    this.scrollToBottom();
  }

  // ── Message Rendering (Flat Style) ─────────────

  private renderMessage(msg: ChatMessage): string {
    switch (msg.type) {
      case 'text':         return this.renderTextMsg(msg);
      case 'action':       return this.renderActionMsg(msg);
      case 'confirmation': return this.renderConfirmMsg(msg);
      case 'executed':     return this.renderExecutedMsg(msg);
      case 'rejected':     return this.renderRejectedMsg(msg);
      case 'error':        return this.renderErrorMsg(msg);
      case 'streaming':    return this.renderStreamingMsg(msg);
      default:             return this.renderTextMsg(msg);
    }
  }

  private msgHeader(msg: ChatMessage): string {
    const isUser = msg.role === 'user';
    const avatar = isUser ? 'S' : 'B';
    const name = isUser ? this.i('you') : this.i('assistant');

    return `
      <div class="bv-msg-header">
        <div class="bv-msg-avatar">${avatar}</div>
        <span class="bv-msg-name">${name}</span>
        <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
      </div>`;
  }

  private renderStreamingMsg(msg: ChatMessage): string {
    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">${msg.content}</div>
      </div>`;
  }

  private renderTextMsg(msg: ChatMessage): string {
    const cls = msg.role === 'user' ? 'bv-msg-user' : 'bv-msg-assistant';
    const body = msg.role === 'user'
      ? this.esc(msg.content)
      : `<div class="bv-md">${this.renderStepsTimeline(msg)}${this.markdown(msg.content)}</div>`;

    return `
      <div class="bv-msg ${cls}">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">${body}</div>
      </div>`;
  }

  private renderActionMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    const result = msg.result;
    if (!intent) return this.renderTextMsg(msg);

    let body = this.renderIntentCard(intent);

    if (result) {
      body += result.success
        ? `<div class="bv-result-success">${icons.checkCircle} ${this.esc(result.message)}</div>`
        : `<div class="bv-result-error">${icons.xCircle} ${this.esc(result.message)}</div>`;

      if (result.success && result.data) {
        body += this.renderDataTable(result.data as Record<string, unknown>[]);
      }
    }

    if (intent.auto_continue) {
      body += `<div class="bv-step-indicator"><span class="bv-step-dot"></span>${this.i('autoStep')}</div>`;
    }

    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">${body}</div>
      </div>`;
  }

  private renderConfirmMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    const desc = msg.content ? `<div class="bv-confirm-desc">${this.esc(msg.content)}</div>` : '';

    // Build action label (user-friendly)
    let actionLabel = this.i('confirmAction_default');
    if (intent?.action) {
      const key = `confirmAction_${intent.action.replace('_record', '')}`;
      actionLabel = this.i(key) || this.i('confirmAction_default');
    }

    // Build details table (collapsible)
    let detailsHtml = '';
    if (intent) {
      const rows: Array<[string, string]> = [];

      // Show where conditions as readable fields
      if (intent.where && Object.keys(intent.where).length > 0) {
        for (const [k, v] of Object.entries(intent.where)) {
          rows.push([this.friendlyFieldName(k), this.valStr(v)]);
        }
      }

      // Show data fields (excluding 'table')
      if (intent.data && Object.keys(intent.data).length > 0) {
        for (const [k, v] of Object.entries(intent.data)) {
          if (k === 'table' || k === 'where' || k === 'select') continue;
          rows.push([this.friendlyFieldName(k), this.valStr(v)]);
        }
      }

      if (rows.length > 0) {
        const tableRows = rows.map(([field, val]) =>
          `<tr><td class="bv-detail-field">${this.esc(field)}</td><td class="bv-detail-value">${this.esc(val)}</td></tr>`
        ).join('');

        detailsHtml = `
          <button class="bv-detail-toggle" data-action="toggle-details">
            ${icons.chevronDown}
            <span class="bv-detail-label">${this.i('details')}</span>
          </button>
          <div class="bv-confirm-details">
            <table class="bv-detail-table">
              <thead><tr><th>${this.i('fieldName')}</th><th>${this.i('fieldValue')}</th></tr></thead>
              <tbody>${tableRows}</tbody>
            </table>
          </div>`;
      }
    }

    // Determine card state class and actions HTML based on _confirmState
    const cState = msg._confirmState;
    const cardStateClass = cState ? `bv-confirm-${cState}` : '';
    let actionsHtml: string;

    if (cState === 'confirmed') {
      actionsHtml = `
        <div class="bv-confirm-actions">
          <div class="bv-confirm-status bv-confirm-status-ok">
            ${icons.checkCircle}
            <span>${this.i('confirmed')}</span>
          </div>
        </div>`;
    } else if (cState === 'rejected') {
      actionsHtml = `
        <div class="bv-confirm-actions">
          <div class="bv-confirm-status bv-confirm-status-rejected">
            ${icons.xCircle}
            <span>${this.i('rejected')}</span>
          </div>
        </div>`;
    } else {
      actionsHtml = `
        <div class="bv-confirm-actions">
          <button class="bv-btn bv-btn-confirm" data-action="confirm">${icons.check} ${this.i('confirm')}</button>
          <button class="bv-btn bv-btn-reject" data-action="reject">${icons.x} ${this.i('reject')}</button>
        </div>`;
    }

    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">
          ${this.renderStepsTimeline(msg)}
          <div class="bv-confirm-card ${cardStateClass}">
            <div class="bv-confirm-header">
              <div class="bv-confirm-icon">${icons.shield}</div>
              <div class="bv-confirm-header-text">
                <div class="bv-confirm-title">${this.i('confirmQuestion')}</div>
                <div class="bv-confirm-action-label">${this.esc(actionLabel)}</div>
              </div>
            </div>
            ${desc}
            ${detailsHtml}
            ${actionsHtml}
          </div>
        </div>
      </div>`;
  }

  private friendlyFieldName(key: string): string {
    // Convert snake_case to readable: first_name → First Name
    return key.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
  }

  private renderExecutedMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    const result = msg.result;

    let body = '';
    if (intent) body += this.renderIntentCard(intent);

    if (result) {
      body += result.success
        ? `<div class="bv-result-success">${icons.checkCircle} ${this.esc(result.message)}</div>`
        : `<div class="bv-result-error">${icons.xCircle} ${this.esc(result.message)}</div>`;

      if (result.success && result.data) {
        const data = result.data as Record<string, unknown>[] | Record<string, unknown>;
        const isWrite = intent?.action && intent.action !== 'read';
        if (isWrite) {
          body += this.renderCompactResult(data, intent!);
        } else {
          body += this.renderDataTable(Array.isArray(data) ? data : [data]);
        }
      }
    }

    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">${body}</div>
      </div>`;
  }

  private renderRejectedMsg(msg: ChatMessage): string {
    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">
          <div class="bv-rejected-msg">${icons.xCircle} ${this.esc(msg.content || this.i('operationCancelled'))}</div>
        </div>
      </div>`;
  }

  private renderErrorMsg(msg: ChatMessage): string {
    return `
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(msg)}
        <div class="bv-msg-body">
          <div class="bv-result-error">${icons.alert} ${this.esc(msg.content)}</div>
        </div>
      </div>`;
  }

  private renderLoading(): string {
    return `
      <div class="bv-msg bv-msg-assistant bv-msg-loading">
        <div class="bv-msg-header">
          <div class="bv-msg-avatar">B</div>
          <span class="bv-msg-name">${this.i('assistant')}</span>
        </div>
        <div class="bv-msg-body">
          <div class="bv-typing-dots">
            <span class="bv-typing-dot"></span>
            <span class="bv-typing-dot"></span>
            <span class="bv-typing-dot"></span>
          </div>
        </div>
      </div>`;
  }

  // ── Steps Timeline ─────────────────────────────

  private renderStepsTimeline(msg: ChatMessage): string {
    const steps = msg._steps;
    if (!steps || steps.length === 0) return '';

    const isExpanded = this.expandedTimelines.has(msg.id);
    const completedCount = steps.filter(s => s.observation).length;

    let timelineHtml = '';
    for (const s of steps) {
      const isDone = !!s.observation;
      const cls = isDone ? 'bv-done' : '';

      timelineHtml += `<div class="bv-tl-step ${cls}">`;
      timelineHtml += `<div class="bv-tl-thought">${this.escapeHtml(s.thought)}</div>`;
      if (s.action) {
        timelineHtml += `<div class="bv-tl-action">${this.escapeHtml(this.formatToolName(s.action))}</div>`;
      }
      timelineHtml += `</div>`;
    }

    return `
      <button class="bv-timeline-toggle${isExpanded ? ' bv-expanded' : ''}" data-action="toggle-timeline" data-msg-id="${msg.id}">
        ${icons.chevronRight}
        ${this.i('steps', { count: steps.length })}
        <span class="bv-tl-badge">${completedCount}</span>
      </button>
      <div class="bv-timeline${isExpanded ? ' bv-visible' : ''}" data-timeline-id="${msg.id}">
        ${timelineHtml}
      </div>
    `;
  }

  // ── Shared Renderers ───────────────────────────

  private renderIntentCard(intent: ResolvedIntent): string {
    const actionClass = intent.action ? `bv-action-${intent.action}` : '';

    let rows = '';
    rows += `<div class="bv-intent-row"><span class="bv-intent-label">${this.i('table')}</span><span class="bv-intent-value">${this.esc(intent.table || '-')}</span></div>`;
    rows += `<div class="bv-intent-row"><span class="bv-intent-label">${this.i('operation')}</span><span class="bv-intent-value ${actionClass}">${this.esc(intent.action || '-')}</span></div>`;

    if (intent.data && Object.keys(intent.data).length > 0) {
      const dataStr = Object.entries(intent.data).map(([k, v]) => `${k}: ${this.valStr(v)}`).join(', ');
      rows += `<div class="bv-intent-row"><span class="bv-intent-label">${this.i('data')}</span><span class="bv-intent-value">${this.esc(dataStr)}</span></div>`;
    }

    if (intent.where && Object.keys(intent.where).length > 0) {
      const whereStr = Object.entries(intent.where).map(([k, v]) => `${k} = ${this.valStr(v)}`).join(', ');
      rows += `<div class="bv-intent-row"><span class="bv-intent-label">${this.i('condition')}</span><span class="bv-intent-value">${this.esc(whereStr)}</span></div>`;
    }

    if (intent.select && intent.select.length > 0) {
      rows += `<div class="bv-intent-row"><span class="bv-intent-label">${this.i('columns')}</span><span class="bv-intent-value">${this.esc(intent.select.join(', '))}</span></div>`;
    }

    return `
      <div class="bv-intent-card">
        <div class="bv-intent-header">${icons.target} ${this.i('actionDetected')}</div>
        <div class="bv-intent-body">${rows}</div>
      </div>`;
  }

  private renderDataTable(data: Record<string, unknown>[], maxRows = 10): string {
    if (!Array.isArray(data) || data.length === 0) return '';

    const total = data.length;
    const display = data.slice(0, maxRows);
    const headers = Object.keys(display[0] || {}).slice(0, 8);

    let html = `<div class="bv-table-wrapper"><table class="bv-table"><thead><tr>`;
    for (const h of headers) {
      html += `<th>${this.esc(h)}</th>`;
    }
    html += `</tr></thead><tbody>`;

    for (const row of display) {
      html += '<tr>';
      for (const h of headers) {
        const val = row[h];
        const str = this.cellStr(val);
        html += `<td title="${this.esc(String(val ?? ''))}">${this.esc(str)}</td>`;
      }
      html += '</tr>';
    }

    html += '</tbody></table></div>';
    html += `<div class="bv-table-footer">${this.i('resultsFound', { count: total })}`;
    if (total > maxRows) html += ` (${this.i('showingFirst', { count: maxRows })})`;
    html += `</div>`;

    return html;
  }

  private renderCompactResult(data: Record<string, unknown>[] | Record<string, unknown>, intent: ResolvedIntent): string {
    const importantKeys = new Set(['id', ...Object.keys(intent.data || {}), ...Object.keys(intent.where || {})]);
    const records = Array.isArray(data) ? data : [data];
    if (records.length === 0) return '';

    const filtered = records.map(r =>
      Object.fromEntries(Object.entries(r).filter(([k]) => importantKeys.has(k)))
    );

    return this.renderDataTable(filtered, 5);
  }

  // ── Schema & Suggestions ───────────────────────

  private async fetchSchema(): Promise<void> {
    try {
      const schema = await this.api.getSchema();
      this.schemaTables = schema.tables || [];

      // Render suggestions in both empty state and input area
      if (this.messages.length === 0) {
        const container = this.$('messages');
        if (container) container.innerHTML = this.renderEmptyState();
      }
      this.renderInputSuggestions();
    } catch {
      // Silently fail
    }
  }

  private generateSuggestions(): SuggestedAction[] {
    const actions: SuggestedAction[] = [];
    for (const table of this.schemaTables.slice(0, 4)) {
      const tableActions = table.actions || (table as any).allowed_actions || [];
      const tableName = table.table || (table as any).name || '';

      if (tableActions.includes('read')) {
        actions.push({
          label: this.i('listAll', { table: tableName }),
          message: `${tableName} listele`,
          icon: icons.search,
        });
      }
      if (tableActions.includes('create') && actions.length < 6) {
        actions.push({
          label: this.i('addNew', { table: tableName }),
          message: `Yeni ${tableName} ekle`,
          icon: icons.plus,
        });
      }
    }
    return actions.slice(0, 6);
  }

  private renderInputSuggestions(): void {
    const container = this.$('input-suggestions');
    if (!container) return;

    const suggestions = this.generateSuggestions();
    if (suggestions.length === 0) return;

    container.innerHTML = suggestions.slice(0, 4).map(s =>
      `<button class="bv-input-chip" data-action="suggest" data-message="${this.esc(s.message)}">${this.esc(s.label)}</button>`
    ).join('');
  }

  // ── Toast Notifications ────────────────────────

  private toast(message: string, type: 'success' | 'error' | 'info' = 'info'): void {
    const container = this.$('toasts');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `bv-toast bv-toast-${type}`;
    el.textContent = message;
    container.appendChild(el);

    setTimeout(() => {
      el.classList.add('bv-toast-exit');
      setTimeout(() => el.remove(), 300);
    }, 3000);
  }

  // ── Conversation History ───────────────────────

  private async toggleHistory(): Promise<void> {
    this.isHistoryOpen = !this.isHistoryOpen;
    const panel = this.$('history-panel');
    if (panel) panel.classList.toggle('bv-visible', this.isHistoryOpen);

    const btn = this.$('btn-history');
    if (btn) btn.classList.toggle('bv-active', this.isHistoryOpen);

    if (this.isHistoryOpen) {
      await this.fetchConversations();
    }
  }

  private async fetchConversations(): Promise<void> {
    this.isLoadingHistory = true;
    this.updateHistoryList();

    try {
      const response = await this.api.getConversations();
      this.conversations = response.conversations;
    } catch (err) {
      console.error('Failed to fetch conversations:', err);
      this.conversations = [];
    } finally {
      this.isLoadingHistory = false;
      this.updateHistoryList();
    }
  }

  private updateHistoryList(): void {
    const list = this.$('history-list');
    if (list) list.innerHTML = this.renderHistoryContent();
  }

  private async loadConversation(id: string): Promise<void> {
    try {
      const response = await this.api.getConversation(id);
      const conv = response.conversation;

      this.conversationId = conv.id;
      this.messages = conv.messages.map(m => {
        let type: ChatMessage['type'] = 'text';
        if (m.role === 'user') {
          type = 'text';
        } else if (m.success === false) {
          type = 'error';
        } else if (m.intent === 'executed') {
          type = 'executed';
        } else if (m.intent === 'rejected') {
          type = 'rejected';
        } else if (m.intent === 'confirmation') {
          type = 'text'; // Show as text in history (no longer actionable)
        }

        return {
          id: m.id,
          role: m.role as 'user' | 'assistant',
          type,
          content: m.content,
          timestamp: new Date(m.created_at),
          intent: m.action ? { table: m.table, action: m.action } as any : undefined,
          result: m.intent === 'executed' ? { success: m.success !== false, message: m.content } : undefined,
        } as ChatMessage;
      });

      // Close history and render messages
      this.isHistoryOpen = false;
      this.$('history-panel')?.classList.remove('bv-visible');
      this.$('btn-history')?.classList.remove('bv-active');
      this.renderMessages();
    } catch (err) {
      console.error('Failed to load conversation:', err);
      this.toast(this.i('error'), 'error');
    }
  }

  private async deleteConversation(id: string): Promise<void> {
    try {
      await this.api.deleteConversation(id);
      this.conversations = this.conversations.filter(c => c.id !== id);
      this.updateHistoryList();
      this.toast(this.i('conversationDeleted'), 'success');

      if (this.conversationId === id) {
        this.conversationId = null;
        this.messages = [];
        this.renderMessages();
      }
    } catch (err) {
      console.error('Failed to delete conversation:', err);
      this.toast(this.i('error'), 'error');
    }
  }

  private startNewConversation(): void {
    this.conversationId = null;
    this.messages = [];
    this.hasPending = false;

    if (this.isHistoryOpen) {
      this.isHistoryOpen = false;
      this.$('history-panel')?.classList.remove('bv-visible');
      this.$('btn-history')?.classList.remove('bv-active');
    }

    this.renderMessages();
    this.$('input')?.focus();
  }

  // ── Sound ──────────────────────────────────────

  private playSound(): void {
    try {
      const ctx = new AudioContext();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 800;
      gain.gain.value = 0.08;
      osc.start();
      osc.stop(ctx.currentTime + 0.1);
    } catch { /* silent fail */ }
  }

  // ── Utilities ──────────────────────────────────

  private $(id: string): HTMLElement | null {
    return this.shadow.getElementById(id);
  }

  private uid(): string {
    return Math.random().toString(36).slice(2, 10);
  }

  private esc(str: string): string {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  private markdown(text: string): string {
    if (!text) return '';

    let html = this.esc(text);

    html = html.replace(/```([\s\S]*?)```/g, '<pre class="bv-md-pre">$1</pre>');

    // Markdown tables (must be before line break conversion)
    html = html.replace(/((?:^\|.+\|$(?:\n|$))+)/gm, (block) => {
      const lines = block.trim().split('\n').filter(l => l.trim());
      if (lines.length < 2) return block;
      // Check for separator line (|---|---|)
      if (!/^\|[\s\-:|]+(?:\|[\s\-:|]+)+\|?$/.test(lines[1])) return block;

      let t = '<div class="bv-table-wrapper"><table class="bv-table"><thead><tr>';
      const hCells = lines[0].replace(/^\||\|$/g, '').split('|');
      for (const c of hCells) t += `<th>${c.trim()}</th>`;
      t += '</tr></thead><tbody>';

      for (let i = 2; i < lines.length; i++) {
        const cells = lines[i].replace(/^\||\|$/g, '').split('|');
        if (cells.length === 0) continue;
        t += '<tr>';
        for (const c of cells) {
          // Restore <br> tags that were escaped
          let cell = c.trim().replace(/&lt;br&gt;/gi, '<br>');
          // Apply inline formatting inside cells
          cell = cell.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
          cell = cell.replace(/\*([^*]+)\*/g, '<em>$1</em>');
          t += `<td>${cell}</td>`;
        }
        t += '</tr>';
      }

      t += '</tbody></table></div>';
      return t;
    });

    // Horizontal rule (---)
    html = html.replace(/^---+$/gm, '<hr class="bv-md-hr">');

    html = html.replace(/`([^`]+)`/g, '<code class="bv-md-code">$1</code>');
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    html = html.replace(/^### (.+)$/gm, '<h4 class="bv-md-h4">$1</h4>');
    html = html.replace(/^## (.+)$/gm, '<h3 class="bv-md-h3">$1</h3>');
    html = html.replace(/^# (.+)$/gm, '<h2 class="bv-md-h2">$1</h2>');
    html = html.replace(/^[-*] (.+)$/gm, '<li class="bv-md-li">$1</li>');
    html = html.replace(/(<li class="bv-md-li">.*<\/li>\n?)+/g, (m) => '<ul class="bv-md-ul">' + m + '</ul>');
    html = html.replace(/^\d+\. (.+)$/gm, '<li class="bv-md-oli">$1</li>');
    html = html.replace(/(<li class="bv-md-oli">.*<\/li>\n?)+/g, (m) => '<ol class="bv-md-ol">' + m + '</ol>');
    html = html.replace(/\n\n/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');

    if (!html.startsWith('<h') && !html.startsWith('<ul') && !html.startsWith('<ol') && !html.startsWith('<pre')) {
      html = '<p>' + html + '</p>';
    }

    return html;
  }

  private fmtTime(date: Date): string {
    return date.toLocaleTimeString(this.locale === 'tr' ? 'tr-TR' : 'en-US', {
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  private valStr(val: unknown): string {
    if (val === null || val === undefined) return '-';
    if (typeof val === 'object') return JSON.stringify(val);
    return String(val);
  }

  private cellStr(val: unknown): string {
    if (val === null || val === undefined) return '-';
    const s = typeof val === 'object' ? JSON.stringify(val) : String(val);
    return s.length > 30 ? s.slice(0, 30) + '…' : s;
  }

  private detectCsrf(): string | null {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null;
  }
}
