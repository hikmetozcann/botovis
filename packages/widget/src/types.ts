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
