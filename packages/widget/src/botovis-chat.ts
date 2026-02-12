// ─────────────────────────────────────────────────
//  Botovis Widget — Main Custom Element
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

export type ViewMode = 'chat' | 'history';

export class BotovisChat extends HTMLElement {

  // ── Observed attributes ────────────────────────
  static observedAttributes = [
    'endpoint', 'lang', 'theme', 'position',
    'title', 'placeholder', 'csrf-token', 'sounds',
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
  
  // Conversation history
  private viewMode: ViewMode = 'chat';
  private conversations: ConversationSummary[] = [];
  private isLoadingHistory = false;

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
    this.$('panel')?.classList.toggle('bv-open', this.isOpen);
    this.$('fab')?.classList.toggle('bv-open', this.isOpen);

    const fabIcon = this.$('fab-icon');
    if (fabIcon) fabIcon.innerHTML = this.isOpen ? icons.close : icons.chat;

    if (this.isOpen) {
      this.unreadCount = 0;
      this.updateBadge();
      setTimeout(() => this.$('input')?.focus(), 100);
    }

    this.dispatchEvent(new CustomEvent(this.isOpen ? 'botovis:open' : 'botovis:close'));
  }

  async send(text: string): Promise<void> {
    if (!text.trim() || this.isLoading) return;

    this.addUserMessage(text);
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
      ${this.renderPanel()}
      <button class="bv-fab" id="fab" aria-label="${this.i('shortcutToggle')}">
        <span class="bv-fab-icon" id="fab-icon">${icons.chat}</span>
        <span class="bv-badge" id="badge"></span>
      </button>
      <div class="bv-toasts" id="toasts"></div>
    `;
    this.shadow.appendChild(root);
    this.applyTheme();
  }

  private renderPanel(): string {
    return `
      <div class="bv-panel" id="panel">
        <div class="bv-header">
          <span class="bv-header-title" id="header-title">${icons.logo}</span>
          <button class="bv-header-btn" id="btn-history" title="${this.i('conversations')}">${icons.clock}</button>
          <button class="bv-header-btn" id="btn-new" title="${this.i('newConversation')}">${icons.plus}</button>
          <button class="bv-header-btn" id="btn-reset" title="${this.i('reset')}">${icons.refresh}</button>
          <button class="bv-header-btn" id="btn-close" title="${this.i('close')}">${icons.minimize}</button>
        </div>
        <div class="bv-view-container">
          <div class="bv-messages" id="messages">
            ${this.renderEmptyState()}
          </div>
          <div class="bv-history" id="history" style="display: none;">
            ${this.renderHistoryContent()}
          </div>
        </div>
        <div class="bv-input-area">
          <textarea class="bv-textarea" id="input"
            placeholder="${this.esc(this.cfg.placeholder)}"
            rows="1"
            aria-label="${this.i('placeholder')}"
          ></textarea>
          <button class="bv-send-btn" id="btn-send" title="${this.i('send')}">${icons.send}</button>
        </div>
        <div class="bv-input-hint">
          <span class="bv-kbd">Enter</span> ${this.i('shortcutSend')} · <span class="bv-kbd">Shift+Enter</span> ${this.i('shortcutNewline')} · <span class="bv-kbd">Esc</span> ${this.i('shortcutClose')}
        </div>
      </div>
    `;
  }

  private renderHistoryContent(): string {
    if (this.isLoadingHistory) {
      return `<div class="bv-history-loading">${this.i('loadingConversations')}</div>`;
    }

    if (this.conversations.length === 0) {
      return `
        <div class="bv-history-empty">
          <div class="bv-history-empty-icon">${icons.messageSquare}</div>
          <div class="bv-history-empty-text">${this.i('noConversations')}</div>
        </div>
      `;
    }

    return `
      <div class="bv-history-list">
        ${this.conversations.map(conv => `
          <div class="bv-history-item" data-action="select-conversation" data-id="${conv.id}">
            <div class="bv-history-item-content">
              <div class="bv-history-item-title">${this.esc(conv.title)}</div>
              <div class="bv-history-item-preview">${conv.last_message ? this.esc(conv.last_message.substring(0, 60)) : ''}</div>
              <div class="bv-history-item-meta">${this.formatDate(conv.updated_at)} · ${conv.message_count} mesaj</div>
            </div>
            <button class="bv-history-item-delete" data-action="delete-conversation" data-id="${conv.id}" title="${this.i('deleteConversation')}">
              ${icons.trash}
            </button>
          </div>
        `).join('')}
      </div>
    `;
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
           <div class="bv-suggestion-label">${this.i('suggestedActions')}</div>
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
    // Delegation on shadow root
    this.shadow.addEventListener('click', (e) => {
      const target = e.target as HTMLElement;
      
      // FAB button - check if click is on fab or any child of fab
      const fab = target.closest('#fab') as HTMLElement | null;
      if (fab) {
        this.toggle();
        return;
      }

      const btn = target.closest('[id], [data-action]') as HTMLElement | null;
      if (!btn) return;

      const id = btn.id;
      const action = btn.dataset.action;

      if (id === 'btn-close') this.close();
      if (id === 'btn-send')  this.handleSend();
      if (id === 'btn-reset') this.handleReset();
      if (id === 'btn-history') this.toggleHistory();
      if (id === 'btn-new') this.startNewConversation();

      if (action === 'confirm') this.handleConfirm();
      if (action === 'reject')  this.handleReject();
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
    });

    // Textarea auto-resize + Enter to send
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
      // Ctrl+K / Cmd+K → toggle
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        this.toggle();
      }
      // Escape → close
      if (e.key === 'Escape' && this.isOpen) {
        this.close();
      }
    };
    document.addEventListener('keydown', this.boundKeyHandler);
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
    let dark = false;

    if (theme === 'dark') dark = true;
    else if (theme === 'auto') dark = !!this.darkMediaQuery?.matches;

    root.classList.toggle('bv-dark', dark);
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

  private async handleReject(): Promise<void> {
    if (!this.conversationId || !this.hasPending) return;

    this.hasPending = false;
    this.disableConfirmButtons();
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

    // Render auto-continue intermediate steps
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

    // Sound notification when panel is closed
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

    // Remove empty state if present
    const empty = container.querySelector('.bv-empty');
    if (empty) empty.remove();

    // Create message element
    const wrapper = document.createElement('div');
    wrapper.innerHTML = this.renderMessage(msg);
    const el = wrapper.firstElementChild;
    if (el) container.appendChild(el);

    this.scrollToBottom();

    // Increment unread when closed
    if (!this.isOpen && msg.role === 'assistant') {
      this.unreadCount++;
      this.updateBadge();
      // Pulse the FAB
      this.$('fab')?.classList.add('bv-pulse');
      setTimeout(() => this.$('fab')?.classList.remove('bv-pulse'), 4000);
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

  private disableConfirmButtons(): void {
    const btns = this.shadow.querySelectorAll('[data-action="confirm"], [data-action="reject"]');
    btns.forEach(btn => (btn as HTMLButtonElement).disabled = true);
  }

  private scrollToBottom(): void {
    const container = this.$('messages');
    if (container) {
      requestAnimationFrame(() => {
        container.scrollTop = container.scrollHeight;
      });
    }
  }

  /**
   * Re-render all messages in the container.
   * Used when loading a conversation from history or starting a new one.
   */
  private renderMessages(): void {
    const container = this.$('messages');
    if (!container) return;

    if (this.messages.length === 0) {
      container.innerHTML = this.renderEmptyState();
      return;
    }

    container.innerHTML = this.messages
      .map(msg => this.renderMessage(msg))
      .join('');

    this.scrollToBottom();
  }

  // ── Message Rendering ──────────────────────────

  private renderMessage(msg: ChatMessage): string {
    switch (msg.type) {
      case 'text':         return this.renderTextMsg(msg);
      case 'action':       return this.renderActionMsg(msg);
      case 'confirmation': return this.renderConfirmMsg(msg);
      case 'executed':     return this.renderExecutedMsg(msg);
      case 'rejected':     return this.renderRejectedMsg(msg);
      case 'error':        return this.renderErrorMsg(msg);
      default:             return this.renderTextMsg(msg);
    }
  }

  private renderTextMsg(msg: ChatMessage): string {
    if (msg.role === 'user') {
      return `
        <div class="bv-msg bv-msg-user">
          <div class="bv-bubble">${this.esc(msg.content)}</div>
          <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
        </div>`;
    }
    return `
      <div class="bv-msg bv-msg-assistant">
        <div class="bv-bubble bv-bubble-md">${this.markdown(msg.content)}</div>
        <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
      </div>`;
  }

  private renderActionMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    const result = msg.result;
    if (!intent) return this.renderTextMsg(msg);

    let html = `<div class="bv-msg bv-msg-assistant">`;
    html += this.renderIntentCard(intent);

    if (result) {
      html += result.success
        ? `<div class="bv-result-success" style="margin-top:8px">${icons.checkCircle} ${this.esc(result.message)}</div>`
        : `<div class="bv-result-error" style="margin-top:8px">${icons.xCircle} ${this.esc(result.message)}</div>`;

      if (result.success && result.data) {
        html += this.renderDataTable(result.data as Record<string, unknown>[]);
      }
    }

    // Auto-continue step indicator
    if (intent.auto_continue) {
      html += `<div class="bv-step-indicator"><span class="bv-step-dot"></span>${this.i('autoStep')}</div>`;
    }

    html += `<span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span></div>`;
    return html;
  }

  private renderConfirmMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    if (!intent) return this.renderTextMsg(msg);

    return `
      <div class="bv-msg bv-msg-assistant">
        <div class="bv-confirm-card">
          <div class="bv-confirm-title">${icons.alert} ${this.i('confirmQuestion')}</div>
          ${this.renderIntentCard(intent)}
          <div class="bv-confirm-actions">
            <button class="bv-btn bv-btn-confirm" data-action="confirm">${icons.check} ${this.i('confirm')}</button>
            <button class="bv-btn bv-btn-reject" data-action="reject">${icons.x} ${this.i('reject')}</button>
          </div>
        </div>
        <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
      </div>`;
  }

  private renderExecutedMsg(msg: ChatMessage): string {
    const intent = msg.intent;
    const result = msg.result;

    let html = `<div class="bv-msg bv-msg-assistant">`;

    if (intent) html += this.renderIntentCard(intent);

    if (result) {
      html += result.success
        ? `<div class="bv-result-success" style="margin-top:8px">${icons.checkCircle} ${this.esc(result.message)}</div>`
        : `<div class="bv-result-error" style="margin-top:8px">${icons.xCircle} ${this.esc(result.message)}</div>`;

      if (result.success && result.data) {
        const data = result.data as Record<string, unknown>[] | Record<string, unknown>;
        // For write results show compact; for read show full table
        const isWrite = intent?.action && intent.action !== 'read';
        if (isWrite) {
          html += this.renderCompactResult(data, intent!);
        } else {
          html += this.renderDataTable(Array.isArray(data) ? data : [data]);
        }
      }
    }

    html += `<span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span></div>`;
    return html;
  }

  private renderRejectedMsg(msg: ChatMessage): string {
    return `
      <div class="bv-msg bv-msg-assistant">
        <div class="bv-rejected-msg">${icons.xCircle} ${this.esc(msg.content || this.i('operationCancelled'))}</div>
        <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
      </div>`;
  }

  private renderErrorMsg(msg: ChatMessage): string {
    return `
      <div class="bv-msg bv-msg-assistant">
        <div class="bv-result-error">${icons.alert} ${this.esc(msg.content)}</div>
        <span class="bv-msg-time">${this.fmtTime(msg.timestamp)}</span>
      </div>`;
  }

  private renderLoading(): string {
    return `
      <div class="bv-msg bv-msg-assistant bv-msg-loading">
        <div class="bv-typing">
          <div class="bv-typing-dots">
            <span class="bv-typing-dot"></span>
            <span class="bv-typing-dot"></span>
            <span class="bv-typing-dot"></span>
          </div>
        </div>
      </div>`;
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
    const headers = Object.keys(display[0] || {}).slice(0, 8); // max 8 cols

    let html = `<div class="bv-table-wrapper"><table class="bv-table"><thead><tr>`;
    for (const h of headers) {
      html += `<th>${this.esc(h)}</th>`;
    }
    html += `</tr></thead><tbody>`;

    for (const row of display) {
      html += '<tr>';
      for (const h of headers) {
        const val = row[h];
        const display = this.cellStr(val);
        html += `<td title="${this.esc(String(val ?? ''))}">${this.esc(display)}</td>`;
      }
      html += '</tr>';
    }

    html += '</tbody></table></div>';

    html += `<div class="bv-table-footer">`;
    html += this.i('resultsFound', { count: total });
    if (total > maxRows) html += ` (${this.i('showingFirst', { count: maxRows })})`;
    html += `</div>`;

    return html;
  }

  private renderCompactResult(data: Record<string, unknown>[] | Record<string, unknown>, intent: ResolvedIntent): string {
    // For write results, show only key fields (id + changed fields + where fields)
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
      // Re-render empty state with suggestions
      if (this.messages.length === 0) {
        const container = this.$('messages');
        if (container) container.innerHTML = this.renderEmptyState();
      }
    } catch {
      // Silently fail — suggestions just won't appear
    }
  }

  private generateSuggestions(): SuggestedAction[] {
    const actions: SuggestedAction[] = [];
    for (const table of this.schemaTables.slice(0, 4)) {
      const tableActions = table.actions || table.allowed_actions || [];
      const tableName = table.table || table.name || '';
      
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
    if (this.viewMode === 'chat') {
      this.viewMode = 'history';
      this.showHistoryView();
      await this.fetchConversations();
    } else {
      this.viewMode = 'chat';
      this.showChatView();
    }
  }

  private showHistoryView(): void {
    const messages = this.$('messages');
    const history = this.$('history');
    const inputArea = this.shadow.querySelector('.bv-input-area') as HTMLElement;
    const inputHint = this.shadow.querySelector('.bv-input-hint') as HTMLElement;
    
    if (messages) messages.style.display = 'none';
    if (history) history.style.display = 'block';
    if (inputArea) inputArea.style.display = 'none';
    if (inputHint) inputHint.style.display = 'none';
    
    // Update header title to text
    const title = this.$('header-title');
    if (title) title.innerHTML = this.i('conversations');
    
    // Update history button to back
    const historyBtn = this.$('btn-history');
    if (historyBtn) {
      historyBtn.innerHTML = icons.arrowLeft;
      historyBtn.title = this.i('backToChat');
    }
  }

  private showChatView(): void {
    const messages = this.$('messages');
    const history = this.$('history');
    const inputArea = this.shadow.querySelector('.bv-input-area') as HTMLElement;
    const inputHint = this.shadow.querySelector('.bv-input-hint') as HTMLElement;
    
    if (messages) messages.style.display = 'flex';
    if (history) history.style.display = 'none';
    if (inputArea) inputArea.style.display = 'flex';
    if (inputHint) inputHint.style.display = 'flex';
    
    // Update header title to logo
    const title = this.$('header-title');
    if (title) title.innerHTML = icons.logo;
    
    // Update back button to history
    const historyBtn = this.$('btn-history');
    if (historyBtn) {
      historyBtn.innerHTML = icons.clock;
      historyBtn.title = this.i('conversations');
    }
  }

  private async fetchConversations(): Promise<void> {
    this.isLoadingHistory = true;
    this.updateHistoryView();

    try {
      const response = await this.api.getConversations();
      this.conversations = response.conversations;
    } catch (err) {
      console.error('Failed to fetch conversations:', err);
      this.conversations = [];
    } finally {
      this.isLoadingHistory = false;
      this.updateHistoryView();
    }
  }

  private updateHistoryView(): void {
    const history = this.$('history');
    if (history) {
      history.innerHTML = this.renderHistoryContent();
    }
  }

  private async loadConversation(id: string): Promise<void> {
    try {
      const response = await this.api.getConversation(id);
      const conv = response.conversation;
      
      this.conversationId = conv.id;
      this.messages = conv.messages.map(m => ({
        id: m.id,
        role: m.role as 'user' | 'assistant',
        type: m.role === 'user' ? 'text' : (m.success === false ? 'error' : 'text'),
        content: m.content,
        timestamp: new Date(m.created_at),
      }));
      
      // Switch to chat view
      this.viewMode = 'chat';
      this.showChatView();
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
      this.updateHistoryView();
      this.toast(this.i('conversationDeleted'), 'success');
      
      // If we deleted the current conversation, reset
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
    
    // If in history view, switch to chat
    if (this.viewMode === 'history') {
      this.viewMode = 'chat';
      this.showChatView();
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

  /**
   * Render markdown in assistant messages.
   * Supports: **bold**, *italic*, `code`, [link](url), headers, lists
   */
  private markdown(text: string): string {
    if (!text) return '';
    
    // First escape HTML
    let html = this.esc(text);
    
    // Code blocks (```code```)
    html = html.replace(/```([\s\S]*?)```/g, '<pre class="bv-md-pre">$1</pre>');
    
    // Inline code (`code`)
    html = html.replace(/`([^`]+)`/g, '<code class="bv-md-code">$1</code>');
    
    // Bold (**text** or __text__)
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Italic (*text* or _text_)
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');
    
    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    
    // Headers (# Header)
    html = html.replace(/^### (.+)$/gm, '<h4 class="bv-md-h4">$1</h4>');
    html = html.replace(/^## (.+)$/gm, '<h3 class="bv-md-h3">$1</h3>');
    html = html.replace(/^# (.+)$/gm, '<h2 class="bv-md-h2">$1</h2>');
    
    // Bullet lists (- item or * item)
    html = html.replace(/^[-*] (.+)$/gm, '<li class="bv-md-li">$1</li>');
    // Wrap consecutive li elements in ul
    html = html.replace(/(<li class="bv-md-li">.*<\/li>\n?)+/g, (match) => {
      return '<ul class="bv-md-ul">' + match + '</ul>';
    });
    
    // Numbered lists (1. item)
    html = html.replace(/^\d+\. (.+)$/gm, '<li class="bv-md-oli">$1</li>');
    html = html.replace(/(<li class="bv-md-oli">.*<\/li>\n?)+/g, (match) => {
      return '<ol class="bv-md-ol">' + match + '</ol>';
    });
    
    // Line breaks (double newline = paragraph)
    html = html.replace(/\n\n/g, '</p><p>');
    html = html.replace(/\n/g, '<br>');
    
    // Wrap in paragraph if not starting with block element
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
