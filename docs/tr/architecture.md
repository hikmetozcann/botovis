# Mimari

Bu doküman Botovis'in iç mimarisini, projeyi anlamak, genişletmek veya katkıda bulunmak isteyen geliştiriciler için açıklar.

## Paket Yapısı

Botovis üç paketten oluşan bir monorepo'dur:

```
botovis/
├── packages/
│   ├── core/           # Framework-bağımsız PHP (arayüzler, DTO'lar, agent mantığı)
│   ├── laravel/        # Laravel entegrasyonu (sürücüler, araçlar, controller'lar)
│   └── widget/         # TypeScript Web Component (sıfır bağımlılık)
├── docs/               # Dokümantasyon
└── tests/              # Paylaşılan test dosyaları
```

### `core` — Framework-Bağımsız PHP

Tüm arayüzleri (contract), DTO'ları, enum'ları, şema modellerini, agent döngüsünü ve orkestrasyon mantığını içerir. **Framework bağımlılığı yoktur** — yalnızca PHP 8.1+ gerektirir.

```
core/src/
├── Contracts/           # 8 arayüz (LlmDriver, SchemaDiscovery, ActionExecutor, vb.)
├── DTO/                 # Veri nesneleri (LlmResponse, SecurityContext, Conversation, Message)
├── Enums/               # ActionType, ColumnType, IntentType, RelationType
├── Schema/              # DatabaseSchema, TableSchema, ColumnSchema, RelationSchema
├── Tools/               # ToolInterface, ToolRegistry, ToolResult
├── Intent/              # IntentResolver, ResolvedIntent (simple mod)
├── Conversation/        # ConversationState
├── Agent/               # AgentLoop, AgentOrchestrator, AgentState, AgentStep, StreamingEvent
├── Orchestrator.php     # Simple mod orkestratörü
└── OrchestratorResponse.php
```

### `laravel` — Laravel Entegrasyonu

Core arayüzlerini Laravel'in Eloquent, Cache, Session, Auth vb. bileşenleri ile uygular.

```
laravel/src/
├── BotovisServiceProvider.php    # DI bağlamaları, yayınlamalar, rotalar, Blade direktifi
├── Http/
│   ├── BotovisController.php     # Sohbet + SSE akış uç noktaları
│   └── ConversationController.php # Konuşma CRUD
├── Llm/
│   ├── LlmDriverFactory.php      # Ayardan sürücü oluşturur
│   ├── AnthropicDriver.php        # Claude API
│   ├── OpenAiDriver.php           # OpenAI API (+ uyumlu uç noktalar)
│   └── OllamaDriver.php          # Yerel Ollama
├── Tools/
│   ├── BaseTool.php               # Paylaşılan Eloquent yardımcıları
│   ├── SearchRecordsTool.php      # 8 veritabanı aracı...
│   └── ...
├── Schema/
│   └── EloquentSchemaDiscovery.php  # Model yansıması + DB iç gözlemi
├── Action/
│   └── EloquentActionExecutor.php   # Eloquent CRUD çalıştırma
├── Security/
│   └── BotovisAuthorizer.php        # Rol tabanlı yetkilendirme
├── Conversation/
│   └── CacheConversationManager.php # Cache tabanlı durum yönetimi
├── Repositories/
│   ├── EloquentConversationRepository.php  # Veritabanı depolama
│   └── SessionConversationRepository.php   # Oturum depolama
├── Models/
│   ├── BotovisConversation.php
│   └── BotovisMessage.php
└── Commands/
    ├── DiscoverCommand.php
    └── ChatCommand.php
```

### `widget` — TypeScript Web Component

Shadow DOM kullanan sıfır bağımlılıklı sohbet arayüzü.

```
widget/src/
├── index.ts           # Custom element kaydı, export'lar
├── botovis-chat.ts    # Ana bileşen (1600+ satır)
├── api.ts             # REST + SSE istemcisi, CSRF yönetimi
├── types.ts           # Tüm TypeScript arayüzleri
├── styles.ts          # Tam CSS (Shadow DOM adopted stylesheet'ler)
├── i18n.ts            # Türkçe + İngilizce çeviriler (68 anahtar)
└── icons.ts           # 30+ inline SVG ikon
```

Çıktı formatları: ES module, UMD ve IIFE (Vite ile derlenir).

## İstek Yaşam Döngüsü

### Agent Modu (varsayılan)

```
Tarayıcı               Widget               Laravel              AgentLoop
  │                      │                     │                     │
  │  Gönder'e tıkla      │                     │                     │
  │─────────────────────>│                     │                     │
  │                      │  POST /stream       │                     │
  │                      │────────────────────>│                     │
  │                      │                     │  SecurityContext oluştur │
  │                      │                     │  Kullanıcı rolünü çöz   │
  │                      │                     │  Konuşma geçmişini al   │
  │                      │                     │────────────────────>│
  │                      │                     │                     │  Sistem promptu oluştur
  │                      │                     │                     │  (şema + kurallar + izinler)
  │                      │                     │                     │
  │                      │                     │                     │  DÖNGÜ:
  │                      │                     │                     │  ┌──────────────────────────┐
  │                      │                     │                     │  │ LLM.chatWithTools()      │
  │  SSE: step           │                     │  step olayı ver     │  │                          │
  │<─────────────────────│<────────────────────│<────────────────────│  │ → metin? → tamamla       │
  │                      │                     │                     │  │ → araç? → çalıştır       │
  │                      │                     │                     │  │   → okuma: çalıştır+gözle│
  │  SSE: step (gözlem)  │                     │  step olayı ver     │  │   → yazma: durdur+sor    │
  │<─────────────────────│<────────────────────│<────────────────────│  │                          │
  │                      │                     │                     │  └──────────────────────────┘
  │                      │                     │                     │
  │  SSE: message        │                     │  mesaj ver          │
  │<─────────────────────│<────────────────────│<────────────────────│
  │  SSE: done           │                     │                     │
  │<─────────────────────│<────────────────────│                     │
```

### Onay Akışı

```
Agent yazma aracı algılar
    │
    ├── tool_call mesajını duruma ekler (düşünce ile)
    ├── [PENDING] araç sonucu yer tutucusu ekler
    ├── SSE confirmation olayı gönderir
    └── Döngüyü duraklatır (AgentState = needs_confirmation)

Kullanıcı Onayla'ya tıklar
    │
    ├── POST /stream-confirm
    ├── Aracı çalıştır
    ├── [PENDING]'i gerçek sonuçla değiştir
    ├── Agent döngüsünü devam ettir (LLM özetleyebilsin)
    └── message + done olayları gönder
```

## Agent Döngüsü

`AgentLoop`, ReAct (Düşün-Eylem-Gözlem) kalıbını uygular:

### Tek Adım

```php
// 1. Şema, araçlar, izinler ve aciliyet uyarılarıyla prompt oluştur
$systemPrompt = $this->buildSystemPrompt($state);

// 2. Generate stopping: son adımda araçları kaldır
$toolDefs = $stepsRemaining <= 1 ? [] : $this->tools->toFunctionDefinitions();

// 3. LLM'yi native tool calling ile çağır
$response = $this->llm->chatWithTools($systemPrompt, $messages, $toolDefs);

// 4. Yanıtı işle
if ($response->isText()) → yanıtla tamamla
if ($response->isToolCall()) → handleToolCalls()
```

### Paralel Araç Çağrıları

LLM tek bir yanıtta birden fazla araç çağrısı döndürdüğünde:

```php
// handleToolCalls():
// 1. Önce TÜM tool_call mesajlarını ekle (API dengeli çiftler gerektirir)
foreach ($toolCalls as $tc) {
    $state->addToolCallMessage($tc['id'], $tc['name'], $tc['params']);
}

// 2. Her birini işle: okuma → çalıştır, yazma → [PENDING]
foreach ($toolCalls as $tc) {
    if ($tool->requiresConfirmation()) {
        $state->addToolResultMessage($tc['id'], '[PENDING]...');
    } else {
        $result = $this->tools->execute($tc['name'], $tc['params']);
        $state->addToolResultMessage($tc['id'], $result->toObservation());
    }
}

// 3. Tüm sonuçlar TEK adım olarak sayılır
$step = AgentStep::action($stepNum, $thought, "count_records, count_records, count_records", ...);
```

### Generate Stopping

"Maksimum adım aşıldı" durumunu cevapsız önler:

1. **Aciliyet uyarıları** — ≤3 adım kaldığında sistem promptuna UYARI eklenir
2. **Kritik mesaj** — ≤1 adım kaldığında yapay zeka hemen yanıt vermeye zorlanır
3. **Araç kaldırma** — Son adımda araçlar API çağrısından çıkarılır, metin yanıtı zorunlu olur

## LLM Sürücü Mimarisi

Tüm sürücüler `LlmDriverInterface` arayüzünü uygular:

```php
interface LlmDriverInterface
{
    public function chat(string $systemPrompt, array $messages): string;
    public function chatWithTools(string $systemPrompt, array $messages, array $tools): LlmResponse;
    public function stream(string $systemPrompt, array $messages, callable $onToken): string;
    public function name(): string;
}
```

### Normalize Mesaj Formatı

Botovis dahili olarak herhangi bir LLM API'sinden bağımsız normalize mesaj formatı kullanır:

```php
// Araç çağrısı (LLM'den)
['role' => 'assistant', 'content' => 'düşünce...', 'tool_call' => [
    'id' => 'call_123',
    'name' => 'count_records',
    'params' => ['table' => 'products'],
]]

// Araç sonucu (gözlem)
['role' => 'tool_result', 'tool_call_id' => 'call_123', 'content' => 'Sayım: 247']
```

Her sürücünün `convertMessages()` metodu bunu API'ye özgü formata dönüştürür:

- **Anthropic**: `tool_use` / `tool_result` tiplerinde içerik blokları, art arda aynı rol mesajları birleştirilir
- **OpenAI**: Assistant mesajlarında `tool_calls` dizisi, ayrı `tool` rolü mesajları
- **Ollama**: OpenAI'ye benzer, sentetik araç çağrısı ID'leri ile

### Mesaj Birleştirme

Anthropic API'si art arda aynı rol mesajlarına izin vermez. Sürücü otomatik birleştirir:
- Art arda assistant mesajları → birleştirilmiş içerik bloklarıyla tek mesaj
- Art arda tool_result mesajları → birden fazla `tool_result` bloğu ile tek user mesajı

## Şema Keşfi

`EloquentSchemaDiscovery` veritabanı şemasını birleştirerek oluşturur:

1. **Eloquent yansıması** — Fillable/guarded, cast'ler, ilişkiler
2. **Veritabanı iç gözlemi** — Kolon tipleri, nullable, varsayılanlar, maks uzunluk, enum değerleri
3. **Kural tabanlı keşif** — Static method'lardan enum değerleri (`statusOptions()`, `statusLabels()`, vb.)
4. **Etiket üretimi** — `ProductCategory` modeli → "Product Categories" etiketi

Oluşturulan `DatabaseSchema`:
- Sistem promptu olarak LLM'ye gönderilir
- Görüntülenmeden önce kullanıcı izinleriyle filtrelenir
- Araçlar tarafından tablo ve kolon adlarını doğrulamak için kullanılır

## Genişletme Noktaları

### Özel LLM Sürücüsü

`LlmDriverInterface` uygulayın ve service provider'da kaydedin:

```php
$this->app->singleton(LlmDriverInterface::class, fn() => new MyCustomDriver());
```

### Özel Araçlar

`ToolInterface` uygulayın ve kaydedin:

```php
$this->app->afterResolving(ToolRegistry::class, function ($registry) {
    $registry->register(new MyCustomTool());
});
```

### Özel Yetkilendirme

Tamamen özel yetkilendirme için `AuthorizerInterface` uygulayın:

```php
$this->app->singleton(AuthorizerInterface::class, fn() => new MyAuthorizer());
```

### Özel Konuşma Depolama

`ConversationRepositoryInterface` uygulayın:

```php
$this->app->singleton(ConversationRepositoryInterface::class, fn() => new RedisConversationRepository());
```

## Service Container Bağlamaları

`BotovisServiceProvider` şu singleton'ları kaydeder:

| Arayüz | Varsayılan Uygulama |
|--------|---------------------|
| `SchemaDiscoveryInterface` | `EloquentSchemaDiscovery` |
| `LlmDriverInterface` | `LlmDriverFactory::make()` |
| `ActionExecutorInterface` | `EloquentActionExecutor` |
| `ConversationManagerInterface` | `CacheConversationManager` |
| `ConversationRepositoryInterface` | `EloquentConversationRepository` |
| `BotovisAuthorizer` | `BotovisAuthorizer` |
| `ToolRegistry` | 8 yerleşik araçla yapılandırılmış |
| `Orchestrator` | Simple mod orkestratörü |
| `AgentOrchestrator` | Agent mod orkestratörü |

## Blade Direktifi

`@botovisWidget` widget view'ını varsayılanlarla render eder:

```php
@botovisWidget
// Genişletilmiş hali:
// endpoint: /{prefix}
// lang: config('botovis.locale')
// theme: 'auto'
// position: 'bottom-right'
// streaming: config('botovis.agent.streaming')
```

Dizi geçirerek özelleştirin:

```php
@botovisWidget(['theme' => 'dark', 'lang' => 'en'])
```

## Varlık Yayınlama

| Etiket | Dosyalar | Hedef |
|--------|----------|-------|
| `botovis-config` | `config/botovis.php` | `config/` |
| `botovis-assets` | Widget JS paketi | `public/vendor/botovis/` |
| `botovis-views` | Blade şablonları | `resources/views/vendor/botovis/` |
| `botovis-migrations` | Migration dosyaları | `database/migrations/` |

---

Önceki: [Artisan Komutları](artisan-commands.md)
