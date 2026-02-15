# Widget

Botovis Widget, Shadow DOM kullanan bağımsız bir Web Component'tir. Herhangi bir bağımlılık gerektirmez ve tüm modern tarayıcılarda çalışır.

## Temel Kullanım

```html
<botovis-chat
    endpoint="/botovis"
    lang="tr"
    theme="auto"
    position="bottom-right"
    streaming="true"
></botovis-chat>

<script src="/vendor/botovis/botovis-widget.iife.js"></script>
```

Laravel'de Blade direktifi ile:

```blade
@botovisWidget
```

## HTML Öznitelikleri

| Öznitelik | Varsayılan | Açıklama |
|-----------|-----------|----------|
| `endpoint` | `/botovis` | API uç noktası URL'i |
| `lang` | `tr` | Widget dili (`tr` veya `en`) |
| `theme` | `auto` | Tema: `light`, `dark` veya `auto` |
| `position` | `bottom-right` | Konum: `bottom-right` veya `bottom-left` |
| `streaming` | `true` | SSE akışı etkin mi |
| `open` | — | Varsa widget açık başlar |
| `expanded` | — | Varsa tam panel olarak başlar |

## JavaScript API

### Programatik Erişim

```javascript
const chat = document.querySelector('botovis-chat');

// Paneli aç/kapat
chat.open();
chat.close();
chat.toggle();

// Mesaj gönder
chat.send("Toplam ürün sayısı kaç?");

// Akışı iptal et
chat.cancelStream();
```

### Olaylar (Events)

```javascript
const chat = document.querySelector('botovis-chat');

// Mesaj gönderildiğinde
chat.addEventListener('botovis:send', (e) => {
    console.log('Gönderilen:', e.detail.message);
});

// Yanıt alındığında
chat.addEventListener('botovis:response', (e) => {
    console.log('Yanıt:', e.detail);
});

// Hata oluştuğunda
chat.addEventListener('botovis:error', (e) => {
    console.log('Hata:', e.detail.error);
});

// Onay gerektiğinde
chat.addEventListener('botovis:confirmation', (e) => {
    console.log('Onay bekleniyor:', e.detail);
});
```

## Klavye Kısayolları

| Kısayol | İşlev |
|---------|-------|
| `Ctrl+Enter` / `⌘+Enter` | Mesaj gönder |
| `Escape` | Paneli kapat |
| `Ctrl+Shift+B` | Widget'ı aç/kapat |

## Tema Özelleştirme

### Hazır Temalar

```html
<botovis-chat theme="light"></botovis-chat>  <!-- Açık tema -->
<botovis-chat theme="dark"></botovis-chat>   <!-- Koyu tema -->
<botovis-chat theme="auto"></botovis-chat>   <!-- Sistem tercihini takip eder -->
```

### CSS Değişkenleri

Widget'ın görünümünü CSS değişkenleri ile özelleştirebilirsiniz:

```css
botovis-chat {
    /* Ana renkler */
    --botovis-primary: #6366f1;
    --botovis-primary-hover: #4f46e5;

    /* Panel boyutları */
    --botovis-panel-width: 420px;
    --botovis-panel-height: 600px;
    --botovis-panel-radius: 16px;

    /* Açma düğmesi */
    --botovis-fab-size: 56px;
    --botovis-fab-radius: 16px;

    /* Mesaj balonları */
    --botovis-msg-user-bg: #6366f1;
    --botovis-msg-user-color: #ffffff;
    --botovis-msg-bot-bg: #f3f4f6;
    --botovis-msg-bot-color: #1f2937;

    /* Yazı tipleri */
    --botovis-font-family: system-ui, -apple-system, sans-serif;
    --botovis-font-size: 14px;

    /* Header */
    --botovis-header-bg: #6366f1;
    --botovis-header-color: #ffffff;
}
```

### Koyu Tema Değişkenleri

```css
botovis-chat[theme="dark"] {
    --botovis-bg: #1a1a2e;
    --botovis-msg-bot-bg: #2d2d44;
    --botovis-msg-bot-color: #e2e8f0;
    --botovis-input-bg: #2d2d44;
    --botovis-input-color: #e2e8f0;
    --botovis-border-color: #374151;
}
```

## Düzen ve Özellikler

Widget şu bileşenlerden oluşur:

1. **FAB (Yüzen Buton)** — Sağ/sol altta konumlanır, tıklayınca paneli açar
2. **Panel** — Sohbet penceresi (header, mesaj listesi, giriş alanı)
3. **Header** — Başlık, genişletme/daraltma düğmesi, kapat düğmesi
4. **Mesaj Listesi** — Kullanıcı ve bot mesajları, araç adımları, onay kartları
5. **Giriş Alanı** — Metin girişi, gönder düğmesi, iptal düğmesi (akış sırasında)

### Markdown Desteği

Bot yanıtlarında desteklenen formatlar:
- **Kalın**, *italik*, `kod`
- Başlıklar (h1-h6)
- Sıralı ve sırasız listeler
- Kod blokları (söz dizimi vurgulamasız)
- Tablolar
- Bağlantılar
- Satır sonları

### Konuşma Geçmişi

Widget, konuşma geçmişi yönetimini destekler:
- Önceki konuşmaları listeler
- Konuşmalar arasında geçiş yapılabilir
- Yeni konuşma başlatılabilir
- Konuşma silme

## Framework Entegrasyonları

### React

```tsx
import { BotovisChat } from '@botovis/widget/react';

function App() {
    return (
        <BotovisChat
            endpoint="/botovis"
            lang="tr"
            theme="auto"
            position="bottom-right"
            streaming
            onSend={(e) => console.log('Gönderildi:', e.detail)}
            onResponse={(e) => console.log('Yanıt:', e.detail)}
        />
    );
}
```

### Vue 3

```vue
<template>
    <BotovisChat
        endpoint="/botovis"
        lang="tr"
        theme="auto"
        position="bottom-right"
        streaming
        @botovis:send="onSend"
        @botovis:response="onResponse"
    />
</template>

<script setup>
import { BotovisChat } from '@botovis/widget/vue';

function onSend(e) {
    console.log('Gönderildi:', e.detail);
}
function onResponse(e) {
    console.log('Yanıt:', e.detail);
}
</script>
```

**Vue 3 yapılandırması** — Custom element'i tanımlayın:

```js
// vite.config.js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => tag === 'botovis-chat',
                },
            },
        }),
    ],
});
```

### CDN Kullanımı

```html
<script src="https://unpkg.com/@botovis/widget/dist/botovis-widget.iife.js"></script>
<botovis-chat endpoint="/botovis" lang="tr" theme="auto"></botovis-chat>
```

## Çeviri (i18n)

Widget 68 çeviri anahtarı içerir. Yerleşik diller:

- `tr` — Türkçe (varsayılan)
- `en` — İngilizce

`lang` özniteliği ile seçim yapılır:

```html
<botovis-chat lang="en"></botovis-chat>
```

## Takip Mesajları

Widget, kullanıcılara bağlamsal takip soruları önerir. Bunlar bot yanıtlarının altında tıklanabilir düğmeler olarak gösterilir ve tıklandığında otomatik olarak gönderilir.

---

Önceki: [Araçlar](tools.md) · Sonraki: [API Referansı](api-reference.md)
