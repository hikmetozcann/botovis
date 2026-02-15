# Artisan Komutları

Botovis iki Artisan komutu sağlar: veritabanı keşfi ve CLI sohbeti.

## botovis:discover

Botovis'in keşfettiği tüm tabloları, kolonları, ilişkileri ve erişim durumunu listeler.

### Kullanım

```bash
php artisan botovis:discover
```

### Örnek Çıktı

```
Botovis Veritabanı Keşfi
========================

Bulunan tablolar: 8

┌──────────────────┬──────────┬──────────┬──────────┐
│ Tablo            │ Kolonlar │ İlişkiler│ Erişim   │
├──────────────────┼──────────┼──────────┼──────────┤
│ products         │ 12       │ 3        │ ✅ read/write │
│ categories       │ 5        │ 1        │ ✅ read/write │
│ orders           │ 8        │ 2        │ ✅ read/write │
│ order_items      │ 6        │ 2        │ ✅ read/write │
│ users            │ 10       │ 0        │ ❌ hariç tutuldu │
└──────────────────┴──────────┴──────────┴──────────┘
```

### Seçenekler

#### `--json`

Çıktıyı JSON formatında verir. Entegrasyon ve hata ayıklama için kullanışlıdır.

```bash
php artisan botovis:discover --json
```

```json
{
    "tables": [
        {
            "name": "products",
            "label": "Ürünler",
            "columns": [
                {"name": "id", "type": "integer", "nullable": false},
                {"name": "name", "type": "string", "nullable": false, "max_length": 255}
            ],
            "relations": [
                {"name": "category", "type": "belongsTo", "related_table": "categories"}
            ]
        }
    ]
}
```

#### `--prompt`

LLM'ye gönderilen sistem promptunu gösterir. Yapay zekanın gördüğü bağlamı incelemek için idealdir.

```bash
php artisan botovis:discover --prompt
```

## botovis:chat

Terminal üzerinden yapay zeka ile etkileşimli sohbet başlatır. Kurulumu test etmek ve hata ayıklamak için kullanışlıdır.

### Kullanım

```bash
php artisan botovis:chat
```

### Örnek Oturum

```
🤖 Botovis CLI Chat
Driver: anthropic (claude-sonnet-4-20250514)
Mode: agent
Çıkmak için 'exit' yazın.

> Toplam kaç ürün var?

🔧 count_records
   {"table": "products"}
   → Sayım: 247

Veritabanında toplam 247 ürün bulunmaktadır.

> Aktif olanların ortalama fiyatı?

💭 Aktif ürünlerin ortalama fiyatını bulmam gerekiyor.

🔧 aggregate_records
   {"table": "products", "function": "avg", "column": "price",
    "conditions": [{"column": "status", "operator": "=", "value": "active"}]}
   → Ortalama: 156.75

Aktif ürünlerin ortalama fiyatı 156,75 TL'dir.

> exit
```

### Seçenekler

#### `--simple`

Agent modu yerine simple modda çalıştırır (araç kullanmaz, doğrudan SQL üretir).

```bash
php artisan botovis:chat --simple
```

### Özellikler

- Tam agent döngüsü desteği (düşünce, araç çağrısı, gözlem)
- Yazma onayı (terminal'de y/n ile)
- Konuşma geçmişi (oturum boyunca bağlamı korur)
- Renkli çıktı (düşünceler, araçlar, sonuçlar farklı renklerde)
- Paralel araç çağrıları

---

Önceki: [API Referansı](api-reference.md) · Sonraki: [Mimari](architecture.md)
