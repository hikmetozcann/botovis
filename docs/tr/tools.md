# Araçlar

Botovis'in agent'ı veritabanıyla etkileşim kurmak için araçları (tools) kullanır. 8 yerleşik araç mevcuttur ve özel araçlar oluşturabilirsiniz.

## Yerleşik Araçlar

### Okuma Araçları

#### `search_records`
Tabloda kayıt arar ve sonuçları döndürür.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `conditions` | array | ❌ | Filtre koşulları |
| `columns` | array | ❌ | Döndürülecek kolonlar |
| `order_by` | string | ❌ | Sıralama kolonu |
| `order_direction` | string | ❌ | `asc` veya `desc` |
| `limit` | int | ❌ | Maksimum kayıt sayısı (varsayılan: 10) |

**Koşul formatı:**
```json
[
    {"column": "status", "operator": "=", "value": "active"},
    {"column": "price", "operator": ">", "value": 100},
    {"column": "name", "operator": "like", "value": "%telefon%"}
]
```

Desteklenen operatörler: `=`, `!=`, `<`, `>`, `<=`, `>=`, `like`, `not like`, `in`, `not in`, `is null`, `is not null`

#### `count_records`
Tablodaki koşullara uyan kayıt sayısını döndürür.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `conditions` | array | ❌ | Filtre koşulları |

#### `aggregate_records`
Toplam, ortalama, min, max gibi hesaplamalar yapar.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `function` | string | ✅ | `sum`, `avg`, `min`, `max` |
| `column` | string | ✅ | Hesaplama yapılacak kolon |
| `conditions` | array | ❌ | Filtre koşulları |

#### `group_records`
Kayıtları gruplar ve her grup için sayı döndürür.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `group_by` | string | ✅ | Gruplama kolonu |
| `conditions` | array | ❌ | Filtre koşulları |
| `having_min` | int | ❌ | Minimum grup boyutu |

#### `list_tables`
Kullanıcının erişebildiği tüm tabloları ve kolon bilgilerini listeler. Parametre almaz.

### Yazma Araçları

> **Not:** Yazma araçları yalnızca `can_write: true` olan roller tarafından kullanılabilir. `write_confirmation` etkinse kullanıcı onayı gerekir.

#### `create_record`
Tabloya yeni kayıt ekler.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `data` | object | ✅ | Kolon-değer çiftleri |

#### `update_record`
Belirtilen koşullara uyan kayıtları günceller.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `conditions` | array | ✅ | Güncelleme koşulları |
| `data` | object | ✅ | Güncellenecek kolon-değer çiftleri |

#### `delete_record`
Belirtilen koşullara uyan kayıtları siler.

| Parametre | Tür | Zorunlu | Açıklama |
|-----------|-----|---------|----------|
| `table` | string | ✅ | Tablo adı |
| `conditions` | array | ✅ | Silme koşulları |

## Özel Araç Oluşturma

### 1. ToolInterface Uygulayın

```php
<?php

namespace App\Botovis\Tools;

use Botovis\Core\Tools\ToolInterface;
use Botovis\Core\Tools\ToolResult;
use Botovis\Core\DTO\SecurityContext;

class WeatherTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Belirtilen şehir için güncel hava durumu bilgisi getirir.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'Şehir adı',
                ],
            ],
            'required' => ['city'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false; // Okuma işlemi, onay gereksiz
    }

    public function execute(array $params, SecurityContext $context): ToolResult
    {
        $city = $params['city'];

        // API çağrısı veya iş mantığı
        $weather = $this->fetchWeather($city);

        return ToolResult::success("$city: {$weather['temp']}°C, {$weather['condition']}");
    }

    private function fetchWeather(string $city): array
    {
        // Gerçek API çağrısı burada yapılır
        return ['temp' => 22, 'condition' => 'Güneşli'];
    }
}
```

### 2. Aracı Kaydedin

Service provider'ınıza ekleyin:

```php
// AppServiceProvider.php
use Botovis\Core\Tools\ToolRegistry;
use App\Botovis\Tools\WeatherTool;

public function boot(): void
{
    $this->app->afterResolving(ToolRegistry::class, function (ToolRegistry $registry) {
        $registry->register(new WeatherTool());
    });
}
```

### 3. Doğrulayın

```bash
php artisan botovis:chat
> Ankara'nın hava durumu nasıl?
```

Agent otomatik olarak `get_weather` aracını çağıracaktır.

## ToolResult

Araç sonuçları `ToolResult` nesnesi olarak döndürülür:

```php
// Başarılı sonuç
ToolResult::success("247 kayıt bulundu");

// Hata sonucu
ToolResult::error("Tablo bulunamadı: invalid_table");
```

`ToolResult` iki parçadan oluşur:
- `success: bool` — İşlem başarılı mı
- `observation: string` — LLM'ye iletilecek sonuç metni

## Paralel Araç Çağrıları

LLM tek bir yanıtta birden fazla araç çağrısı döndürebilir. Örneğin:

```
Kullanıcı: "Toplam ürün sayısı, aktif ürün sayısı ve ortalama fiyat nedir?"

Agent düşünür: 3 farklı sorgu yapmam gerekiyor, hepsini aynı anda çağırabilirim.

→ count_records(table: products)
→ count_records(table: products, conditions: [{column: status, operator: =, value: active}])
→ aggregate_records(table: products, function: avg, column: price)
```

Tüm çağrılar tek adımda işlenir. Widget'ta birleşik araç adı olarak gösterilir (örn. "count ×2, aggregate").

## Araç Güvenliği

- Tablo adları şemaya karşı doğrulanır — geçersiz tablo adları reddedilir
- `excluded_tables` listesindeki tablolara erişim engellenir
- Yazma araçları `can_write` iznine bağlıdır
- `requiresConfirmation()` yazma araçlarında onay mekanizmasını etkinleştirir
- Tüm araçlara `SecurityContext` aktarılır — araçlar kendi ek kontrollerini yapabilir

---

Önceki: [Güvenlik](security.md) · Sonraki: [Widget](widget.md)
