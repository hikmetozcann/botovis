// ─────────────────────────────────────────────────
//  Botovis Widget — REST API Client
// ─────────────────────────────────────────────────

import type { ApiResponse, SchemaResponse, ConversationListResponse, ConversationResponse, StreamingEvent, StreamingHandlers, AgentStep } from './types';

export class BotovisApi {
  private csrfToken: string | null;

  constructor(
    private endpoint: string,
    csrfToken: string | null = null,
  ) {
    this.csrfToken = csrfToken;
  }

  // ── Public Methods ──────────────────────────────

  async chat(message: string, conversationId?: string): Promise<ApiResponse> {
    return this.post<ApiResponse>('/chat', {
      message,
      conversation_id: conversationId,
    });
  }

  async confirm(conversationId: string): Promise<ApiResponse> {
    return this.post<ApiResponse>('/confirm', { conversation_id: conversationId });
  }

  async reject(conversationId: string): Promise<ApiResponse> {
    return this.post<ApiResponse>('/reject', { conversation_id: conversationId });
  }

  async reset(conversationId: string): Promise<void> {
    await this.post('/reset', { conversation_id: conversationId });
  }

  async getSchema(): Promise<SchemaResponse> {
    return this.get<SchemaResponse>('/schema');
  }

  async getStatus(): Promise<{ status: string }> {
    return this.get('/status');
  }

  // ── Streaming Methods (SSE) ─────────────────────

  /**
   * Stream chat response using Server-Sent Events.
   * Returns an AbortController to allow cancellation.
   */
  streamChat(
    message: string,
    conversationId: string | undefined,
    handlers: StreamingHandlers,
  ): AbortController {
    const controller = new AbortController();
    
    this.performStream('/stream', { message, conversation_id: conversationId }, handlers, controller.signal);
    
    return controller;
  }

  /**
   * Stream confirmation response.
   */
  streamConfirm(
    conversationId: string,
    handlers: StreamingHandlers,
  ): AbortController {
    const controller = new AbortController();
    
    this.performStream('/stream-confirm', { conversation_id: conversationId }, handlers, controller.signal);
    
    return controller;
  }

  private async performStream(
    path: string,
    body: Record<string, unknown>,
    handlers: StreamingHandlers,
    signal: AbortSignal,
  ): Promise<void> {
    const url = `${this.endpoint}${path}`;

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'text/event-stream',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (this.csrfToken) {
      headers['X-CSRF-TOKEN'] = this.csrfToken;
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        credentials: 'same-origin',
        signal,
      });

      if (!response.ok) {
        const text = await response.text();
        handlers.onError?.(new BotovisApiError(response.status, text));
        return;
      }

      const reader = response.body?.getReader();
      if (!reader) {
        handlers.onError?.(new Error('ReadableStream not supported'));
        return;
      }

      const decoder = new TextDecoder();
      let buffer = '';

      while (true) {
        const { done, value } = await reader.read();
        
        if (done) break;
        
        buffer += decoder.decode(value, { stream: true });
        
        // Parse SSE events from buffer
        const events = this.parseSseEvents(buffer);
        buffer = events.remaining;

        for (const event of events.parsed) {
          this.dispatchEvent(event, handlers);
          
          if (event.type === 'done') {
            return;
          }
        }
      }
    } catch (error) {
      if ((error as Error).name === 'AbortError') {
        handlers.onAbort?.();
      } else {
        handlers.onError?.(error as Error);
      }
    }
  }

  private parseSseEvents(buffer: string): { parsed: StreamingEvent[]; remaining: string } {
    const events: StreamingEvent[] = [];
    const lines = buffer.split('\n');
    
    let eventType = '';
    let eventData = '';
    let i = 0;
    
    for (; i < lines.length; i++) {
      const line = lines[i];
      
      if (line.startsWith('event: ')) {
        eventType = line.slice(7).trim();
      } else if (line.startsWith('data: ')) {
        eventData = line.slice(6);
      } else if (line === '' && eventType && eventData) {
        try {
          events.push({
            type: eventType as StreamingEvent['type'],
            data: JSON.parse(eventData),
          });
        } catch {
          // Invalid JSON, skip
        }
        eventType = '';
        eventData = '';
      }
    }

    // Return remaining buffer (incomplete event)
    const remaining = eventType || eventData ? lines.slice(i - 2).join('\n') : '';
    
    return { parsed: events, remaining };
  }

  private dispatchEvent(event: StreamingEvent, handlers: StreamingHandlers): void {
    handlers.onEvent?.(event);

    switch (event.type) {
      case 'init':
        handlers.onInit?.(event.data.conversation_id as string);
        break;
      case 'step':
        handlers.onStep?.(event.data as unknown as AgentStep);
        break;
      case 'thinking':
        handlers.onThinking?.(event.data.step as number, event.data.thought as string);
        break;
      case 'tool_call':
        handlers.onToolCall?.(event.data.step as number, event.data.tool as string, event.data.params as Record<string, unknown>);
        break;
      case 'tool_result':
        handlers.onToolResult?.(event.data.step as number, event.data.tool as string, event.data.observation as string);
        break;
      case 'confirmation':
        handlers.onConfirmation?.(event.data.action as string, event.data.params as Record<string, unknown>, event.data.description as string);
        break;
      case 'message':
        handlers.onMessage?.(event.data.content as string);
        break;
      case 'error':
        handlers.onError?.(new Error(event.data.message as string));
        break;
      case 'done':
        handlers.onDone?.(event.data.steps as AgentStep[], event.data.message as string | null);
        break;
    }
  }

  // ── Conversation Management ─────────────────────

  async getConversations(): Promise<ConversationListResponse> {
    return this.get<ConversationListResponse>('/conversations');
  }

  async getConversation(id: string): Promise<ConversationResponse> {
    return this.get<ConversationResponse>(`/conversations/${id}`);
  }

  async createConversation(title?: string): Promise<ConversationResponse> {
    return this.post<ConversationResponse>('/conversations', { title });
  }

  async deleteConversation(id: string): Promise<void> {
    await this.delete(`/conversations/${id}`);
  }

  async updateConversationTitle(id: string, title: string): Promise<void> {
    await this.patch(`/conversations/${id}/title`, { title });
  }

  updateCsrfToken(token: string): void {
    this.csrfToken = token;
  }

  updateEndpoint(endpoint: string): void {
    this.endpoint = endpoint;
  }

  // ── Internal ────────────────────────────────────

  private async request<T>(path: string, options: RequestInit): Promise<T> {
    const url = `${this.endpoint}${path}`;

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (this.csrfToken) {
      headers['X-CSRF-TOKEN'] = this.csrfToken;
    }

    let response = await fetch(url, {
      ...options,
      headers: { ...headers, ...(options.headers as Record<string, string> || {}) },
      credentials: 'same-origin',
    });

    // Handle CSRF token mismatch — refresh and retry once
    if (response.status === 419) {
      const freshToken = this.detectCsrfFromMeta();
      if (freshToken) {
        this.csrfToken = freshToken;
        headers['X-CSRF-TOKEN'] = freshToken;
        response = await fetch(url, {
          ...options,
          headers: { ...headers, ...(options.headers as Record<string, string> || {}) },
          credentials: 'same-origin',
        });
      }
    }

    if (!response.ok) {
      throw new BotovisApiError(response.status, await response.text());
    }

    return response.json();
  }

  private post<T>(path: string, body: Record<string, unknown>): Promise<T> {
    return this.request<T>(path, {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  private get<T>(path: string): Promise<T> {
    return this.request<T>(path, { method: 'GET' });
  }

  private delete<T>(path: string): Promise<T> {
    return this.request<T>(path, { method: 'DELETE' });
  }

  private patch<T>(path: string, body: Record<string, unknown>): Promise<T> {
    return this.request<T>(path, {
      method: 'PATCH',
      body: JSON.stringify(body),
    });
  }

  private detectCsrfFromMeta(): string | null {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? null;
  }
}

export class BotovisApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: string,
  ) {
    super(`HTTP ${status}`);
    this.name = 'BotovisApiError';
  }
}
