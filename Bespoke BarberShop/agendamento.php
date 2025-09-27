<?php
// Arquivo convertido de HTML para PHP
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendamento - Barbearia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
<style>
   body {
    background-color: #0d0d0d;
    color: white;
  }
  .step-card { max-width: 850px; margin: 0 auto; }
  .card-custom {
    background-color: #111;
    border-radius: 15px;
    padding: 15px;
    box-shadow: 0 0 10px rgba(255,204,0,0.4);
    margin-bottom: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .card-custom:hover { transform: scale(1.03); box-shadow: 0 0 18px rgba(255,204,0,0.7); }
  .card-custom.selected { border: 2px solid #ff9900; background-color: #222; }
  .card-custom h5 { color: #ffcc00; font-weight: 600; margin-bottom: 10px; font-size: 1.3rem; }
  .unit-name { font-weight: 600; font-size: 1.1rem; }
  .unit-address { font-size: 0.9rem; color: #ccc; margin-top: 4px; }
  .btn-custom {
    background-color: transparent;
    border: 1px solid #ffcc00;
    color: #ffcc00;
    border-radius: 10px;
    padding: 6px 15px;
    transition: 0.3s;
    font-size: 1rem;
  }
  .btn-custom:hover { background-color: #ffcc00; color: black; transform: scale(1.05); }
  .btn-back { margin-top: 15px; font-size: 0.9rem; }
  .btn-custom[disabled] { opacity: 0.5; cursor: not-allowed; transform: none; }
  .progress-container { display: flex; justify-content: space-between; margin-bottom: 30px; }
  .step { text-align: center; flex: 1; position: relative; }
  .step .circle {
    width: 35px; height: 35px; border-radius: 50%;
    background: #111; border: 2px solid #ffcc00;
    color: #ffcc00; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 8px; font-size: 1rem; font-weight: bold; transition: 0.3s;
  }
  .step.active .circle, .step.completed .circle { background: #ffcc00; color: black; }
  .step::after {
    content: "";
    position: absolute; top: 17px; left: 50%;
    width: 100%; height: 2px; background: #555; z-index: -1;
  }
  .step:last-child::after { display: none; }
  .step.completed::after { background: #ffcc00; }

  /* Calendário */
  .calendar { width: 320px; padding: 15px; border-radius: 12px; background: #1a1a1a; color: white; font-size: 0.9rem; }
  .calendar-header { display: flex; justify-content: space-between; align-items: center; color: #ffcc00; font-weight: 600; font-size: 1.1rem; margin-bottom: 10px; }
  .month-year { display: flex; align-items: center; gap: 5px; }
  .change-btn { cursor: pointer; padding: 3px 6px; border-radius: 5px; transition: 0.3s; color: #ffcc00; font-size: 1rem; }
  .change-btn:hover { background: #ffcc00; color: black; }
  .calendar-week-day, .calendar-day { display: grid; grid-template-columns: repeat(7,1fr); text-align: center; gap: 3px; }
  .calendar-week-day div { font-weight: 600; color: #ffcc00; font-size: 0.8rem; }
  .calendar-day div {
    padding: 10px; border-radius: 5px; background: #111; font-size: 0.9rem; cursor: pointer; transition: all 0.3s;
  }
  .calendar-day div:hover { background: #ffcc00; color: black; font-weight: 600; transform: scale(1.05); }
  .current-date { background: #ffcc00 !important; color: black !important; font-weight: 600; }
  .selected-date { background: #ff9900 !important; color: black !important; font-weight: 600; }

  /* Horários */
  .time-btn { border: 1px solid #ffcc00; border-radius: 8px; padding: 8px 12px; background: transparent; color: white; margin: 4px 0; font-size: 1rem; transition: all 0.3s; cursor: pointer; width: 100px; text-align: center; display: block; }
  .time-btn:hover { background: #ffcc00; color: black; transform: scale(1.05); }
  .time-btn.selected { background: #ff9900; color: black; font-weight: 600; }
  .time-scroll { max-height: 320px; overflow-y: auto; overflow-x: hidden; padding-right: 6px; }

  /* Serviços */
  .service-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; }
  .service-card {
    background: #1a1a1a; border: 1px solid #ffcc00; border-radius: 10px; padding: 15px; font-size: 0.95rem; color: white; cursor: pointer; transition: all 0.3s; width: 180px; text-align: center;
  }
  .service-card:hover { transform: scale(1.05); box-shadow: 0 0 15px rgba(255,204,0,0.6); }
  .service-card.selected { border: 2px solid #ff9900; background-color: #222; }
  .service-card h6 { color: #ffcc00; margin-bottom: 5px; font-size: 1rem; font-weight: 600; }
  .service-card small { display: block; font-size: 0.85rem; color: #ccc; }

  /* Unidades quadradas */
  .unit-btn { width: 180px; height: 180px; color: #ffcc00 ; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 1.2rem; margin: 0 auto; }
  .unit-btn .address { font-size: 0.8rem; color: #ccc; margin-top: 6px; }

  @media (max-width: 900px) {
    .calendar { width: 100%; }
    .time-scroll { width: 100%; max-height: 200px; }
    .service-card, .unit-btn { width: 140px; height: auto; }
    .step-card { padding: 12px; }
  }
</style>
</head>
<body class="container py-5">

<div class="progress-container mb-4">
  <div class="step active" id="step1"><div class="circle">1</div><small>Unidade</small></div>
  <div class="step" id="step2"><div class="circle">2</div><small>Data & Hora</small></div>
  <div class="step" id="step3"><div class="circle">3</div><small>Barbeiro</small></div>
  <div class="step" id="step4"><div class="circle">4</div><small>Serviço</small></div>
</div>

<div id="step-content">

  <div class="card-custom step-card" data-step="1">
    <h5>Escolha a Unidade</h5>
    <div class="d-flex gap-4 justify-content-center flex-wrap">
      <div class="card-custom unit-btn" onclick="selectUnit('matilde', this)">
        <span class="unit-name">Vila Matilde</span>
        <span class="unit-address">R. José Mascarenhas, 861</span>
      </div>
      <div class="card-custom unit-btn" onclick="selectUnit('carrao', this)">
        <span class="unit-name">Vila Carrão</span>
        <span class="unit-address">R. João Vieira Prioste, 785</span>
      </div>
    </div>
  </div>

  <div class="card-custom step-card d-none" data-step="2">
    <h5>Escolha a Data e o Horário</h5>
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
        <div class="calendar-week-day">
          <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
        </div>
        <div class="calendar-day" id="calendar-days"></div>
      </div>

      <div class="time-scroll">
        <div id="time-buttons-container"></div>
      </div>
    </div>
    <button class="btn-custom mt-3" onclick="nextStep(2)">Continuar</button>
    <button class="btn-custom btn-back" onclick="prevStep(2)">← Voltar</button>
  </div>

  <div class="card-custom step-card d-none" data-step="3">
    <h5>Escolha o Barbeiro</h5>
    <div class="d-flex gap-3 justify-content-center flex-wrap" id="barber-container"></div>
    <button class="btn-custom mt-3" onclick="nextStep(3)">Continuar</button>
    <button class="btn-custom btn-back" onclick="prevStep(3)">← Voltar</button>
  </div>

  <div class="card-custom step-card d-none" data-step="4">
    <h5>Escolha o Serviço</h5>
    <div class="service-container">
      <div class="service-card" onclick="selectService(this)"><h6>Corte</h6><small>R$ 35,00 | 30min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Barba</h6><small>R$ 25,00 | 20min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Corte + Barba</h6><small>R$ 55,00 | 50min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Sobrancelha</h6><small>R$ 15,00 | 10min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Luzes</h6><small>R$ 120,00 | 150min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Pigmentação</h6><small>R$ 80,00 | 40min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Hidratação</h6><small>R$ 60,00 | 30min</small></div>
      <div class="service-card" onclick="selectService(this)"><h6>Limpeza de Pele</h6><small>R$ 50,00 | 30min</small></div>
    </div>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button class="btn-custom btn-back" onclick="prevStep(4)">← Voltar</button>
      <form id="form-agendamento" method="POST" action="resumo.php" style="display:inline;">
        <input type="hidden" name="unidade" id="form-unidade">
        <input type="hidden" name="data" id="form-data">
        <input type="hidden" name="hora" id="form-hora">
        <input type="hidden" name="barbeiro" id="form-barbeiro">
        <input type="hidden" name="servico" id="form-servico">
        <input type="hidden" name="preco" id="form-preco">
        <input type="hidden" name="duracao" id="form-duracao">
        <button id="finalize-btn" class="btn-custom" disabled type="button" onclick="finalizeAgendamento()">Finalizar Agendamento</button>
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

// Barbeiros reais por unidade
const barbersData = {
  'matilde':['Carlos Silva','Ricardo Mendes','João Pedro'],
  'carrao':['Felipe Santos','André Oliveira','Lucas Ferreira']
};

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
}
function selectService(el){
  if(selectedService) selectedService.classList.remove('selected');
  el.classList.add('selected');
  selectedService = el;
  updateFinalizeButtonState();
}
function populateBarbers(){
  const container = document.getElementById('barber-container');
  container.innerHTML = '';
  const list = barbersData[currentUnit] || [];
  list.forEach(name=>{
    const div = document.createElement('div');
    div.className = 'card-custom p-3';
    div.textContent = '👤 ' + name;
    div.onclick = ()=>selectBarber(div);
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

const months = ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"];
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
    if(isPast){ dayEl.style.opacity="0.4"; dayEl.style.cursor="not-allowed"; } 
    else { dayEl.addEventListener("click", ()=>{ selectedDay={day,month,year}; renderCalendar(month,year); updateFinalizeButtonState(); }); }
    if(selectedDay && day===selectedDay.day && month===selectedDay.month && year===selectedDay.year) dayEl.classList.add("selected-date");
    if(day===today.getDate() && month===today.getMonth() && year===today.getFullYear()) dayEl.classList.add("current-date");
    daysContainer.appendChild(dayEl);
  }
}
prevMonth.addEventListener("click", ()=>{
  if(currentYear < today.getFullYear() || (currentYear === today.getFullYear() && currentMonth <= today.getMonth())) return;
  currentMonth--; if(currentMonth<0){currentMonth=11; currentYear--;}
  renderCalendar(currentMonth,currentYear);
});
nextMonth.addEventListener("click", ()=>{
  currentMonth++; if(currentMonth>11){currentMonth=0; currentYear++;}
  renderCalendar(currentMonth,currentYear);
});
renderCalendar(currentMonth,currentYear);

// Horários até 21:00
const timeContainer = document.getElementById("time-buttons-container");
function generateTimes(){
  timeContainer.innerHTML="";
  for(let hour=9; hour<=21; hour++){
    ["00","30"].forEach(min=>{
      const btn = document.createElement("button");
      btn.className = "time-btn";
      btn.textContent = `${hour}:${min}`;
      btn.onclick = ()=>{
        document.querySelectorAll(".time-btn").forEach(b=>b.classList.remove("selected"));
        btn.classList.add("selected");
        selectedTime = btn.textContent;
        updateFinalizeButtonState();
      };
      timeContainer.appendChild(btn);
    });
  }
}
generateTimes();

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
  const servNome=selectedService.querySelector('h6')?selectedService.querySelector('h6').textContent.trim():selectedService.textContent.trim();
  const servInfo=selectedService.querySelector('small')?selectedService.querySelector('small').textContent.trim():'';
  let preco='', duracao='';
  if(servInfo){ const parts=servInfo.split('|'); preco=parts[0]?parts[0].trim():'', duracao=parts[1]?parts[1].trim():''; }
  // Preencher os campos do formulário
  document.getElementById('form-unidade').value = currentUnit;
  document.getElementById('form-data').value = dataFormatada;
  document.getElementById('form-hora').value = selectedTime;
  document.getElementById('form-barbeiro').value = barbeiroNome;
  document.getElementById('form-servico').value = servNome;
  document.getElementById('form-preco').value = preco;
  document.getElementById('form-duracao').value = duracao;
  // Submeter o formulário
  document.getElementById('form-agendamento').submit();
}
updateFinalizeButtonState();
</script>

</body>
</html>