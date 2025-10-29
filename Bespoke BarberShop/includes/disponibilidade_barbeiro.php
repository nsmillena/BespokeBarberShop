<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
try { $bd = new Banco(); $conn = $bd->getConexao(); } catch(Exception $e){ http_response_code(500); echo json_encode(['error'=>'DB error']); exit; }
$barbeiroId = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;
$scope = $_GET['scope'] ?? 'day';

if ($barbeiroId <= 0) { echo json_encode(['error'=>'invalid']); exit; }

// Escopo mensal: retorna dias indisponíveis (folga semanal no período + férias)
if ($scope === 'month') {
	$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
	$month = isset($_GET['month']) ? (int)$_GET['month'] : 0; // 1-12
	if ($year < 2000 || $month < 1 || $month > 12) { echo json_encode(['disabledDays'=>[]]); exit; }
	$first = sprintf('%04d-%02d-01', $year, $month);
	$daysInMonth = (int)date('t', strtotime($first));
	$last = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

	// Carrega regras de folga semanal e férias do barbeiro
	$folgas = [];
	$qf = $conn->prepare("SELECT weekday, inicio, fim FROM FolgaSemanal WHERE Barbeiro_idBarbeiro=? AND (fim IS NULL OR fim >= ?) AND inicio <= ?");
	$qf->bind_param('iss', $barbeiroId, $first, $last);
	$qf->execute(); $rf = $qf->get_result();
	while($row=$rf->fetch_assoc()){ $folgas[]=$row; }
	$qf->close();

	$ferias = [];
	$qv = $conn->prepare("SELECT inicio, fim FROM FeriasBarbeiro WHERE Barbeiro_idBarbeiro=? AND NOT (fim < ? OR inicio > ?)");
	$qv->bind_param('iss', $barbeiroId, $first, $last);
	$qv->execute(); $rv = $qv->get_result();
	while($row=$rv->fetch_assoc()){ $ferias[]=$row; }
	$qv->close();

	$disabled = [];
	for ($d=1; $d <= $daysInMonth; $d++) {
		$dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
		$dow = (int)date('w', strtotime($dateStr)); // 0=Dom..6=Sab
		// DAYOFWEEK em MySQL: 1=Dom..7=Sab — vamos converter
		$mysqlDow = $dow + 1;
		$isOff = false;
		foreach($folgas as $f){
			$ini = $f['inicio']; $fim = $f['fim'];
			if ((int)$f['weekday'] === $mysqlDow && $ini <= $dateStr && (empty($fim) || $fim >= $dateStr)) { $isOff = true; break; }
		}
		if ($isOff) { $disabled[] = $d; continue; }
		$inFerias = false;
		foreach($ferias as $v){ if ($v['inicio'] <= $dateStr && $v['fim'] >= $dateStr) { $inFerias = true; break; } }
		if ($inFerias) { $disabled[] = $d; continue; }
	}
	echo json_encode(['disabledDays'=>$disabled]);
	exit;
}

$data = $_GET['data'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { echo json_encode(['dayOff'=>false,'ferias'=>false,'blocks'=>[]]); exit; }

$dayOff = false; $ferias = false; $blocks = []; $booked = [];

// Folga semanal ativa neste dia
$qf = $conn->prepare("SELECT 1 FROM FolgaSemanal WHERE Barbeiro_idBarbeiro=? AND weekday = DAYOFWEEK(?) AND inicio <= ? AND (fim IS NULL OR fim >= ?) LIMIT 1");
$qf->bind_param('isss', $barbeiroId, $data, $data, $data);
$qf->execute(); $qf->store_result(); $dayOff = $qf->num_rows > 0; $qf->close();

// Férias
$qv = $conn->prepare("SELECT 1 FROM FeriasBarbeiro WHERE Barbeiro_idBarbeiro=? AND inicio <= ? AND fim >= ? LIMIT 1");
$qv->bind_param('iss', $barbeiroId, $data, $data);
$qv->execute(); $qv->store_result(); $ferias = $qv->num_rows > 0; $qv->close();

// Bloqueios pontuais (para hoje, ignora bloqueios que já terminaram)
$isToday = ($data === date('Y-m-d'));
if ($isToday) {
	$qb = $conn->prepare("SELECT horaInicio, horaFim FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? AND horaFim > CURTIME() ORDER BY horaInicio");
	$qb->bind_param('is', $barbeiroId, $data);
} else {
	$qb = $conn->prepare("SELECT horaInicio, horaFim FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? ORDER BY horaInicio");
	$qb->bind_param('is', $barbeiroId, $data);
}
$qb->execute(); $rb = $qb->get_result();
while($row = $rb->fetch_assoc()){ $blocks[] = $row; }
$qb->close();

// Agendamentos já marcados (intervalos ocupados)
$qa = $conn->prepare("SELECT a.hora AS horaInicio, ADDTIME(a.hora, SEC_TO_TIME(SUM(ahs.tempoEstimado)*60)) AS horaFim FROM Agendamento a JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento WHERE a.Barbeiro_idBarbeiro=? AND a.data=? AND a.statusAgendamento='Agendado' GROUP BY a.idAgendamento, a.hora ORDER BY a.hora");
$qa->bind_param('is', $barbeiroId, $data);
$qa->execute(); $ra = $qa->get_result();
while($row = $ra->fetch_assoc()){ $booked[] = $row; }
$qa->close();

echo json_encode(['dayOff'=>$dayOff,'ferias'=>$ferias,'blocks'=>$blocks,'booked'=>$booked]);
