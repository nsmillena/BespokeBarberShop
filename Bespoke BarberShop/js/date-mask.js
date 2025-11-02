(function(){
  // Apply only for English locale
  var lang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
  if (!lang || (lang !== 'en' && !lang.startsWith('en-'))) return;

  // Use Flatpickr to guarantee calendar + MM/DD/YYYY while submitting YYYY-MM-DD
  function loadFlatpickr(cb){
    if (window.flatpickr) { cb(); return; }
    var css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
    document.head.appendChild(css);
    var s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
    s.onload = function(){ cb(); };
    document.head.appendChild(s);
  }

  function init(){
    var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="date"]'));
    inputs.forEach(function(inp){
      if (inp.dataset.bbFlatpickr === '1') return;
      // Keep the original name and value; Flatpickr will show alt input while the original keeps Y-m-d
      try { inp.type = 'text'; } catch(_) {}
      var defaultDate = null;
      var v = (inp.value || '').trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(v)) defaultDate = v;
      window.flatpickr(inp, {
        dateFormat: 'Y-m-d',     // value enviado no form
        altInput: true,
        altFormat: 'm/d/Y',      // exibido ao usuário
        allowInput: true,
        defaultDate: defaultDate || undefined,
        clickOpens: true,
        // Mantém estilo consistente (classe bootstrap-like já aplicada ao input original)
        onReady: function(selectedDates, dateStr, instance){
          if (instance && instance.altInput) {
            instance.altInput.className = inp.className; // copia classes visuais
            instance.altInput.placeholder = 'MM/DD/YYYY';
          }
        }
      });
      inp.dataset.bbFlatpickr = '1';
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    loadFlatpickr(init);
  });
})();
