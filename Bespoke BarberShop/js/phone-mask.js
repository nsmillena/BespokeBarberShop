(function(){
  function onlyDigits(s){ return (s||'').replace(/\D/g,''); }
  function formatBR(v){
    const d = onlyDigits(v).slice(0,11);
    if (d.length <= 10) { // (00) 0000-0000
      const p1 = d.slice(0,2);
      const p2 = d.slice(2,6);
      const p3 = d.slice(6,10);
      return (p1?`(${p1}`:'') + (p1&&p1.length===2?') ': (p1?'':'') ) + (p2||'') + (p3?`-${p3}`:'');
    } else { // 11 digits: (00) 00000-0000
      const p1 = d.slice(0,2);
      const p2 = d.slice(2,7);
      const p3 = d.slice(7,11);
      return `(${p1}) ${p2}-${p3}`;
    }
  }
  function setCursorToEnd(el){
    const len = el.value.length; el.setSelectionRange(len,len);
  }
  function attachMask(el){
    if (!el) return;
    const handler = function(e){
      const cur = el.value; const f = formatBR(cur);
      if (cur !== f){ el.value = f; setCursorToEnd(el); }
    };
    el.addEventListener('input', handler);
    el.addEventListener('blur', handler);
    // format initial
    handler();
  }
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('input.bb-phone, input[type="tel"].bb-phone').forEach(attachMask);
  });
})();