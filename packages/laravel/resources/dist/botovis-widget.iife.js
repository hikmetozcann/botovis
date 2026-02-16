var BotovisWidget=function(m){"use strict";class T{constructor(e,t=null){this.endpoint=e,this.csrfToken=t}async chat(e,t){return this.post("/chat",{message:e,conversation_id:t})}async confirm(e){return this.post("/confirm",{conversation_id:e})}async reject(e){return this.post("/reject",{conversation_id:e})}async reset(e){await this.post("/reset",{conversation_id:e})}async getSchema(){return this.get("/schema")}async getStatus(){return this.get("/status")}streamChat(e,t,s){const i=new AbortController;return this.performStream("/stream",{message:e,conversation_id:t},s,i.signal),i}streamConfirm(e,t){const s=new AbortController;return this.performStream("/stream-confirm",{conversation_id:e},t,s.signal),s}async performStream(e,t,s,i){var o,c,a,d,b;const n=`${this.endpoint}${e}`,r={"Content-Type":"application/json",Accept:"text/event-stream","X-Requested-With":"XMLHttpRequest"};this.csrfToken&&(r["X-CSRF-TOKEN"]=this.csrfToken);try{const h=await fetch(n,{method:"POST",headers:r,body:JSON.stringify(t),credentials:"same-origin",signal:i});if(!h.ok){const w=await h.text();(o=s.onError)==null||o.call(s,new u(h.status,w));return}const $=(c=h.body)==null?void 0:c.getReader();if(!$){(a=s.onError)==null||a.call(s,new Error("ReadableStream not supported"));return}const L=new TextDecoder;let y="";for(;;){const{done:w,value:z}=await $.read();if(w)break;y+=L.decode(z,{stream:!0});const C=this.parseSseEvents(y);y=C.remaining;for(const S of C.parsed)if(this.dispatchEvent(S,s),S.type==="done")return}}catch(h){h.name==="AbortError"?(d=s.onAbort)==null||d.call(s):(b=s.onError)==null||b.call(s,h)}}parseSseEvents(e){const t=[],s=e.split(`
`);let i="",n="",r=0;for(;r<s.length;r++){const c=s[r];if(c.startsWith("event: "))i=c.slice(7).trim();else if(c.startsWith("data: "))n=c.slice(6);else if(c===""&&i&&n){try{t.push({type:i,data:JSON.parse(n)})}catch{}i="",n=""}}const o=i||n?s.slice(r-2).join(`
`):"";return{parsed:t,remaining:o}}dispatchEvent(e,t){var s,i,n,r,o,c,a,d,b,h;switch((s=t.onEvent)==null||s.call(t,e),e.type){case"init":(i=t.onInit)==null||i.call(t,e.data.conversation_id);break;case"step":(n=t.onStep)==null||n.call(t,e.data);break;case"thinking":(r=t.onThinking)==null||r.call(t,e.data.step,e.data.thought);break;case"tool_call":(o=t.onToolCall)==null||o.call(t,e.data.step,e.data.tool,e.data.params);break;case"tool_result":(c=t.onToolResult)==null||c.call(t,e.data.step,e.data.tool,e.data.observation);break;case"confirmation":(a=t.onConfirmation)==null||a.call(t,e.data.action,e.data.params,e.data.description);break;case"message":(d=t.onMessage)==null||d.call(t,e.data.content);break;case"error":(b=t.onError)==null||b.call(t,new Error(e.data.message));break;case"done":(h=t.onDone)==null||h.call(t,e.data.steps,e.data.message);break}}async getConversations(){return this.get("/conversations")}async getConversation(e){return this.get(`/conversations/${e}`)}async createConversation(e){return this.post("/conversations",{title:e})}async deleteConversation(e){await this.delete(`/conversations/${e}`)}async updateConversationTitle(e,t){await this.patch(`/conversations/${e}/title`,{title:t})}updateCsrfToken(e){this.csrfToken=e}updateEndpoint(e){this.endpoint=e}async request(e,t){const s=`${this.endpoint}${e}`,i={"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest"};this.csrfToken&&(i["X-CSRF-TOKEN"]=this.csrfToken);let n=await fetch(s,{...t,headers:{...i,...t.headers||{}},credentials:"same-origin"});if(n.status===419){const r=this.detectCsrfFromMeta();r&&(this.csrfToken=r,i["X-CSRF-TOKEN"]=r,n=await fetch(s,{...t,headers:{...i,...t.headers||{}},credentials:"same-origin"}))}if(!n.ok)throw new u(n.status,await n.text());return n.json()}post(e,t){return this.request(e,{method:"POST",body:JSON.stringify(t)})}get(e){return this.request(e,{method:"GET"})}delete(e){return this.request(e,{method:"DELETE"})}patch(e,t){return this.request(e,{method:"PATCH",body:JSON.stringify(t)})}detectCsrfFromMeta(){const e=document.querySelector('meta[name="csrf-token"]');return(e==null?void 0:e.getAttribute("content"))??null}}class u extends Error{constructor(e,t){super(`HTTP ${e}`),this.status=e,this.body=t,this.name="BotovisApiError"}}const v='xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"',l={chat:'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="305 310 400 400" fill="currentColor"><path d="M370.222 333.496L494.113 333.514L532.839 333.488C542.836 333.485 552.876 333.275 562.82 334.377C573.176 335.469 583.29 338.209 592.78 342.495C653.775 370.352 669.939 455.653 609.757 493.867C603.058 498.121 596.696 500.698 589.306 503.385C616.408 514.451 635.709 529.259 646.401 557.593C666.176 609.995 639.736 665.752 587.271 685.037C566.915 691.835 543.411 691.01 522.212 691.009L476.143 690.995L372.122 691.017L372.174 501.479L490.519 501.459C481.278 487.815 470.739 473.725 461.066 460.282L404.004 381.191C393.494 366.612 379.594 348.255 370.222 333.496Z"/></svg>',close:`<svg ${v} width="22" height="22" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,send:`<svg ${v} width="18" height="18" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`,check:`<svg ${v} width="14" height="14" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,checkCircle:'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="9 12 12 15 16 10"/></svg>',x:`<svg ${v} width="14" height="14" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,xCircle:'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',plus:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>`,search:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>`,trash:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>`,alert:'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',command:'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>',target:'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',clock:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,arrowLeft:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>`,messageSquare:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`,sun:`<svg ${v} width="15" height="15" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`,moon:`<svg ${v} width="15" height="15" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`,chevronDown:`<svg ${v} width="14" height="14" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>`,shield:`<svg ${v} width="16" height="16" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,chevronRight:`<svg ${v} width="12" height="12" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>`,botAvatar:'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="305 310 400 400" fill="currentColor"><path d="M370.222 333.496L494.113 333.514L532.839 333.488C542.836 333.485 552.876 333.275 562.82 334.377C573.176 335.469 583.29 338.209 592.78 342.495C653.775 370.352 669.939 455.653 609.757 493.867C603.058 498.121 596.696 500.698 589.306 503.385C616.408 514.451 635.709 529.259 646.401 557.593C666.176 609.995 639.736 665.752 587.271 685.037C566.915 691.835 543.411 691.01 522.212 691.009L476.143 690.995L372.122 691.017L372.174 501.479L490.519 501.459C481.278 487.815 470.739 473.725 461.066 460.282L404.004 381.191C393.494 366.612 379.594 348.255 370.222 333.496Z"/></svg>',userIcon:'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',logo:'<svg xmlns="http://www.w3.org/2000/svg" height="20" viewBox="280 360 1260 300" fill="currentColor"><path d="M590.283 384.185C648.309 376.345 701.647 417.189 709.223 475.263C716.798 533.338 675.724 586.504 617.63 593.818C559.906 601.087 507.165 560.321 499.638 502.616C492.111 444.912 532.628 391.975 590.283 384.185Z"/><path fill="var(--bv-bg)" d="M596.949 442.607C623.201 438.418 647.919 456.188 652.317 482.412C656.715 508.637 639.148 533.502 612.966 538.111C586.486 542.772 561.282 524.957 556.833 498.434C552.385 471.911 570.398 446.844 596.949 442.607Z"/><path d="M958.601 384.474C1016.45 377.929 1068.68 419.452 1075.36 477.298C1082.04 535.144 1040.65 587.482 982.83 594.299C924.822 601.138 872.284 559.57 865.582 501.532C858.881 443.494 900.562 391.041 958.601 384.474Z"/><path fill="var(--bv-bg)" d="M961.126 442.994C987.153 437.947 1012.36 454.885 1017.54 480.893C1022.71 506.901 1005.9 532.202 979.925 537.503C953.768 542.841 928.26 525.885 923.051 499.696C917.842 473.508 934.919 448.077 961.126 442.994Z"/><path d="M316.408 389.13L385.119 389.168C395.074 389.162 413.046 388.664 422.367 389.619C427.641 390.134 432.824 391.338 437.784 393.202C466.713 404.346 482.841 443.536 461.693 468.352C454.703 476.555 448.209 479.576 438.735 483.519L439.151 483.674C452.726 488.776 464.776 497.908 470.052 511.655C482.735 544.705 468.762 572.683 437.982 586.176C423.078 591.095 392.494 589.519 375.655 589.487L318.205 589.466C318.174 553.675 317.709 516.836 318.433 481.127C339.719 480.836 362.731 480.549 383.95 481.457C378.389 472.743 368.452 459.949 362.048 451.263C346.662 430.68 331.448 409.968 316.408 389.13Z"/><path d="M1420.87 388.793C1427.53 387.814 1444.33 388.723 1451.41 389.023C1462.11 389.477 1471.45 389.43 1482.19 389.221C1481.06 404.845 1482.18 425.434 1482.2 441.778C1471.22 438.045 1418.75 423.22 1418.7 450.079C1418.68 462.443 1445.96 470.148 1457.38 474.81C1495.33 490.314 1499.9 540.212 1474.63 568.877C1460.77 584.535 1437.41 588.991 1416.21 589.428L1356.02 589.348C1356.05 571.316 1355.92 553.285 1355.62 535.255C1374.24 540.349 1393.87 544.124 1413.22 543.259C1420.85 542.918 1435.81 537.94 1433.39 528.145C1430.11 514.822 1405.26 510.953 1394.36 505.598C1384.37 500.693 1376 496.662 1369 487.442C1353.5 467.293 1356.4 431.326 1372.17 412.147C1384.67 396.937 1401.97 390.782 1420.87 388.793Z"/><path d="M1058.68 389.261C1080.51 389.476 1102.33 389.566 1124.16 389.531C1137.66 415.197 1150.9 440.998 1163.89 466.93C1175.47 441.682 1190.8 414.682 1203.34 389.348L1266.51 389.374C1257.04 411.893 1237.1 448.834 1225.55 471.874C1205.58 512.112 1185.28 552.186 1164.66 592.092C1155.19 572.321 1142.12 549.041 1131.86 529.183C1107.77 482.385 1083.37 435.744 1058.68 389.261Z"/><path d="M719.118 390.043C764.182 389.001 812.907 389.948 858.239 390C857.94 406.079 857.853 422.162 857.977 438.243L818.612 438.271C818.826 475.06 818.834 511.849 818.635 548.638L818.575 589.298L759.814 589.325C759.91 539.201 759.444 488.304 760.247 438.261L719.434 438.013C718.531 424.118 719.11 404.374 719.118 390.043Z"/><path d="M1275.16 389.616C1293.9 389.674 1314.53 390.134 1333.12 389.274C1335.23 453.217 1333.66 524.876 1333.79 589.338L1275.18 589.28C1273.91 523.923 1274.77 455.093 1275.16 389.616Z"/></svg>'},k={tr:{title:"Botovis Asistan",placeholder:"Bir şey sorun...",send:"Gönder",thinking:"Düşünüyorum...",confirm:"Onayla",reject:"İptal",confirmQuestion:"Bu işlemi onaylıyor musunuz?",actionDetected:"Aksiyon Tespit Edildi",table:"Tablo",operation:"İşlem",data:"Veri",condition:"Koşul",columns:"Sütunlar",confidence:"Güven",noResults:"Sonuç bulunamadı",resultsFound:"{count} sonuç bulundu",showingFirst:"ilk {count} gösteriliyor",operationCancelled:"İşlem iptal edildi",operationSuccess:"İşlem başarılı",operationFailed:"İşlem başarısız",error:"Bir hata oluştu",reset:"Sıfırla",resetDone:"Konuşma sıfırlandı",close:"Kapat",emptyState:"Merhaba! Size nasıl yardımcı olabilirim?",suggestedActions:"Önerilen işlemler",listAll:"{table} listele",addNew:"Yeni {table} ekle",autoStep:"Otomatik adım",connectionError:"Bağlantı hatası, tekrar deneyin",sessionExpired:"Oturum süresi dolmuş, sayfayı yenileyin",tooManyRequests:"Çok fazla istek, lütfen bekleyin",shortcutToggle:"Aç/kapat",shortcutSend:"Gönder",shortcutNewline:"Yeni satır",shortcutClose:"Kapat",comingSoon:"Yakında",conversations:"Sohbetler",newConversation:"Yeni Sohbet",backToChat:"Sohbete Dön",noConversations:"Henüz sohbet yok",loadingConversations:"Yükleniyor...",deleteConversation:"Sohbeti sil",conversationDeleted:"Sohbet silindi",you:"Sen",assistant:"Botovis",themeLight:"Açık Tema",themeDark:"Koyu Tema",toolsUsed:"{count} araç",steps:"{count} adım",running:"Çalıştırılıyor",details:"Detaylar",hideDetails:"Gizle",confirmed:"Onaylandı",rejected:"Reddedildi",processing:"İşleniyor...",confirmAction_create:"Yeni kayıt ekleme",confirmAction_update:"Kayıt güncelleme",confirmAction_delete:"Kayıt silme",confirmAction_default:"Veritabanı işlemi",fieldName:"Alan",fieldValue:"Değer",messageCount:"{count} mesaj",yesterday:"Dün"},en:{title:"Botovis Assistant",placeholder:"Ask something...",send:"Send",thinking:"Thinking...",confirm:"Confirm",reject:"Cancel",confirmQuestion:"Do you confirm this operation?",actionDetected:"Action Detected",table:"Table",operation:"Operation",data:"Data",condition:"Condition",columns:"Columns",confidence:"Confidence",noResults:"No results found",resultsFound:"{count} results found",showingFirst:"showing first {count}",operationCancelled:"Operation cancelled",operationSuccess:"Operation successful",operationFailed:"Operation failed",error:"An error occurred",reset:"Reset",resetDone:"Conversation reset",close:"Close",emptyState:"Hello! How can I help you?",suggestedActions:"Suggested actions",listAll:"List {table}",addNew:"Add new {table}",autoStep:"Auto step",connectionError:"Connection error, please retry",sessionExpired:"Session expired, please refresh the page",tooManyRequests:"Too many requests, please wait",shortcutToggle:"Toggle",shortcutSend:"Send",shortcutNewline:"New line",shortcutClose:"Close",comingSoon:"Coming soon",conversations:"Conversations",newConversation:"New Chat",backToChat:"Back to Chat",noConversations:"No conversations yet",loadingConversations:"Loading...",deleteConversation:"Delete conversation",conversationDeleted:"Conversation deleted",you:"You",assistant:"Botovis",themeLight:"Light Theme",themeDark:"Dark Theme",toolsUsed:"{count} tools",steps:"{count} steps",running:"Running",details:"Details",hideDetails:"Hide",confirmed:"Confirmed",rejected:"Rejected",processing:"Processing...",confirmAction_create:"Create new record",confirmAction_update:"Update record",confirmAction_delete:"Delete record",confirmAction_default:"Database operation",fieldName:"Field",fieldValue:"Value",messageCount:"{count} messages",yesterday:"Yesterday"}};function f(p,e="en",t){var i;let s=((i=k[e])==null?void 0:i[p])??k.en[p]??p;if(t)for(const[n,r]of Object.entries(t))s=s.replace(`{${n}}`,String(r));return s}const M=`

/* ── Reset & Host ─────────────────────────── */

:host {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
               'Helvetica Neue', Arial, sans-serif;
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

/* ── Theme Variables ──────────────────────── */

.bv-root {
  --bv-primary: #6366f1;
  --bv-primary-hover: #4f46e5;
  --bv-primary-light: #eef2ff;
  --bv-primary-text: #ffffff;

  --bv-accent: #0ea5e9;
  --bv-accent-light: #e0f2fe;

  --bv-bg: #ffffff;
  --bv-bg-elevated: #ffffff;
  --bv-surface: #f8fafc;
  --bv-surface-hover: #f1f5f9;
  --bv-text: #0f172a;
  --bv-text-secondary: #475569;
  --bv-text-muted: #94a3b8;
  --bv-border: #e2e8f0;
  --bv-border-light: #f1f5f9;

  --bv-success: #059669;
  --bv-success-bg: #ecfdf5;
  --bv-success-text: #065f46;
  --bv-error: #dc2626;
  --bv-error-bg: #fef2f2;
  --bv-error-text: #991b1b;
  --bv-warning: #d97706;
  --bv-warning-bg: #fffbeb;
  --bv-warning-text: #92400e;
  --bv-info: #0284c7;
  --bv-info-bg: #f0f9ff;

  --bv-drawer-width: 420px;
  --bv-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 20px 40px -8px rgba(15,23,42,.12);
  --bv-shadow-sm: 0 1px 3px rgba(15,23,42,.08);
  --bv-radius: 12px;
  --bv-radius-sm: 8px;
  --bv-radius-xs: 4px;
}

.bv-root.bv-dark {
  --bv-primary: #818cf8;
  --bv-primary-hover: #a5b4fc;
  --bv-primary-light: #1e1b4b;

  --bv-accent: #38bdf8;
  --bv-accent-light: #0c4a6e;

  --bv-bg: #0f172a;
  --bv-bg-elevated: #1e293b;
  --bv-surface: #1e293b;
  --bv-surface-hover: #334155;
  --bv-text: #f1f5f9;
  --bv-text-secondary: #94a3b8;
  --bv-text-muted: #64748b;
  --bv-border: #334155;
  --bv-border-light: #1e293b;

  --bv-success-bg: #064e3b;
  --bv-success-text: #6ee7b7;
  --bv-error-bg: #450a0a;
  --bv-error-text: #fca5a5;
  --bv-warning-bg: #451a03;
  --bv-warning-text: #fcd34d;
  --bv-info-bg: #0c4a6e;

  --bv-shadow: 0 0 0 1px rgba(255,255,255,.05), 0 20px 40px -8px rgba(0,0,0,.4);
  --bv-shadow-sm: 0 1px 3px rgba(0,0,0,.3);
}

/* ── Layout Root ──────────────────────────── */

.bv-root {
  position: fixed;
  inset: 0;
  z-index: 99999;
  pointer-events: none;
  color: var(--bv-text);
}

/* ── FAB Button ───────────────────────────── */

.bv-fab {
  position: absolute;
  bottom: 24px;
  right: 24px;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
  box-shadow: var(--bv-shadow-sm);
  transition: all 0.15s ease;
  outline: none;
}

.bv-fab:hover {
  transform: translateY(-1px);
  background: var(--bv-primary-hover);
  box-shadow: var(--bv-shadow);
}

.bv-fab:active { transform: translateY(0) scale(0.97); }

.bv-fab .bv-fab-icon {
  transition: transform 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bv-fab.bv-hidden { display: none; }

.bv-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  background: var(--bv-error);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  opacity: 0;
  transform: scale(0);
  transition: all 0.15s ease;
}

.bv-badge.bv-visible {
  opacity: 1;
  transform: scale(1);
}

/* ── Left positioning ── */
.bv-root.bv-left .bv-fab { right: auto; left: 24px; }
.bv-root.bv-left .bv-drawer { right: auto; left: 0; border-right: 1px solid var(--bv-border); border-left: none; }
.bv-root.bv-left .bv-resize-handle { right: -3px; left: auto; cursor: ew-resize; }

/* ── Drawer ───────────────────────────────── */

.bv-drawer {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: var(--bv-drawer-width);
  max-width: 90vw;
  min-width: 320px;
  background: var(--bv-bg);
  border-left: 1px solid var(--bv-border);
  display: flex;
  flex-direction: column;
  pointer-events: auto;
  transform: translateX(100%);
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.bv-root.bv-left .bv-drawer {
  transform: translateX(-100%);
}

.bv-drawer.bv-open {
  transform: translateX(0);
}

/* Resize Handle */
.bv-resize-handle {
  position: absolute;
  top: 0;
  left: -3px;
  bottom: 0;
  width: 6px;
  cursor: ew-resize;
  z-index: 10;
  transition: background 0.1s;
}

.bv-resize-handle:hover,
.bv-resize-handle.bv-active {
  background: var(--bv-primary);
  opacity: 0.3;
}

/* ── Header ───────────────────────────────── */

.bv-header {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--bv-border);
  flex-shrink: 0;
  gap: 2px;
  background: var(--bv-bg);
}

.bv-header-logo {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.bv-header-logo svg {
  height: 18px;
  width: auto;
  flex-shrink: 0;
}

.bv-header-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--bv-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-hbtn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: var(--bv-text-muted);
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.1s ease;
  outline: none;
  flex-shrink: 0;
}

.bv-hbtn:hover {
  background: var(--bv-surface);
  color: var(--bv-text);
}

.bv-hbtn.bv-active {
  background: var(--bv-primary-light);
  color: var(--bv-primary);
}

.bv-hbtn svg { width: 15px; height: 15px; }

.bv-hsep {
  width: 1px;
  height: 16px;
  background: var(--bv-border);
  margin: 0 4px;
}

/* ── Content Area ─────────────────────────── */

.bv-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  position: relative;
}

/* ── History Panel (overlay) ──────────────── */

.bv-history-panel {
  display: none;
  position: absolute;
  inset: 0;
  background: var(--bv-bg);
  z-index: 5;
  flex-direction: column;
  animation: bvSlideDown 0.15s ease;
}

.bv-history-panel.bv-visible { display: flex; }

.bv-history-header {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid var(--bv-border);
  gap: 8px;
}

.bv-history-title {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
}

.bv-history-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.bv-history-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: var(--bv-radius-sm);
  cursor: pointer;
  transition: background 0.1s;
}

.bv-history-item:hover { background: var(--bv-surface-hover); }

.bv-history-item-content { flex: 1; min-width: 0; }

.bv-history-item-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--bv-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bv-history-item-meta {
  font-size: 11px;
  color: var(--bv-text-muted);
  margin-top: 2px;
}

.bv-history-item-delete {
  opacity: 0;
  padding: 4px;
  background: none;
  border: none;
  color: var(--bv-text-muted);
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.1s;
  display: flex;
}

.bv-history-item:hover .bv-history-item-delete { opacity: 1; }
.bv-history-item-delete:hover { background: var(--bv-error-bg); color: var(--bv-error); }

.bv-history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  color: var(--bv-text-muted);
  font-size: 13px;
  gap: 8px;
}

/* ── Messages Area ────────────────────────── */

.bv-messages {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  scroll-behavior: smooth;
  overscroll-behavior: contain;
}

.bv-messages::-webkit-scrollbar { width: 5px; }
.bv-messages::-webkit-scrollbar-track { background: transparent; }
.bv-messages::-webkit-scrollbar-thumb { background: var(--bv-border); border-radius: 3px; }
.bv-messages::-webkit-scrollbar-thumb:hover { background: var(--bv-text-muted); }

/* ── Empty State ──────────────────────────── */

.bv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 16px;
  padding: 48px 32px;
  flex: 1;
}

.bv-empty-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bv-primary-light);
  border-radius: 10px;
  color: var(--bv-primary);
}

.bv-empty-text {
  color: var(--bv-text-secondary);
  font-size: 14px;
  max-width: 280px;
  line-height: 1.5;
}

/* Suggestion Chips */
.bv-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  max-width: 340px;
  margin-top: 4px;
}

.bv-suggestion {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: var(--bv-bg);
  border: 1px solid var(--bv-border);
  border-radius: 20px;
  cursor: pointer;
  font-size: 12px;
  color: var(--bv-text-secondary);
  transition: all 0.1s ease;
  font-family: inherit;
}

.bv-suggestion:hover {
  border-color: var(--bv-primary);
  color: var(--bv-primary);
}

.bv-suggestion-icon {
  display: flex;
  color: var(--bv-text-muted);
}

.bv-suggestion:hover .bv-suggestion-icon { color: var(--bv-primary); }

/* ── Flat Message Style ───────────────────── */

.bv-msg {
  padding: 14px 20px;
  animation: bvFadeIn 0.15s ease;
}

.bv-msg + .bv-msg {
  border-top: 1px solid var(--bv-border-light);
}

.bv-msg-user { background: var(--bv-surface); }
.bv-msg-assistant { background: var(--bv-bg); }

.bv-msg-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}

.bv-msg-avatar {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.bv-msg-avatar svg {
  width: 14px;
  height: 14px;
}

.bv-msg-user .bv-msg-avatar {
  border-radius: 50%;
  background: var(--bv-text);
  color: var(--bv-bg);
}

.bv-msg-assistant .bv-msg-avatar {
  border-radius: 6px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
}

.bv-msg-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--bv-text);
}

.bv-msg-time {
  font-size: 11px;
  color: var(--bv-text-muted);
  margin-left: auto;
  opacity: 0;
  transition: opacity 0.1s;
}

.bv-msg:hover .bv-msg-time { opacity: 1; }

/* ── Follow-up Messages (no header, connected) ── */

.bv-msg-followup {
  border-top: none !important;
  padding-top: 0;
}

.bv-msg-followup .bv-msg-body {
  padding-left: 30px;
}

/* ── Loading Indicator ────────────────────── */

.bv-loading-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 0;
}

.bv-loading-pulse {
  display: flex;
  gap: 4px;
}

.bv-loading-dot {
  width: 6px;
  height: 6px;
  background: var(--bv-primary);
  border-radius: 50%;
  animation: bvLoadingPulse 1.4s infinite ease-in-out both;
}

.bv-loading-dot:nth-child(1) { animation-delay: -0.32s; }
.bv-loading-dot:nth-child(2) { animation-delay: -0.16s; }
.bv-loading-dot:nth-child(3) { animation-delay: 0s; }

@keyframes bvLoadingPulse {
  0%, 80%, 100% { transform: scale(0.4); opacity: 0.4; }
  40% { transform: scale(1); opacity: 1; }
}

.bv-loading-text {
  font-size: 13px;
  color: var(--bv-text-muted);
  animation: bvFadeInOut 2s infinite ease-in-out;
}

@keyframes bvFadeInOut {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.bv-msg-body {
  padding-left: 30px;
  font-size: 14px;
  color: var(--bv-text);
  line-height: 1.6;
}

/* ── Agent Timeline Steps ─────────────────── */

.bv-timeline-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  margin-bottom: 8px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  color: var(--bv-text-secondary);
  font-family: inherit;
  transition: all 0.1s;
}

.bv-timeline-toggle:hover {
  background: var(--bv-surface-hover);
  border-color: var(--bv-text-muted);
}

.bv-timeline-toggle svg {
  width: 12px;
  height: 12px;
  color: var(--bv-text-muted);
  transition: transform 0.15s;
}

.bv-timeline-toggle.bv-expanded svg {
  transform: rotate(90deg);
}

.bv-tl-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 16px;
  height: 16px;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border-radius: 8px;
  font-size: 10px;
  font-weight: 600;
  padding: 0 4px;
}

.bv-timeline {
  display: none;
  position: relative;
  padding-left: 16px;
  margin: 4px 0 10px 0;
}

.bv-timeline.bv-visible {
  display: block;
  animation: bvSlideDown 0.15s ease;
}

.bv-timeline::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 4px;
  bottom: 4px;
  width: 2px;
  background: var(--bv-border);
  border-radius: 1px;
}

.bv-tl-step {
  position: relative;
  padding: 6px 0;
  font-size: 12px;
  line-height: 1.5;
}

.bv-tl-step::before {
  content: '';
  position: absolute;
  left: -14px;
  top: 12px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--bv-text-muted);
  border: 2px solid var(--bv-bg);
}

.bv-tl-step.bv-done::before { background: var(--bv-success); }
.bv-tl-step.bv-running::before { background: var(--bv-primary); animation: bvPulse 1s infinite; }

.bv-tl-thought {
  color: var(--bv-text-secondary);
  line-height: 1.5;
  word-break: break-word;
}

.bv-tl-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  padding: 2px 8px;
  background: var(--bv-info-bg);
  border-radius: 4px;
  font-size: 11px;
  color: var(--bv-info);
  font-family: ui-monospace, monospace;
}

/* ── Streaming Thinking ───────────────────── */

.bv-thinking-line {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  margin-bottom: 4px;
}

.bv-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid var(--bv-border);
  border-top-color: var(--bv-primary);
  border-radius: 50%;
  animation: bvSpin 0.7s linear infinite;
  flex-shrink: 0;
}

.bv-thinking-label {
  font-size: 13px;
  color: var(--bv-text-muted);
}

.bv-thinking-count {
  margin-left: auto;
  font-size: 11px;
  color: var(--bv-text-muted);
  background: var(--bv-surface);
  padding: 1px 6px;
  border-radius: 8px;
}

/* ── Intent Card ──────────────────────────── */

.bv-intent-card {
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: var(--bv-radius-sm);
  overflow: hidden;
  font-size: 13px;
  margin: 8px 0;
}

.bv-intent-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  color: var(--bv-text-secondary);
  font-weight: 500;
  font-size: 12px;
  border-bottom: 1px solid var(--bv-border);
}

.bv-intent-header svg { color: var(--bv-text-muted); }
.bv-intent-body { padding: 8px 12px; }

.bv-intent-row { display: flex; padding: 3px 0; }

.bv-intent-label {
  width: 64px;
  flex-shrink: 0;
  color: var(--bv-text-muted);
  font-size: 11px;
}

.bv-intent-value {
  color: var(--bv-text);
  font-size: 12px;
  font-family: ui-monospace, monospace;
}

.bv-intent-value.bv-action-create { color: var(--bv-success); }
.bv-intent-value.bv-action-read   { color: var(--bv-info); }
.bv-intent-value.bv-action-update { color: var(--bv-warning); }
.bv-intent-value.bv-action-delete { color: var(--bv-error); }

/* ── Data Table ───────────────────────────── */

.bv-table-wrapper {
  max-width: 100%;
  overflow-x: auto;
  margin: 8px 0;
  border-radius: var(--bv-radius-sm);
  border: 1px solid var(--bv-border);
}

.bv-table { width: 100%; border-collapse: collapse; font-size: 12px; }

.bv-table th {
  background: var(--bv-surface);
  font-weight: 500;
  text-align: left;
  padding: 6px 10px;
  border-bottom: 1px solid var(--bv-border);
  white-space: nowrap;
  color: var(--bv-text-secondary);
  font-size: 11px;
}

.bv-table td {
  padding: 6px 10px;
  border-bottom: 1px solid var(--bv-border-light);
  white-space: nowrap;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
}

.bv-table tr:last-child td { border-bottom: none; }
.bv-table tr:hover td { background: var(--bv-surface); }

.bv-table-footer { font-size: 11px; color: var(--bv-text-muted); padding: 6px 0; }

/* ── Confirmation ─────────────────────────── */

.bv-confirm-card {
  background: var(--bv-bg-elevated);
  border: 1px solid var(--bv-primary);
  border-left: 3px solid var(--bv-primary);
  border-radius: var(--bv-radius-sm);
  padding: 14px 16px;
  margin: 8px 0;
  transition: all 0.2s ease;
}

.bv-confirm-card.bv-confirm-confirmed {
  border-color: var(--bv-success);
  border-left-color: var(--bv-success);
  background: var(--bv-success-bg);
}

.bv-confirm-card.bv-confirm-rejected {
  border-color: var(--bv-border);
  border-left-color: var(--bv-text-muted);
  background: var(--bv-surface);
  opacity: 0.75;
}

.bv-confirm-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
}

.bv-confirm-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bv-primary-light);
  color: var(--bv-primary);
  border-radius: 8px;
}

.bv-confirm-confirmed .bv-confirm-icon {
  background: var(--bv-success-bg);
  color: var(--bv-success);
}

.bv-confirm-rejected .bv-confirm-icon {
  background: var(--bv-surface);
  color: var(--bv-text-muted);
}

.bv-confirm-header-text {
  flex: 1;
  min-width: 0;
}

.bv-confirm-title {
  font-weight: 600;
  font-size: 13px;
  color: var(--bv-text);
  line-height: 1.3;
}

.bv-confirm-action-label {
  font-size: 12px;
  color: var(--bv-text-secondary);
  margin-top: 2px;
}

.bv-confirm-desc {
  font-size: 13px;
  color: var(--bv-text);
  margin-bottom: 10px;
  line-height: 1.5;
}

/* Details toggle */
.bv-detail-toggle {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: var(--bv-text-secondary);
  font-size: 12px;
  cursor: pointer;
  padding: 4px 0;
  margin-bottom: 4px;
  font-family: inherit;
  transition: color 0.15s;
}

.bv-detail-toggle:hover { color: var(--bv-primary); }

.bv-detail-toggle svg {
  transition: transform 0.2s ease;
}

.bv-detail-toggle.bv-open svg {
  transform: rotate(180deg);
}

.bv-confirm-details {
  display: none;
  margin-bottom: 10px;
}

.bv-confirm-details.bv-open {
  display: block;
}

.bv-detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.bv-detail-table th {
  text-align: left;
  padding: 5px 8px;
  font-weight: 500;
  color: var(--bv-text-secondary);
  background: var(--bv-surface);
  border-bottom: 1px solid var(--bv-border);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.bv-detail-table td {
  padding: 5px 8px;
  border-bottom: 1px solid var(--bv-border-light);
  color: var(--bv-text);
}

.bv-detail-field {
  font-weight: 500;
  color: var(--bv-text-secondary);
  white-space: nowrap;
  width: 1%;
}

.bv-detail-value {
  word-break: break-word;
}

.bv-confirm-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

/* Status indicator (replaces buttons after confirm/reject) */
.bv-confirm-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.bv-confirm-status-ok {
  color: var(--bv-success-text);
  background: var(--bv-success-bg);
}

.bv-confirm-status-rejected {
  color: var(--bv-text-muted);
  background: var(--bv-surface);
}

.bv-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 16px;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  outline: none;
  font-family: inherit;
}

.bv-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.bv-btn-confirm {
  background: var(--bv-primary);
  color: var(--bv-primary-text);
}
.bv-btn-confirm:hover:not(:disabled) {
  background: var(--bv-primary-hover);
}

.bv-btn-reject {
  background: var(--bv-bg);
  color: var(--bv-text);
  border: 1px solid var(--bv-border);
}
.bv-btn-reject:hover:not(:disabled) {
  background: var(--bv-error-bg);
  color: var(--bv-error-text);
  border-color: var(--bv-error);
}

/* ── Results ──────────────────────────────── */

.bv-result-success {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: var(--bv-success-bg);
  color: var(--bv-success-text);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  margin: 6px 0;
}

.bv-result-error {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: var(--bv-error-bg);
  color: var(--bv-error-text);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  margin: 6px 0;
}

.bv-rejected-msg {
  padding: 8px 10px;
  background: var(--bv-surface);
  color: var(--bv-text-muted);
  border-radius: 6px;
  font-size: 12px;
}

.bv-step-indicator {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--bv-text-muted);
  padding: 2px 0;
}

.bv-step-dot {
  width: 4px;
  height: 4px;
  background: var(--bv-primary);
  border-radius: 50%;
}

/* ── Input Area ───────────────────────────── */

.bv-input-area {
  padding: 12px 16px;
  border-top: 1px solid var(--bv-border);
  background: var(--bv-bg);
  flex-shrink: 0;
}

.bv-input-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 10px;
  padding: 8px 12px;
  transition: border-color 0.15s;
}

.bv-input-row:focus-within { border-color: var(--bv-primary); }

.bv-textarea {
  flex: 1;
  border: none;
  background: transparent;
  color: var(--bv-text);
  font-size: 13px;
  font-family: inherit;
  line-height: 1.5;
  resize: none;
  outline: none;
  min-height: 20px;
  max-height: 120px;
}

.bv-textarea::placeholder { color: var(--bv-text-muted); }

.bv-send-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: var(--bv-primary);
  color: var(--bv-primary-text);
  border-radius: 7px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.1s;
  outline: none;
  flex-shrink: 0;
}

.bv-send-btn:hover { background: var(--bv-primary-hover); }
.bv-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.bv-send-btn svg { width: 14px; height: 14px; }

/* Input suggestions */
.bv-input-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  padding: 8px 0 0 0;
}

.bv-input-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: transparent;
  border: 1px solid var(--bv-border);
  border-radius: 14px;
  cursor: pointer;
  font-size: 11px;
  color: var(--bv-text-muted);
  transition: all 0.1s;
  font-family: inherit;
}

.bv-input-chip:hover {
  border-color: var(--bv-primary);
  color: var(--bv-primary);
  background: var(--bv-primary-light);
}

.bv-input-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px 8px;
  font-size: 10px;
  color: var(--bv-text-muted);
  flex-shrink: 0;
}

.bv-kbd {
  display: inline-flex;
  padding: 1px 4px;
  background: var(--bv-surface);
  border: 1px solid var(--bv-border);
  border-radius: 3px;
  font-size: 10px;
  font-family: ui-monospace, monospace;
  line-height: 1.4;
}

/* ── Toasts ───────────────────────────────── */

.bv-toasts {
  position: absolute;
  top: 60px;
  left: 16px;
  right: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  pointer-events: auto;
  z-index: 20;
}

.bv-toast {
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  box-shadow: var(--bv-shadow-sm);
  animation: bvSlideDown 0.15s ease;
}

.bv-toast-success { background: var(--bv-success); color: #fff; }
.bv-toast-error   { background: var(--bv-error);   color: #fff; }
.bv-toast-info    { background: var(--bv-text); color: var(--bv-bg); }
.bv-toast-exit    { animation: bvFadeOut 0.2s ease forwards; }

/* ── Markdown ─────────────────────────────── */

.bv-md p { margin: 0 0 0.5em 0; }
.bv-md p:last-child { margin-bottom: 0; }
.bv-md strong { font-weight: 600; }
.bv-md em { font-style: italic; }
.bv-md a { color: var(--bv-primary); text-decoration: none; }
.bv-md a:hover { text-decoration: underline; }

.bv-md-code {
  font-family: ui-monospace, monospace;
  font-size: 0.85em;
  background: var(--bv-surface);
  padding: 0.15em 0.4em;
  border-radius: 4px;
  color: var(--bv-primary);
}

.bv-md-pre {
  font-family: ui-monospace, monospace;
  font-size: 0.85em;
  background: var(--bv-surface);
  padding: 12px 14px;
  border-radius: var(--bv-radius-sm);
  overflow-x: auto;
  margin: 8px 0;
  white-space: pre-wrap;
  word-wrap: break-word;
  border: 1px solid var(--bv-border);
}

.bv-md-h2, .bv-md-h3, .bv-md-h4 { font-weight: 600; margin: 0.75em 0 0.25em 0; line-height: 1.3; }
.bv-md-h2 { font-size: 1.2em; }
.bv-md-h3 { font-size: 1.05em; }
.bv-md-h4 { font-size: 1em; }

.bv-md-ul, .bv-md-ol { margin: 0.5em 0; padding-left: 1.5em; }
.bv-md-li, .bv-md-oli { margin: 0.25em 0; }

.bv-md-hr {
  border: none;
  border-top: 1px solid var(--bv-border);
  margin: 12px 0;
}

/* ── Animations ───────────────────────────── */

@keyframes bvFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

@keyframes bvSlideDown {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes bvFadeOut { to { opacity: 0; } }

@keyframes bvSpin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

@keyframes bvPulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

@keyframes bvTypingDot {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
  30%           { transform: translateY(-3px); opacity: 1; }
}

/* Loading dots */
.bv-typing-dots {
  display: flex;
  gap: 4px;
  padding: 4px 0;
}

.bv-typing-dot {
  width: 5px;
  height: 5px;
  background: var(--bv-text-muted);
  border-radius: 50%;
  animation: bvTypingDot 1.2s infinite ease-in-out;
}

.bv-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.bv-typing-dot:nth-child(3) { animation-delay: 0.3s; }

/* ── Responsive ───────────────────────────── */

@media (max-width: 640px) {
  .bv-drawer { width: 100% !important; max-width: 100vw; }
  .bv-resize-handle { display: none; }
  .bv-fab { bottom: 20px; right: 20px; }
  .bv-root.bv-left .bv-fab { right: auto; left: 20px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
`,x=class x extends HTMLElement{constructor(){super(),this.messages=[],this.conversationId=null,this.isOpen=!1,this.isLoading=!1,this.hasPending=!1,this.unreadCount=0,this.schemaTables=[],this.darkMediaQuery=null,this.boundKeyHandler=null,this.isHistoryOpen=!1,this.conversations=[],this.isLoadingHistory=!1,this.streamController=null,this.currentStreamingMessageId=null,this.streamingSteps=[],this.drawerWidth=420,this.expandedTimelines=new Set,this.isDarkTheme=!1,this.handleMediaChange=()=>{this.applyTheme()},this.shadow=this.attachShadow({mode:"open"})}connectedCallback(){this.api=new T(this.cfg.endpoint,this.cfg.csrfToken),this.render(),this.bindEvents(),this.setupKeyboard(),this.setupTheme(),this.setupResize(),this.fetchSchema()}disconnectedCallback(){var e;this.boundKeyHandler&&document.removeEventListener("keydown",this.boundKeyHandler),(e=this.darkMediaQuery)==null||e.removeEventListener("change",this.handleMediaChange)}attributeChangedCallback(e,t,s){var i,n;e==="endpoint"&&s&&((i=this.api)==null||i.updateEndpoint(s)),e==="csrf-token"&&s&&((n=this.api)==null||n.updateCsrfToken(s)),e==="theme"&&this.applyTheme()}get cfg(){return{endpoint:this.getAttribute("endpoint")||"/botovis",lang:this.getAttribute("lang")||"en",theme:this.getAttribute("theme")||"auto",position:this.getAttribute("position")||"bottom-right",title:this.getAttribute("title")||f("title",this.locale),placeholder:this.getAttribute("placeholder")||f("placeholder",this.locale),csrfToken:this.getAttribute("csrf-token")||this.detectCsrf(),sounds:this.getAttribute("sounds")!=="false",streaming:this.getAttribute("streaming")!=="false"}}get locale(){return this.getAttribute("lang")||"en"}i(e,t){return f(e,this.locale,t)}open(){this.isOpen||this.toggle()}close(){this.isOpen&&this.toggle()}toggle(){var e,t;this.isOpen=!this.isOpen,(e=this.$("drawer"))==null||e.classList.toggle("bv-open",this.isOpen),(t=this.$("fab"))==null||t.classList.toggle("bv-hidden",this.isOpen),this.isOpen&&(this.unreadCount=0,this.updateBadge(),setTimeout(()=>{var s;return(s=this.$("input"))==null?void 0:s.focus()},100)),this.dispatchEvent(new CustomEvent(this.isOpen?"botovis:open":"botovis:close"))}async send(e){if(!(!e.trim()||this.isLoading)){if(this.addUserMessage(e),this.cfg.streaming){this.isLoading=!0;const t=this.$("btn-send");t&&(t.disabled=!0),this.sendWithStreaming(e);return}this.setLoading(!0);try{const t=await this.api.chat(e,this.conversationId??void 0);this.processResponse(t)}catch(t){this.handleError(t)}finally{this.setLoading(!1)}}}sendWithStreaming(e){this.streamingSteps=[],this.currentStreamingMessageId=this.uid(),this.addMessage({id:this.currentStreamingMessageId,role:"assistant",type:"loading",content:"",timestamp:new Date}),this.streamController=this.api.streamChat(e,this.conversationId??void 0,{onInit:t=>{this.conversationId=t},onStep:t=>{this.dispatchEvent(new CustomEvent("botovis:step",{detail:t})),this.streamingSteps.push({step:t.step,thought:t.thought,action:t.action??void 0,observation:t.observation??void 0}),this.updateStreamingStepsUI()},onMessage:t=>{this.finalizeStreamingMessage(t,"text")},onConfirmation:(t,s,i)=>{this.hasPending=!0,this.finalizeStreamingMessage(i,"confirmation",{type:"operation",action:t,table:s.table||null,data:s,where:{},select:[],message:i,confidence:1,auto_continue:!1})},onError:t=>{this.finalizeStreamingMessage(t.message,"error")},onDone:()=>{this.setLoading(!1),this.streamController=null,!this.isOpen&&this.cfg.sounds&&this.playSound()},onAbort:()=>{this.setLoading(!1),this.streamController=null}})}updateStreamingStepsUI(){if(!this.currentStreamingMessageId)return;const e=this.messages.findIndex(a=>a.id===this.currentStreamingMessageId);if(e===-1)return;const t=this.streamingSteps,s=t[t.length-1];if(!s)return;const i=t.filter(a=>a.observation).length,n=s.action&&!s.observation;let r="";for(const a of t){const d=!!a.observation;r+=`<div class="bv-tl-step ${d?"bv-done":a===s&&!d?"bv-running":""}">`,r+=`<div class="bv-tl-thought">${this.escapeHtml(a.thought)}</div>`,a.action&&(r+=`<div class="bv-tl-action">${this.escapeHtml(this.formatToolName(a.action))}</div>`),r+="</div>"}const o=n?`${this.i("running")}: ${this.formatToolName(s.action)}`:s.thought,c=`
      <div class="bv-thinking-line">
        <div class="bv-spinner"></div>
        <span class="bv-thinking-label">${this.escapeHtml(o)}</span>
        ${i>0?`<span class="bv-thinking-count">${this.i("toolsUsed",{count:i})}</span>`:""}
      </div>
      <div class="bv-timeline bv-visible">${r}</div>
    `;this.messages[e].type="streaming",this.messages[e].content=c,this.renderMessages()}formatToolName(e){const t=e.split(", ").filter(Boolean);if(t.length>1){const s=t.map(n=>this.formatSingleToolName(n)),i=[...new Set(s)];return i.length===1?`${i[0]} ×${t.length}`:i.join(", ")}return this.formatSingleToolName(e)}formatSingleToolName(e){const t=e.match(/^(\w+)/);if(t){const s=t[1];return{search_records:"search",count_records:"count",get_sample_data:"sample data",get_column_stats:"statistics",aggregate:"aggregate",create_record:"create",update_record:"update",delete_record:"delete"}[s]||s.replace(/_/g," ")}return e.substring(0,15)}escapeHtml(e){const t=document.createElement("div");return t.textContent=e,t.innerHTML}finalizeStreamingMessage(e,t,s){if(!this.currentStreamingMessageId)return;const i=this.messages.findIndex(n=>n.id===this.currentStreamingMessageId);if(i!==-1){const n=[...this.streamingSteps];this.messages[i]={...this.messages[i],type:t,content:e,intent:s||null,_steps:n},this.renderMessages()}this.currentStreamingMessageId=null}cancelStream(){this.streamController&&(this.streamController.abort(),this.streamController=null,this.setLoading(!1))}render(){const e=new CSSStyleSheet;e.replaceSync(M),this.shadow.adoptedStyleSheets=[e];const t=this.cfg.position,s=document.createElement("div");s.className=`bv-root${t==="bottom-left"?" bv-left":""}`,s.id="root",s.innerHTML=`
      ${this.renderDrawer()}
      <button class="bv-fab" id="fab" aria-label="${this.i("shortcutToggle")}">
        <span class="bv-fab-icon" id="fab-icon">${l.chat}</span>
        <span class="bv-badge" id="badge"></span>
      </button>
      <div class="bv-toasts" id="toasts"></div>
    `,this.shadow.appendChild(s),this.applyTheme()}renderDrawer(){return`
      <div class="bv-drawer" id="drawer" style="width:${this.drawerWidth}px">
        <div class="bv-resize-handle" id="resize-handle"></div>

        <div class="bv-header">
          <div class="bv-header-logo">
            ${l.logo}
          </div>
          <button class="bv-hbtn" id="btn-new" title="${this.i("newConversation")}">${l.plus}</button>
          <button class="bv-hbtn" id="btn-history" title="${this.i("conversations")}">${l.clock}</button>
          <span class="bv-hsep"></span>
          <button class="bv-hbtn" id="btn-theme" title="${this.i("themeLight")}">${l.sun}</button>
          <button class="bv-hbtn" id="btn-close" title="${this.i("close")}">${l.close}</button>
        </div>

        <div class="bv-content">
          <!-- History overlay -->
          <div class="bv-history-panel" id="history-panel">
            <div class="bv-history-header">
              <button class="bv-hbtn" id="btn-history-back">${l.arrowLeft}</button>
              <span class="bv-history-title">${this.i("conversations")}</span>
            </div>
            <div class="bv-history-list" id="history-list">
              ${this.renderHistoryContent()}
            </div>
          </div>

          <!-- Messages -->
          <div class="bv-messages" id="messages">
            ${this.renderEmptyState()}
          </div>
        </div>

        <div class="bv-input-area">
          <div class="bv-input-row">
            <textarea class="bv-textarea" id="input"
              placeholder="${this.esc(this.cfg.placeholder)}"
              rows="1"
              aria-label="${this.i("placeholder")}"
            ></textarea>
            <button class="bv-send-btn" id="btn-send" title="${this.i("send")}">${l.send}</button>
          </div>
          <div class="bv-input-suggestions" id="input-suggestions"></div>
        </div>
        <div class="bv-input-hint">
          <span class="bv-kbd">Enter</span> ${this.i("shortcutSend")} · <span class="bv-kbd">Esc</span> ${this.i("shortcutClose")}
        </div>
      </div>
    `}renderHistoryContent(){return this.isLoadingHistory?`<div class="bv-history-empty">${this.i("loadingConversations")}</div>`:this.conversations.length===0?`
        <div class="bv-history-empty">
          ${l.messageSquare}
          <span>${this.i("noConversations")}</span>
        </div>
      `:this.conversations.map(e=>`
      <div class="bv-history-item" data-action="select-conversation" data-id="${e.id}">
        <div class="bv-history-item-content">
          <div class="bv-history-item-title">${this.esc(e.title)}</div>
          <div class="bv-history-item-meta">${this.formatDate(e.updated_at)} · ${this.i("messageCount",{count:e.message_count})}</div>
        </div>
        <button class="bv-history-item-delete" data-action="delete-conversation" data-id="${e.id}" title="${this.i("deleteConversation")}">
          ${l.trash}
        </button>
      </div>
    `).join("")}formatDate(e){const t=new Date(e),i=new Date().getTime()-t.getTime(),n=Math.floor(i/(1e3*60*60*24));return n===0?t.toLocaleTimeString(this.locale,{hour:"2-digit",minute:"2-digit"}):n===1?this.i("yesterday"):n<7?t.toLocaleDateString(this.locale,{weekday:"short"}):t.toLocaleDateString(this.locale,{day:"numeric",month:"short"})}renderEmptyState(){const e=this.generateSuggestions(),t=e.length>0?`<div class="bv-suggestions">
           ${e.map(s=>`
             <button class="bv-suggestion" data-action="suggest" data-message="${this.esc(s.message)}">
               <span class="bv-suggestion-icon">${s.icon}</span>
               ${this.esc(s.label)}
             </button>
           `).join("")}
         </div>`:"";return`
      <div class="bv-empty" id="empty-state">
        <div class="bv-empty-icon">${l.command}</div>
        <div class="bv-empty-text">${this.i("emptyState")}</div>
        ${t}
      </div>
    `}bindEvents(){this.shadow.addEventListener("click",t=>{var c;const s=t.target;if(s.closest("#fab")){this.toggle();return}const n=s.closest("[id], [data-action]");if(!n)return;const r=n.id,o=n.dataset.action;if(r==="btn-close"&&this.close(),r==="btn-send"&&this.handleSend(),r==="btn-new"&&this.startNewConversation(),r==="btn-history"&&this.toggleHistory(),r==="btn-history-back"&&this.toggleHistory(),r==="btn-theme"&&this.toggleTheme(),o==="confirm"&&this.handleConfirm(),o==="reject"&&this.handleReject(),o==="toggle-details"){const a=(c=n.closest(".bv-confirm-card"))==null?void 0:c.querySelector(".bv-confirm-details");if(a){const d=a.classList.toggle("bv-open"),b=n.querySelector(".bv-detail-label");b&&(b.textContent=d?this.i("hideDetails"):this.i("details")),n.classList.toggle("bv-open",d)}}if(o==="suggest"){const a=n.dataset.message;a&&this.send(a)}if(o==="select-conversation"){const a=n.dataset.id;a&&this.loadConversation(a)}if(o==="delete-conversation"){t.stopPropagation();const a=n.dataset.id;a&&this.deleteConversation(a)}if(o==="toggle-timeline"){const a=n.dataset.msgId;a&&this.toggleTimeline(a)}});const e=this.$("input");e&&(e.addEventListener("keydown",t=>{t.key==="Enter"&&!t.shiftKey&&(t.preventDefault(),this.handleSend())}),e.addEventListener("input",()=>{e.style.height="auto",e.style.height=Math.min(e.scrollHeight,120)+"px"}))}setupKeyboard(){this.boundKeyHandler=e=>{(e.ctrlKey||e.metaKey)&&e.key==="k"&&(e.preventDefault(),this.toggle()),e.key==="Escape"&&this.isOpen&&(this.isHistoryOpen?this.toggleHistory():this.close())},document.addEventListener("keydown",this.boundKeyHandler)}setupResize(){const e=this.$("resize-handle");if(!e)return;const t=this.cfg.position==="bottom-left";let s=0,i=0;const n=o=>{const c=t?o.clientX-s:s-o.clientX,a=Math.max(320,Math.min(i+c,window.innerWidth*.9));this.drawerWidth=a;const d=this.$("drawer");d&&(d.style.width=a+"px")},r=()=>{document.removeEventListener("mousemove",n),document.removeEventListener("mouseup",r),e.classList.remove("bv-active"),document.body.style.cursor="",document.body.style.userSelect=""};e.addEventListener("mousedown",o=>{o.preventDefault(),s=o.clientX,i=this.drawerWidth,e.classList.add("bv-active"),document.body.style.cursor="ew-resize",document.body.style.userSelect="none",document.addEventListener("mousemove",n),document.addEventListener("mouseup",r)})}setupTheme(){this.darkMediaQuery=window.matchMedia("(prefers-color-scheme: dark)"),this.darkMediaQuery.addEventListener("change",this.handleMediaChange),this.applyTheme()}applyTheme(){var s;const e=this.$("root");if(!e)return;const t=this.cfg.theme;t==="dark"?this.isDarkTheme=!0:t==="light"?this.isDarkTheme=!1:this.isDarkTheme=!!((s=this.darkMediaQuery)!=null&&s.matches),e.classList.toggle("bv-dark",this.isDarkTheme),this.updateThemeButton()}toggleTheme(){this.isDarkTheme=!this.isDarkTheme;const e=this.$("root");e&&e.classList.toggle("bv-dark",this.isDarkTheme),this.updateThemeButton()}updateThemeButton(){const e=this.$("btn-theme");e&&(e.innerHTML=this.isDarkTheme?l.moon:l.sun,e.title=this.isDarkTheme?this.i("themeDark"):this.i("themeLight"))}toggleTimeline(e){const t=this.shadow.querySelector(`[data-timeline-id="${e}"]`),s=this.shadow.querySelector(`[data-msg-id="${e}"]`);t&&(this.expandedTimelines.has(e)?(this.expandedTimelines.delete(e),t.classList.remove("bv-visible"),s==null||s.classList.remove("bv-expanded")):(this.expandedTimelines.add(e),t.classList.add("bv-visible"),s==null||s.classList.add("bv-expanded")))}async handleSend(){const e=this.$("input");if(!e)return;const t=e.value.trim();t&&(e.value="",e.style.height="auto",await this.send(t))}async handleConfirm(){if(!(!this.conversationId||!this.hasPending)){if(this.hasPending=!1,this.disableConfirmButtons(),this.cfg.streaming){this.confirmWithStreaming();return}this.setLoading(!0);try{const e=await this.api.confirm(this.conversationId);this.processResponse(e)}catch(e){this.handleError(e)}finally{this.setLoading(!1)}}}confirmWithStreaming(){this.streamingSteps=[],this.currentStreamingMessageId=this.uid(),this.addMessage({id:this.currentStreamingMessageId,role:"assistant",type:"loading",content:"",timestamp:new Date,_isFollowUp:!0}),this.isLoading=!0;const e=this.$("btn-send");e&&(e.disabled=!0),this.streamController=this.api.streamConfirm(this.conversationId,{onStep:t=>{this.streamingSteps.push({step:t.step,thought:t.thought,action:t.action??void 0,observation:t.observation??void 0}),this.updateStreamingStepsUI()},onConfirmation:(t,s,i)=>{this.hasPending=!0,this.finalizeStreamingMessage(i,"confirmation",{type:"operation",action:t,table:s.table||null,data:s,where:{},select:[],message:i,confidence:1,auto_continue:!1})},onMessage:t=>{this.finalizeStreamingMessage(t,"text")},onError:t=>{this.finalizeStreamingMessage(t.message,"error")},onDone:()=>{this.isLoading=!1,e&&(e.disabled=!1),this.streamController=null},onAbort:()=>{this.isLoading=!1,e&&(e.disabled=!1),this.streamController=null}})}async handleReject(){if(!(!this.conversationId||!this.hasPending)){this.hasPending=!1,this.disableConfirmButtons("rejected"),this.setLoading(!0);try{const e=await this.api.reject(this.conversationId);this.processResponse(e)}catch(e){this.handleError(e)}finally{this.setLoading(!1)}}}async handleReset(){if(this.conversationId)try{await this.api.reset(this.conversationId)}catch{}this.messages=[],this.conversationId=null,this.hasPending=!1;const e=this.$("messages");e&&(e.innerHTML=this.renderEmptyState()),this.toast(this.i("resetDone"),"info")}processResponse(e){var t;if(this.conversationId=e.conversation_id,(t=e.steps)!=null&&t.length)for(const s of e.steps)this.addMessage({id:this.uid(),role:"assistant",type:"action",content:"",timestamp:new Date,intent:s.intent,result:s.result});switch(e.type){case"message":this.addMessage({id:this.uid(),role:"assistant",type:"text",content:e.message,timestamp:new Date,intent:e.intent});break;case"confirmation":this.hasPending=!0,this.addMessage({id:this.uid(),role:"assistant",type:"confirmation",content:e.message,timestamp:new Date,intent:e.intent});break;case"executed":this.addMessage({id:this.uid(),role:"assistant",type:"executed",content:e.message,timestamp:new Date,intent:e.intent,result:e.result});break;case"rejected":this.hasPending=!1,this.addMessage({id:this.uid(),role:"assistant",type:"rejected",content:e.message,timestamp:new Date});break;case"error":this.addMessage({id:this.uid(),role:"assistant",type:"error",content:e.message,timestamp:new Date});break}!this.isOpen&&this.cfg.sounds&&this.playSound()}handleError(e){let t=this.i("error");e instanceof u?e.status===401||e.status===403?t=this.i("sessionExpired"):e.status===429?t=this.i("tooManyRequests"):e.status===419&&(t=this.i("sessionExpired")):e instanceof TypeError&&(t=this.i("connectionError")),this.addMessage({id:this.uid(),role:"assistant",type:"error",content:t,timestamp:new Date}),this.toast(t,"error")}addUserMessage(e){this.addMessage({id:this.uid(),role:"user",type:"text",content:e,timestamp:new Date})}addMessage(e){this.messages.push(e);const t=this.$("messages"),s=t.querySelector(".bv-empty");s&&s.remove();const i=document.createElement("div");i.innerHTML=this.renderMessage(e);const n=i.firstElementChild;n&&t.appendChild(n),this.scrollToBottom(),!this.isOpen&&e.role==="assistant"&&(this.unreadCount++,this.updateBadge())}setLoading(e){var i;this.isLoading=e;const t=this.$("messages"),s=this.$("btn-send");if(s&&(s.disabled=e),e){const n=document.createElement("div");n.innerHTML=this.renderLoading();const r=n.firstElementChild;r&&(t==null||t.appendChild(r)),this.scrollToBottom()}else(i=t==null?void 0:t.querySelector(".bv-msg-loading"))==null||i.remove()}updateBadge(){const e=this.$("badge");e&&(e.textContent=this.unreadCount>0?String(this.unreadCount):"",e.classList.toggle("bv-visible",this.unreadCount>0))}disableConfirmButtons(e="confirmed"){const t=this.messages.find(s=>s.type==="confirmation"&&!s._confirmState);t&&(t._confirmState=e),this.renderMessages()}scrollToBottom(){const e=this.$("messages");e&&requestAnimationFrame(()=>{e.scrollTop=e.scrollHeight})}renderMessages(){const e=this.$("messages");if(e){if(this.messages.length===0){e.innerHTML=this.renderEmptyState();return}e.innerHTML=this.messages.map(t=>this.renderMessage(t)).join(""),this.scrollToBottom()}}renderMessage(e){switch(e.type){case"text":return this.renderTextMsg(e);case"action":return this.renderActionMsg(e);case"confirmation":return this.renderConfirmMsg(e);case"executed":return this.renderExecutedMsg(e);case"rejected":return this.renderRejectedMsg(e);case"error":return this.renderErrorMsg(e);case"loading":return this.renderLoadingMsg(e);case"streaming":return this.renderStreamingMsg(e);default:return this.renderTextMsg(e)}}msgHeader(e){const t=e.role==="user",s=t?l.userIcon:l.botAvatar,i=t?this.i("you"):this.i("assistant");return`
      <div class="bv-msg-header">
        <div class="bv-msg-avatar">${s}</div>
        <span class="bv-msg-name">${i}</span>
        <span class="bv-msg-time">${this.fmtTime(e.timestamp)}</span>
      </div>`}renderStreamingMsg(e){return`
      <div class="bv-msg bv-msg-assistant${e._isFollowUp?" bv-msg-followup":""}">
        ${e._isFollowUp?"":this.msgHeader(e)}
        <div class="bv-msg-body${e._isFollowUp,""}">${e.content}</div>
      </div>`}renderLoadingMsg(e){return`
      <div class="bv-msg bv-msg-assistant bv-msg-loading-state${e._isFollowUp?" bv-msg-followup":""}">
        ${e._isFollowUp?"":`
        <div class="bv-msg-header">
          <div class="bv-msg-avatar">${l.botAvatar}</div>
          <span class="bv-msg-name">${this.i("assistant")}</span>
        </div>`}
        <div class="bv-msg-body">
          <div class="bv-loading-indicator">
            <div class="bv-loading-pulse">
              <span class="bv-loading-dot"></span>
              <span class="bv-loading-dot"></span>
              <span class="bv-loading-dot"></span>
            </div>
            <span class="bv-loading-text">${this.i("thinking")}</span>
          </div>
        </div>
      </div>`}renderTextMsg(e){const t=e.role==="user"?"bv-msg-user":"bv-msg-assistant",s=e._isFollowUp?" bv-msg-followup":"",i=e.role==="user"?this.esc(e.content):`<div class="bv-md">${this.renderStepsTimeline(e)}${this.markdown(e.content)}</div>`;return`
      <div class="bv-msg ${t}${s}">
        ${e._isFollowUp?"":this.msgHeader(e)}
        <div class="bv-msg-body">${i}</div>
      </div>`}renderActionMsg(e){const t=e.intent,s=e.result;if(!t)return this.renderTextMsg(e);let i=this.renderIntentCard(t);return s&&(i+=s.success?`<div class="bv-result-success">${l.checkCircle} ${this.esc(s.message)}</div>`:`<div class="bv-result-error">${l.xCircle} ${this.esc(s.message)}</div>`,s.success&&s.data&&(i+=this.renderDataTable(s.data))),t.auto_continue&&(i+=`<div class="bv-step-indicator"><span class="bv-step-dot"></span>${this.i("autoStep")}</div>`),`
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(e)}
        <div class="bv-msg-body">${i}</div>
      </div>`}renderConfirmMsg(e){const t=e.intent,s=e.content?`<div class="bv-confirm-desc">${this.esc(e.content)}</div>`:"";let i=this.i("confirmAction_default");if(t!=null&&t.action){const a=`confirmAction_${t.action.replace("_record","")}`;i=this.i(a)||this.i("confirmAction_default")}let n="";if(t){const a=[];if(t.where&&Object.keys(t.where).length>0)for(const[d,b]of Object.entries(t.where))a.push([this.friendlyFieldName(d),this.valStr(b)]);if(t.data&&Object.keys(t.data).length>0)for(const[d,b]of Object.entries(t.data))d==="table"||d==="where"||d==="select"||a.push([this.friendlyFieldName(d),this.valStr(b)]);if(a.length>0){const d=a.map(([b,h])=>`<tr><td class="bv-detail-field">${this.esc(b)}</td><td class="bv-detail-value">${this.esc(h)}</td></tr>`).join("");n=`
          <button class="bv-detail-toggle" data-action="toggle-details">
            ${l.chevronDown}
            <span class="bv-detail-label">${this.i("details")}</span>
          </button>
          <div class="bv-confirm-details">
            <table class="bv-detail-table">
              <thead><tr><th>${this.i("fieldName")}</th><th>${this.i("fieldValue")}</th></tr></thead>
              <tbody>${d}</tbody>
            </table>
          </div>`}}const r=e._confirmState,o=r?`bv-confirm-${r}`:"";let c;return r==="confirmed"?c=`
        <div class="bv-confirm-actions">
          <div class="bv-confirm-status bv-confirm-status-ok">
            ${l.checkCircle}
            <span>${this.i("confirmed")}</span>
          </div>
        </div>`:r==="rejected"?c=`
        <div class="bv-confirm-actions">
          <div class="bv-confirm-status bv-confirm-status-rejected">
            ${l.xCircle}
            <span>${this.i("rejected")}</span>
          </div>
        </div>`:c=`
        <div class="bv-confirm-actions">
          <button class="bv-btn bv-btn-confirm" data-action="confirm">${l.check} ${this.i("confirm")}</button>
          <button class="bv-btn bv-btn-reject" data-action="reject">${l.x} ${this.i("reject")}</button>
        </div>`,`
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(e)}
        <div class="bv-msg-body">
          ${this.renderStepsTimeline(e)}
          <div class="bv-confirm-card ${o}">
            <div class="bv-confirm-header">
              <div class="bv-confirm-icon">${l.shield}</div>
              <div class="bv-confirm-header-text">
                <div class="bv-confirm-title">${this.i("confirmQuestion")}</div>
                <div class="bv-confirm-action-label">${this.esc(i)}</div>
              </div>
            </div>
            ${s}
            ${n}
            ${c}
          </div>
        </div>
      </div>`}friendlyFieldName(e){return e.split("_").map(t=>t.charAt(0).toUpperCase()+t.slice(1)).join(" ")}renderExecutedMsg(e){const t=e.intent,s=e.result;let i="";if(t&&(i+=this.renderIntentCard(t)),s&&(i+=s.success?`<div class="bv-result-success">${l.checkCircle} ${this.esc(s.message)}</div>`:`<div class="bv-result-error">${l.xCircle} ${this.esc(s.message)}</div>`,s.success&&s.data)){const n=s.data;(t==null?void 0:t.action)&&t.action!=="read"?i+=this.renderCompactResult(n,t):i+=this.renderDataTable(Array.isArray(n)?n:[n])}return`
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(e)}
        <div class="bv-msg-body">${i}</div>
      </div>`}renderRejectedMsg(e){return`
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(e)}
        <div class="bv-msg-body">
          <div class="bv-rejected-msg">${l.xCircle} ${this.esc(e.content||this.i("operationCancelled"))}</div>
        </div>
      </div>`}renderErrorMsg(e){return`
      <div class="bv-msg bv-msg-assistant">
        ${this.msgHeader(e)}
        <div class="bv-msg-body">
          <div class="bv-result-error">${l.alert} ${this.esc(e.content)}</div>
        </div>
      </div>`}renderLoading(){return`
      <div class="bv-msg bv-msg-assistant bv-msg-loading">
        <div class="bv-msg-header">
          <div class="bv-msg-avatar">${l.botAvatar}</div>
          <span class="bv-msg-name">${this.i("assistant")}</span>
        </div>
        <div class="bv-msg-body">
          <div class="bv-loading-indicator">
            <div class="bv-loading-pulse">
              <span class="bv-loading-dot"></span>
              <span class="bv-loading-dot"></span>
              <span class="bv-loading-dot"></span>
            </div>
            <span class="bv-loading-text">${this.i("thinking")}</span>
          </div>
        </div>
      </div>`}renderStepsTimeline(e){const t=e._steps;if(!t||t.length===0)return"";const s=this.expandedTimelines.has(e.id),i=t.filter(r=>r.observation).length;let n="";for(const r of t){const c=!!r.observation?"bv-done":"";n+=`<div class="bv-tl-step ${c}">`,n+=`<div class="bv-tl-thought">${this.escapeHtml(r.thought)}</div>`,r.action&&(n+=`<div class="bv-tl-action">${this.escapeHtml(this.formatToolName(r.action))}</div>`),n+="</div>"}return`
      <button class="bv-timeline-toggle${s?" bv-expanded":""}" data-action="toggle-timeline" data-msg-id="${e.id}">
        ${l.chevronRight}
        ${this.i("steps",{count:t.length})}
        <span class="bv-tl-badge">${i}</span>
      </button>
      <div class="bv-timeline${s?" bv-visible":""}" data-timeline-id="${e.id}">
        ${n}
      </div>
    `}renderIntentCard(e){const t=e.action?`bv-action-${e.action}`:"";let s="";if(s+=`<div class="bv-intent-row"><span class="bv-intent-label">${this.i("table")}</span><span class="bv-intent-value">${this.esc(e.table||"-")}</span></div>`,s+=`<div class="bv-intent-row"><span class="bv-intent-label">${this.i("operation")}</span><span class="bv-intent-value ${t}">${this.esc(e.action||"-")}</span></div>`,e.data&&Object.keys(e.data).length>0){const i=Object.entries(e.data).map(([n,r])=>`${n}: ${this.valStr(r)}`).join(", ");s+=`<div class="bv-intent-row"><span class="bv-intent-label">${this.i("data")}</span><span class="bv-intent-value">${this.esc(i)}</span></div>`}if(e.where&&Object.keys(e.where).length>0){const i=Object.entries(e.where).map(([n,r])=>`${n} = ${this.valStr(r)}`).join(", ");s+=`<div class="bv-intent-row"><span class="bv-intent-label">${this.i("condition")}</span><span class="bv-intent-value">${this.esc(i)}</span></div>`}return e.select&&e.select.length>0&&(s+=`<div class="bv-intent-row"><span class="bv-intent-label">${this.i("columns")}</span><span class="bv-intent-value">${this.esc(e.select.join(", "))}</span></div>`),`
      <div class="bv-intent-card">
        <div class="bv-intent-header">${l.target} ${this.i("actionDetected")}</div>
        <div class="bv-intent-body">${s}</div>
      </div>`}renderDataTable(e,t=10){if(!Array.isArray(e)||e.length===0)return"";const s=e.length,i=e.slice(0,t),n=Object.keys(i[0]||{}).slice(0,8);let r='<div class="bv-table-wrapper"><table class="bv-table"><thead><tr>';for(const o of n)r+=`<th>${this.esc(o)}</th>`;r+="</tr></thead><tbody>";for(const o of i){r+="<tr>";for(const c of n){const a=o[c],d=this.cellStr(a);r+=`<td title="${this.esc(String(a??""))}">${this.esc(d)}</td>`}r+="</tr>"}return r+="</tbody></table></div>",r+=`<div class="bv-table-footer">${this.i("resultsFound",{count:s})}`,s>t&&(r+=` (${this.i("showingFirst",{count:t})})`),r+="</div>",r}renderCompactResult(e,t){const s=new Set(["id",...Object.keys(t.data||{}),...Object.keys(t.where||{})]),i=Array.isArray(e)?e:[e];if(i.length===0)return"";const n=i.map(r=>Object.fromEntries(Object.entries(r).filter(([o])=>s.has(o))));return this.renderDataTable(n,5)}async fetchSchema(){try{const e=await this.api.getSchema();if(this.schemaTables=e.tables||[],this.messages.length===0){const t=this.$("messages");t&&(t.innerHTML=this.renderEmptyState())}this.renderInputSuggestions()}catch{}}generateSuggestions(){const e=[];for(const t of this.schemaTables.slice(0,4)){const s=t.actions||t.allowed_actions||[],i=t.table||t.name||"";s.includes("read")&&e.push({label:this.i("listAll",{table:i}),message:this.i("listAll",{table:i}),icon:l.search}),s.includes("create")&&e.length<6&&e.push({label:this.i("addNew",{table:i}),message:this.i("addNew",{table:i}),icon:l.plus})}return e.slice(0,6)}renderInputSuggestions(){const e=this.$("input-suggestions");if(!e)return;const t=this.generateSuggestions();t.length!==0&&(e.innerHTML=t.slice(0,4).map(s=>`<button class="bv-input-chip" data-action="suggest" data-message="${this.esc(s.message)}">${this.esc(s.label)}</button>`).join(""))}toast(e,t="info"){const s=this.$("toasts");if(!s)return;const i=document.createElement("div");i.className=`bv-toast bv-toast-${t}`,i.textContent=e,s.appendChild(i),setTimeout(()=>{i.classList.add("bv-toast-exit"),setTimeout(()=>i.remove(),300)},3e3)}async toggleHistory(){this.isHistoryOpen=!this.isHistoryOpen;const e=this.$("history-panel");e&&e.classList.toggle("bv-visible",this.isHistoryOpen);const t=this.$("btn-history");t&&t.classList.toggle("bv-active",this.isHistoryOpen),this.isHistoryOpen&&await this.fetchConversations()}async fetchConversations(){this.isLoadingHistory=!0,this.updateHistoryList();try{const e=await this.api.getConversations();this.conversations=e.conversations}catch(e){console.error("Failed to fetch conversations:",e),this.conversations=[]}finally{this.isLoadingHistory=!1,this.updateHistoryList()}}updateHistoryList(){const e=this.$("history-list");e&&(e.innerHTML=this.renderHistoryContent())}async loadConversation(e){var t,s;try{const n=(await this.api.getConversation(e)).conversation;this.conversationId=n.id,this.messages=n.messages.map(r=>{let o="text";return r.role==="user"?o="text":r.success===!1?o="error":r.intent==="executed"?o="executed":r.intent==="rejected"?o="rejected":r.intent==="confirmation"&&(o="text"),{id:r.id,role:r.role,type:o,content:r.content,timestamp:new Date(r.created_at),intent:r.action?{table:r.table,action:r.action}:void 0,result:r.intent==="executed"?{success:r.success!==!1,message:r.content}:void 0}}),this.isHistoryOpen=!1,(t=this.$("history-panel"))==null||t.classList.remove("bv-visible"),(s=this.$("btn-history"))==null||s.classList.remove("bv-active"),this.renderMessages()}catch(i){console.error("Failed to load conversation:",i),this.toast(this.i("error"),"error")}}async deleteConversation(e){try{await this.api.deleteConversation(e),this.conversations=this.conversations.filter(t=>t.id!==e),this.updateHistoryList(),this.toast(this.i("conversationDeleted"),"success"),this.conversationId===e&&(this.conversationId=null,this.messages=[],this.renderMessages())}catch(t){console.error("Failed to delete conversation:",t),this.toast(this.i("error"),"error")}}startNewConversation(){var e,t,s;this.conversationId=null,this.messages=[],this.hasPending=!1,this.isHistoryOpen&&(this.isHistoryOpen=!1,(e=this.$("history-panel"))==null||e.classList.remove("bv-visible"),(t=this.$("btn-history"))==null||t.classList.remove("bv-active")),this.renderMessages(),(s=this.$("input"))==null||s.focus()}playSound(){try{const e=new AudioContext,t=e.createOscillator(),s=e.createGain();t.connect(s),s.connect(e.destination),t.frequency.value=800,s.gain.value=.08,t.start(),t.stop(e.currentTime+.1)}catch{}}$(e){return this.shadow.getElementById(e)}uid(){return Math.random().toString(36).slice(2,10)}esc(e){const t=document.createElement("div");return t.textContent=e,t.innerHTML}markdown(e){if(!e)return"";let t=this.esc(e);return t=t.replace(/```([\s\S]*?)```/g,'<pre class="bv-md-pre">$1</pre>'),t=t.replace(/((?:^\|.+\|$(?:\n|$))+)/gm,s=>{const i=s.trim().split(`
`).filter(o=>o.trim());if(i.length<2||!/^\|[\s\-:|]+(?:\|[\s\-:|]+)+\|?$/.test(i[1]))return s;let n='<div class="bv-table-wrapper"><table class="bv-table"><thead><tr>';const r=i[0].replace(/^\||\|$/g,"").split("|");for(const o of r)n+=`<th>${o.trim()}</th>`;n+="</tr></thead><tbody>";for(let o=2;o<i.length;o++){const c=i[o].replace(/^\||\|$/g,"").split("|");if(c.length!==0){n+="<tr>";for(const a of c){let d=a.trim().replace(/&lt;br&gt;/gi,"<br>");d=d.replace(/\*\*([^*]+)\*\*/g,"<strong>$1</strong>"),d=d.replace(/\*([^*]+)\*/g,"<em>$1</em>"),n+=`<td>${d}</td>`}n+="</tr>"}}return n+="</tbody></table></div>",n}),t=t.replace(/^---+$/gm,'<hr class="bv-md-hr">'),t=t.replace(/`([^`]+)`/g,'<code class="bv-md-code">$1</code>'),t=t.replace(/\*\*([^*]+)\*\*/g,"<strong>$1</strong>"),t=t.replace(/__([^_]+)__/g,"<strong>$1</strong>"),t=t.replace(/\*([^*]+)\*/g,"<em>$1</em>"),t=t.replace(/_([^_]+)_/g,"<em>$1</em>"),t=t.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>'),t=t.replace(/^### (.+)$/gm,'<h4 class="bv-md-h4">$1</h4>'),t=t.replace(/^## (.+)$/gm,'<h3 class="bv-md-h3">$1</h3>'),t=t.replace(/^# (.+)$/gm,'<h2 class="bv-md-h2">$1</h2>'),t=t.replace(/^[-*] (.+)$/gm,'<li class="bv-md-li">$1</li>'),t=t.replace(/(<li class="bv-md-li">.*<\/li>\n?)+/g,s=>'<ul class="bv-md-ul">'+s+"</ul>"),t=t.replace(/^\d+\. (.+)$/gm,'<li class="bv-md-oli">$1</li>'),t=t.replace(/(<li class="bv-md-oli">.*<\/li>\n?)+/g,s=>'<ol class="bv-md-ol">'+s+"</ol>"),t=t.replace(/\n\n/g,"</p><p>"),t=t.replace(/\n/g,"<br>"),!t.startsWith("<h")&&!t.startsWith("<ul")&&!t.startsWith("<ol")&&!t.startsWith("<pre")&&(t="<p>"+t+"</p>"),t}fmtTime(e){return e.toLocaleTimeString(this.locale==="tr"?"tr-TR":"en-US",{hour:"2-digit",minute:"2-digit"})}valStr(e){return e==null?"-":typeof e=="object"?JSON.stringify(e):String(e)}cellStr(e){if(e==null)return"-";const t=typeof e=="object"?JSON.stringify(e):String(e);return t.length>30?t.slice(0,30)+"…":t}detectCsrf(){var e;return((e=document.querySelector('meta[name="csrf-token"]'))==null?void 0:e.getAttribute("content"))??null}};x.observedAttributes=["endpoint","lang","theme","position","title","placeholder","csrf-token","sounds","streaming"];let g=x;return customElements.get("botovis-chat")||customElements.define("botovis-chat",g),m.BotovisChat=g,Object.defineProperty(m,Symbol.toStringTag,{value:"Module"}),m}({});
//# sourceMappingURL=botovis-widget.iife.js.map
