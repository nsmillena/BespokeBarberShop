<?php
include "includes/db.php";
include_once "includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();

// Buscar unidades
$unidades = [];
$resUnidades = $conn->query("SELECT idUnidade, nomeUnidade, endereco FROM Unidade");
while($row = $resUnidades->fetch_assoc()) {
  $unidades[] = $row;
}

// Buscar barbeiros agrupados por unidade
$barbeiros = [];
$resBarb = $conn->query("SELECT b.idBarbeiro, b.nomeBarbeiro, u.idUnidade, u.nomeUnidade FROM Barbeiro b JOIN Unidade u ON b.Unidade_idUnidade = u.idUnidade WHERE b.statusBarbeiro = 'Ativo'");
while($row = $resBarb->fetch_assoc()) {
  $barbeiros[$row['idUnidade']][] = $row;
}

// Buscar serviços
$servicos = [];
$resServ = $conn->query("SELECT idServico, nomeServico, precoServico, duracaoPadrao FROM Servico");
while($row = $resServ->fetch_assoc()) {
  $servicos[] = $row;
}

// Prefill opcional (repetir último serviço): unidade, barbeiro e servico via GET
$prefill = [
  'unidade' => isset($_GET['unidade']) ? (int)$_GET['unidade'] : null,
  'barbeiro' => isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : null,
  'servico' => isset($_GET['servico']) ? (int)$_GET['servico'] : null,
];
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('sched.title') ?> - Bespoke BarberShop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="agendamento.css">
</head>
<body class="container py-5">

<!-- Botão Voltar ao Painel agora fica fixo no canto inferior esquerdo (definido ao final da página) -->

<div class="progress-container mb-4">
  <div class="step active" id="step1"><div class="circle">1</div><small><?= t('sched.step_unit') ?></small></div>
  <div class="step" id="step2"><div class="circle">2</div><small><?= t('sched.step_barber') ?></small></div>
  <div class="step" id="step3"><div class="circle">3</div><small><?= t('sched.step_datetime') ?></small></div>
  <div class="step" id="step4"><div class="circle">4</div><small><?= t('sched.step_service') ?></small></div>
</div>

<div id="step-content">

  <!-- PASSO 1: Escolha a Unidade (cards) -->
  <div class="card-custom step-card" data-step="1">
  <h5><?= t('sched.choose_unit') ?></h5>
    <div class="d-flex gap-4 justify-content-center flex-wrap">
      <?php foreach($unidades as $unidade): ?>
        <div class="card-custom unit-btn" onclick="selectUnit(<?= $unidade['idUnidade'] ?>, this)">
          <span class="unit-name"><?= htmlspecialchars($unidade['nomeUnidade']) ?></span>
          <span class="unit-address"><?= htmlspecialchars($unidade['endereco']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- PASSO 2: Escolha o Barbeiro (cards filtrados) -->
  <div class="card-custom step-card d-none" data-step="2">
    <h5><?= t('sched.choose_barber') ?></h5>
    <div class="d-flex gap-3 justify-content-center flex-wrap mt-3" id="barber-container"></div>
    <div class="d-flex justify-content-between mt-3 w-100">
  <button class="btn-custom btn-back" onclick="prevStep(2)">← <?= t('sched.back') ?></button>
  <button class="btn-custom" onclick="nextStep(2)"><?= t('sched.continue') ?> →</button>
    </div>
  </div>

  <!-- PASSO 3: Data & Hora -->
  <div class="card-custom step-card d-none" data-step="3">
    <h5><?= t('sched.choose_datetime') ?></h5>
    <div class="d-flex gap-4 justify-content-center flex-wrap">
      <div class="calendar">
        <div class="calendar-header">
          <span class="change-btn" id="prev-month">&lt;</span>
          <div class="month-year">
            <span class="month-picker" id="month-picker">Fevereiro</span>
            <span id="year">2023</span>
          </div>
          <span class="change-btn" id="next-month">&gt;</span>
        </div>
        <div class="calendar-week-day" id="weekday-header"></div>
        <div class="calendar-day" id="calendar-days"></div>
      </div>

      <div class="time-scroll">
        <div id="time-buttons-container"></div>
      </div>
    </div>
  <div id="day-unavailable" class="text-warning mt-2 d-none"><?= t('sched.unavailable_day') ?></div>
    <div class="d-flex justify-content-between mt-3 w-100">
  <button class="btn-custom btn-back" onclick="prevStep(3)">← <?= t('sched.back') ?></button>
  <button class="btn-custom" onclick="nextStep(3)"><?= t('sched.continue') ?> →</button>
    </div>
  </div>

  <!-- PASSO 4: Serviço -->
  <div class="card-custom step-card d-none" data-step="4">
    <h5><?= t('sched.choose_service') ?></h5>
    <div class="service-container">
      <?php foreach($servicos as $serv): ?>
              <div class="service-card" data-id="<?= $serv['idServico'] ?>" data-durmins="<?= (int)$serv['duracaoPadrao'] ?>" data-preco_brl="<?= htmlspecialchars((float)$serv['precoServico']) ?>" onclick="selectService(this)">
                <h6><?= htmlspecialchars(bb_service_display($serv['nomeServico'])) ?></h6>
                <small><?= bb_format_currency_local((float)$serv['precoServico']) ?> | <?= bb_format_minutes($serv['duracaoPadrao']) ?></small>
              </div>
      <?php endforeach; ?>
    </div>
    <div class="final-actions d-flex justify-content-between mt-3 align-items-center w-100" style="gap: 16px;">
      <button class="btn-custom btn-back" onclick="prevStep(4)">← <?= t('sched.back') ?></button>
      <form id="form-agendamento" method="POST" action="resumo.php" class="d-flex align-items-center ms-auto" style="gap: 16px;">
        <input type="hidden" name="unidade" id="form-unidade">
        <input type="hidden" name="data" id="form-data">
        <input type="hidden" name="hora" id="form-hora">
        <input type="hidden" name="barbeiro" id="form-barbeiro">
        <input type="hidden" name="barbeiro_id" id="form-barbeiro-id">
        <input type="hidden" name="servico" id="form-servico">
        <input type="hidden" name="servico_id" id="form-servico-id">
        <input type="hidden" name="preco_brl" id="form-preco-brl">
        <input type="hidden" name="duracao" id="form-duracao">
        <button id="finalize-btn" class="btn-custom finalize-btn" disabled type="button" onclick="finalizeAgendamento()"><?= t('sched.finalize') ?> →</button>
      </form>
    </div>
  </div>

</div>

<script>
let currentUnit = null;
let selectedBarber = null;
let selectedService = null;
let selectedDay = null;
let selectedTime = null;
// cache de dias indisponíveis por mês para o barbeiro selecionado: key `${year}-${month}` => Set(dias)
const monthDisabledCache = new Map();

// Barbeiros reais por unidade (do PHP)
const barbersData = <?php echo json_encode($barbeiros); ?>;
const prefill = <?php echo json_encode($prefill); ?>;

function updateProgress(step){
  for(let i=1;i<=4;i++){
    document.getElementById(`step${i}`).classList.remove('active','completed');
    if(i<step) document.getElementById(`step${i}`).classList.add('completed');
    else if(i===step) document.getElementById(`step${i}`).classList.add('active');
  }
}
function nextStep(step){
  document.querySelector(`[data-step="${step}"]`).classList.add("d-none");
  document.querySelector(`[data-step="${step+1}"]`).classList.remove("d-none");
  updateProgress(step+1);
  updateFinalizeButtonState();
}
function prevStep(step){
  document.querySelector(`[data-step="${step}"]`).classList.add("d-none");
  document.querySelector(`[data-step="${step-1}"]`).classList.remove("d-none");
  updateProgress(step-1);
  updateFinalizeButtonState();
}
function selectUnit(unit, el){
  document.querySelectorAll('.unit-btn').forEach(b=>b.classList.remove('selected'));
  el.classList.add('selected');
  currentUnit = unit;
  populateBarbers();
  updateFinalizeButtonState();
  nextStep(1);
}
function selectBarber(el){
  if(selectedBarber) selectedBarber.classList.remove('selected');
  el.classList.add('selected');
  selectedBarber = el;
  updateFinalizeButtonState();
  markUnavailableTimes();
}
function selectService(el){
  if(selectedService) selectedService.classList.remove('selected');
  el.classList.add('selected');
  selectedService = el;
  updateFinalizeButtonState();
  markUnavailableTimes();
}
function populateBarbers(){
  const container = document.getElementById('barber-container');
  container.innerHTML = '';
  if (!currentUnit || !barbersData[currentUnit]) {
    container.innerHTML = '<div class="text-warning"><?= t('sched.select_unit_barbers') ?></div>';
    return;
  }
  barbersData[currentUnit].forEach(barb => {
    const div = document.createElement('div');
    div.className = 'card-custom p-3';
    div.textContent = '👤 ' + barb.nomeBarbeiro;
    div.setAttribute('data-id', barb.idBarbeiro);
    div.onclick = ()=>{
      selectBarber(div);
      // limpa cache quando troca de barbeiro
      monthDisabledCache.clear();
      // recarrega indisponibilidades do mês atual
      fetchMonthDisabledDays().then(()=>renderCalendar(currentMonth,currentYear));
    };
    container.appendChild(div);
  });
}

// Calendário
const daysContainer = document.getElementById("calendar-days");
const monthPicker = document.getElementById("month-picker");
const yearDisplay = document.getElementById("year");
const prevMonth = document.getElementById("prev-month");
const nextMonth = document.getElementById("next-month");
let today = new Date();
let currentMonth = today.getMonth();
let currentYear = today.getFullYear();

const isEn = <?= bb_is_en() ? 'true' : 'false' ?>;
const months = isEn
  ? ["January","February","March","April","May","June","July","August","September","October","November","December"]
  : ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"];
const weekdays = isEn
  ? ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"]
  : ["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"];
// render weekday header
document.addEventListener('DOMContentLoaded', ()=>{
  const w = document.getElementById('weekday-header');
  if (w) w.innerHTML = weekdays.map(d=>`<div>${d}</div>`).join('');
});
// Limite de navegação: mês atual e próximo mês
const maxDate = new Date(today.getFullYear(), today.getMonth() + 1, 1); // primeiro dia do próximo mês
function renderCalendar(month, year){
  daysContainer.innerHTML = "";
  monthPicker.textContent = months[month];
  yearDisplay.textContent = year;

  let firstDay = new Date(year, month, 1).getDay();
  let daysInMonth = new Date(year, month+1, 0).getDate();

  for(let i=0; i<firstDay; i++){ daysContainer.innerHTML += `<div></div>`; }

  for(let day=1; day<=daysInMonth; day++){
    const dayEl = document.createElement("div");
    dayEl.textContent = day;
    const isPast = (year < today.getFullYear()) || (year === today.getFullYear() && month < today.getMonth()) || (year === today.getFullYear() && month === today.getMonth() && day < today.getDate());
    const mKey = `${year}-${String(month+1).padStart(2,'0')}`;
    const disabledSet = monthDisabledCache.get(mKey);
    const isDisabled = disabledSet ? disabledSet.has(day) : false;
    if(isPast){ dayEl.style.opacity="0.4"; dayEl.style.cursor="not-allowed"; } 
  else if (isDisabled) { dayEl.classList.add('disabled-day'); }
  else { dayEl.addEventListener("click", ()=>{ 
      // Ao mudar a data: limpar horário selecionado
      document.querySelectorAll(".time-btn").forEach(b=>b.classList.remove("selected"));
      selectedTime = null;
      // Seleciona a nova data
      selectedDay={day,month,year}; 
      // Recria horários com janela adequada para o dia
      renderTimesForDate(new Date(year, month, day));
      renderCalendar(month,year); 
      updateFinalizeButtonState(); 
      markUnavailableTimes(); 
    }); }
    if(selectedDay && day===selectedDay.day && month===selectedDay.month && year===selectedDay.year) dayEl.classList.add("selected-date");
    if(day===today.getDate() && month===today.getMonth() && year===today.getFullYear()) dayEl.classList.add("current-date");
    daysContainer.appendChild(dayEl);
  }
}
prevMonth.addEventListener("click", ()=>{
  // Bloqueia voltar antes do mês atual
  if (currentYear < today.getFullYear() || (currentYear === today.getFullYear() && currentMonth <= today.getMonth())) return;
  currentMonth--; if(currentMonth<0){currentMonth=11; currentYear--;}
  fetchMonthDisabledDays().then(()=>renderCalendar(currentMonth,currentYear));
});
nextMonth.addEventListener("click", ()=>{
  // Bloqueia avançar além do próximo mês
  const isAtOrBeyondNext = (currentYear > maxDate.getFullYear()) || (currentYear === maxDate.getFullYear() && currentMonth >= maxDate.getMonth());
  if (isAtOrBeyondNext) return;
  currentMonth++; if(currentMonth>11){currentMonth=0; currentYear++;}
  // Se passarmos do limite por virada de ano, corrige
  const beyond = (currentYear > maxDate.getFullYear()) || (currentYear === maxDate.getFullYear() && currentMonth > maxDate.getMonth());
  if (beyond) { currentYear = maxDate.getFullYear(); currentMonth = maxDate.getMonth(); }
  fetchMonthDisabledDays().then(()=>renderCalendar(currentMonth,currentYear));
});
// Busca inicial dos dias indisponíveis, então renderiza
fetchMonthDisabledDays().then(()=>renderCalendar(currentMonth,currentYear));

// Horários por data: abertura mantida e fechamento reduzido em domingos/feriados
const timeContainer = document.getElementById("time-buttons-container");

// Algoritmo de Páscoa (Greg.) e feriados móveis comuns (Carnaval, Sexta Santa, Corpus Christi)
function easterDate(year){
  const a = year % 19; const b = Math.floor(year/100); const c = year % 100; const d = Math.floor(b/4); const e = b % 4;
  const f = Math.floor((b + 8) / 25); const g = Math.floor((b - f + 1) / 3); const h = (19*a + b - d - g + 15) % 30;
  const i = Math.floor(c/4); const k = c % 4; const l = (32 + 2*e + 2*i - h - k) % 7; const m = Math.floor((a + 11*h + 22*l) / 451);
  const month = Math.floor((h + l - 7*m + 114) / 31); const day = ((h + l - 7*m + 114) % 31) + 1;
  return new Date(year, month-1, day);
}
function addDays(date, days){ const d=new Date(date); d.setDate(d.getDate()+days); return d; }
function fixedHolidays(year){
  const list = ["01-01","04-21","05-01","09-07","10-12","11-02","11-15","12-25"]; 
  return new Set(list.map(md=>`${year}-${md}`));
}
function movableHolidays(year){
  const easter = easterDate(year);
  const carnival = addDays(easter, -47);
  const goodFriday = addDays(easter, -2);
  const corpusChristi = addDays(easter, 60);
  const set = new Set();
  [carnival, goodFriday, easter, corpusChristi].forEach(d=>{
    const iso = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    set.add(iso);
  });
  return set;
}
function isHoliday(date){
  const y = date.getFullYear();
  const iso = `${y}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
  const fixed = fixedHolidays(y); const movable = movableHolidays(y);
  return fixed.has(iso) || movable.has(iso);
}
function businessWindowFor(date){
  const openMin = 9*60; // 09:00
  const isSunday = date.getDay() === 0;
  const reduced = isSunday || isHoliday(date);
  const closeMin = reduced ? 16*60 : 21*60; // fecha mais cedo em domingos/feriados
  return { openMin, closeMin };
}
function toDisplayTime(h, m){
  if (!isEn) return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hh = h % 12 || 12;
  return `${hh}:${String(m).padStart(2,'0')} ${ampm}`;
}
function renderTimesForDate(date){
  const { openMin, closeMin } = businessWindowFor(date);
  timeContainer.innerHTML = "";
  for(let mins=openMin; mins<=closeMin; mins+=30){
    const h = Math.floor(mins/60), m = mins%60;
    const btn = document.createElement("button");
    btn.className = "time-btn";
    btn.textContent = toDisplayTime(h, m);
    btn.dataset.value24 = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
    btn.onclick = ()=>{
      document.querySelectorAll(".time-btn").forEach(b=>b.classList.remove("selected"));
      btn.classList.add("selected");
      selectedTime = btn.dataset.value24;
      updateFinalizeButtonState();
    };
    timeContainer.appendChild(btn);
  }
}
// Render inicial (hoje)
renderTimesForDate(new Date());

// Finalizar
function updateFinalizeButtonState(){
  const finalizeBtn=document.getElementById('finalize-btn');
  const ok=currentUnit && selectedDay && selectedTime && selectedBarber && selectedService;
  if(ok) finalizeBtn.removeAttribute('disabled'); else finalizeBtn.setAttribute('disabled','');
}
function finalizeAgendamento(){
  if(!(currentUnit && selectedDay && selectedTime && selectedBarber && selectedService)){
    alert('Selecione todos os campos antes de finalizar.'); return;
  }
  const dd=String(selectedDay.day).padStart(2,'0');
  const mm=String(selectedDay.month+1).padStart(2,'0');
  const yyyy=selectedDay.year;
  const dataFormatada=`${dd}/${mm}/${yyyy}`;
  const barbeiroNome=selectedBarber.textContent.replace(/^👤\s*/,'').trim();
  const barbeiroId=selectedBarber.getAttribute('data-id');
  const servNome=selectedService.querySelector('h6')?selectedService.querySelector('h6').textContent.trim():selectedService.textContent.trim();
  const servId=selectedService.getAttribute('data-id');
  const preco = selectedService.dataset.precoBrl || selectedService.dataset.preco_brl || selectedService.dataset.preco || '';
  const duracao = (selectedService.dataset.durmins || '').toString();
  // Preencher os campos do formulário
  document.getElementById('form-unidade').value = currentUnit;
  document.getElementById('form-data').value = dataFormatada;
  document.getElementById('form-hora').value = selectedTime;
  document.getElementById('form-barbeiro').value = barbeiroNome;
  document.getElementById('form-barbeiro-id').value = barbeiroId;
  document.getElementById('form-servico').value = servNome;
  document.getElementById('form-servico-id').value = servId;
  document.getElementById('form-preco-brl').value = preco;
  document.getElementById('form-duracao').value = duracao;
  // Submeter o formulário
  document.getElementById('form-agendamento').submit();
}
updateFinalizeButtonState();

// Aplicar prefill (se existir): agora após preencher unidade/barbeiro/serviço, vai direto para Data & Hora (passo 3)
window.addEventListener('DOMContentLoaded', () => {
  try {
    if (prefill && (prefill.unidade || prefill.barbeiro || prefill.servico)) {
      if (prefill.unidade && barbersData[prefill.unidade]) {
        currentUnit = prefill.unidade;
        populateBarbers();
        let hasBarber = false;
        if (prefill.barbeiro) {
          const el = Array.from(document.querySelectorAll('#barber-container .card-custom'))
            .find(d => d.getAttribute('data-id') === String(prefill.barbeiro));
          if (el) { selectBarber(el); hasBarber = true; }
        }
        if (prefill.servico) {
          const svcEl = Array.from(document.querySelectorAll('.service-card'))
            .find(s => s.getAttribute('data-id') === String(prefill.servico));
          if (svcEl) { selectService(svcEl); }
        }
        // Avança: esconde passo 1 e, se já tiver barbeiro predefinido, mostra passo 3 (Data & Hora); senão, passo 2
        document.querySelector('[data-step="1"]').classList.add('d-none');
        if (hasBarber) {
          document.querySelector('[data-step="3"]').classList.remove('d-none');
          updateProgress(3);
          // renderiza calendário/horários com base no barbeiro selecionado
          markUnavailableTimes();
        } else {
          document.querySelector('[data-step="2"]').classList.remove('d-none');
          updateProgress(2);
        }
      }
    }
  } catch(e) {}
});

// Desabilita visualmente horários indisponíveis com base em bloqueios do barbeiro e duração do serviço
// util: converte HH:MM:SS => segundos a partir de 00:00
function toSec(hms){
  const [h,m,s] = (hms||'00:00:00').split(':').map(v=>parseInt(v,10));
  return (h*3600)+(m*60)+(isNaN(s)?0:s);
}
// retorna string HH:MM:SS a partir de segundos
function fromSec(total){ const h=Math.floor(total/3600)%24; const m=Math.floor((total%3600)/60); return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:00`; }

async function markUnavailableTimes(){
  try {
    // reset initial state
    document.querySelectorAll('.time-btn').forEach(btn=>{ btn.disabled=false; btn.classList.remove('disabled'); btn.title=''; });
    // precisa ter pelo menos barbeiro e dia; serviço é opcional (assume 30min por padrão)
    if (!selectedBarber || !selectedDay) return;
    const barberId = selectedBarber.getAttribute('data-id');
    let durMins = 30;
    if (selectedService && selectedService.dataset && selectedService.dataset.durmins) {
      durMins = parseInt(selectedService.dataset.durmins, 10) || 30;
    }
    // janela de funcionamento para o dia selecionado
    const biz = businessWindowFor(new Date(selectedDay.year, selectedDay.month, selectedDay.day));
    const closeSec = (biz.closeMin || (21*60)) * 60; // fallback 21:00
    const y = selectedDay.year; const m = String(selectedDay.month+1).padStart(2,'0'); const d = String(selectedDay.day).padStart(2,'0');
    const isoDate = `${y}-${m}-${d}`;
    const resp = await fetch(`includes/disponibilidade_barbeiro.php?barbeiro=${encodeURIComponent(barberId)}&data=${encodeURIComponent(isoDate)}`);
    const data = await resp.json();
    const dayUnavailableBanner = document.getElementById('day-unavailable');
    if (data.dayOff || data.ferias) {
      // Limpa horário antes de desabilitar todos
      document.querySelectorAll('.time-btn').forEach(btn=>{ btn.classList.remove('selected'); btn.disabled=true; btn.classList.add('disabled'); btn.title = data.ferias ? '<?= t('sched.vacation') ?>' : '<?= t('sched.day_off') ?>'; });
      selectedTime = null;
      // Remove seleção da data indisponível para não permitir avanço
      selectedDay = null;
      renderCalendar(currentMonth,currentYear);
      updateFinalizeButtonState();
      if (dayUnavailableBanner) dayUnavailableBanner.classList.remove('d-none');
      return;
    }
    if (dayUnavailableBanner) dayUnavailableBanner.classList.add('d-none');
    const blocks = Array.isArray(data.blocks) ? data.blocks : [];
    const booked = Array.isArray(data.booked) ? data.booked : [];
    if (blocks.length===0 && booked.length===0) return;
    // Intervalo [start,end) sobrepõe [bStart,bEnd)?
    const overlaps = (start, end, b) => {
      const s = toSec(start), e = toSec(end), bs = toSec(b.horaInicio), be = toSec(b.horaFim);
      return !(e <= bs || s >= be);
    };
    let selectedStillValid = true;
    document.querySelectorAll('.time-btn').forEach(btn=>{
  const t = (btn.dataset.value24 || btn.textContent.trim());
      // HH:MM to seconds
      const [hh,mm] = t.split(':').map(x=>parseInt(x,10));
      const start = `${String(hh).padStart(2,'0')}:${String(mm).padStart(2,'0')}:00`;
      // soma duração em minutos de forma precisa
      const end = fromSec((hh*3600) + (mm*60) + durMins*60);
      // fora do horário de funcionamento (fim ultrapassa fechamento)
      const startSec = (hh*3600) + (mm*60);
      const endSec = startSec + durMins*60;
      const afterClose = endSec > closeSec;
      const blockedOverlap = blocks.some(b=>overlaps(start,end,b));
      const bookedOverlap = booked.some(b=>overlaps(start,end,b));
      if (afterClose || blockedOverlap || bookedOverlap) {
        btn.disabled = true; btn.classList.add('disabled');
        if (afterClose) btn.title = '<?= t('sched.outside_hours') ?>';
        else btn.title = bookedOverlap ? '<?= t('sched.already_booked') ?>' : '<?= t('sched.unavailable_time') ?>';
        if (selectedTime === t) { selectedStillValid = false; }
      }
    });
    if (!selectedStillValid) {
      document.querySelectorAll('.time-btn').forEach(b=>b.classList.remove('selected'));
      selectedTime = null;
      updateFinalizeButtonState();
    }
  } catch(e) { /* no-op */ }
}

// Busca e cacheia os dias indisponíveis para o mês atual e barbeiro selecionado
async function fetchMonthDisabledDays(){
  try {
    if (!selectedBarber) return;
    const bId = selectedBarber.getAttribute('data-id');
    const m = currentMonth + 1; const y = currentYear;
    const mKey = `${y}-${String(m).padStart(2,'0')}`;
    // usa cache se já existe
    if (monthDisabledCache.has(mKey)) return;
    const url = `includes/disponibilidade_barbeiro.php?scope=month&barbeiro=${encodeURIComponent(bId)}&year=${y}&month=${m}`;
    const resp = await fetch(url);
    const data = await resp.json();
    const set = new Set(Array.isArray(data.disabledDays) ? data.disabledDays : []);
    monthDisabledCache.set(mKey, set);
  } catch(e) { /* no-op */ }
}
</script>


</body>
<!-- Botão fixo no canto inferior esquerdo -->
<a href="usuario/index_usuario.php" class="btn btn-outline-warning" style="position: fixed; left: 16px; bottom: 16px; z-index: 1050;">
  <i class="bi bi-box-arrow-left"></i> <?= t('sched.back_panel') ?>
</a>
</html>