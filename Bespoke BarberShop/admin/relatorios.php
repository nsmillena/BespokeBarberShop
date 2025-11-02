<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
include_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = $_SESSION['usuario_id'];

// Buscar unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();
if (empty($unidade_id)) {
    header('Location: index_admin.php?ok=0&msg=' . urlencode(t('admin.not_linked_unit')));
    exit;
}

// Filtros
$hoje = date('Y-m-d');
$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-t');
$inicio = isset($_GET['inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['inicio']) ? $_GET['inicio'] : $defaultStart;
$fim = isset($_GET['fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fim']) ? $_GET['fim'] : $defaultEnd;
$status = isset($_GET['status']) ? $_GET['status'] : 'Finalizado';
$permitidos = ['Agendado','Cancelado','Finalizado','Todos'];
if (!in_array($status, $permitidos)) { $status = 'Todos'; }
$barbeiro = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Barber list for filter (unit only)
$barbeiros = [];
$qb = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade=? ORDER BY nomeBarbeiro");
$qb->bind_param("i", $unidade_id);
$qb->execute();
$rb = $qb->get_result();
while ($row = $rb->fetch_assoc()) { $barbeiros[] = $row; }
$qb->close();

// Build WHERE
$where = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ?";
$params = [$unidade_id, $inicio, $fim];
$types = "iss";
if ($status !== 'Todos') { $where .= " AND a.statusAgendamento = ?"; $params[] = $status; $types .= "s"; }
if ($barbeiro > 0) { $where .= " AND a.Barbeiro_idBarbeiro = ?"; $params[] = $barbeiro; $types .= "i"; }

$sqlRep = "
SELECT a.idAgendamento, a.data, a.hora, a.statusAgendamento,
       c.nomeCliente, b.nomeBarbeiro,
       GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
       SUM(ahs.precoFinal) AS precoTotal,
       SUM(ahs.tempoEstimado) AS tempoTotal
FROM Agendamento a
JOIN Cliente c ON c.idCliente = a.Cliente_idCliente
JOIN Barbeiro b ON b.idBarbeiro = a.Barbeiro_idBarbeiro
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
JOIN Servico s ON s.idServico = ahs.Servico_idServico
WHERE $where
GROUP BY a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente, b.nomeBarbeiro
ORDER BY a.data ASC, a.hora ASC
";

$stmt = $conn->prepare($sqlRep);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
$totQtd = 0; $totValor = 0.0; $totTempo = 0;
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $totQtd++;
    $totValor += (float)$r['precoTotal'];
    $totTempo += (int)$r['tempoTotal'];
}
$stmt->close();

// KPIs profissionais: considerar apenas FINALIZADOS (independente do filtro de status),
// mas respeitar intervalo de datas e barbeiro selecionado
$sumWhere = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ? AND a.statusAgendamento = 'Finalizado'";
$sumTypes = "iss";
$sumParams = [$unidade_id, $inicio, $fim];
if ($barbeiro > 0) { $sumWhere .= " AND a.Barbeiro_idBarbeiro = ?"; $sumTypes .= "i"; $sumParams[] = $barbeiro; }

$sqlSum = "
SELECT COUNT(DISTINCT a.idAgendamento) AS qtd,
       COALESCE(SUM(ahs.precoFinal),0) AS receita,
       COALESCE(SUM(ahs.tempoEstimado),0) AS tempo
FROM Agendamento a
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
WHERE $sumWhere
";
$stSum = $conn->prepare($sqlSum);
$stSum->bind_param($sumTypes, ...$sumParams);
$stSum->execute();
$stSum->bind_result($kpiQtd, $kpiReceita, $kpiTempo);
$stSum->fetch();
$stSum->close();

// Use shared bb_format_minutes from includes/helpers.php

// Dados para gráficos (sempre Finalizados)
// Receita por dia
$wDaily = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ? AND a.statusAgendamento = 'Finalizado'";
$tDaily = "iss"; $pDaily = [$unidade_id, $inicio, $fim];
if ($barbeiro > 0) { $wDaily .= " AND a.Barbeiro_idBarbeiro = ?"; $tDaily .= "i"; $pDaily[] = $barbeiro; }
$sqlDaily = "
SELECT a.data, COALESCE(SUM(ahs.precoFinal),0) AS receita, COUNT(DISTINCT a.idAgendamento) AS qtd
FROM Agendamento a
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
WHERE $wDaily
GROUP BY a.data
ORDER BY a.data ASC
";
$stDaily = $conn->prepare($sqlDaily);
$stDaily->bind_param($tDaily, ...$pDaily);
$stDaily->execute();
$rDaily = $stDaily->get_result();
$labelsDaily = []; $dataDaily = []; $countDaily = [];
while ($row = $rDaily->fetch_assoc()){
    $labelsDaily[] = date('d/m', strtotime($row['data']));
    $dataDaily[] = (float)$row['receita'];
    $countDaily[] = (int)$row['qtd'];
}
$stDaily->close();

// Receita por barbeiro
$wBarber = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ? AND a.statusAgendamento = 'Finalizado'";
$tBarber = "iss"; $pBarber = [$unidade_id, $inicio, $fim];
if ($barbeiro > 0) { $wBarber .= " AND a.Barbeiro_idBarbeiro = ?"; $tBarber .= "i"; $pBarber[] = $barbeiro; }
$sqlBarbers = "
SELECT b.nomeBarbeiro, b.idBarbeiro, COALESCE(SUM(ahs.precoFinal),0) AS receita, COUNT(DISTINCT a.idAgendamento) AS qtd
FROM Agendamento a
JOIN Barbeiro b ON b.idBarbeiro = a.Barbeiro_idBarbeiro
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
WHERE $wBarber
GROUP BY b.idBarbeiro, b.nomeBarbeiro
ORDER BY receita DESC
";
$stBar = $conn->prepare($sqlBarbers);
$stBar->bind_param($tBarber, ...$pBarber);
$stBar->execute();
$rBar = $stBar->get_result();
$labelsBar = []; $dataBar = []; $countBar = [];
while ($row = $rBar->fetch_assoc()){
    $labelsBar[] = $row['nomeBarbeiro'];
    $dataBar[] = (float)$row['receita'];
    $countBar[] = (int)$row['qtd'];
}
$stBar->close();

// Top serviços por receita (Finalizados)
$wSrv = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ? AND a.statusAgendamento = 'Finalizado'";
$tSrv = "iss"; $pSrv = [$unidade_id, $inicio, $fim];
if ($barbeiro > 0) { $wSrv .= " AND a.Barbeiro_idBarbeiro = ?"; $tSrv .= "i"; $pSrv[] = $barbeiro; }
$sqlSrv = "
SELECT s.nomeServico, s.idServico, COALESCE(SUM(ahs.precoFinal),0) AS receita
FROM Agendamento a
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
JOIN Servico s ON s.idServico = ahs.Servico_idServico
WHERE $wSrv
GROUP BY s.idServico, s.nomeServico
ORDER BY receita DESC
LIMIT 8
";
$stSrv = $conn->prepare($sqlSrv);
$stSrv->bind_param($tSrv, ...$pSrv);
$stSrv->execute();
$rSrv = $stSrv->get_result();
$labelsSrv = []; $dataSrv = [];
while ($row = $rSrv->fetch_assoc()){
    $labelsSrv[] = $row['nomeServico'];
    $dataSrv[] = (float)$row['receita'];
}
$stSrv->close();

// Ajuste opcional de moeda nos gráficos: se EN, converter valores para USD (mesmo fixo da UI)
if (function_exists('bb_is_en') && function_exists('bb_amount_for_locale') && bb_is_en()) {
    foreach ($dataDaily as $i => $val) { $dataDaily[$i] = (float)bb_amount_for_locale((float)$val); }
    foreach ($dataBar as $i => $val) { $dataBar[$i] = (float)bb_amount_for_locale((float)$val); }
    foreach ($dataSrv as $i => $val) { $dataSrv[$i] = (float)bb_amount_for_locale((float)$val); }
}

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_agendamentos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        t('sched.date'), t('sched.time'), t('barber.client'), t('sched.barber'), t('sched.service'), t('user.total'), t('sched.duration'), t('user.status')
    ]);
    foreach ($rows as $r) {
        // Localize service names in CSV as well
        $srvCsv = (string)($r['servicos'] ?? '');
        $srvArr = array_map('trim', explode(',', $srvCsv));
        $srvArr = array_filter($srvArr, fn($s)=>$s!=='');
        $srvArrLoc = array_map('bb_service_display', $srvArr);
        $srvCsvLoc = implode(', ', $srvArrLoc);
        fputcsv($out, [
            bb_format_date($r['data']),
            bb_format_time($r['hora']),
            $r['nomeCliente'],
            $r['nomeBarbeiro'],
            $srvCsvLoc,
            // Exporta sempre em BRL por compatibilidade histórica
            number_format($r['precoTotal'], 2, ',', '.'),
            bb_format_minutes((int)$r['tempoTotal']),
            $r['statusAgendamento']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('admin.reports_title') ?> - Admin</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="dashboard-admin">
<div class="container py-5">
    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="dashboard-title mb-0"><i class="bi bi-graph-up"></i> <?= t('admin.reports_title') ?></h2>
            <div class="d-flex gap-2">
                <a class="dashboard-action" href="index_admin.php"><i class="bi bi-arrow-left"></i> <?= t('common.back') ?></a>
                <a class="dashboard-action" href="?inicio=<?= urlencode($inicio) ?>&fim=<?= urlencode($fim) ?>&status=<?= urlencode($status) ?>&barbeiro=<?= (int)$barbeiro ?>&export=csv"><i class="bi bi-download"></i> <?= t('admin.export_csv') ?></a>
            </div>
        </div>
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-sm-6 col-md-3">
                <label class="form-label"><?= t('admin.start') ?></label>
                <input type="date" name="inicio" class="form-control" value="<?= htmlspecialchars($inicio) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label"><?= t('admin.end') ?></label>
                <input type="date" name="fim" class="form-control" value="<?= htmlspecialchars($fim) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label"><?= t('admin.status') ?></label>
                <select name="status" class="form-select">
                    <?php foreach ($permitidos as $opt): ?>
                        <option value="<?= $opt ?>" <?= $status===$opt?'selected':'' ?>><?= htmlspecialchars(bb_status_label($opt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label"><?= t('admin.choose_barber') ?></label>
                <select name="barbeiro" class="form-select">
                    <option value="0"><?= t('admin.all') ?></option>
                    <?php foreach ($barbeiros as $b): ?>
                        <option value="<?= (int)$b['idBarbeiro'] ?>" <?= $barbeiro===(int)$b['idBarbeiro']?'selected':'' ?>><?= htmlspecialchars($b['nomeBarbeiro']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="dashboard-action"><i class="bi bi-funnel"></i> <?= t('admin.apply_filters') ?></button>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dashboard-section-title mb-0"><i class="bi bi-wallet2"></i> <?= t('admin.summary_finished') ?></div>
        </div>
        <div class="row g-3 kpi-wrap">
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-top"><i class="bi bi-people kpi-icon"></i><span class="kpi-label"><?= t('admin.appointments') ?></span></div>
                    <div class="kpi-value"><?= (int)$kpiQtd ?></div>
                    <div class="kpi-sub"><?= t('admin.period') ?> <?= bb_format_date($inicio) ?> - <?= bb_format_date($fim) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-top"><i class="bi bi-cash-coin kpi-icon"></i><span class="kpi-label"><?= t('admin.kpi_revenue') ?></span></div>
                    <div class="kpi-value"><?= bb_format_currency_local((float)$kpiReceita) ?></div>
                    <div class="kpi-sub"><?= t('admin.avg_ticket') ?>: <?= ((int)$kpiQtd>0? bb_format_currency_local((float)$kpiReceita/(int)$kpiQtd) : '—') ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card">
                    <div class="kpi-top"><i class="bi bi-clock-history kpi-icon"></i><span class="kpi-label"><?= t('admin.total_duration') ?></span></div>
                    <div class="kpi-value"><?= bb_format_minutes($kpiTempo) ?></div>
                    <div class="kpi-sub"><?= t('admin.avg_duration') ?> <?= ((int)$kpiQtd>0? bb_format_minutes(round($kpiTempo/$kpiQtd)) : '—') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="dashboard-section-title mb-0"><i class="bi bi-activity"></i> <?= t('admin.charts_finalized') ?></div>
        </div>
        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="chart-card">
                    <div class="mb-1" style="color:#f0d58a; font-weight:600;"><?= t('admin.chart_revenue_by_day') ?></div>
                    <?php if (!empty($labelsDaily)): ?>
                        <canvas id="chartDailyRevenue" class="chart-canvas"></canvas>
                    <?php else: ?>
                        <div class="chart-empty"><?= t('admin.no_chart_data') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="chart-card">
                    <div class="mb-1" style="color:#f0d58a; font-weight:600;"><?= t('admin.chart_revenue_by_barber') ?></div>
                    <?php if (!empty($labelsBar)): ?>
                        <canvas id="chartBarberRevenue" class="chart-canvas"></canvas>
                    <?php else: ?>
                        <div class="chart-empty"><?= t('admin.no_chart_data') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="chart-card">
                    <div class="mb-1" style="color:#f0d58a; font-weight:600;"><?= t('admin.chart_top_services') ?></div>
                    <?php if (!empty($labelsSrv)): ?>
                        <canvas id="chartServiceTop" class="chart-canvas"></canvas>
                    <?php else: ?>
                        <div class="chart-empty"><?= t('admin.no_chart_data') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle dashboard-table">
                <thead>
                    <tr>
                        <th><?= t('sched.date') ?></th>
                        <th><?= t('sched.time') ?></th>
                        <th><?= t('barber.client') ?></th>
                        <th><?= t('sched.barber') ?></th>
                        <th><?= t('sched.service') ?></th>
                        <th><?= t('user.total') ?></th>
                        <th><?= t('sched.duration') ?></th>
                        <th><?= t('user.status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows)>0): foreach ($rows as $row): ?>
                        <tr>
                            <td><?= bb_format_date($row['data']) ?></td>
                            <td><?= bb_format_time($row['hora']) ?></td>
                            <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                            <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                            <?php
                                $tblSrvCsv = (string)($row['servicos'] ?? '');
                                $tblSrvArr = array_map('trim', explode(',', $tblSrvCsv));
                                $tblSrvArr = array_filter($tblSrvArr, fn($s)=>$s!=='');
                                $tblSrvArrLoc = array_map('bb_service_display', $tblSrvArr);
                                $tblSrvCsvLoc = implode(', ', $tblSrvArrLoc);
                            ?>
                            <td title="<?= htmlspecialchars($tblSrvCsvLoc) ?>"><?= htmlspecialchars(strlen($tblSrvCsvLoc)>32 ? substr($tblSrvCsvLoc,0,32).'…' : $tblSrvCsvLoc) ?></td>
                            <td><?= bb_format_currency_local((float)$row['precoTotal']) ?></td>
                            <td><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                            <td><?= htmlspecialchars(bb_status_label($row['statusAgendamento'])) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-center text-muted"><?= t('common.no_data') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php @include_once("../Footer/footer.html"); ?>
<script src="../js/date-mask.js"></script>
<script>
// Dados dos gráficos vindos do PHP
const labelsDaily = <?= json_encode($labelsDaily ?? []) ?>;
const dataDaily = <?= json_encode($dataDaily ?? []) ?>;
const labelsBar = <?= json_encode($labelsBar ?? []) ?>;
const dataBar = <?= json_encode($dataBar ?? []) ?>;
const countDaily = <?= json_encode($countDaily ?? []) ?>;
// Localize service labels for the chart
<?php
    $labelsSrvLoc = [];
    foreach (($labelsSrv ?? []) as $ls) { $labelsSrvLoc[] = bb_service_display($ls); }
?>
const labelsSrv = <?= json_encode($labelsSrvLoc) ?>;
const dataSrv = <?= json_encode($dataSrv ?? []) ?>;

const isEN = <?= bb_is_en() ? 'true' : 'false' ?>;
const moneyFmt = new Intl.NumberFormat(isEN ? 'en-US' : 'pt-BR', { style: 'currency', currency: isEN ? 'USD' : 'BRL' });
const commonScales = {
    x: { ticks: { color: 'rgba(255,255,255,.8)' }, grid: { color: 'rgba(255,255,255,.08)' } },
    y: { ticks: { color: 'rgba(255,255,255,.8)', callback: (v)=> moneyFmt.format(v) }, grid: { color: 'rgba(255,255,255,.08)' } }
};

// Receita por dia (linha/area)
if (document.getElementById('chartDailyRevenue') && labelsDaily.length){
    const ctx = document.getElementById('chartDailyRevenue');
    const gradient = ctx.getContext('2d').createLinearGradient(0,0,0,320);
    gradient.addColorStop(0, 'rgba(218,165,32,0.45)');
    gradient.addColorStop(1, 'rgba(218,165,32,0.05)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labelsDaily,
            datasets: [{
                label: '<?= t('admin.kpi_revenue') ?>',
                data: dataDaily,
                borderColor: '#daa520',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 2,
                pointHoverRadius: 4,
                yAxisID: 'y'
            },{
                label: '<?= t('admin.appointments') ?>',
                data: countDaily,
                borderColor: '#6cc5a1',
                backgroundColor: 'transparent',
                borderWidth: 2,
                fill: false,
                tension: 0.35,
                pointRadius: 2,
                pointHoverRadius: 4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { color: 'rgba(255,255,255,.85)' } },
                tooltip: { callbacks: { label: (ctx)=> {
                    // datasetIndex 0 = revenue, 1 = appointments
                    if (ctx.datasetIndex === 0) {
                        return `${ctx.dataset.label}: ${moneyFmt.format(ctx.parsed.y)}`;
                    }
                    return `${ctx.dataset.label}: ${ctx.parsed.y}`;
                } } }
            },
            scales: {
                x: commonScales.x,
                y: commonScales.y,
                y1: {
                    position: 'right',
                    grid: { display: false },
                    ticks: {
                        color: 'rgba(255,255,255,.8)',
                        stepSize: 1,
                        callback: (v)=> Number.isInteger(v) ? v : ''
                    },
                    beginAtZero: true
                }
            },
            layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } },
            elements: { line: { capBezierPoints: true } }
        }
    });
}

// Receita por barbeiro (barras)
if (document.getElementById('chartBarberRevenue') && labelsBar.length){
    const ctx2 = document.getElementById('chartBarberRevenue');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: labelsBar,
            datasets: [{
                label: '<?= t('admin.kpi_revenue') ?>',
                data: dataBar,
                backgroundColor: 'rgba(218,165,32,0.35)',
                borderColor: '#daa520',
                borderWidth: 1.5,
                borderRadius: 6,
                maxBarThickness: 28,
            }]
        },
        options: {
            indexAxis: labelsBar.length > 6 ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx)=> `${moneyFmt.format(ctx.parsed.y ?? ctx.parsed.x)}` } }
            },
            scales: commonScales,
            layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } }
        }
    });
}

// Top serviços por receita (barras horizontais)
if (document.getElementById('chartServiceTop') && labelsSrv.length){
    const ctx3 = document.getElementById('chartServiceTop');
    new Chart(ctx3, {
        type: 'bar',
        data: { labels: labelsSrv, datasets: [{
            label: '<?= t('admin.kpi_revenue') ?>', data: dataSrv,
            backgroundColor: 'rgba(218,165,32,0.35)', borderColor: '#daa520', borderWidth: 1.5,
            borderRadius: 6, maxBarThickness: 26,
        }]},
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx)=> moneyFmt.format(ctx.parsed.x ?? ctx.parsed.y) } }
            },
            scales: {
                x: { ticks: { color: 'rgba(255,255,255,.8)', callback: (v)=> moneyFmt.format(v) }, grid: { color: 'rgba(255,255,255,.08)' } },
                y: { ticks: { color: 'rgba(255,255,255,.8)' }, grid: { color: 'rgba(255,255,255,.08)' } }
            },
            layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } }
        }
    });
}
</script>
</body>
</html>
