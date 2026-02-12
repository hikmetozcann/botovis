// ─────────────────────────────────────────────────
//  Botovis Widget — Internationalization
// ─────────────────────────────────────────────────

export type Locale = 'tr' | 'en';

const translations: Record<Locale, Record<string, string>> = {
  tr: {
    title: 'Botovis Asistan',
    placeholder: 'Bir şey sorun...',
    send: 'Gönder',
    thinking: 'Düşünüyorum...',
    confirm: 'Onayla',
    reject: 'İptal',
    confirmQuestion: 'Bu işlemi onaylıyor musunuz?',
    actionDetected: 'Aksiyon Tespit Edildi',
    table: 'Tablo',
    operation: 'İşlem',
    data: 'Veri',
    condition: 'Koşul',
    columns: 'Sütunlar',
    confidence: 'Güven',
    noResults: 'Sonuç bulunamadı',
    resultsFound: '{count} sonuç bulundu',
    showingFirst: 'ilk {count} gösteriliyor',
    operationCancelled: 'İşlem iptal edildi',
    operationSuccess: 'İşlem başarılı',
    operationFailed: 'İşlem başarısız',
    error: 'Bir hata oluştu',
    reset: 'Sıfırla',
    resetDone: 'Konuşma sıfırlandı',
    close: 'Kapat',
    emptyState: 'Merhaba! Size nasıl yardımcı olabilirim?',
    suggestedActions: 'Önerilen işlemler',
    listAll: '{table} listele',
    addNew: 'Yeni {table} ekle',
    autoStep: 'Otomatik adım',
    connectionError: 'Bağlantı hatası, tekrar deneyin',
    sessionExpired: 'Oturum süresi dolmuş, sayfayı yenileyin',
    tooManyRequests: 'Çok fazla istek, lütfen bekleyin',
    shortcutToggle: 'Aç/kapat',
    shortcutSend: 'Gönder',
    shortcutNewline: 'Yeni satır',
    shortcutClose: 'Kapat',
    comingSoon: 'Yakında',
  },
  en: {
    title: 'Botovis Assistant',
    placeholder: 'Ask something...',
    send: 'Send',
    thinking: 'Thinking...',
    confirm: 'Confirm',
    reject: 'Cancel',
    confirmQuestion: 'Do you confirm this operation?',
    actionDetected: 'Action Detected',
    table: 'Table',
    operation: 'Operation',
    data: 'Data',
    condition: 'Condition',
    columns: 'Columns',
    confidence: 'Confidence',
    noResults: 'No results found',
    resultsFound: '{count} results found',
    showingFirst: 'showing first {count}',
    operationCancelled: 'Operation cancelled',
    operationSuccess: 'Operation successful',
    operationFailed: 'Operation failed',
    error: 'An error occurred',
    reset: 'Reset',
    resetDone: 'Conversation reset',
    close: 'Close',
    emptyState: 'Hello! How can I help you?',
    suggestedActions: 'Suggested actions',
    listAll: 'List {table}',
    addNew: 'Add new {table}',
    autoStep: 'Auto step',
    connectionError: 'Connection error, please retry',
    sessionExpired: 'Session expired, please refresh the page',
    tooManyRequests: 'Too many requests, please wait',
    shortcutToggle: 'Toggle',
    shortcutSend: 'Send',
    shortcutNewline: 'New line',
    shortcutClose: 'Close',
    comingSoon: 'Coming soon',
  },
};

export function t(key: string, locale: Locale = 'tr', params?: Record<string, string | number>): string {
  let text = translations[locale]?.[key] ?? translations.tr[key] ?? key;
  if (params) {
    for (const [k, v] of Object.entries(params)) {
      text = text.replace(`{${k}}`, String(v));
    }
  }
  return text;
}
