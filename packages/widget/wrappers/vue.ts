// ─────────────────────────────────────────────────
//  Botovis Widget — Vue Wrapper
// ─────────────────────────────────────────────────
//
//  Usage (Vue 3):
//    import { BotovisChat } from '@botovis/widget/vue';
//    <BotovisChat endpoint="/botovis" lang="tr" />
//
//  Note: Register the custom element in your Vue app:
//    app.config.compilerOptions.isCustomElement = (tag) => tag === 'botovis-chat';
//

import { defineComponent, h, ref, onMounted, onUnmounted } from 'vue';

export const BotovisChat = defineComponent({
  name: 'BotovisChat',
  props: {
    endpoint:    { type: String, default: '/botovis' },
    lang:        { type: String, default: 'tr' },
    theme:       { type: String, default: 'auto' },
    position:    { type: String, default: 'bottom-right' },
    title:       { type: String, default: undefined },
    placeholder: { type: String, default: undefined },
    csrfToken:   { type: String, default: undefined },
    sounds:      { type: String, default: undefined },
  },
  emits: ['open', 'close'],
  setup(props, { emit }) {
    const elRef = ref<HTMLElement | null>(null);

    const onOpen = () => emit('open');
    const onClose = () => emit('close');

    onMounted(async () => {
      // Ensure custom element is registered
      await import('../src/index');

      const el = elRef.value;
      if (el) {
        el.addEventListener('botovis:open', onOpen);
        el.addEventListener('botovis:close', onClose);
      }
    });

    onUnmounted(() => {
      const el = elRef.value;
      if (el) {
        el.removeEventListener('botovis:open', onOpen);
        el.removeEventListener('botovis:close', onClose);
      }
    });

    return () =>
      h('botovis-chat', {
        ref: elRef,
        endpoint: props.endpoint,
        lang: props.lang,
        theme: props.theme,
        position: props.position,
        title: props.title,
        placeholder: props.placeholder,
        'csrf-token': props.csrfToken,
        sounds: props.sounds,
      });
  },
});

export default BotovisChat;
