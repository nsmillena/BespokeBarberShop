(function(){
  // Simple i18n dictionary based on document language
  const lang = (document.documentElement.lang || '').toLowerCase().startsWith('en') ? 'en' : 'pt';
  const I18N = {
    en: {
      strengthLabel: 'Password strength: ',
      typeHint: 'Type a password (min. 8, uppercase, lowercase, number and symbol)',
      levels: {
        vweak: 'Very weak', weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong', excellent: 'Excellent'
      },
      req: {
        len: 'Min. 8 characters', lower: 'Lowercase letter', upper: 'Uppercase letter', digit: 'Number', symbol: 'Symbol'
      },
      showHide: 'Show/Hide password',
      confirmOk: 'Passwords match',
      confirmBad: 'Passwords do not match'
    },
    pt: {
      strengthLabel: 'Força da senha: ',
      typeHint: 'Digite uma senha (mín. 8, maiúscula, minúscula, número e símbolo)',
      levels: {
        vweak: 'Muito fraca', weak: 'Fraca', fair: 'Razoável', good: 'Boa', strong: 'Forte', excellent: 'Excelente'
      },
      req: {
        len: 'Mín. 8 caracteres', lower: 'Letra minúscula', upper: 'Letra maiúscula', digit: 'Número', symbol: 'Símbolo'
      },
      showHide: 'Mostrar/ocultar senha',
      confirmOk: 'Senhas conferem',
      confirmBad: 'Senhas não conferem'
    }
  }[lang];
  // Inject minimal styles once (scoped by .bb-pass-wrap / .bb-strength)
  if (!document.getElementById('bb-strength-styles')){
    const css = `
      .bb-pass-wrap{ position: relative; }
  .bb-pass-wrap input.bb-password, .bb-pass-wrap input.bb-password-confirm, .bb-pass-wrap input[data-bb-toggle]{ padding-right: 2.5rem; }
      .bb-toggle-pass{ position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); background: transparent; border: 0; color: rgba(255,255,255,.75); padding: .25rem; cursor:pointer; }
      .bb-toggle-pass:hover{ color:#daa520; }
      .bb-strength{ margin-top: .5rem; }
      .bb-strength .progress{ height: 8px; background: rgba(255,255,255,.08); }
      .bb-strength .progress-bar{ transition: width .25s ease; }
      .bb-req-list{ list-style:none; margin:.35rem 0 0; padding:0; display:flex; flex-wrap:wrap; gap:.6rem 1rem; }
      .bb-req-list li{ font-size: .9rem; display:flex; align-items:center; gap:.35rem; color: rgba(255,255,255,.6); }
      .bb-req-ok{ color:#28a745 !important; }
      .bb-req-bad{ color:#dc3545 !important; }
    `;
    const st = document.createElement('style');
    st.id = 'bb-strength-styles';
    st.innerHTML = css;
    document.head.appendChild(st);
  }

  function computeScore(pw){
    if(!pw) return 0;
    let score = 0;
    const len = pw.length;
    const hasLower = /[a-z]/.test(pw);
    const hasUpper = /[A-Z]/.test(pw);
    const hasDigit = /\d/.test(pw);
    const hasSymbol = /[^A-Za-z0-9]/.test(pw);
    if(len >= 8) score += 1;
    if(len >= 12) score += 1;
    if(hasLower) score += 1;
    if(hasUpper) score += 1;
    if(hasDigit) score += 1;
    if(hasSymbol) score += 1;
    return Math.min(score, 6);
  }
  function scoreToUI(score){
    // Map score (0..6) to percent and label/color
    let percent = [0,16,32,50,72,88,100][score];
    let label;
    let clazz;
    switch(true){
      case score <= 1: label = I18N.levels.vweak; clazz='bg-danger'; break;
      case score === 2: label = I18N.levels.weak; clazz='bg-warning'; break;
      case score === 3: label = I18N.levels.fair; clazz='bg-info'; break;
      case score === 4: label = I18N.levels.good; clazz='bg-primary'; break;
      case score === 5: label = I18N.levels.strong; clazz='bg-success'; break;
      default: label = I18N.levels.excellent; clazz='bg-success'; break;
    }
    return {percent, label, clazz};
  }

  function ensureWrap(input){
    if (input.closest('.bb-pass-wrap')) return input.closest('.bb-pass-wrap');
    const wrap = document.createElement('div');
    wrap.className = 'bb-pass-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'bb-toggle-pass';
  toggle.setAttribute('aria-label', I18N.showHide);
    toggle.innerHTML = '<i class="bi bi-eye"></i>';
    // Keep the eye vertically centered relative to the INPUT (not the wrapper)
    function positionToggle(){
      // offsetTop is relative to wrap; centers using the input height only
      const center = input.offsetTop + (input.offsetHeight / 2);
      toggle.style.top = center + 'px';
    }
    toggle.addEventListener('click', ()=>{
      const isPass = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPass ? 'text' : 'password');
      toggle.innerHTML = isPass ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
    wrap.appendChild(toggle);
    // Initial align and on layout changes
    positionToggle();
    window.addEventListener('resize', positionToggle);
    input.addEventListener('input', positionToggle);
    input.addEventListener('focus', positionToggle);
    input.addEventListener('blur', positionToggle);
    return wrap;
  }

  function ensureMeter(input){
    let meter = input._bbMeter;
    if (meter) return meter;
    const inputWrap = ensureWrap(input); // wrapper that positions the eye
    const strength = document.createElement('div');
    strength.className = 'bb-strength';
    const progress = document.createElement('div');
    progress.className = 'progress';
    const bar = document.createElement('div');
    bar.className = 'progress-bar';
    bar.style.width = '0%';
    bar.setAttribute('role','progressbar');
    progress.appendChild(bar);
    const small = document.createElement('small');
    small.className = 'bb-strength-text text-muted';
    small.style.display = 'block';
    small.style.marginTop = '6px';
    const req = document.createElement('ul');
    req.className = 'bb-req-list';
    req.innerHTML = `
      <li data-rule="len"><i class="bi bi-x-circle"></i><span>${I18N.req.len}</span></li>
      <li data-rule="lower"><i class="bi bi-x-circle"></i><span>${I18N.req.lower}</span></li>
      <li data-rule="upper"><i class="bi bi-x-circle"></i><span>${I18N.req.upper}</span></li>
      <li data-rule="digit"><i class="bi bi-x-circle"></i><span>${I18N.req.digit}</span></li>
      <li data-rule="symbol"><i class="bi bi-x-circle"></i><span>${I18N.req.symbol}</span></li>
    `;
    strength.appendChild(progress);
    strength.appendChild(small);
    strength.appendChild(req);
    // Insert strength block AFTER the wrapper, so the eye stays centered inside the input
    inputWrap.insertAdjacentElement('afterend', strength);
    input._bbMeter = {wrap: strength, progress, bar, small, req};
    return input._bbMeter;
  }

  function updateChecklist(meter, pw){
    const rules = {
      len: (pw||'').length >= 8,
      lower: /[a-z]/.test(pw||''),
      upper: /[A-Z]/.test(pw||''),
      digit: /\d/.test(pw||''),
      symbol: /[^A-Za-z0-9]/.test(pw||''),
    };
    Array.from(meter.req.querySelectorAll('li')).forEach(li=>{
      const r = li.getAttribute('data-rule');
      const ok = !!rules[r];
      li.classList.toggle('bb-req-ok', ok);
      li.classList.toggle('bb-req-bad', !ok);
      const icon = li.querySelector('i');
      if (icon) icon.className = ok ? 'bi bi-check-circle' : 'bi bi-x-circle';
    });
  }

  function updateMeter(input){
    const meter = ensureMeter(input);
    const pw = input.value || '';
    const score = computeScore(pw);
    const ui = scoreToUI(score);
    meter.bar.style.width = ui.percent + '%';
    meter.bar.className = 'progress-bar ' + ui.clazz;
  meter.small.textContent = pw ? (I18N.strengthLabel + ui.label) : I18N.typeHint;
    updateChecklist(meter, pw);
  }

  function setupConfirm(confirmInput){
    ensureWrap(confirmInput);
    let msg = confirmInput._bbConfirmMsg;
    if(!msg){
      msg = document.createElement('small');
      msg.className = 'bb-confirm-msg';
      msg.style.display = 'block';
      msg.style.marginTop = '4px';
      confirmInput.insertAdjacentElement('afterend', msg);
      confirmInput._bbConfirmMsg = msg;
    }
    function validate(){
      const sel = confirmInput.getAttribute('data-match');
      let pwInput = null;
      if (sel){ pwInput = confirmInput.closest('form')?.querySelector(sel); }
      if (!pwInput){ pwInput = confirmInput.closest('form')?.querySelector('.bb-password'); }
      const pwVal = pwInput ? (pwInput.value || '') : '';
      const confVal = confirmInput.value || '';

      // Caso de edição sem troca de senha: ambos vazios não deve bloquear
      if (pwVal.length === 0 && confVal.length === 0) {
        confirmInput.setCustomValidity('');
        msg.textContent = '';
        msg.className = 'bb-confirm-msg';
        return;
      }

  const ok = pwVal.length > 0 && confVal.length > 0 && pwVal === confVal;
  confirmInput.setCustomValidity(ok ? '' : I18N.confirmBad);
  msg.textContent = ok ? I18N.confirmOk : (confVal ? I18N.confirmBad : '');
      msg.className = 'bb-confirm-msg ' + (ok ? 'text-success' : (confVal ? 'text-danger' : ''));
    }
    confirmInput.addEventListener('input', validate);
    const form = confirmInput.closest('form');
    form && form.addEventListener('input', (e)=>{
      if (e.target && e.target.matches('.bb-password')) validate();
    });
    validate();
  }

  function init(){
    document.querySelectorAll('input.bb-password').forEach((inp)=>{
      updateMeter(inp);
      inp.addEventListener('input', ()=> updateMeter(inp));
    });
    document.querySelectorAll('input.bb-password-confirm').forEach((inp)=> setupConfirm(inp));

    // Add toggle-only support: inputs with data-bb-toggle (no meter)
    document.querySelectorAll('input[data-bb-toggle]:not(.bb-password):not(.bb-password-confirm)').forEach((inp)=>{
      ensureWrap(inp);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
