// ─────────────────────────────────────────────────
//  Botovis Widget — Entry Point
// ─────────────────────────────────────────────────
//
//  Usage:
//    <script src="botovis-widget.iife.js"></script>
//    <botovis-chat endpoint="/botovis" lang="tr"></botovis-chat>
//
//  Or via ESM:
//    import '@botovis/widget';
//

import { BotovisChat } from './botovis-chat';

if (!customElements.get('botovis-chat')) {
  customElements.define('botovis-chat', BotovisChat);
}

export { BotovisChat };
export type { BotovisConfig, ChatMessage, ApiResponse, ResolvedIntent, ActionResult } from './types';
