<?php
include "includes/db.php";
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
<link rel="stylesheet" href="agendamento.css">
</head>
<body class="container py-5">

<div class="progress-container mb-4">
  <div class="step active" id="step1"><div class="circle">1</div><small>Unidade</small></div>
  <div class="step" id="step2"><div class="circle">2</div><small>Data & Hora</small></div>
  <div class="step" id="step3"><div class="circle">3</div><small>Barbeiro</small></div>
  <div class="step" id="step4"><div class="circle">4</div><small>Serviço</small></div>
</div>

<div id="step-content">

  <!-- PASSO 1: Escolha a Unidade (cards) -->
  <div class="card-custom step-card" data-step="1">
    <h5>Escolha a Unidade</h5>
    <div class="d-flex gap-4 justify-content-center flex-wrap">
      <?php foreach($unidades as $unidade): ?>
        <div class="card-custom unit-btn" onclick="selectUnit(<?= $unidade['idUnidade'] ?>, this)">
          <span class="unit-name"><?= htmlspecialchars($unidade['nomeUnidade']) ?></span>
          <span class="unit-address"><?= htmlspecialchars($unidade['endereco']) ?></span>
        </div>
      <?php endforeach; ?>
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

  <!-- PASSO 3: Escolha o Barbeiro (cards filtrados) -->
  <div class="card-custom step-card d-none" data-step="3">
    <h5>Escolha o Barbeiro</h5>
    <div class="d-flex gap-3 justify-content-center flex-wrap" id="barber-container"></div>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button class="btn-custom" onclick="prevStep(3)">← Voltar</button>
      <button class="btn-custom" onclick="nextStep(3)">Continuar</button>
    </div>
  </div>

  <div class="card-custom step-card d-none" data-step="4">
    <h5>Escolha o Serviço</h5>
    <div class="service-container">
      <?php foreach($servicos as $serv): ?>
        <div class="service-card" data-id="<?= $serv['idServico'] ?>" onclick="selectService(this)">
          <h6><?= htmlspecialchars($serv['nomeServico']) ?></h6>
          <small>R$ <?= number_format($serv['precoServico'],2,',','.') ?> | <?= $serv['duracaoPadrao'] ?>min</small>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-center gap-3 mt-3 align-items-center" style="gap: 16px;">
      <button class="btn-custom btn-back" onclick="prevStep(4)">← Voltar</button>
      <form id="form-agendamento" method="POST" action="resumo.php" class="d-flex align-items-center" style="gap: 16px;">
        <input type="hidden" name="unidade" id="form-unidade">
        <input type="hidden" name="data" id="form-data">
        <input type="hidden" name="hora" id="form-hora">
        <input type="hidden" name="barbeiro" id="form-barbeiro">
        <input type="hidden" name="barbeiro_id" id="form-barbeiro-id">
  <input type="hidden" name="servico" id="form-servico">
  <input type="hidden" name="servico_id" id="form-servico-id">
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

// Barbeiros reais por unidade (do PHP)
const barbersData = <?php echo json_encode($barbeiros); ?>;

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
  if (!currentUnit || !barbersData[currentUnit]) {
    container.innerHTML = '<div class="text-warning">Selecione uma unidade para ver os barbeiros disponíveis.</div>';
    return;
  }
  barbersData[currentUnit].forEach(barb => {
    const div = document.createElement('div');
    div.className = 'card-custom p-3';
    div.textContent = '👤 ' + barb.nomeBarbeiro;
    div.setAttribute('data-id', barb.idBarbeiro);
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
  const barbeiroId=selectedBarber.getAttribute('data-id');
  const servNome=selectedService.querySelector('h6')?selectedService.querySelector('h6').textContent.trim():selectedService.textContent.trim();
  const servId=selectedService.getAttribute('data-id');
  const servInfo=selectedService.querySelector('small')?selectedService.querySelector('small').textContent.trim():'';
  let preco='', duracao='';
  if(servInfo){ const parts=servInfo.split('|'); preco=parts[0]?parts[0].trim():'', duracao=parts[1]?parts[1].trim():''; }
  // Preencher os campos do formulário
  document.getElementById('form-unidade').value = currentUnit;
  document.getElementById('form-data').value = dataFormatada;
  document.getElementById('form-hora').value = selectedTime;
  document.getElementById('form-barbeiro').value = barbeiroNome;
  document.getElementById('form-barbeiro-id').value = barbeiroId;
  document.getElementById('form-servico').value = servNome;
  document.getElementById('form-servico-id').value = servId;
  document.getElementById('form-preco').value = preco;
  document.getElementById('form-duracao').value = duracao;
  // Submeter o formulário
  document.getElementById('form-agendamento').submit();
}
updateFinalizeButtonState();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const unidadeSelect = document.getElementById('select-unidade');
  const barbeiroSelect = document.getElementById('select-barbeiro');
  unidadeSelect.addEventListener('change', function() {
    const unidadeId = this.value;
    barbeiroSelect.innerHTML = '<option value="">Carregando barbeiros...</option>';
    barbeiroSelect.disabled = true;
    if (!unidadeId) {
      barbeiroSelect.innerHTML = '<option value="">Selecione a unidade primeiro</option>';
      barbeiroSelect.disabled = true;
      return;
    }
    fetch('includes/barbeiros_por_unidade.php?unidade_id=' + unidadeId)
      .then(resp => resp.json())
      .then(data => {
        barbeiroSelect.innerHTML = '';
        if (data.length === 0) {
          barbeiroSelect.innerHTML = '<option value="">Nenhum barbeiro disponível</option>';
        } else {
          barbeiroSelect.innerHTML = '<option value="">Selecione o barbeiro</option>';
          data.forEach(barbeiro => {
            barbeiroSelect.innerHTML += `<option value="${barbeiro.idBarbeiro}">${barbeiro.nomeBarbeiro}</option>`;
          });
        }
        barbeiroSelect.disabled = false;
      })
      .catch(() => {
        barbeiroSelect.innerHTML = '<option value="">Erro ao carregar barbeiros</option>';
        barbeiroSelect.disabled = true;
      });
  });
});
</script>

</body>
</html>