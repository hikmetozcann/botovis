// ─────────────────────────────────────────────────
//  Botovis Widget — Shadow DOM Styles
// ─────────────────────────────────────────────────

export const styles = /*css*/`

/* ── Reset & Host ─────────────────────────── */

:host {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
               Oxygen, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
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
  --bv-primary: #6366f1;
  --bv-primary-hover: #4f46e5;
  --bv-primary-light: #eef2ff;
  --bv-primary-text: #ffffff;

  --bv-bg: #ffffff;
  --bv-surface: #f9fafb;
  --bv-surface-hover: #f3f4f6;
  --bv-text: #111827;
  --bv-text-secondary: #6b7280;
  --bv-text-muted: #9ca3af;
  --bv-border: #e5e7eb;
  --bv-border-light: #f3f4f6;

  --bv-user-bg: #6366f1;
  --bv-user-text: #ffffff;
  --bv-assistant-bg: #f3f4f6;
  --bv-assistant-text: #111827;

  --bv-success: #10b981;
  --bv-success-bg: #ecfdf5;
  --bv-error: #ef4444;
  --bv-error-bg: #fef2f2;
  --bv-warning: #f59e0b;
  --bv-warning-bg: #fffbeb;
  --bv-info: #3b82f6;
  --bv-info-bg: #eff6ff;

  --bv-shadow: 0 25px 50px -12px rgba(0,0,0,.25);
  --bv-shadow-sm: 0 4px 6px -1px rgba(0,0,0,.1);
  --bv-radius: 16px;
  --bv-radius-sm: 10px;
  --bv-radius-xs: 6px;
}

.bv-root.bv-dark {
  --bv-primary: #818cf8;
  --bv-primary-hover: #6366f1;
  --bv-primary-light: #1e1b4b;
  --bv-primary-text: #ffffff;

  --bv-bg: #111827;
  --bv-surface: #1f2937;
  --bv-surface-hover: #374151;
  --bv-text: #f9fafb;
  --bv-text-secondary: #9ca3af;
  --bv-text-muted: #6b7280;
  --bv-border: #374151;
  --bv-border-light: #1f2937;

  --bv-user-bg: #6366f1;
  --bv-user-text: #ffffff;
  --bv-assistant-bg: #1f2937;
  --bv-assistant-text: #f9fafb;

  --bv-success-bg: #064e3b;
  --bv-error-bg: #7f1d1d;
  --bv-warning-bg: #78350f;
  --bv-info-bg: #1e3a5f;

  --bv-shadow: 0 25px 50px -12px rgba(0,0,0,.5);
  --bv-shadow-sm: 0 4px 6px -1px rgba(0,0,0,.3);
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
  width: 56px;
  height: 56px;
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
  transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
  outline: none;
}

.bv-fab:hover {
  transform: scale(1.08);
  background: var(--bv-primary-hover);
  box-shadow: var(--bv-shadow);
}

.bv-fab:active { transform: scale(0.95); }

.bv-fab .bv-fab-icon {
  transition: transform 0.3s ease, opacity 0.2s ease;
}

.bv-fab.bv-open .bv-fab-icon { transform: rotate(90deg); }

.bv-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 20px;
  height: 20px;
  background: var(--bv-error);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  opacity: 0;
  transform: scale(0);
  transition: all 0.2s ease;
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
  bottom: 96px;
  right: 24px;
  width: 400px;
  height: 600px;
  max-height: calc(100vh - 120px);
  max-height: calc(100dvh - 120px);
  background: var(--bv-bg);
  border-radius: var(--bv-radius);
  box-shadow: var(--bv-shadow);
  border: 1px solid var(--bv-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  pointer-events: auto;

  opacity: 0;
  transform: translateY(16px) scale(0.96);
  visibility: hidden;
  transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s ease;
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
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  flex-shrink: 0;
}

.bv-header-title {
  flex: 1;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.bv-header-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: rgba(255,255,255,0.15);
  color: var(--bv-primary-text);
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s ease;
  margin-left: 6px;
  outline: none;
}

.bv-header-btn:hover { background: rgba(255,255,255,0.25); }

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
  gap: 16px;
  padding: 32px 16px;
  flex: 1;
}

.bv-empty-icon {
  font-size: 48px;
  line-height: 1;
}

.bv-empty-text {
  color: var(--bv-text-secondary);
  font-size: 15px;
}

.bv-suggestions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  max-width: 280px;
  margin-top: 8px;
}

.bv-suggestion-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--bv-text-muted);
  margin-bottom: 2px;
}

.bv-suggestion {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  font-size: 13px;
  color: var(--bv-text);
  transition: all 0.15s ease;
  text-align: left;
}

.bv-suggestion:hover {
  background: var(--bv-surface-hover);
  border-color: var(--bv-primary);
  color: var(--bv-primary);
}

.bv-suggestion-icon {
  color: var(--bv-primary);
  flex-shrink: 0;
  display: flex;
}

/* ── Message Bubbles ──────────────────────── */

.bv-msg {
  display: flex;
  flex-direction: column;
  animation: bvFadeInUp 0.25s ease;
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
  border-bottom-right-radius: 4px;
}

.bv-msg-assistant .bv-bubble {
  background: var(--bv-assistant-bg);
  color: var(--bv-assistant-text);
  border-bottom-left-radius: 4px;
}

.bv-msg-time {
  font-size: 10px;
  color: var(--bv-text-muted);
  margin-top: 3px;
  padding: 0 4px;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.bv-msg:hover .bv-msg-time { opacity: 1; }

/* ── Intent Card ──────────────────────────── */

.bv-intent-card {
  max-width: 100%;
  background: var(--bv-surface);
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
  background: var(--bv-primary-light);
  color: var(--bv-primary);
  font-weight: 600;
  font-size: 12px;
}

.bv-intent-body { padding: 10px 12px; }

.bv-intent-row {
  display: flex;
  padding: 3px 0;
}

.bv-intent-label {
  width: 70px;
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
  font-weight: 600;
  text-align: left;
  padding: 6px 10px;
  border-bottom: 1px solid var(--bv-border);
  white-space: nowrap;
  color: var(--bv-text-secondary);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.bv-table td {
  padding: 6px 10px;
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
  padding: 6px 0 2px;
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
  font-weight: 600;
  font-size: 13px;
  color: var(--bv-warning);
  margin-bottom: 8px;
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
  padding: 7px 16px;
  border: none;
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  outline: none;
  font-family: inherit;
}

.bv-btn-confirm {
  background: var(--bv-success);
  color: #fff;
}
.bv-btn-confirm:hover { filter: brightness(1.1); }

.bv-btn-reject {
  background: var(--bv-surface);
  color: var(--bv-text);
  border: 1px solid var(--bv-border);
}
.bv-btn-reject:hover { background: var(--bv-error-bg); color: var(--bv-error); border-color: var(--bv-error); }

/* ── Result Messages ──────────────────────── */

.bv-result-success {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: var(--bv-success-bg);
  color: var(--bv-success);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
}

.bv-result-error {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: var(--bv-error-bg);
  color: var(--bv-error);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
}

.bv-rejected-msg {
  padding: 8px 12px;
  background: var(--bv-surface);
  color: var(--bv-text-muted);
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-style: italic;
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
  width: 6px;
  height: 6px;
  background: var(--bv-primary);
  border-radius: 50%;
}

/* ── Loading / Typing ─────────────────────── */

.bv-typing {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: var(--bv-assistant-bg);
  border-radius: var(--bv-radius-sm);
  border-bottom-left-radius: 4px;
  max-width: 100px;
}

.bv-typing-dots {
  display: flex;
  gap: 4px;
}

.bv-typing-dot {
  width: 7px;
  height: 7px;
  background: var(--bv-text-muted);
  border-radius: 50%;
  animation: bvTypingDot 1.4s infinite ease-in-out;
}

.bv-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.bv-typing-dot:nth-child(3) { animation-delay: 0.4s; }

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
  background: var(--bv-surface);
  color: var(--bv-text);
  resize: none;
  outline: none;
  transition: border-color 0.15s ease;
  min-height: 40px;
  max-height: 120px;
  overflow-y: auto;
}

.bv-textarea::placeholder { color: var(--bv-text-muted); }
.bv-textarea:focus { border-color: var(--bv-primary); }

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
  transition: all 0.15s ease;
  outline: none;
}

.bv-send-btn:hover { background: var(--bv-primary-hover); }
.bv-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Keyboard shortcut hint */
.bv-input-hint {
  font-size: 10px;
  color: var(--bv-text-muted);
  text-align: center;
  padding: 0 16px 8px;
  background: var(--bv-bg);
}

.bv-kbd {
  display: inline-block;
  padding: 1px 5px;
  font-size: 10px;
  font-family: inherit;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 3px;
  margin: 0 1px;
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
  padding: 10px 16px;
  border-radius: var(--bv-radius-xs);
  font-size: 13px;
  font-weight: 500;
  box-shadow: var(--bv-shadow-sm);
  animation: bvSlideIn 0.3s ease;
  max-width: 320px;
}

.bv-toast-success { background: var(--bv-success); color: #fff; }
.bv-toast-error   { background: var(--bv-error);   color: #fff; }
.bv-toast-info    { background: var(--bv-info);     color: #fff; }

.bv-toast-exit { animation: bvFadeOut 0.3s ease forwards; }

/* ── Animations ───────────────────────────── */

@keyframes bvFadeInUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes bvTypingDot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
  30%           { transform: translateY(-4px); opacity: 1; }
}

@keyframes bvSlideIn {
  from { opacity: 0; transform: translateX(40px); }
  to   { opacity: 1; transform: translateX(0); }
}

@keyframes bvFadeOut {
  to { opacity: 0; transform: translateY(-8px); }
}

@keyframes bvPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
  50%      { box-shadow: 0 0 0 12px rgba(99,102,241,0); }
}

.bv-fab.bv-pulse { animation: bvPulse 2s ease infinite; }

/* ── Responsive (Mobile) ──────────────────── */

@media (max-width: 640px) {
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
    bottom: 16px;
    right: 16px;
    width: 48px;
    height: 48px;
  }

  .bv-root.bv-left .bv-fab { right: auto; left: 16px; }
}

/* ── Reduced motion ───────────────────────── */

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
`;
