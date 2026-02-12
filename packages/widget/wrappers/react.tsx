// ─────────────────────────────────────────────────
//  Botovis Widget — React Wrapper
// ─────────────────────────────────────────────────
//
//  Usage:
//    import { BotovisChat } from '@botovis/widget/react';
//    <BotovisChat endpoint="/botovis" lang="tr" />
//

import React, { useRef, useEffect } from 'react';

// Tell TypeScript about the custom element
declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace JSX {
    interface IntrinsicElements {
      'botovis-chat': React.DetailedHTMLProps<
        React.HTMLAttributes<HTMLElement> & BotovisChatProps,
        HTMLElement
      >;
    }
  }
}

export interface BotovisChatProps {
  endpoint?: string;
  lang?: 'tr' | 'en';
  theme?: 'light' | 'dark' | 'auto';
  position?: 'bottom-right' | 'bottom-left';
  title?: string;
  placeholder?: string;
  'csrf-token'?: string;
  sounds?: string;
  onOpen?: () => void;
  onClose?: () => void;
}

export function BotovisChat({
  endpoint = '/botovis',
  lang = 'tr',
  theme = 'auto',
  position = 'bottom-right',
  title,
  placeholder,
  'csrf-token': csrfToken,
  sounds,
  onOpen,
  onClose,
}: BotovisChatProps): React.ReactElement {
  const ref = useRef<HTMLElement>(null);

  useEffect(() => {
    // Ensure custom element is registered
    import('../src/index');
  }, []);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const handleOpen = () => onOpen?.();
    const handleClose = () => onClose?.();

    el.addEventListener('botovis:open', handleOpen);
    el.addEventListener('botovis:close', handleClose);

    return () => {
      el.removeEventListener('botovis:open', handleOpen);
      el.removeEventListener('botovis:close', handleClose);
    };
  }, [onOpen, onClose]);

  return React.createElement('botovis-chat', {
    ref,
    endpoint,
    lang,
    theme,
    position,
    ...(title && { title }),
    ...(placeholder && { placeholder }),
    ...(csrfToken && { 'csrf-token': csrfToken }),
    ...(sounds !== undefined && { sounds }),
  });
}

export default BotovisChat;
