// ─────────────────────────────────────────────────
//  Botovis Widget — Shadow DOM Styles
//  Clean, professional design — no AI chatbot clichés
// ─────────────────────────────────────────────────

export const styles = /*css*/`

/* ── Reset & Host ─────────────────────────── */

:host {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
               'Helvetica Neue', Arial, sans-serif;
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* ── Theme Variables ──────────────────────── */

.bv-root {
  /* Neutral, professional palette */
  --bv-primary: #0f172a;
  --bv-primary-hover: #1e293b;
  --bv-primary-light: #f1f5f9;
  --bv-primary-text: #ffffff;

  --bv-accent: #0ea5e9;
  --bv-accent-light: #e0f2fe;

  --bv-bg: #ffffff;
  --bv-surface: #f8fafc;
  --bv-surface-hover: #f1f5f9;
  --bv-text: #0f172a;
  --bv-text-secondary: #475569;
  --bv-text-muted: #94a3b8;
  --bv-border: #e2e8f0;
  --bv-border-light: #f1f5f9;

  --bv-user-bg: #0f172a;
  --bv-user-text: #ffffff;
  --bv-assistant-bg: #f1f5f9;
  --bv-assistant-text: #0f172a;

  --bv-success: #059669;
  --bv-success-bg: #ecfdf5;
  --bv-success-text: #065f46;
  --bv-error: #dc2626;
  --bv-error-bg: #fef2f2;
  --bv-error-text: #991b1b;
  --bv-warning: #d97706;
  --bv-warning-bg: #fffbeb;
  --bv-warning-text: #92400e;
  --bv-info: #0284c7;
  --bv-info-bg: #f0f9ff;

  --bv-shadow: 0 20px 40px -8px rgba(15,23,42,.15), 0 8px 16px -4px rgba(15,23,42,.08);
  --bv-shadow-sm: 0 2px 8px -2px rgba(15,23,42,.1);
  --bv-radius: 12px;
  --bv-radius-sm: 8px;
  --bv-radius-xs: 4px;
}

.bv-root.bv-dark {
  --bv-primary: #e2e8f0;
  --bv-primary-hover: #f1f5f9;
  --bv-primary-light: #1e293b;
  --bv-primary-text: #0f172a;

  --bv-accent: #38bdf8;
  --bv-accent-light: #0c4a6e;

  --bv-bg: #0f172a;
  --bv-surface: #1e293b;
  --bv-surface-hover: #334155;
  --bv-text: #f1f5f9;
  --bv-text-secondary: #94a3b8;
  --bv-text-muted: #64748b;
  --bv-border: #334155;
  --bv-border-light: #1e293b;

  --bv-user-bg: #e2e8f0;
  --bv-user-text: #0f172a;
  --bv-assistant-bg: #1e293b;
  --bv-assistant-text: #f1f5f9;

  --bv-success-bg: #064e3b;
  --bv-success-text: #6ee7b7;
  --bv-error-bg: #450a0a;
  --bv-error-text: #fca5a5;
  --bv-warning-bg: #451a03;
  --bv-warning-text: #fcd34d;
  --bv-info-bg: #0c4a6e;

  --bv-shadow: 0 20px 40px -8px rgba(0,0,0,.4);
  --bv-shadow-sm: 0 2px 8px -2px rgba(0,0,0,.3);
}

/* ── Layout Root ──────────────────────────── */

.bv-root {
  position: fixed;
  inset: 0;
  z-index: 99999;
  pointer-events: none;
  color: var(--bv-text);
}

/* ── FAB Button ───────────────────────────── */

.bv-fab {
  position: absolute;
  bottom: 24px;
  right: 24px;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
  box-shadow: var(--bv-shadow-sm);
  transition: transform 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
  outline: none;
}

.bv-fab:hover {
  transform: translateY(-2px);
  background: var(--bv-primary-hover);
  box-shadow: var(--bv-shadow);
}

.bv-fab:active { transform: translateY(0) scale(0.97); }

.bv-fab .bv-fab-icon {
  transition: transform 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bv-fab.bv-open .bv-fab-icon { transform: rotate(45deg); }

.bv-badge {
  position: absolute;
  top: -2px;
  right: -2px;
  min-width: 18px;
  height: 18px;
  background: var(--bv-accent);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  opacity: 0;
  transform: scale(0);
  transition: all 0.15s ease;
}

.bv-badge.bv-visible {
  opacity: 1;
  transform: scale(1);
}

/* ── Left positioning ── */

.bv-root.bv-left .bv-fab { right: auto; left: 24px; }
.bv-root.bv-left .bv-panel { right: auto; left: 24px; }

/* ── Panel ────────────────────────────────── */

.bv-panel {
  position: absolute;
  bottom: 92px;
  right: 24px;
  width: 380px;
  height: 560px;
  max-height: calc(100vh - 116px);
  max-height: calc(100dvh - 116px);
  background: var(--bv-bg);
  border-radius: var(--bv-radius);
  box-shadow: var(--bv-shadow);
  border: 1px solid var(--bv-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  pointer-events: auto;

  opacity: 0;
  transform: translateY(12px) scale(0.98);
  visibility: hidden;
  transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
}

.bv-panel.bv-open {
  opacity: 1;
  transform: translateY(0) scale(1);
  visibility: visible;
}

/* ── Header ───────────────────────────────── */

.bv-header {
  display: flex;
  align-items: center;
  padding: 14px 16px;
  background: var(--bv-bg);
  border-bottom: 1px solid var(--bv-border);
  flex-shrink: 0;
}

.bv-header-title {
  flex: 1;
  font-size: 15px;
  font-weight: 600;
  color: var(--bv-text);
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
}

.bv-header-title svg {
  height: 22px;
  width: auto;
}

.bv-header-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: var(--bv-text-muted);
  border-radius: var(--bv-radius-xs);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.1s ease, color 0.1s ease;
  margin-left: 4px;
  outline: none;
}

.bv-header-btn:hover { 
  background: var(--bv-surface); 
  color: var(--bv-text);
}

/* ── Messages Area ────────────────────────── */

.bv-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scroll-behavior: smooth;
  overscroll-behavior: contain;
}

.bv-messages::-webkit-scrollbar { width: 4px; }
.bv-messages::-webkit-scrollbar-track { background: transparent; }
.bv-messages::-webkit-scrollbar-thumb {
  background: var(--bv-border);
  border-radius: 2px;
}

/* ── Empty State ──────────────────────────── */

.bv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 12px;
  padding: 32px 20px;
  flex: 1;
}

.bv-empty-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bv-surface);
  border-radius: 50%;
  color: var(--bv-text-muted);
}

.bv-empty-text {
  color: var(--bv-text-secondary);
  font-size: 14px;
  max-width: 240px;
}

.bv-suggestions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
  max-width: 260px;
  margin-top: 8px;
}

.bv-suggestion-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--bv-text-muted);
  margin-bottom: 2px;
}

.bv-suggestion {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: var(--bv-bg);
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  font-size: 13px;
  color: var(--bv-text);
  transition: all 0.1s ease;
  text-align: left;
}

.bv-suggestion:hover {
  background: var(--bv-surface);
  border-color: var(--bv-text-muted);
}

.bv-suggestion-icon {
  color: var(--bv-text-muted);
  flex-shrink: 0;
  display: flex;
}

/* ── Message Bubbles ──────────────────────── */

.bv-msg {
  display: flex;
  flex-direction: column;
  animation: bvFadeIn 0.15s ease;
}

.bv-msg-user {
  align-items: flex-end;
}

.bv-msg-assistant {
  align-items: flex-start;
}

.bv-bubble {
  max-width: 85%;
  padding: 10px 14px;
  border-radius: var(--bv-radius-sm);
  word-break: break-word;
  white-space: pre-wrap;
  font-size: 14px;
  line-height: 1.5;
}

.bv-msg-user .bv-bubble {
  background: var(--bv-user-bg);
  color: var(--bv-user-text);
  border-bottom-right-radius: var(--bv-radius-xs);
}

.bv-msg-assistant .bv-bubble {
  background: var(--bv-assistant-bg);
  color: var(--bv-assistant-text);
  border-bottom-left-radius: var(--bv-radius-xs);
}

.bv-msg-time {
  font-size: 10px;
  color: var(--bv-text-muted);
  margin-top: 4px;
  padding: 0 4px;
  opacity: 0;
  transition: opacity 0.1s ease;
}

.bv-msg:hover .bv-msg-time { opacity: 1; }

/* ── Intent Card ──────────────────────────── */

.bv-intent-card {
  max-width: 100%;
  background: var(--bv-bg);
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  overflow: hidden;
  font-size: 13px;
}

.bv-intent-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: var(--bv-surface);
  color: var(--bv-text);
  font-weight: 500;
  font-size: 12px;
  border-bottom: 1px solid var(--bv-border);
}

.bv-intent-header svg {
  color: var(--bv-text-muted);
}

.bv-intent-body { padding: 10px 12px; }

.bv-intent-row {
  display: flex;
  padding: 4px 0;
}

.bv-intent-label {
  width: 72px;
  flex-shrink: 0;
  color: var(--bv-text-muted);
  font-size: 12px;
}

.bv-intent-value {
  color: var(--bv-text);
  font-size: 12px;
  font-family: 'SF Mono', SFMono-Regular, ui-monospace, 'Cascadia Code', Menlo, monospace;
}

.bv-intent-value.bv-action-create { color: var(--bv-success); }
.bv-intent-value.bv-action-read   { color: var(--bv-info); }
.bv-intent-value.bv-action-update { color: var(--bv-warning); }
.bv-intent-value.bv-action-delete { color: var(--bv-error); }

/* ── Data Table ───────────────────────────── */

.bv-table-wrapper {
  max-width: 100%;
  overflow-x: auto;
  margin-top: 8px;
  border-radius: var(--bv-radius-xs);
  border: 1px solid var(--bv-border);
}

.bv-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.bv-table th {
  background: var(--bv-surface);
  font-weight: 500;
  text-align: left;
  padding: 8px 10px;
  border-bottom: 1px solid var(--bv-border);
  white-space: nowrap;
  color: var(--bv-text-secondary);
  font-size: 11px;
}

.bv-table td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--bv-border-light);
  white-space: nowrap;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-table tr:last-child td { border-bottom: none; }
.bv-table tr:hover td { background: var(--bv-surface); }

.bv-table-footer {
  font-size: 11px;
  color: var(--bv-text-muted);
  padding: 8px 0 2px;
}

/* ── Confirmation Card ────────────────────── */

.bv-confirm-card {
  max-width: 100%;
  background: var(--bv-warning-bg);
  border: 1px solid var(--bv-warning);
  border-radius: var(--bv-radius-sm);
  padding: 12px;
}

.bv-confirm-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
  font-size: 13px;
  color: var(--bv-warning-text);
  margin-bottom: 10px;
}

.bv-confirm-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.bv-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: none;
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.1s ease;
  outline: none;
  font-family: inherit;
}

.bv-btn-confirm {
  background: var(--bv-success);
  color: #fff;
}
.bv-btn-confirm:hover { filter: brightness(1.08); }

.bv-btn-reject {
  background: var(--bv-bg);
  color: var(--bv-text);
  border: 1px solid var(--bv-border);
}
.bv-btn-reject:hover { 
  background: var(--bv-error-bg); 
  color: var(--bv-error-text); 
  border-color: var(--bv-error); 
}

/* ── Result Messages ──────────────────────── */

.bv-result-success {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: var(--bv-success-bg);
  color: var(--bv-success-text);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
}

.bv-result-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: var(--bv-error-bg);
  color: var(--bv-error-text);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
}

.bv-rejected-msg {
  padding: 10px 12px;
  background: var(--bv-surface);
  color: var(--bv-text-muted);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
}

/* ── Step Indicator (auto-continue) ───────── */

.bv-step-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--bv-text-muted);
  padding: 4px 0;
}

.bv-step-dot {
  width: 5px;
  height: 5px;
  background: var(--bv-accent);
  border-radius: 50%;
}

/* ── Loading / Typing ─────────────────────── */

.bv-typing {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  background: var(--bv-assistant-bg);
  border-radius: var(--bv-radius-sm);
  border-bottom-left-radius: var(--bv-radius-xs);
}

.bv-typing-dots {
  display: flex;
  gap: 4px;
}

.bv-typing-dot {
  width: 6px;
  height: 6px;
  background: var(--bv-text-muted);
  border-radius: 50%;
  animation: bvTypingDot 1.2s infinite ease-in-out;
}

.bv-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.bv-typing-dot:nth-child(3) { animation-delay: 0.3s; }

/* ── Input Area ───────────────────────────── */

.bv-input-area {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--bv-border);
  background: var(--bv-bg);
  flex-shrink: 0;
}

.bv-textarea {
  flex: 1;
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  padding: 10px 12px;
  font-size: 14px;
  font-family: inherit;
  line-height: 1.4;
  background: var(--bv-bg);
  color: var(--bv-text);
  resize: none;
  outline: none;
  transition: border-color 0.1s ease, box-shadow 0.1s ease;
  min-height: 40px;
  max-height: 100px;
  overflow-y: auto;
}

.bv-textarea::placeholder { color: var(--bv-text-muted); }
.bv-textarea:focus { 
  border-color: var(--bv-text-muted); 
  box-shadow: 0 0 0 3px var(--bv-surface);
}

.bv-send-btn {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: var(--bv-radius-sm);
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.1s ease;
  outline: none;
}

.bv-send-btn:hover { background: var(--bv-primary-hover); }
.bv-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* Keyboard shortcut hint */
.bv-input-hint {
  font-size: 10px;
  color: var(--bv-text-muted);
  text-align: center;
  padding: 0 16px 10px;
  background: var(--bv-bg);
}

.bv-kbd {
  display: inline-block;
  padding: 2px 5px;
  font-size: 9px;
  font-family: inherit;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 3px;
  margin: 0 2px;
}

/* ── Toasts ───────────────────────────────── */

.bv-toasts {
  position: absolute;
  top: 16px;
  right: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: auto;
  z-index: 1;
}

.bv-root.bv-left .bv-toasts { right: auto; left: 24px; }

.bv-toast {
  padding: 10px 14px;
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
  box-shadow: var(--bv-shadow-sm);
  animation: bvSlideIn 0.2s ease;
  max-width: 300px;
}

.bv-toast-success { background: var(--bv-success); color: #fff; }
.bv-toast-error   { background: var(--bv-error);   color: #fff; }
.bv-toast-info    { background: var(--bv-primary); color: var(--bv-primary-text); }

.bv-toast-exit { animation: bvFadeOut 0.2s ease forwards; }

/* ── Animations ───────────────────────────── */

@keyframes bvFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes bvTypingDot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
  30%           { transform: translateY(-3px); opacity: 1; }
}

@keyframes bvSlideIn {
  from { opacity: 0; transform: translateX(20px); }
  to   { opacity: 1; transform: translateX(0); }
}

@keyframes bvFadeOut {
  to { opacity: 0; }
}

/* ── Conversation History ─────────────────── */

.bv-view-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.bv-history {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.bv-history-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
  color: var(--bv-text-muted);
}

.bv-history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
}

.bv-history-empty-icon {
  color: var(--bv-text-muted);
  margin-bottom: 12px;
  opacity: 0.5;
}

.bv-history-empty-icon svg {
  width: 32px;
  height: 32px;
}

.bv-history-empty-text {
  color: var(--bv-text-muted);
  font-size: 13px;
}

.bv-history-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bv-history-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  transition: background 0.15s;
  border: 1px solid transparent;
}

.bv-history-item:hover {
  background: var(--bv-surface-hover);
}

.bv-history-item-content {
  flex: 1;
  min-width: 0;
}

.bv-history-item-title {
  font-weight: 500;
  color: var(--bv-text);
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-history-item-preview {
  color: var(--bv-text-muted);
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
}

.bv-history-item-meta {
  color: var(--bv-text-muted);
  font-size: 11px;
  margin-top: 4px;
}

.bv-history-item-delete {
  opacity: 0;
  padding: 6px;
  background: none;
  border: none;
  color: var(--bv-text-muted);
  cursor: pointer;
  border-radius: var(--bv-radius-xs);
  transition: all 0.15s;
}

.bv-history-item:hover .bv-history-item-delete {
  opacity: 1;
}

.bv-history-item-delete:hover {
  background: var(--bv-error-bg);
  color: var(--bv-error);
}

/* ── Markdown Styles ──────────────────────── */

.bv-bubble-md {
  /* Reset paragraph margins within markdown bubbles */
}

.bv-bubble-md p {
  margin: 0 0 0.5em 0;
}

.bv-bubble-md p:last-child {
  margin-bottom: 0;
}

.bv-bubble-md strong {
  font-weight: 600;
}

.bv-bubble-md em {
  font-style: italic;
}

.bv-bubble-md a {
  color: var(--bv-accent);
  text-decoration: none;
}

.bv-bubble-md a:hover {
  text-decoration: underline;
}

.bv-md-code {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 0.875em;
  background: var(--bv-surface);
  padding: 0.15em 0.4em;
  border-radius: var(--bv-radius-xs);
  color: var(--bv-accent);
}

.bv-md-pre {
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
  font-size: 0.85em;
  background: var(--bv-surface);
  padding: 0.75em 1em;
  border-radius: var(--bv-radius-sm);
  overflow-x: auto;
  margin: 0.5em 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.bv-md-h2, .bv-md-h3, .bv-md-h4 {
  font-weight: 600;
  margin: 0.75em 0 0.25em 0;
  line-height: 1.3;
}

.bv-md-h2 { font-size: 1.25em; }
.bv-md-h3 { font-size: 1.1em; }
.bv-md-h4 { font-size: 1em; }

.bv-md-ul, .bv-md-ol {
  margin: 0.5em 0;
  padding-left: 1.5em;
}

.bv-md-li, .bv-md-oli {
  margin: 0.25em 0;
}

/* ── Responsive (Mobile) ──────────────────── */

@media (max-width: 480px) {
  .bv-panel {
    bottom: 0;
    right: 0;
    left: 0;
    width: 100%;
    height: 100%;
    max-height: 100vh;
    max-height: 100dvh;
    border-radius: 0;
    border: none;
  }

  .bv-panel.bv-open {
    transform: translateY(0);
  }

  .bv-fab {
    bottom: 20px;
    right: 20px;
    width: 48px;
    height: 48px;
  }

  .bv-root.bv-left .bv-fab { right: auto; left: 20px; }
}

/* ── Reduced motion ───────────────────────── */

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
`;
