# Yapılandırma

Tüm yapılandırmalar `config/botovis.php` dosyasında bulunur. Çoğu ayar `.env` dosyasından ortam değişkenleri ile yönetilebilir.

## Çalışma Modu

```php
'mode' => env('BOTOVIS_MODE', 'agent'),
```

| Mod | Açıklama |
|-----|----------|
| `agent` | Tam ReAct agent döngüsü — araç çağırmayı destekler (varsayılan) |
| `simple` | Tek seferlik sorgu-cevap — LLM'ye SQL ürettirir ve döndürür |

Agent modu, çok adımlı akıl yürütme, araç kullanımı ve write onayı gibi tüm özellikleri sunar. Simple mod yalnızca basit SQL sorguları için uygundur.

## Dil

```php
'locale' => env('BOTOVIS_LOCALE', 'tr'),
```

Desteklenen değerler: `tr` (Türkçe), `en` (İngilizce). Widget ve backend yanıtlarının dilini belirler.

## Agent Ayarları

```php
'agent' => [
    'max_steps' => env('BOTOVIS_MAX_STEPS', 30),
    'streaming' => env('BOTOVIS_STREAMING', true),
],
```

| Ayar | Varsayılan | Açıklama |
|------|-----------|----------|
| `max_steps` | `30` | Agent döngüsü başına maksimum adım sayısı |
| `streaming` | `true` | SSE ile gerçek zamanlı token akışı |

> **Not:** Generate stopping mekanizması, son adımlarda yapay zekayı yanıt vermeye zorlar. 3 adım kaldığında uyarı, 1 adım kaldığında araçlar kaldırılır.

## Model Keşfi

```php
'models' => [
    'namespace' => 'App\\Models',
    'path' => app_path('Models'),
    'exclude' => [],
    'table_labels' => [],
],
```

| Ayar | Açıklama |
|------|----------|
| `namespace` | Eloquent model namespace'i |
| `path` | Model dosyalarının bulunduğu dizin |
| `exclude` | Hariç tutulacak model sınıfları listesi |
| `table_labels` | Tablo isimleri için insan dostu etiketler |

**Örnek:**

```php
'exclude' => [
    \App\Models\PersonalAccessToken::class,
    \App\Models\FailedJob::class,
],

'table_labels' => [
    'products' => 'Ürünler',
    'order_items' => 'Sipariş Kalemleri',
],
```

## LLM Yapılandırması

### Sürücü Seçimi

```php
'llm' => [
    'driver' => env('BOTOVIS_LLM_DRIVER', 'anthropic'),
    'drivers' => [
        // ...
    ],
],
```

### Anthropic (Claude)

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 8096),
    'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
],
```

### OpenAI (GPT)

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o'),
    'max_tokens' => env('OPENAI_MAX_TOKENS', 8096),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
],
```

> **İpucu:** `url` alanını değiştirerek Azure OpenAI veya diğer uyumlu API'leri kullanabilirsiniz.

### Ollama (Yerel)

```php
'ollama' => [
    'model' => env('OLLAMA_MODEL', 'llama3'),
    'url' => env('OLLAMA_HOST', 'http://localhost:11434'),
],
```

## Güvenlik

```php
'security' => [
    'auth' => [
        'enabled' => env('BOTOVIS_AUTH_ENABLED', true),
        'guard' => env('BOTOVIS_AUTH_GUARD', null),
    ],
    'role' => [
        'method' => 'property',
        'value' => 'role',
        'default_role' => 'viewer',
    ],
    'write_confirmation' => [
        'enabled' => env('BOTOVIS_WRITE_CONFIRM', true),
    ],
    'roles' => [
        'admin' => [
            'can_read' => true,
            'can_write' => true,
            'excluded_tables' => [],
        ],
        'editor' => [
            'can_read' => true,
            'can_write' => true,
            'excluded_tables' => ['users', 'migrations'],
        ],
        'viewer' => [
            'can_read' => true,
            'can_write' => false,
            'excluded_tables' => ['users', 'personal_access_tokens', 'migrations'],
        ],
    ],
],
```

Detaylı bilgi için [Güvenlik](security.md) rehberine bakın.

### Rol Çözümleme Yöntemleri

| Yöntem | `method` | `value` | Açıklama |
|--------|----------|---------|----------|
| Model özelliği | `property` | `role` | `$user->role` |
| Model metodu | `method` | `getRole` | `$user->getRole()` |
| Config eşleme | `config_map` | `email` | `$user->email` sonucu config'ten aranır |
| Gate | `gate` | — | Laravel Gate'ler ile yetkilendirme |

## Rota Ayarları

```php
'route' => [
    'prefix' => env('BOTOVIS_ROUTE_PREFIX', 'botovis'),
    'middleware' => ['web', 'auth'],
],
```

| Ayar | Varsayılan | Açıklama |
|------|-----------|----------|
| `prefix` | `botovis` | URL ön eki (örn. `/botovis/chat`) |
| `middleware` | `['web', 'auth']` | Rotaya uygulanacak middleware'ler |

## Konuşma Ayarları

```php
'conversations' => [
    'storage' => env('BOTOVIS_CONVERSATION_STORAGE', 'database'),
    'per_page' => 20,
],
```

| Depolama | Açıklama |
|----------|----------|
| `database` | Konuşmalar veritabanına kaydedilir (varsayılan, kalıcı) |
| `session` | Konuşmalar oturumda tutulur (geçici) |

## Ortam Değişkenleri Özeti

| Değişken | Varsayılan | Açıklama |
|----------|-----------|----------|
| `BOTOVIS_MODE` | `agent` | Çalışma modu |
| `BOTOVIS_LOCALE` | `tr` | Dil |
| `BOTOVIS_LLM_DRIVER` | `anthropic` | LLM sürücüsü |
| `BOTOVIS_MAX_STEPS` | `30` | Maks. agent adımı |
| `BOTOVIS_STREAMING` | `true` | SSE akışı |
| `BOTOVIS_AUTH_ENABLED` | `true` | Kimlik doğrulama zorunluluğu |
| `BOTOVIS_AUTH_GUARD` | `null` | Auth guard |
| `BOTOVIS_WRITE_CONFIRM` | `true` | Yazma onayı |
| `BOTOVIS_ROUTE_PREFIX` | `botovis` | Rota ön eki |
| `BOTOVIS_CONVERSATION_STORAGE` | `database` | Konuşma depolama yöntemi |
| `ANTHROPIC_API_KEY` | — | Anthropic API anahtarı |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-20250514` | Anthropic model |
| `OPENAI_API_KEY` | — | OpenAI API anahtarı |
| `OPENAI_MODEL` | `gpt-4o` | OpenAI model |
| `OLLAMA_HOST` | `http://localhost:11434` | Ollama sunucu adresi |
| `OLLAMA_MODEL` | `llama3` | Ollama model |

---

Önceki: [Kurulum](installation.md) · Sonraki: [Güvenlik](security.md)
