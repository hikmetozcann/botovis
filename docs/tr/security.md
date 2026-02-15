# Güvenlik

Botovis çok katmanlı bir güvenlik sistemi kullanır. Her istek kimlik doğrulama, rol tabanlı erişim kontrolü ve şema filtreleme katmanlarından geçer.

## Güvenlik Katmanları

```
İstek → Kimlik Doğrulama → Rol Çözümleme → RBAC (İzinler) → Gate Kontrolleri → Şema Filtreleme → İşlem
```

## 1. Kimlik Doğrulama

```php
// config/botovis.php
'security' => [
    'auth' => [
        'enabled' => env('BOTOVIS_AUTH_ENABLED', true),
        'guard' => env('BOTOVIS_AUTH_GUARD', null),
    ],
],
```

- `enabled: true` (varsayılan) — Giriş yapmamış kullanıcılar 401 hatası alır
- `enabled: false` — Herkes erişebilir (geliştirme ortamı için)
- `guard` — Özel auth guard belirtmek için (örn. `'sanctum'`, `'api'`)

## 2. Rol Çözümleme

Botovis, kullanıcı rolünü belirlemek için 4 farklı yöntem sunar:

### Yöntem 1: Model Özelliği (Varsayılan)

```php
'role' => [
    'method' => 'property',
    'value' => 'role',       // $user->role
    'default_role' => 'viewer',
],
```

Kullanıcının `role` alanını doğrudan okur: `User::role`.

### Yöntem 2: Model Metodu

```php
'role' => [
    'method' => 'method',
    'value' => 'getBotovisRole',
    'default_role' => 'viewer',
],
```

Kullanıcı modelindeki bir metod çağrılır:

```php
// User.php
public function getBotovisRole(): string
{
    if ($this->is_super_admin) return 'admin';
    if ($this->can('edit-records')) return 'editor';
    return 'viewer';
}
```

### Yöntem 3: Config Eşleme

```php
'role' => [
    'method' => 'config_map',
    'value' => 'email',  // $user->email değerine göre eşleme yapar
    'default_role' => 'viewer',
    'map' => [
        'admin@sirket.com' => 'admin',
        'editor@sirket.com' => 'editor',
    ],
],
```

Belirli kullanıcılara sabit roller atamak için kullanışlıdır.

### Yöntem 4: Gate

```php
'role' => [
    'method' => 'gate',
    'default_role' => 'viewer',
],
```

Laravel Gate sistemi ile entegre olur. Yapılandırma dosyasındaki her rol için `botovis-role-{rolAdı}` gate'i kontrol edilir:

```php
// AuthServiceProvider.php
Gate::define('botovis-role-admin', fn(User $user) => $user->is_admin);
Gate::define('botovis-role-editor', fn(User $user) => $user->department === 'IT');
```

## 3. Rol Tabanlı Erişim Kontrolü (RBAC)

Her rol için `can_read`, `can_write` ve `excluded_tables` ayarlanır:

```php
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
```

### İzin Matrisi

| İzin | Açıklama |
|------|----------|
| `can_read` | Veri sorgulama (SELECT) araçlarına erişim |
| `can_write` | Veri değişikliği (CREATE/UPDATE/DELETE) araçlarına erişim |
| `excluded_tables` | Bu tablolar şemadan tamamen gizlenir |

### Okuma-Yazma Araç Sınıflandırması

| Araç | Tür | Açıklama |
|------|-----|----------|
| `search_records` | Okuma | Kayıt arama |
| `count_records` | Okuma | Kayıt sayma |
| `aggregate_records` | Okuma | Toplam, ortalama, min, max |
| `group_records` | Okuma | Gruplama ve sayma |
| `list_tables` | Okuma | Tablo listesi |
| `create_record` | Yazma | Yeni kayıt oluşturma |
| `update_record` | Yazma | Kayıt güncelleme |
| `delete_record` | Yazma | Kayıt silme |

> **Not:** `can_write: false` olan roller yazma araçlarını göremez — ne şemada görünür ne de LLM tarafından çağrılabilir.

## 4. Gate Kontrolleri

Ek yetkilendirme için Gate'ler kullanılabilir:

```php
// Tüm Botovis erişimini kontrol et
Gate::define('use-botovis', fn(User $user) => $user->is_active);

// Belirli tablolar için
Gate::define('botovis-read-salary_records', fn(User $user) => $user->is_hr);
Gate::define('botovis-write-products', fn(User $user) => $user->department === 'warehouse');
```

## 5. Yazma Onayı

```php
'write_confirmation' => [
    'enabled' => env('BOTOVIS_WRITE_CONFIRM', true),
],
```

Etkinleştirildiğinde, yazma araçları (create, update, delete) otomatik olarak çalıştırılmaz. Agent döngüsü durur ve kullanıcıya onay sorusu gösterilir:

```
🔧 create_record çalıştırılmak isteniyor:
   Tablo: products
   Veri: {"name": "Yeni Ürün", "price": 99.90}

   [Onayla]  [Reddet]
```

- **Onayla** → İşlem gerçekleştirilir, agent sonucu görür ve özetler
- **Reddet** → İşlem iptal edilir, agent bilgilendirilir

## 6. Şema Filtreleme

`excluded_tables` listesindeki tablolar tam olarak gizlenir:

- LLM'ye gönderilen sistem promptunda görünmez
- `list_tables` aracının çıktısında listelenmez
- Araç çağrılarında tablo adı olarak kullanılamaz

Bu, yapay zekanın hassas tabloların varlığından bile habersiz olmasını sağlar.

## SecurityContext

Tüm güvenlik bilgileri `SecurityContext` DTO'sunda taşınır:

```php
SecurityContext {
    userId: int|string|null
    userRole: string       // Çözümlenmiş rol
    canRead: bool
    canWrite: bool
    excludedTables: array  // Gizlenecek tablolar
}
```

Bu nesne:
- Her istekte oluşturulur
- Tüm araçlara aktarılır
- Şema filtrelemeyi kontrol eder
- Agent promptuna izin bilgisi olarak eklenir

## En İyi Uygulamalar

1. **Üretim ortamında auth'u her zaman aktif tutun** — `BOTOVIS_AUTH_ENABLED=true`
2. **Yazma onayını devre dışı bırakmayın** — Yanlışlıkla veri değişikliğini önler
3. **Hassas tabloları hariç tutun** — `users`, `password_resets`, `personal_access_tokens` vb.
4. **En az yetki ilkesi** — Varsayılan rolü `viewer` olarak ayarlayın
5. **Gate'leri kullanın** — Tablo bazlı ince ayarlı erişim kontrolü için
6. **Özel guard kullanın** — API erişimi için `sanctum` gibi guard'ları tercih edin

## Güvenlik Denetimi

Mevcut yapılandırmanızı kontrol edin:

```bash
# Keşfedilen tabloları ve erişim durumunu görün
php artisan botovis:discover

# JSON formatında detaylı çıktı
php artisan botovis:discover --json
```

---

Önceki: [Yapılandırma](configuration.md) · Sonraki: [Araçlar](tools.md)
