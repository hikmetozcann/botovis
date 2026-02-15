# API Referansı

Botovis'in tüm HTTP uç noktaları, istek/yanıt formatları ve SSE olayları.

## Temel URL

Tüm rotalar yapılandırılmış prefix ile başlar:

```
/{prefix}/...
Varsayılan: /botovis/...
```

## Kimlik Doğrulama

Tüm istekler yapılandırılmış middleware'den geçer (varsayılan: `web`, `auth`). CSRF token gereklidir.

```
X-CSRF-TOKEN: {token}
// veya
Cookie: XSRF-TOKEN={token}
```

## Uç Noktalar

### Sohbet (Basit Mod)

#### `POST /chat`

Tek seferlik sorgu-cevap (non-streaming, simple mod).

**İstek:**
```json
{
    "message": "Toplam ürün sayısı kaç?"
}
```

**Yanıt:**
```json
{
    "response": "Veritabanında toplam 247 ürün bulunuyor.",
    "sql": "SELECT COUNT(*) FROM products",
    "intent": "aggregate"
}
```

### Akış (Agent Modu)

#### `POST /stream`

SSE ile agent akışı başlatır.

**İstek:**
```json
{
    "message": "Aktif ürünlerin fiyat ortalaması nedir?",
    "conversation_id": "uuid-optional"
}
```

**Yanıt:** `text/event-stream` (SSE)

#### `POST /stream-confirm`

Bekleyen yazma işlemini onaylar ve akışı devam ettirir.

**İstek:**
```json
{
    "conversation_id": "uuid"
}
```

**Yanıt:** `text/event-stream` (SSE)

### Onay İşlemleri

#### `POST /confirm`

Non-streaming onay (simple mod).

**İstek:**
```json
{
    "conversation_id": "uuid"
}
```

**Yanıt:**
```json
{
    "response": "Kayıt başarıyla oluşturuldu.",
    "status": "confirmed"
}
```

#### `POST /reject`

Bekleyen işlemi reddeder.

**İstek:**
```json
{
    "conversation_id": "uuid"
}
```

**Yanıt:**
```json
{
    "response": "İşlem iptal edildi.",
    "status": "rejected"
}
```

### Sıfırlama

#### `POST /reset`

Konuşma durumunu sıfırlar.

**İstek:**
```json
{
    "conversation_id": "uuid"
}
```

**Yanıt:**
```json
{
    "status": "reset"
}
```

### Şema

#### `GET /schema`

Kullanıcının erişebildiği veritabanı şemasını döndürür.

**Yanıt:**
```json
{
    "tables": [
        {
            "name": "products",
            "label": "Ürünler",
            "columns": [
                {"name": "id", "type": "integer", "nullable": false},
                {"name": "name", "type": "string", "nullable": false},
                {"name": "price", "type": "decimal", "nullable": false}
            ],
            "relations": [
                {"name": "category", "type": "belongsTo", "related_table": "categories"}
            ]
        }
    ]
}
```

### Durum

#### `GET /status`

API ve yapılandırma durumunu döndürür.

**Yanıt:**
```json
{
    "status": "ok",
    "mode": "agent",
    "driver": "anthropic",
    "streaming": true,
    "authenticated": true
}
```

### Konuşma Yönetimi

#### `GET /conversations`

Kullanıcının konuşma listesini döndürür.

**Yanıt:**
```json
{
    "data": [
        {
            "id": "uuid",
            "title": "Ürün analizi",
            "created_at": "2024-01-15T10:30:00Z",
            "updated_at": "2024-01-15T10:35:00Z",
            "message_count": 5
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 42
    }
}
```

#### `GET /conversations/{id}`

Tek bir konuşmayı mesajlarıyla birlikte döndürür.

**Yanıt:**
```json
{
    "id": "uuid",
    "title": "Ürün analizi",
    "messages": [
        {"role": "user", "content": "Toplam ürün sayısı kaç?"},
        {"role": "assistant", "content": "Veritabanında 247 ürün var."}
    ]
}
```

#### `DELETE /conversations/{id}`

Konuşmayı siler.

**Yanıt:**
```json
{
    "status": "deleted"
}
```

#### `POST /conversations/{id}/title`

Konuşma başlığını günceller.

**İstek:**
```json
{
    "title": "Ürün fiyat analizi"
}
```

**Yanıt:**
```json
{
    "id": "uuid",
    "title": "Ürün fiyat analizi"
}
```

## SSE Olay Tipleri

Akış modunda sunucu şu olayları gönderir:

| Olay | Açıklama | Veri |
|------|----------|------|
| `step` | Agent bir adım tamamladı | `AgentStep` JSON |
| `message` | Son metin yanıtı | `{content, conversation_id}` |
| `token` | Tek token (streaming) | `{token}` |
| `confirmation` | Yazma onayı bekliyor | `{step, conversation_id}` |
| `error` | Hata oluştu | `{error, message}` |
| `done` | Akış tamamlandı | `{}` |

### AgentStep Formatı

```json
{
    "step": 1,
    "type": "action",
    "thought": "Ürün sayısını bulmam gerekiyor.",
    "action": "count_records",
    "actionInput": {"table": "products"},
    "observation": "Sayım: 247"
}
```

Paralel araç çağrılarında `action` alanı virgülle ayrılmış şekilde gelir:

```json
{
    "step": 1,
    "type": "action",
    "action": "count_records, count_records, aggregate_records",
    "actionInput": {"table": "products"},
    "observation": "Sayım: 247\n---\nSayım: 189\n---\nOrtalama: 85.50"
}
```

## Hata Kodları

| HTTP Kodu | Açıklama |
|-----------|----------|
| `401` | Kimlik doğrulama başarısız |
| `403` | Yetkilendirme hatası |
| `404` | Konuşma bulunamadı |
| `419` | CSRF token geçersiz veya eksik |
| `422` | Doğrulama hatası (eksik/geçersiz parametre) |
| `429` | İstek sınırı aşıldı |
| `500` | Sunucu hatası (LLM bağlantısı vb.) |

## CSRF Yönetimi

Widget otomatik olarak CSRF token'ı yönetir:

1. `<meta name="csrf-token">` etiketinden okur
2. Her isteğe `X-CSRF-TOKEN` header'ı olarak ekler
3. 419 yanıtında token'ı yeniler ve isteği tekrarlar

Manuel kullanım için:

```javascript
fetch('/botovis/stream', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'text/event-stream',
    },
    body: JSON.stringify({ message: 'Merhaba' }),
});
```

---

Önceki: [Widget](widget.md) · Sonraki: [Artisan Komutları](artisan-commands.md)
