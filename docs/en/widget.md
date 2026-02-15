# Widget

Botovis ships with a zero-dependency Web Component that works in any HTML page, framework, or CMS.

## Usage in Laravel

### Blade Directive

```blade
@botovisWidget
```

With options:

```blade
@botovisWidget([
    'theme'     => 'dark',
    'lang'      => 'tr',
    'position'  => 'bottom-left',
    'streaming' => false,
    'title'     => 'Help Desk',
])
```

### Manual HTML

```html
<botovis-chat
    endpoint="/botovis"
    lang="en"
    theme="auto"
    position="bottom-right"
    streaming="true"
    csrf-token="{{ csrf_token() }}"
></botovis-chat>

<script src="{{ asset('vendor/botovis/botovis-widget.iife.js') }}"></script>
```

## HTML Attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `endpoint` | string | `/botovis` | Backend API path |
| `lang` | `en` \| `tr` | `en` | Widget UI language |
| `theme` | `auto` \| `light` \| `dark` | `auto` | Color theme |
| `position` | `bottom-right` \| `bottom-left` | `bottom-right` | FAB position |
| `title` | string | `Botovis Assistant` | Header title |
| `placeholder` | string | `Ask something...` | Input placeholder |
| `csrf-token` | string | Auto-detected | CSRF token for Laravel |
| `sounds` | `true` \| `false` | `true` | Notification sound |
| `streaming` | `true` \| `false` | `true` | Enable SSE streaming |

Attributes can be changed after initialization — the widget observes `endpoint`, `csrf-token`, and `theme` dynamically.

## JavaScript API

```javascript
const widget = document.querySelector('botovis-chat');

// Open/close
widget.open();
widget.close();
widget.toggle();

// Send a message programmatically
widget.send('How many users are there?');

// Cancel current stream
widget.cancelStream();
```

## Events

```javascript
document.querySelector('botovis-chat').addEventListener('botovis:open', () => {
  console.log('Widget opened');
});

document.querySelector('botovis-chat').addEventListener('botovis:close', () => {
  console.log('Widget closed');
});

document.querySelector('botovis-chat').addEventListener('botovis:step', (e) => {
  console.log('Agent step:', e.detail);
});
```

| Event | Payload | When |
|-------|---------|------|
| `botovis:open` | — | Drawer opens |
| `botovis:close` | — | Drawer closes |
| `botovis:step` | `AgentStep` | Each agent reasoning step |

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` / `⌘K` | Toggle widget |
| `Enter` | Send message |
| `Shift+Enter` | New line |
| `Esc` | Close widget |

## Theming

The widget uses CSS custom properties. Since it runs in Shadow DOM, you can't style it from outside — but you can choose between `light`, `dark`, and `auto` themes.

### `auto` Mode

Follows the system preference via `prefers-color-scheme`. Changes in real-time when the OS theme switches.

### Toggle

Users can toggle between light and dark using the sun/moon button in the widget header.

### CSS Variables (internal)

These variables are used internally and define the complete color scheme:

| Variable | Light | Dark |
|----------|-------|------|
| `--bv-primary` | `#6366f1` | `#818cf8` |
| `--bv-bg` | `#ffffff` | `#0f172a` |
| `--bv-surface` | `#f8fafc` | `#1e293b` |
| `--bv-text` | `#0f172a` | `#f1f5f9` |
| `--bv-text-secondary` | `#475569` | `#94a3b8` |
| `--bv-border` | `#e2e8f0` | `#334155` |
| `--bv-success` | `#059669` | `#059669` |
| `--bv-error` | `#dc2626` | `#dc2626` |
| `--bv-radius` | `12px` | `12px` |

## Layout

The widget consists of:

1. **Floating Action Button (FAB)** — 48×48px circle with the Botovis logo, positioned fixed in the corner. Shows unread message badge.

2. **Side Drawer** — Full-height panel (default 420px wide) that slides in from the right (or left). Resizable by dragging the edge. On mobile (≤640px), it goes full-width.

3. **Header** — Title with action buttons: new conversation, history, theme toggle, close.

4. **Message Area** — Scrollable chat with:
   - User messages (gray background)
   - Assistant messages (white/dark background) with Botovis avatar
   - Confirmation cards (with approve/reject buttons)
   - Loading indicator (pulsing dots)
   - Streaming timeline (real-time reasoning steps)

5. **Input Area** — Auto-resizing textarea with send button and suggestion chips.

## Markdown Support

Assistant messages support Markdown rendering:

- **Bold** and *italic* text
- `inline code` and code blocks (``` ... ```)
- [Links](https://example.com)
- Lists (ordered and unordered)
- Tables
- Headings (h1–h3)
- Horizontal rules

## Conversation History

The widget includes a built-in history panel:

- **List conversations** — Shows previous chats with title, message count, and date
- **Load conversation** — Click to restore messages and continue
- **New conversation** — Start fresh
- **Delete conversation** — Remove with confirmation

History is stored according to the `conversations.driver` config (database or session).

## React Wrapper

```bash
npm install @botovis/widget
```

```jsx
import BotovisChat from '@botovis/widget/react';

function App() {
  return (
    <BotovisChat
      endpoint="/api/botovis"
      lang="en"
      theme="dark"
      position="bottom-right"
      onOpen={() => console.log('opened')}
      onClose={() => console.log('closed')}
    />
  );
}
```

### Props

| Prop | Type | Default |
|------|------|---------|
| `endpoint` | string | `/botovis` |
| `lang` | string | `en` |
| `theme` | string | `auto` |
| `position` | string | `bottom-right` |
| `title` | string | — |
| `placeholder` | string | — |
| `csrfToken` | string | — |
| `sounds` | string | — |
| `onOpen` | function | — |
| `onClose` | function | — |

## Vue 3 Wrapper

```bash
npm install @botovis/widget
```

```vue
<script setup>
import BotovisChat from '@botovis/widget/vue';
</script>

<template>
  <BotovisChat
    endpoint="/api/botovis"
    lang="en"
    theme="dark"
    @open="onOpen"
    @close="onClose"
  />
</template>
```

### Vue Configuration

Tell Vue to recognize the custom element:

```js
// vite.config.ts
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

## CDN / Non-Laravel Usage

The widget works anywhere — it just needs a backend endpoint:

```html
<script src="https://unpkg.com/@botovis/widget/dist/botovis-widget.iife.js"></script>

<botovis-chat endpoint="https://your-api.com/botovis"></botovis-chat>
```

## i18n

Built-in translations: English (`en`) and Turkish (`tr`). The locale is set via the `lang` attribute.

The AI assistant automatically responds in the language the user writes in, regardless of the widget locale setting.

---

Next: [API Reference](api-reference.md) · Previous: [Tools](tools.md)
