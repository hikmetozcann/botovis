// ─────────────────────────────────────────────────
//  Botovis Widget — Shadow DOM Styles v2
//  Full-height drawer, flat messages, timeline steps
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
  --bv-primary: #6366f1;
  --bv-primary-hover: #4f46e5;
  --bv-primary-light: #eef2ff;
  --bv-primary-text: #ffffff;

  --bv-accent: #0ea5e9;
  --bv-accent-light: #e0f2fe;

  --bv-bg: #ffffff;
  --bv-bg-elevated: #ffffff;
  --bv-surface: #f8fafc;
  --bv-surface-hover: #f1f5f9;
  --bv-text: #0f172a;
  --bv-text-secondary: #475569;
  --bv-text-muted: #94a3b8;
  --bv-border: #e2e8f0;
  --bv-border-light: #f1f5f9;

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

  --bv-drawer-width: 420px;
  --bv-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 20px 40px -8px rgba(15,23,42,.12);
  --bv-shadow-sm: 0 1px 3px rgba(15,23,42,.08);
  --bv-radius: 12px;
  --bv-radius-sm: 8px;
  --bv-radius-xs: 4px;
}

.bv-root.bv-dark {
  --bv-primary: #818cf8;
  --bv-primary-hover: #a5b4fc;
  --bv-primary-light: #1e1b4b;

  --bv-accent: #38bdf8;
  --bv-accent-light: #0c4a6e;

  --bv-bg: #0f172a;
  --bv-bg-elevated: #1e293b;
  --bv-surface: #1e293b;
  --bv-surface-hover: #334155;
  --bv-text: #f1f5f9;
  --bv-text-secondary: #94a3b8;
  --bv-text-muted: #64748b;
  --bv-border: #334155;
  --bv-border-light: #1e293b;

  --bv-success-bg: #064e3b;
  --bv-success-text: #6ee7b7;
  --bv-error-bg: #450a0a;
  --bv-error-text: #fca5a5;
  --bv-warning-bg: #451a03;
  --bv-warning-text: #fcd34d;
  --bv-info-bg: #0c4a6e;

  --bv-shadow: 0 0 0 1px rgba(255,255,255,.05), 0 20px 40px -8px rgba(0,0,0,.4);
  --bv-shadow-sm: 0 1px 3px rgba(0,0,0,.3);
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
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
  box-shadow: var(--bv-shadow-sm);
  transition: all 0.15s ease;
  outline: none;
}

.bv-fab:hover {
  transform: translateY(-1px);
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

.bv-fab.bv-hidden { display: none; }

.bv-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  background: var(--bv-error);
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
.bv-root.bv-left .bv-drawer { right: auto; left: 0; border-right: 1px solid var(--bv-border); border-left: none; }
.bv-root.bv-left .bv-resize-handle { right: -3px; left: auto; cursor: ew-resize; }

/* ── Drawer ───────────────────────────────── */

.bv-drawer {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: var(--bv-drawer-width);
  max-width: 90vw;
  min-width: 320px;
  background: var(--bv-bg);
  border-left: 1px solid var(--bv-border);
  display: flex;
  flex-direction: column;
  pointer-events: auto;
  transform: translateX(100%);
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.bv-root.bv-left .bv-drawer {
  transform: translateX(-100%);
}

.bv-drawer.bv-open {
  transform: translateX(0);
}

/* Resize Handle */
.bv-resize-handle {
  position: absolute;
  top: 0;
  left: -3px;
  bottom: 0;
  width: 6px;
  cursor: ew-resize;
  z-index: 10;
  transition: background 0.1s;
}

.bv-resize-handle:hover,
.bv-resize-handle.bv-active {
  background: var(--bv-primary);
  opacity: 0.3;
}

/* ── Header ───────────────────────────────── */

.bv-header {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--bv-border);
  flex-shrink: 0;
  gap: 2px;
  background: var(--bv-bg);
}

.bv-header-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.bv-header-logo svg {
  height: 18px;
  width: auto;
  flex-shrink: 0;
}

.bv-header-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--bv-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-hbtn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: var(--bv-text-muted);
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.1s ease;
  outline: none;
  flex-shrink: 0;
}

.bv-hbtn:hover {
  background: var(--bv-surface);
  color: var(--bv-text);
}

.bv-hbtn.bv-active {
  background: var(--bv-primary-light);
  color: var(--bv-primary);
}

.bv-hbtn svg { width: 15px; height: 15px; }

.bv-hsep {
  width: 1px;
  height: 16px;
  background: var(--bv-border);
  margin: 0 4px;
}

/* ── Content Area ─────────────────────────── */

.bv-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  position: relative;
}

/* ── History Panel (overlay) ──────────────── */

.bv-history-panel {
  display: none;
  position: absolute;
  inset: 0;
  background: var(--bv-bg);
  z-index: 5;
  flex-direction: column;
  animation: bvSlideDown 0.15s ease;
}

.bv-history-panel.bv-visible { display: flex; }

.bv-history-header {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid var(--bv-border);
  gap: 8px;
}

.bv-history-title {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
}

.bv-history-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.bv-history-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  transition: background 0.1s;
}

.bv-history-item:hover { background: var(--bv-surface-hover); }

.bv-history-item-content { flex: 1; min-width: 0; }

.bv-history-item-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--bv-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-history-item-meta {
  font-size: 11px;
  color: var(--bv-text-muted);
  margin-top: 2px;
}

.bv-history-item-delete {
  opacity: 0;
  padding: 4px;
  background: none;
  border: none;
  color: var(--bv-text-muted);
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.1s;
  display: flex;
}

.bv-history-item:hover .bv-history-item-delete { opacity: 1; }
.bv-history-item-delete:hover { background: var(--bv-error-bg); color: var(--bv-error); }

.bv-history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: var(--bv-text-muted);
  font-size: 13px;
  gap: 8px;
}

/* ── Messages Area ────────────────────────── */

.bv-messages {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  scroll-behavior: smooth;
  overscroll-behavior: contain;
}

.bv-messages::-webkit-scrollbar { width: 5px; }
.bv-messages::-webkit-scrollbar-track { background: transparent; }
.bv-messages::-webkit-scrollbar-thumb { background: var(--bv-border); border-radius: 3px; }
.bv-messages::-webkit-scrollbar-thumb:hover { background: var(--bv-text-muted); }

/* ── Empty State ──────────────────────────── */

.bv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 16px;
  padding: 48px 32px;
  flex: 1;
}

.bv-empty-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bv-primary-light);
  border-radius: 10px;
  color: var(--bv-primary);
}

.bv-empty-text {
  color: var(--bv-text-secondary);
  font-size: 14px;
  max-width: 280px;
  line-height: 1.5;
}

/* Suggestion Chips */
.bv-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  max-width: 340px;
  margin-top: 4px;
}

.bv-suggestion {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: var(--bv-bg);
  border: 1px solid var(--bv-border);
  border-radius: 20px;
  cursor: pointer;
  font-size: 12px;
  color: var(--bv-text-secondary);
  transition: all 0.1s ease;
  font-family: inherit;
}

.bv-suggestion:hover {
  border-color: var(--bv-primary);
  color: var(--bv-primary);
}

.bv-suggestion-icon {
  display: flex;
  color: var(--bv-text-muted);
}

.bv-suggestion:hover .bv-suggestion-icon { color: var(--bv-primary); }

/* ── Flat Message Style ───────────────────── */

.bv-msg {
  padding: 14px 20px;
  animation: bvFadeIn 0.15s ease;
}

.bv-msg + .bv-msg {
  border-top: 1px solid var(--bv-border-light);
}

.bv-msg-user { background: var(--bv-surface); }
.bv-msg-assistant { background: var(--bv-bg); }

.bv-msg-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.bv-msg-avatar {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.bv-msg-user .bv-msg-avatar {
  border-radius: 50%;
  background: var(--bv-text);
  color: var(--bv-bg);
}

.bv-msg-assistant .bv-msg-avatar {
  border-radius: 6px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
}

.bv-msg-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--bv-text);
}

.bv-msg-time {
  font-size: 11px;
  color: var(--bv-text-muted);
  margin-left: auto;
  opacity: 0;
  transition: opacity 0.1s;
}

.bv-msg:hover .bv-msg-time { opacity: 1; }

.bv-msg-body {
  padding-left: 28px;
  font-size: 14px;
  color: var(--bv-text);
  line-height: 1.6;
}

/* ── Agent Timeline Steps ─────────────────── */

.bv-timeline-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  margin-bottom: 8px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  color: var(--bv-text-secondary);
  font-family: inherit;
  transition: all 0.1s;
}

.bv-timeline-toggle:hover {
  background: var(--bv-surface-hover);
  border-color: var(--bv-text-muted);
}

.bv-timeline-toggle svg {
  width: 12px;
  height: 12px;
  color: var(--bv-text-muted);
  transition: transform 0.15s;
}

.bv-timeline-toggle.bv-expanded svg {
  transform: rotate(90deg);
}

.bv-tl-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 16px;
  height: 16px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border-radius: 8px;
  font-size: 10px;
  font-weight: 600;
  padding: 0 4px;
}

.bv-timeline {
  display: none;
  position: relative;
  padding-left: 16px;
  margin: 4px 0 10px 0;
}

.bv-timeline.bv-visible {
  display: block;
  animation: bvSlideDown 0.15s ease;
}

.bv-timeline::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 4px;
  bottom: 4px;
  width: 2px;
  background: var(--bv-border);
  border-radius: 1px;
}

.bv-tl-step {
  position: relative;
  padding: 6px 0;
  font-size: 12px;
  line-height: 1.5;
}

.bv-tl-step::before {
  content: '';
  position: absolute;
  left: -14px;
  top: 12px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--bv-text-muted);
  border: 2px solid var(--bv-bg);
}

.bv-tl-step.bv-done::before { background: var(--bv-success); }
.bv-tl-step.bv-running::before { background: var(--bv-primary); animation: bvPulse 1s infinite; }

.bv-tl-thought {
  color: var(--bv-text-secondary);
  line-height: 1.5;
  word-break: break-word;
}

.bv-tl-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  padding: 2px 8px;
  background: var(--bv-info-bg);
  border-radius: 4px;
  font-size: 11px;
  color: var(--bv-info);
  font-family: ui-monospace, monospace;
}

/* ── Streaming Thinking ───────────────────── */

.bv-thinking-line {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  margin-bottom: 4px;
}

.bv-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid var(--bv-border);
  border-top-color: var(--bv-primary);
  border-radius: 50%;
  animation: bvSpin 0.7s linear infinite;
  flex-shrink: 0;
}

.bv-thinking-label {
  font-size: 13px;
  color: var(--bv-text-muted);
}

.bv-thinking-count {
  margin-left: auto;
  font-size: 11px;
  color: var(--bv-text-muted);
  background: var(--bv-surface);
  padding: 1px 6px;
  border-radius: 8px;
}

/* ── Intent Card ──────────────────────────── */

.bv-intent-card {
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  overflow: hidden;
  font-size: 13px;
  margin: 8px 0;
}

.bv-intent-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  color: var(--bv-text-secondary);
  font-weight: 500;
  font-size: 12px;
  border-bottom: 1px solid var(--bv-border);
}

.bv-intent-header svg { color: var(--bv-text-muted); }
.bv-intent-body { padding: 8px 12px; }

.bv-intent-row { display: flex; padding: 3px 0; }

.bv-intent-label {
  width: 64px;
  flex-shrink: 0;
  color: var(--bv-text-muted);
  font-size: 11px;
}

.bv-intent-value {
  color: var(--bv-text);
  font-size: 12px;
  font-family: ui-monospace, monospace;
}

.bv-intent-value.bv-action-create { color: var(--bv-success); }
.bv-intent-value.bv-action-read   { color: var(--bv-info); }
.bv-intent-value.bv-action-update { color: var(--bv-warning); }
.bv-intent-value.bv-action-delete { color: var(--bv-error); }

/* ── Data Table ───────────────────────────── */

.bv-table-wrapper {
  max-width: 100%;
  overflow-x: auto;
  margin: 8px 0;
  border-radius: var(--bv-radius-sm);
  border: 1px solid var(--bv-border);
}

.bv-table { width: 100%; border-collapse: collapse; font-size: 12px; }

.bv-table th {
  background: var(--bv-surface);
  font-weight: 500;
  text-align: left;
  padding: 6px 10px;
  border-bottom: 1px solid var(--bv-border);
  white-space: nowrap;
  color: var(--bv-text-secondary);
  font-size: 11px;
}

.bv-table td {
  padding: 6px 10px;
  border-bottom: 1px solid var(--bv-border-light);
  white-space: nowrap;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
}

.bv-table tr:last-child td { border-bottom: none; }
.bv-table tr:hover td { background: var(--bv-surface); }

.bv-table-footer { font-size: 11px; color: var(--bv-text-muted); padding: 6px 0; }

/* ── Confirmation ─────────────────────────── */

.bv-confirm-card {
  background: var(--bv-bg-elevated);
  border: 1px solid var(--bv-primary);
  border-left: 3px solid var(--bv-primary);
  border-radius: var(--bv-radius-sm);
  padding: 14px 16px;
  margin: 8px 0;
  transition: all 0.2s ease;
}

.bv-confirm-card.bv-confirm-confirmed {
  border-color: var(--bv-success);
  border-left-color: var(--bv-success);
  background: var(--bv-success-bg);
}

.bv-confirm-card.bv-confirm-rejected {
  border-color: var(--bv-border);
  border-left-color: var(--bv-text-muted);
  background: var(--bv-surface);
  opacity: 0.75;
}

.bv-confirm-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}

.bv-confirm-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bv-primary-light);
  color: var(--bv-primary);
  border-radius: 8px;
}

.bv-confirm-confirmed .bv-confirm-icon {
  background: var(--bv-success-bg);
  color: var(--bv-success);
}

.bv-confirm-rejected .bv-confirm-icon {
  background: var(--bv-surface);
  color: var(--bv-text-muted);
}

.bv-confirm-header-text {
  flex: 1;
  min-width: 0;
}

.bv-confirm-title {
  font-weight: 600;
  font-size: 13px;
  color: var(--bv-text);
  line-height: 1.3;
}

.bv-confirm-action-label {
  font-size: 12px;
  color: var(--bv-text-secondary);
  margin-top: 2px;
}

.bv-confirm-desc {
  font-size: 13px;
  color: var(--bv-text);
  margin-bottom: 10px;
  line-height: 1.5;
}

/* Details toggle */
.bv-detail-toggle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: var(--bv-text-secondary);
  font-size: 12px;
  cursor: pointer;
  padding: 4px 0;
  margin-bottom: 4px;
  font-family: inherit;
  transition: color 0.15s;
}

.bv-detail-toggle:hover { color: var(--bv-primary); }

.bv-detail-toggle svg {
  transition: transform 0.2s ease;
}

.bv-detail-toggle.bv-open svg {
  transform: rotate(180deg);
}

.bv-confirm-details {
  display: none;
  margin-bottom: 10px;
}

.bv-confirm-details.bv-open {
  display: block;
}

.bv-detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.bv-detail-table th {
  text-align: left;
  padding: 5px 8px;
  font-weight: 500;
  color: var(--bv-text-secondary);
  background: var(--bv-surface);
  border-bottom: 1px solid var(--bv-border);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.bv-detail-table td {
  padding: 5px 8px;
  border-bottom: 1px solid var(--bv-border-light);
  color: var(--bv-text);
}

.bv-detail-field {
  font-weight: 500;
  color: var(--bv-text-secondary);
  white-space: nowrap;
  width: 1%;
}

.bv-detail-value {
  word-break: break-word;
}

.bv-confirm-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

/* Status indicator (replaces buttons after confirm/reject) */
.bv-confirm-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.bv-confirm-status-ok {
  color: var(--bv-success-text);
  background: var(--bv-success-bg);
}

.bv-confirm-status-rejected {
  color: var(--bv-text-muted);
  background: var(--bv-surface);
}

.bv-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 16px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  outline: none;
  font-family: inherit;
}

.bv-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.bv-btn-confirm {
  background: var(--bv-primary);
  color: var(--bv-primary-text);
}
.bv-btn-confirm:hover:not(:disabled) {
  background: var(--bv-primary-hover);
}

.bv-btn-reject {
  background: var(--bv-bg);
  color: var(--bv-text);
  border: 1px solid var(--bv-border);
}
.bv-btn-reject:hover:not(:disabled) {
  background: var(--bv-error-bg);
  color: var(--bv-error-text);
  border-color: var(--bv-error);
}

/* ── Results ──────────────────────────────── */

.bv-result-success {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: var(--bv-success-bg);
  color: var(--bv-success-text);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  margin: 6px 0;
}

.bv-result-error {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: var(--bv-error-bg);
  color: var(--bv-error-text);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  margin: 6px 0;
}

.bv-rejected-msg {
  padding: 8px 10px;
  background: var(--bv-surface);
  color: var(--bv-text-muted);
  border-radius: 6px;
  font-size: 12px;
}

.bv-step-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--bv-text-muted);
  padding: 2px 0;
}

.bv-step-dot {
  width: 4px;
  height: 4px;
  background: var(--bv-primary);
  border-radius: 50%;
}

/* ── Input Area ───────────────────────────── */

.bv-input-area {
  padding: 12px 16px;
  border-top: 1px solid var(--bv-border);
  background: var(--bv-bg);
  flex-shrink: 0;
}

.bv-input-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 10px;
  padding: 8px 12px;
  transition: border-color 0.15s;
}

.bv-input-row:focus-within { border-color: var(--bv-primary); }

.bv-textarea {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--bv-text);
  font-size: 13px;
  font-family: inherit;
  line-height: 1.5;
  resize: none;
  outline: none;
  min-height: 20px;
  max-height: 120px;
}

.bv-textarea::placeholder { color: var(--bv-text-muted); }

.bv-send-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border-radius: 7px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.1s;
  outline: none;
  flex-shrink: 0;
}

.bv-send-btn:hover { background: var(--bv-primary-hover); }
.bv-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.bv-send-btn svg { width: 14px; height: 14px; }

/* Input suggestions */
.bv-input-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 8px 0 0 0;
}

.bv-input-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: transparent;
  border: 1px solid var(--bv-border);
  border-radius: 14px;
  cursor: pointer;
  font-size: 11px;
  color: var(--bv-text-muted);
  transition: all 0.1s;
  font-family: inherit;
}

.bv-input-chip:hover {
  border-color: var(--bv-primary);
  color: var(--bv-primary);
  background: var(--bv-primary-light);
}

.bv-input-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px 8px;
  font-size: 10px;
  color: var(--bv-text-muted);
  flex-shrink: 0;
}

.bv-kbd {
  display: inline-flex;
  padding: 1px 4px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 3px;
  font-size: 10px;
  font-family: ui-monospace, monospace;
  line-height: 1.4;
}

/* ── Toasts ───────────────────────────────── */

.bv-toasts {
  position: absolute;
  top: 60px;
  left: 16px;
  right: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  pointer-events: auto;
  z-index: 20;
}

.bv-toast {
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  box-shadow: var(--bv-shadow-sm);
  animation: bvSlideDown 0.15s ease;
}

.bv-toast-success { background: var(--bv-success); color: #fff; }
.bv-toast-error   { background: var(--bv-error);   color: #fff; }
.bv-toast-info    { background: var(--bv-text); color: var(--bv-bg); }
.bv-toast-exit    { animation: bvFadeOut 0.2s ease forwards; }

/* ── Markdown ─────────────────────────────── */

.bv-md p { margin: 0 0 0.5em 0; }
.bv-md p:last-child { margin-bottom: 0; }
.bv-md strong { font-weight: 600; }
.bv-md em { font-style: italic; }
.bv-md a { color: var(--bv-primary); text-decoration: none; }
.bv-md a:hover { text-decoration: underline; }

.bv-md-code {
  font-family: ui-monospace, monospace;
  font-size: 0.85em;
  background: var(--bv-surface);
  padding: 0.15em 0.4em;
  border-radius: 4px;
  color: var(--bv-primary);
}

.bv-md-pre {
  font-family: ui-monospace, monospace;
  font-size: 0.85em;
  background: var(--bv-surface);
  padding: 12px 14px;
  border-radius: var(--bv-radius-sm);
  overflow-x: auto;
  margin: 8px 0;
  white-space: pre-wrap;
  word-wrap: break-word;
  border: 1px solid var(--bv-border);
}

.bv-md-h2, .bv-md-h3, .bv-md-h4 { font-weight: 600; margin: 0.75em 0 0.25em 0; line-height: 1.3; }
.bv-md-h2 { font-size: 1.2em; }
.bv-md-h3 { font-size: 1.05em; }
.bv-md-h4 { font-size: 1em; }

.bv-md-ul, .bv-md-ol { margin: 0.5em 0; padding-left: 1.5em; }
.bv-md-li, .bv-md-oli { margin: 0.25em 0; }

.bv-md-hr {
  border: none;
  border-top: 1px solid var(--bv-border);
  margin: 12px 0;
}

/* ── Animations ───────────────────────────── */

@keyframes bvFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

@keyframes bvSlideDown {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes bvFadeOut { to { opacity: 0; } }

@keyframes bvSpin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

@keyframes bvPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

@keyframes bvTypingDot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
  30%           { transform: translateY(-3px); opacity: 1; }
}

/* Loading dots */
.bv-typing-dots {
  display: flex;
  gap: 4px;
  padding: 4px 0;
}

.bv-typing-dot {
  width: 5px;
  height: 5px;
  background: var(--bv-text-muted);
  border-radius: 50%;
  animation: bvTypingDot 1.2s infinite ease-in-out;
}

.bv-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.bv-typing-dot:nth-child(3) { animation-delay: 0.3s; }

/* ── Responsive ───────────────────────────── */

@media (max-width: 640px) {
  .bv-drawer { width: 100% !important; max-width: 100vw; }
  .bv-resize-handle { display: none; }
  .bv-fab { bottom: 20px; right: 20px; }
  .bv-root.bv-left .bv-fab { right: auto; left: 20px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
`;
