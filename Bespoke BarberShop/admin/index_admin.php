
<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Conexão e busca do nome do admin e unidade
include_once "../includes/db.php";
include_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = $_SESSION['usuario_id'];
$nome_admin = 'Administrador';
$unidade_nome = '';
$unidade_id = null;

$sql = $conn->prepare("SELECT nomeAdmin, Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($nome_admin, $unidade_id);
if ($sql->fetch()) {
    $sql->close(); // FECHA ANTES DE ABRIR OUTRO STATEMENT
    // Buscar nome da unidade
    $sql2 = $conn->prepare("SELECT nomeUnidade FROM Unidade WHERE idUnidade = ?");
    $sql2->bind_param("i", $unidade_id);
    $sql2->execute();
    $sql2->bind_result($unidade_nome);
    $sql2->fetch();
    $sql2->close();
} else {
    $sql->close();
}

// Buscar barbeiros da unidade
$barbeiros = [];
if (!empty($unidade_id)) {
    $sql3 = $conn->prepare("SELECT nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade = ? AND statusBarbeiro = 'Ativo'");
    $sql3->bind_param("i", $unidade_id);
    $sql3->execute();
    $result = $sql3->get_result();
    while ($row = $result->fetch_assoc()) {
        $barbeiros[] = $row['nomeBarbeiro'];
    }
    $sql3->close();
}

// Buscar serviços da UNIDADE (quando vinculados); se não houver unidade, mostrar catálogo global
$servicos = [];
if (!empty($unidade_id)) {
    $resServ = $conn->prepare("SELECT s.idServico, s.nomeServico FROM Unidade_has_Servico uhs JOIN Servico s ON s.idServico = uhs.Servico_idServico WHERE uhs.Unidade_idUnidade = ? ORDER BY s.nomeServico");
    $resServ->bind_param("i", $unidade_id);
    $resServ->execute();
    $rs = $resServ->get_result();
    while($row = $rs->fetch_assoc()) { $servicos[] = $row; }
    $resServ->close();
} else {
    $rs2 = $conn->query("SELECT idServico, nomeServico FROM Servico ORDER BY nomeServico");
    while($row = $rs2->fetch_assoc()) { $servicos[] = $row; }
}

// Próximos agendamentos da unidade
$proxAg = [];
if (!empty($unidade_id)) {
    $stmtAg = $conn->prepare("
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
    WHERE a.Unidade_idUnidade = ? AND a.data >= CURDATE() AND a.statusAgendamento = 'Agendado'
    GROUP BY a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente, b.nomeBarbeiro
    ORDER BY a.data ASC, a.hora ASC
    LIMIT 8
    ");
    $stmtAg->bind_param("i", $unidade_id);
    $stmtAg->execute();
    $rAg = $stmtAg->get_result();
    while($row = $rAg->fetch_assoc()) { $proxAg[] = $row; }
    $stmtAg->close();
}

// KPIs do mês (Finalizados) e resumo rápido de agenda (Hoje/Amanhã)
$kpiQtd = 0; $kpiReceita = 0.0; $kpiTempo = 0; $kpiMedio = 0;
$cntHoje = 0; $cntAmanha = 0;
if (!empty($unidade_id)) {
    $inicioMes = date('Y-m-01');
    $fimMes    = date('Y-m-t');
    // KPIs baseados em FINALIZADOS no mês
    $sqlSum = "
        SELECT COUNT(DISTINCT a.idAgendamento) AS qtd,
               COALESCE(SUM(ahs.precoFinal),0) AS receita,
               COALESCE(SUM(ahs.tempoEstimado),0) AS tempo
        FROM Agendamento a
        JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
        WHERE a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ? AND a.statusAgendamento = 'Finalizado'";
    if ($st = $conn->prepare($sqlSum)) {
        $st->bind_param('iss', $unidade_id, $inicioMes, $fimMes);
        $st->execute();
        $st->bind_result($kpiQtd, $kpiReceita, $kpiTempo);
        $st->fetch();
        $st->close();
        if ((int)$kpiQtd > 0 && (int)$kpiTempo > 0) { $kpiMedio = round($kpiTempo / $kpiQtd); }
    }
    // Agenda hoje/amanhã (Agendado)
    $sqlCnt = "SELECT COUNT(DISTINCT idAgendamento) FROM Agendamento WHERE Unidade_idUnidade=? AND data=? AND statusAgendamento='Agendado'";
    if ($st2 = $conn->prepare($sqlCnt)) {
        $hoje = date('Y-m-d'); $amanha = date('Y-m-d', strtotime('+1 day'));
        $st2->bind_param('is', $unidade_id, $hoje);
        $st2->execute(); $st2->bind_result($cntHoje); $st2->fetch(); $st2->close();
        if ($st3 = $conn->prepare($sqlCnt)) {
            $st3->bind_param('is', $unidade_id, $amanha);
            $st3->execute(); $st3->bind_result($cntAmanha); $st3->fetch(); $st3->close();
        }
    }
    // MTD anterior para tendência
    $diaAtual = (int)date('j');
    $prevInicio = date('Y-m-01', strtotime('first day of previous month'));
    $prevLast   = date('Y-m-t', strtotime('last day of previous month'));
    // limitar fim anterior ao mesmo dia do mês atual (ou último dia do mês anterior)
    $prevFimCalc = date('Y-m-d', strtotime($prevInicio . ' +'.($diaAtual-1).' days'));
    if (strtotime($prevFimCalc) > strtotime($prevLast)) { $prevFimCalc = $prevLast; }
    $kpiQtdPrev = 0; $kpiRecPrev = 0.0; $kpiTempPrev = 0;
    if ($stp = $conn->prepare($sqlSum)) {
        $stp->bind_param('iss', $unidade_id, $prevInicio, $prevFimCalc);
        $stp->execute();
        $stp->bind_result($kpiQtdPrev, $kpiRecPrev, $kpiTempPrev);
        $stp->fetch();
        $stp->close();
    }
    // funções auxiliares de tendência
    $pct = function($cur, $prev){ if ($prev <= 0) return $cur>0? 100 : 0; return round((($cur-$prev)/$prev)*100); };
    $tQtd = $pct((int)$kpiQtd, (int)$kpiQtdPrev);
    $tRec = $pct((float)$kpiReceita, (float)$kpiRecPrev);
    $tTmp = $pct((int)$kpiTempo, (int)$kpiTempPrev);
}

?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
    <meta charset="UTF-8">
        <title><?= t('admin.dashboard_title') ?> | Bespoke BarberShop</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="container py-4">
        <?php
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $currentUrl = $scheme.'://'.$host.$uri;
        ?>
        <div class="d-flex justify-content-end mb-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= t('nav.language') ?>">
                <a class="btn btn-outline-warning <?= bb_is_en() ? '' : 'active' ?>" href="../includes/locale.php?set=pt_BR&redirect=<?= urlencode($currentUrl) ?>"><?= t('nav.pt') ?></a>
                <a class="btn btn-outline-warning <?= bb_is_en() ? 'active' : '' ?>" href="../includes/locale.php?set=en_US&redirect=<?= urlencode($currentUrl) ?>"><?= t('nav.en') ?></a>
            </div>
        </div>
        <?php if (!empty($unidade_id)): ?>
        <div class="row g-4 stack-xl">
            <div class="col-12">
                <div class="dashboard-card p-3 kpi-accent">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0" style="color:#ffd24d;"><i class="bi bi-speedometer2"></i> <?= t('admin.month_summary') ?></div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="text-muted fw-semibold"><?= t('admin.period') ?> <?= date('d/m', strtotime($inicioMes)) ?> - <?= date('d/m', strtotime($fimMes)) ?></div>
                            <a href="relatorios.php?status=Finalizado&inicio=<?= $inicioMes ?>&fim=<?= $fimMes ?>" class="dashboard-action dashboard-btn-small" title="<?= t('admin.view_month_report') ?>">
                                <i class="bi bi-graph-up"></i> <?= t('admin.view_month_report') ?>
                            </a>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="kpi-card">
                                <div class="kpi-top"><i class="bi bi-people kpi-icon"></i><span class="kpi-label"><?= t('admin.appointments') ?></span></div>
                                <div class="kpi-value"><?= (int)$kpiQtd ?></div>
                                <div class="kpi-sub"><?= t('admin.avg_duration') ?> <?= ((int)$kpiQtd>0? bb_format_minutes($kpiMedio) : '—') ?></div>
                                <div class="kpi-trend <?= ($tQtd>=0?'up':'down') ?>">
                                    <i class="bi <?= ($tQtd>=0? 'bi-arrow-up-right':'bi-arrow-down-right') ?>"></i>
                                        <?= ($tQtd>=0?'+':'') . $tQtd ?>% <?= t('admin.vs_prev_month') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="kpi-card">
                                <div class="kpi-top"><i class="bi bi-cash-coin kpi-icon"></i><span class="kpi-label"><?= t('admin.kpi_revenue') ?></span></div>
                                <div class="kpi-value"><?= bb_format_currency_local((float)$kpiReceita) ?></div>
                                    <div class="kpi-sub"><?= t('admin.avg_ticket') ?>: <?= ((int)$kpiQtd>0? bb_format_currency_local((float)$kpiReceita/(int)$kpiQtd) : '—') ?></div>
                                <div class="kpi-trend <?= ($tRec>=0?'up':'down') ?>">
                                    <i class="bi <?= ($tRec>=0? 'bi-arrow-up-right':'bi-arrow-down-right') ?>"></i>
                                        <?= ($tRec>=0?'+':'') . $tRec ?>% <?= t('admin.vs_prev_month') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="kpi-card">
                                <div class="kpi-top"><i class="bi bi-clock-history kpi-icon"></i><span class="kpi-label"><?= t('admin.duration_label') ?></span></div>
                                <div class="kpi-value"><?= bb_format_minutes($kpiTempo) ?></div>
                                <div class="kpi-sub"><?= t('admin.finished_in_month') ?></div>
                                <div class="kpi-trend <?= ($tTmp>=0?'up':'down') ?>">
                                    <i class="bi <?= ($tTmp>=0? 'bi-arrow-up-right':'bi-arrow-down-right') ?>"></i>
                                    <?= ($tTmp>=0?'+':'') . $tTmp ?>% <?= t('admin.vs_prev_month') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-12 col-md-6">
                            <div class="kpi-card" style="border-style:dashed;">
                                <div class="text-warning" style="font-weight:600;"><i class="bi bi-calendar-day"></i> <?= bb_is_en() ? 'Today' : 'Hoje' ?></div>
                                <div class="fs-5" style="font-weight:800; color:#ffd24d;"><?= bb_is_en() ? 'Scheduled' : 'Agendados' ?>: <?= (int)$cntHoje ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="kpi-card" style="border-style:dashed;">
                                <div class="text-warning" style="font-weight:600;"><i class="bi bi-calendar-check"></i> <?= bb_is_en() ? 'Tomorrow' : 'Amanhã' ?></div>
                                <div class="fs-5" style="font-weight:800; color:#ffd24d;"><?= bb_is_en() ? 'Scheduled' : 'Agendados' ?>: <?= (int)$cntAmanha ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Toast container -->
        <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>
    <div class="row mb-4 welcome-gap">
            <div class="col-12">
                <div class="dashboard-card dashboard-welcome-card">
                    <span class="dashboard-welcome-text fs-1 fs-md-2 fs-lg-1"><?= t('admin.welcome_admin') ?> <?= htmlspecialchars($nome_admin) ?>!</span>
                    <div class="mt-2 fs-4 fs-md-5 fs-lg-4"><?= t('admin.unit_label') ?>: <span style="color:#daa520; font-weight:600;"><?= htmlspecialchars($unidade_nome) ?></span></div>
                </div>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <?php if (empty($unidade_id)): ?>
            <div class="col-12">
                <div class="alert alert-warning border-0" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= t('admin.not_linked_unit') ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($unidade_id)): ?>
            <!-- Card Agendamentos da Unidade -->
            <div class="col-12 col-lg-12 d-flex align-items-stretch">
                <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-week"></i> <?= t('admin.next_unit_appointments') ?></div>
                        <div class="row gx-2">
                            <div class="col-auto">
                                <?php $d1 = date('Y-m-d'); $d2 = date('Y-m-d', strtotime('+30 days')); ?>
                                <a href="relatorios.php?status=Agendado&inicio=<?= $d1 ?>&fim=<?= $d2 ?>" class="dashboard-action dashboard-btn-small" title="Ver próximos 30 dias">
                                    <i class="bi bi-eye"></i> <?= t('admin.view_all') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-container-fix flex-fill">
                        <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
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
                                <?php if (count($proxAg) > 0): foreach($proxAg as $row): ?>
                                    <tr>
                                        <td title="<?= bb_format_date($row['data']) ?>"><?= bb_format_date($row['data']) ?></td>
                                        <td><?= bb_format_time($row['hora']) ?></td>
                                        <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                                        <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                                        <?php
                                            $srvCsv = (string)($row['servicos'] ?? '');
                                            $srvArr = array_map('trim', explode(',', $srvCsv));
                                            $srvArr = array_filter($srvArr, fn($s)=>$s!=='');
                                            $srvArrLoc = array_map('bb_service_display', $srvArr);
                                            $srvCsvLoc = implode(', ', $srvArrLoc);
                                        ?>
                                        <td title="<?= htmlspecialchars($srvCsvLoc) ?>"><?= htmlspecialchars(strlen($srvCsvLoc)>28 ? substr($srvCsvLoc,0,28).'…' : $srvCsvLoc) ?></td>
                                        <td><?= bb_format_currency_local((float)$row['precoTotal']) ?></td>
                                        <td><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                        <td><?= htmlspecialchars(bb_status_label($row['statusAgendamento'])) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="8" class="text-center text-muted"><?= t('admin.no_future') ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- Card de barbeiros -->
            <div class="col-12 col-lg-7 d-flex align-items-stretch">
                <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-badge"></i> <?= t('admin.unit_barbers') ?></div>
                        <div class="row gx-2">
                            <div class="col-auto">
                                <a href="cadastrar_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-person-plus"></i> <?= t('admin.register_barber') ?></a>
                            </div>
                            <?php if (!empty($unidade_id)): ?>
                            <div class="col-auto">
                                <a href="gerenciar_barbeiros.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-people"></i> <?= t('admin.manage_barbers') ?></a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-fill">
                        <ul class="list-group list-group-flush mb-3">
                            <?php if (count($barbeiros) > 0): foreach($barbeiros as $b): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0 fs-5 fs-md-6 fs-lg-5"><i class="bi bi-scissors"></i> <?= htmlspecialchars($b) ?></li>
                            <?php endforeach; else: ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0"><?= t('admin.none_found') ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="dashboard-section-title mb-2 mt-3 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-gear"></i> <?= !empty($unidade_id) ? t('admin.available_services_unit') : t('admin.available_services_catalog') ?></div>
                    <div class="flex-fill">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($servicos as $serv): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0 fs-5 fs-md-6 fs-lg-5"><i class="bi bi-check2-circle"></i> <?= htmlspecialchars(bb_service_display($serv['nomeServico'])) ?></li>
                            <?php endforeach; ?>
                            <?php if (empty($servicos)): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0"><?= t('admin.no_services_unit') ?> <a href="gerenciar_servicos.php" class="text-warning"><?= t('admin.link_now') ?></a>.</li>
                            <?php endif; ?>
                        </ul>
                        <a href="gerenciar_servicos.php" class="dashboard-action w-100"><i class="bi bi-pencil-square"></i> <?= t('admin.manage_services') ?></a>
                    </div>
                </div>
            </div>

            <!-- Card de perfil e ações -->
            <div class="col-12 col-lg-5 d-flex flex-column gap-4">
                <div class="dashboard-card p-3 flex-fill mb-0">
                    <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-circle"></i> <?= t('admin.my_profile') ?></div>
                    <div class="mb-2 fs-5 fs-md-6 fs-lg-5"><?= t('admin.manage_profile_desc') ?></div>
                    <a href="editar_perfil.php" class="dashboard-action mt-2 w-100"><i class="bi bi-pencil-square"></i> <?= t('admin.edit_profile') ?></a>
                    <a href="bloqueios.php" class="dashboard-action mt-2 w-100"><i class="bi bi-calendar-x"></i> <?= t('admin.unit_blocks') ?></a>
                    <a href="escalas.php" class="dashboard-action mt-2 w-100"><i class="bi bi-calendar3"></i> <?= t('admin.scales_title') ?></a>
                    <a href="metas.php" class="dashboard-action mt-2 w-100"><i class="bi bi-bullseye"></i> <?= t('admin.goals_title') ?></a>
                    <a href="../logout.php" class="dashboard-action mt-2 w-100 dashboard-btn-logout"><i class="bi bi-box-arrow-right"></i> <?= t('user.logout') ?></a>
                </div>
                
                <div class="dashboard-card p-3 flex-fill mb-0">
                    <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-info-circle"></i> <?= t('admin.important_info') ?></div>
                    <ul class="dashboard-info-list fs-5 fs-md-6 fs-lg-5">
                        <li><?= t('admin.info1') ?></li>
                        <li><?= t('admin.info2') ?></li>
                        <li><?= t('admin.info3') ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toasts
        function getParam(name){ const url = new URL(window.location.href); return url.searchParams.get(name); }
        function showToast(message, ok){
            const container = document.getElementById('toast-msg-container'); if(!container || !message) return;
            const el = document.createElement('div');
            el.className = `toast align-items-center ${ok==='1' ? 'text-bg-success' : 'text-bg-danger'} border-0`;
            el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); el.setAttribute('aria-atomic','true');
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`;
            container.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
        }
        const msg = getParam('msg'); const ok = getParam('ok'); if (msg) { try { showToast(decodeURIComponent(msg), ok); } catch(_) { showToast(msg, ok); } }
    </script>
    <?php @include_once("../Footer/footer.html"); ?>
</body>
</html>