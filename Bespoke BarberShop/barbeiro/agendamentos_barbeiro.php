<?php
session_start();
include "../includes/db.php";
include_once "../includes/helpers.php";

if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}

$bd = new Banco();
$conn = $bd->getConexao();
$idBarbeiro = $_SESSION['usuario_id'];

// Se for obrigatório trocar a senha, bloquear acesso e redirecionar para editar perfil
$chkMust = $conn->prepare("SELECT deveTrocarSenha FROM Barbeiro WHERE idBarbeiro=?");
$chkMust->bind_param("i", $idBarbeiro);
$chkMust->execute();
$chkMust->bind_result($must);
$chkMust->fetch();
$chkMust->close();
if ((int)$must === 1) {
    header('Location: editar_perfil.php?ok=0&msg=' . urlencode(t('barber.must_change_required')));
    exit;
}

// Filtro por status via GET (padrão: Agendado)
$status = isset($_GET['status']) ? $_GET['status'] : 'Agendado';
$statusPermitidos = ['Agendado','Cancelado','Finalizado','Todos'];
if (!in_array($status, $statusPermitidos)) { $status = 'Agendado'; }

// Monta cláusula de status
switch ($status) {
    case 'Cancelado':
        $whereStatus = "AND a.statusAgendamento = 'Cancelado' AND a.data >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
        break;
    case 'Finalizado':
        $whereStatus = "AND a.statusAgendamento = 'Finalizado' AND a.data >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
        break;
    case 'Todos':
        $whereStatus = "AND a.statusAgendamento IN ('Agendado','Cancelado','Finalizado')";
        break;
    case 'Agendado':
    default:
        $whereStatus = "AND a.statusAgendamento = 'Agendado'";
}

// Período via GET (inicio/fim em YYYY-MM-DD) para chips Hoje/Amanhã/Semana
$inicio = isset($_GET['inicio']) ? trim($_GET['inicio']) : '';
$fim    = isset($_GET['fim'])    ? trim($_GET['fim'])    : '';
// Validação simples de data
$isValidDate = function($d){
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y,$m,$day] = explode('-', $d); return checkdate((int)$m,(int)$day,(int)$y);
};
$periodoTxt = '';
if ($inicio && $fim && $isValidDate($inicio) && $isValidDate($fim)) {
    // Segurança: datas validadas, inclusão direta é aceitável neste contexto
    $whereStatus .= " AND a.data BETWEEN '".$conn->real_escape_string($inicio)."' AND '".$conn->real_escape_string($fim)."'";
    $periodoTxt = date('d/m', strtotime($inicio)).' - '.date('d/m', strtotime($fim));
} elseif ($inicio && $isValidDate($inicio)) {
    $whereStatus .= " AND a.data = '".$conn->real_escape_string($inicio)."'";
    $periodoTxt = date('d/m', strtotime($inicio));
}

$sqlLista = "
SELECT a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente,
             GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
             SUM(ahs.precoFinal) AS precoTotal,
             SUM(ahs.tempoEstimado) AS tempoTotal
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ?
    $whereStatus
GROUP BY a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente
ORDER BY a.data DESC, a.hora DESC
";

$stmt = $conn->prepare($sqlLista);
if ($stmt) {
    $stmt->bind_param("i", $idBarbeiro);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
    } else {
        $idSafe = (int)$idBarbeiro;
        $result = $conn->query(str_replace('?', $idSafe, $sqlLista));
    }
} else {
    $idSafe = (int)$idBarbeiro;
    $result = $conn->query(str_replace('?', $idSafe, $sqlLista));
}
?>
<!DOCTYPE html>
<html lang='<?= bb_is_en() ? 'en' : 'pt-br' ?>'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= t('barber.all_appointments') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_barbeiro.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .table thead th { white-space: nowrap; }
    </style>
    <?php /* Re-executar a consulta incluindo o status para uso na tabela completa */ ?>
</head>
<body class='dashboard-barbeiro-novo'>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-title"><i class="bi bi-calendar-week"></i> Todos os seus atendimentos</span>
                    <span class="dashboard-title"><i class="bi bi-calendar-week"></i> <?= t('barber.all_appointments') ?></span>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <!-- Filtros -->
        <div class="col-12">
            <div class="dashboard-card p-3 mb-0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-funnel"></i> Filtros</div>
                        <div class="dashboard-section-title mb-0"><i class="bi bi-funnel"></i> <?= t('barber.filters') ?></div>
                    <button class="dashboard-action dashboard-btn-small" type="button" data-bs-toggle="collapse" data-bs-target="#filtros" aria-expanded="false" aria-controls="filtros">
                        <i class="bi bi-sliders"></i> <?= t('barber.filter') ?>
                    </button>
                </div>
                <div class="collapse" id="filtros">
                    <div class="d-flex flex-wrap gap-2">
                        <?php 
                        $mk = function($label){ return strtolower($label) === 'todos' ? 'bi-list-ul' : (strtolower($label)==='agendado' ? 'bi-calendar-check' : (strtolower($label)==='finalizado' ? 'bi-check2-circle' : 'bi-x-circle')); };
                        $opts = ['Agendado','Cancelado','Finalizado','Todos'];
                        foreach($opts as $opt):
                            $active = ($status === $opt) ? 'style="background:#bfa12a !important;color:#fff !important;"' : '';
                        ?>
                        <a href="?status=<?= $opt ?>" class="dashboard-action dashboard-btn-small" <?= $active ?>><i class="bi <?= $mk($opt) ?>"></i> <?= htmlspecialchars(bb_status_label($opt)) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-2 text-warning" style="font-size:0.95rem;"><?= t('barber.current_filter') ?> <b><?= htmlspecialchars(bb_status_label($status)) ?></b>
                <?php if(!empty($periodoTxt)): ?> • Período: <b><?= htmlspecialchars($periodoTxt) ?></b><?php endif; ?></div>
            </div>
        </div>
        <div class="col-12">
            <div class="dashboard-card p-3">
                <!-- Toast container for feedback messages -->
                <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toast-msg-container" style="z-index:1080;"></div>
                <div class='table-responsive table-container-fix'>
                    <table class='table table-dark table-striped table-sm align-middle mb-0 dashboard-table'>
                        <thead>
                            <tr>
                                <th><?= t('sched.date') ?></th>
                                <th><?= t('sched.time') ?></th>
                                <th><?= t('barber.client') ?></th>
                                <th><?= t('sched.service') ?></th>
                                <th><?= t('user.total') ?></th>
                                <th><?= t('sched.duration') ?></th>
                                <th><?= t('user.status') ?></th>
                                <th><?= t('user.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <?php $status = $row['statusAgendamento']; $idAg = (int)$row['idAgendamento']; ?>
                            <tr>
                                <td><?= bb_format_date($row['data']) ?></td>
                                <td><?= bb_format_time($row['hora']) ?></td>
                                <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                                <?php
                                    $srvCsv = (string)($row['servicos'] ?? '');
                                    $srvArr = array_map('trim', explode(',', $srvCsv));
                                    $srvArr = array_filter($srvArr, fn($s)=>$s!=='');
                                    $srvArrLoc = array_map('bb_service_display', $srvArr);
                                    $srvCsvLoc = implode(', ', $srvArrLoc);
                                ?>
                                <td><?= htmlspecialchars($srvCsvLoc) ?></td>
                                <td><?= bb_format_currency_local((float)$row['precoTotal']) ?></td>
                                <td><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                <td><?= htmlspecialchars(bb_status_label($status)) ?></td>
                                <td>
                                    <?php if ($status === 'Agendado' && $idAg > 0): ?>
                                        <form method="post" action="concluir_agendamento.php" class="d-inline form-concluir" data-id="<?= $idAg ?>">
                                            <input type="hidden" name="idAgendamento" value="<?= $idAg ?>">
                                            <button type="button" class="dashboard-action dashboard-btn-small btn-open-concluir btn-concluir"><i class="bi bi-check2-circle"></i> <?= t('sched.finalize') ?></button>
                                        </form>
                                    <?php elseif ($status === 'Cancelado'): ?>
                                        <span class="badge bg-danger"><?= bb_status_label('Cancelado') ?></span>
                                    <?php elseif ($status === 'Finalizado'): ?>
                                        <span class="badge bg-success"><?= bb_status_label('Finalizado') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href='index_barbeiro.php' class='dashboard-action dashboard-btn-small'><i class="bi bi-arrow-left"></i> <?= t('barber.back_panel') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal de confirmação de conclusão -->
<div class="modal fade" id="modalConcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-check2-circle"></i> <?= t('barber.confirm_complete') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= t('sched.back') ?>"></button>
            </div>
            <div class="modal-body">
                <?= t('barber.confirm_complete_body') ?>
            </div>
            <div class="modal-footer border-secondary d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> <?= t('sched.back') ?></button>
                <button type="button" class="btn btn-success" id="btnConfirmConcluir"><i class="bi bi-check"></i> <?= t('user.confirm') ?></button>
            </div>
        </div>
    </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let formToSubmit = null;
document.querySelectorAll('.btn-open-concluir').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        formToSubmit = btn.closest('form');
        const modal = new bootstrap.Modal(document.getElementById('modalConcluir'));
        modal.show();
    });
});
document.getElementById('btnConfirmConcluir').addEventListener('click', ()=>{
    if (formToSubmit) {
        formToSubmit.submit();
    }
});

// Toast feedback based on URL params
function getParam(name){
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
}
function showToast(message, ok){
    const container = document.getElementById('toast-msg-container');
    if(!container || !message) return;
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center ${ok==='1' ? 'text-bg-success' : 'text-bg-danger'} border-0`;
    toastEl.setAttribute('role','alert');
    toastEl.setAttribute('aria-live','assertive');
    toastEl.setAttribute('aria-atomic','true');
    toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}
const msg = getParam('msg');
const ok = getParam('ok');
if (msg) { try { showToast(decodeURIComponent(msg), ok); } catch(_) { showToast(msg, ok); } }
</script>
</body>
</html>