// ─────────────────────────────────────────────────
//  Botovis Widget — Type Definitions
// ─────────────────────────────────────────────────

export interface BotovisConfig {
  endpoint: string;
  lang: 'tr' | 'en';
  theme: 'light' | 'dark' | 'auto';
  position: 'bottom-right' | 'bottom-left';
  title: string;
  placeholder: string;
  csrfToken: string | null;
  sounds: boolean;
}

export type MessageRole = 'user' | 'assistant' | 'system';

export type MessageType =
  | 'text'
  | 'action'
  | 'confirmation'
  | 'executed'
  | 'rejected'
  | 'error'
  | 'loading';

export interface ChatMessage {
  id: string;
  role: MessageRole;
  type: MessageType;
  content: string;
  timestamp: Date;
  intent?: ResolvedIntent | null;
  result?: ActionResult | null;
}

export interface ResolvedIntent {
  type: string;
  action: string | null;
  table: string | null;
  data: Record<string, unknown>;
  where: Record<string, unknown>;
  select: string[];
  message: string;
  confidence: number;
  auto_continue: boolean;
}

export interface ActionResult {
  success: boolean;
  message: string;
  data: unknown;
  affected: number;
}

export interface IntermediateStep {
  intent: ResolvedIntent;
  result: ActionResult;
}

export interface ApiResponse {
  conversation_id: string;
  type: 'message' | 'confirmation' | 'executed' | 'rejected' | 'error';
  message: string;
  intent?: ResolvedIntent;
  result?: ActionResult;
  steps?: IntermediateStep[];
}

export interface SchemaTable {
  table: string;
  actions: string[];
  columns: number;
}

export interface SchemaResponse {
  tables: SchemaTable[];
}

export interface SuggestedAction {
  label: string;
  message: string;
  icon: string;
}

// ── Conversation Types ───────────────────────────

export interface ConversationSummary {
  id: string;
  title: string;
  message_count: number;
  last_message: string | null;
  created_at: string;
  updated_at: string;
}

export interface ConversationMessage {
  id: string;
  role: MessageRole;
  content: string;
  intent: string | null;
  action: string | null;
  success: boolean | null;
  created_at: string;
}

export interface ConversationDetail {
  id: string;
  title: string;
  messages: ConversationMessage[];
  created_at: string;
  updated_at: string;
}

export interface ConversationListResponse {
  conversations: ConversationSummary[];
}

export interface ConversationResponse {
  conversation: ConversationDetail;
}
