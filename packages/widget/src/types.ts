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
  streaming: boolean;
}

export type MessageRole = 'user' | 'assistant' | 'system';

export type MessageType =
  | 'text'
  | 'action'
  | 'confirmation'
  | 'executed'
  | 'rejected'
  | 'error'
  | 'loading'
  | 'streaming';

export interface ChatMessage {
  id: string;
  role: MessageRole;
  type: MessageType;
  content: string;
  timestamp: Date;
  intent?: ResolvedIntent | null;
  result?: ActionResult | null;
  /** Snapshot of agent steps from streaming (for timeline rendering) */
  _steps?: Array<{step: number; thought: string; action?: string; observation?: string}>;
  /** Tracks whether this confirmation was confirmed or rejected */
  _confirmState?: 'confirmed' | 'rejected' | null;
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

// ── Streaming Types ──────────────────────────────

export type StreamingEventType =
  | 'init'
  | 'step'
  | 'thinking'
  | 'tool_call'
  | 'tool_result'
  | 'confirmation'
  | 'message'
  | 'error'
  | 'done';

export interface StreamingEvent {
  type: StreamingEventType;
  data: Record<string, unknown>;
}

export interface AgentStep {
  step: number;
  thought: string;
  action: string | null;
  action_params: Record<string, unknown> | null;
  observation: string | null;
}

export interface StreamingHandlers {
  /** Called for every event */
  onEvent?: (event: StreamingEvent) => void;
  /** Called when conversation ID is received */
  onInit?: (conversationId: string) => void;
  /** Called when a reasoning step completes */
  onStep?: (step: AgentStep) => void;
  /** Called when agent is thinking */
  onThinking?: (step: number, thought: string) => void;
  /** Called when agent calls a tool */
  onToolCall?: (step: number, tool: string, params: Record<string, unknown>) => void;
  /** Called when tool returns result */
  onToolResult?: (step: number, tool: string, observation: string) => void;
  /** Called when confirmation is needed */
  onConfirmation?: (action: string, params: Record<string, unknown>, description: string) => void;
  /** Called with final message */
  onMessage?: (content: string) => void;
  /** Called on error */
  onError?: (error: Error) => void;
  /** Called when stream completes */
  onDone?: (steps: AgentStep[], message: string | null) => void;
  /** Called when stream is aborted */
  onAbort?: () => void;
}
