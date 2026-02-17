# Kurulum

## Gereksinimler

- **PHP** 8.1+
- **Laravel** 10, 11 veya 12
- **Veritabanı** MySQL, PostgreSQL veya SQLite
- **LLM API Anahtarı** Anthropic, OpenAI veya yerel Ollama erişimi

## Adım 1: Composer ile Kurulum

```bash
composer require botovis/botovis-laravel
```

## Adım 2: Yapılandırma Dosyasını Yayınlama

```bash
php artisan vendor:publish --tag=botovis-config
```

Bu komut `config/botovis.php` dosyasını oluşturur. Tüm yapılandırma seçenekleri için [Yapılandırma](configuration.md) rehberine bakın.

## Adım 3: Veritabanı Migration'ları

```bash
php artisan vendor:publish --tag=botovis-migrations
php artisan migrate
```

Bu iki tablo oluşturur:
- `botovis_conversations` — Konuşma geçmişi
- `botovis_messages` — Mesaj kayıtları

## Adım 4: Ortam Değişkenleri

`.env` dosyanıza ekleyin:

```env
# Anthropic (varsayılan)
ANTHROPIC_API_KEY=sk-ant-...
BOTOVIS_LLM_DRIVER=anthropic

# VEYA OpenAI
OPENAI_API_KEY=sk-...
BOTOVIS_LLM_DRIVER=openai

# VEYA Ollama (yerel)
BOTOVIS_LLM_DRIVER=ollama
OLLAMA_HOST=http://localhost:11434
```

## Adım 5: Eloquent Modelleri Yapılandırma

Botovis, otomatik olarak `app/Models` dizinindeki Eloquent modellerini keşfeder. Modelin Botovis tarafından bulunabilmesi için `$fillable` özelliğinin tanımlı olması gerekir.

**İsteğe bağlı model özellikleri:**

```php
class Product extends Model
{
    protected $fillable = ['name', 'price', 'category', 'status'];

    // Kolon açıklamaları (yapay zeka için bağlam sağlar)
    public static array $columnDescriptions = [
        'status' => 'Ürün durumu: active, passive, draft',
        'sku' => 'Stok Takip Ünitesi kodu',
    ];

    // Enum değerleri keşfi (LLM'nin geçerli değerleri bilmesini sağlar)
    public static function statusOptions(): array
    {
        return ['active', 'passive', 'draft'];
    }

    // İlişkiler (otomatik olarak keşfedilir)
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

## Adım 6: Widget Varlıkları

```bash
php artisan vendor:publish --tag=botovis-assets
```

Bu komut widget JavaScript dosyasını `public/vendor/botovis/` dizinine kopyalar.

## Adım 7: Blade Şablonuna Ekleme

Ana layout dosyanıza ekleyin:

```blade
{{-- resources/views/layouts/app.blade.php --}}
<body>
    {{ $slot }}

    @botovisWidget
</body>
```

Widget artık sayfanın sağ alt köşesinde görünecektir.

## Kurulumu Doğrulama

### Model Tarama ve Yapılandırma

```bash
php artisan botovis:models
```

Projenizdeki Eloquent model'leri tarar, hangi model'leri eklemek istediğinizi sorar ve config snippet'i üretir. `--write` ile doğrudan `config/botovis.php`'ye yazabilirsiniz.

### Keşif Kontrolü

```bash
php artisan botovis:discover
```

Botovis'in keşfettiği tüm tabloları ve kolonları listeler.

### CLI ile Test

```bash
php artisan botovis:chat
```

Terminal üzerinden yapay zekaya soru sorarak her şeyin çalıştığını doğrulayın.

## Sorun Giderme

### Widget Görünmüyor

1. Varlıkların yayınlandığından emin olun:
   ```bash
   php artisan vendor:publish --tag=botovis-assets --force
   ```
2. `public/vendor/botovis/botovis-widget.iife.js` dosyasının var olduğunu kontrol edin
3. Tarayıcı konsolunda JavaScript hatalarını inceleyin

### LLM Bağlantı Hataları

1. API anahtarınızı kontrol edin: `php artisan tinker` → `config('botovis.llm.drivers.anthropic.api_key')`
2. `.env` dosyasında ortam değişkenlerini kontrol edin
3. `BOTOVIS_LLM_DRIVER` değerinin doğru olduğundan emin olun

### Model Keşfedilmiyor

1. `app/Models` dizininde olduğundan emin olun
2. `$fillable` özelliğinin tanımlı olduğunu kontrol edin
3. Yapılandırmada `models.exclude` listesinde olmadığını kontrol edin
4. `php artisan botovis:discover --json` ile detaylı çıktıyı inceleyin

### CSRF Token Hataları

Widget otomatik olarak `<meta name="csrf-token">` etiketinden CSRF token'ı alır. Etiketin `<head>` bölümünde mevcut olduğundan emin olun:

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

---

Sonraki: [Yapılandırma](configuration.md)
