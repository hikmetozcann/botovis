// ─────────────────────────────────────────────────
//  Botovis Widget — REST API Client
// ─────────────────────────────────────────────────

import type { ApiResponse, SchemaResponse } from './types';

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
