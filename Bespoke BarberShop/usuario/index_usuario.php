<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
include_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT nomeCliente, emailCliente, telefoneCliente FROM Cliente WHERE idCliente = ?");
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$stmt->bind_result($nomeCompleto, $email, $telefone);
$stmt->fetch();
$stmt->close();
$primeiroNome = explode(' ', trim($nomeCompleto))[0];

// Buscar todos os agendamentos do usuário
$stmt2 = $conn->prepare("
SELECT 
        a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro,
        GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
        SUM(ahs.precoFinal) AS precoTotal,
        SUM(ahs.tempoEstimado) AS tempoTotal,
        a.statusAgendamento
FROM Agendamento a
JOIN Unidade u ON a.Unidade_idUnidade = u.idUnidade
JOIN Barbeiro b ON a.Barbeiro_idBarbeiro = b.idBarbeiro
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Cliente_idCliente = ?
    AND a.statusAgendamento <> 'Finalizado'
    AND NOT EXISTS (
        SELECT 1 FROM AgendamentoOcultoCliente aoc
        WHERE aoc.Cliente_id = ? AND aoc.Agendamento_id = a.idAgendamento
    )
GROUP BY a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro, a.statusAgendamento
ORDER BY a.data ASC, a.hora ASC
LIMIT 5
");
$stmt2->bind_param("ii", $idCliente, $idCliente);
$stmt2->execute();
$result = $stmt2->get_result();

// Buscar último atendimento finalizado para habilitar "Repetir último serviço"
$stmtUlt = $conn->prepare("SELECT a.Unidade_idUnidade AS unidadeId, a.Barbeiro_idBarbeiro AS barbeiroId, (SELECT ahs.Servico_idServico FROM Agendamento_has_Servico ahs WHERE ahs.Agendamento_idAgendamento=a.idAgendamento LIMIT 1) AS servicoId FROM Agendamento a WHERE a.Cliente_idCliente = ? AND a.statusAgendamento='Finalizado' ORDER BY a.data DESC, a.hora DESC LIMIT 1");
$stmtUlt->bind_param("i", $idCliente);
$stmtUlt->execute();
$prefill = $stmtUlt->get_result()->fetch_assoc();
$stmtUlt->close();

// Próximo horário do cliente (não finalizado)
$prox = null;
if ($stNext = $conn->prepare("SELECT a.idAgendamento, a.data, a.hora, u.idUnidade AS unidadeId, u.nomeUnidade, b.idBarbeiro AS barbeiroId, b.nomeBarbeiro, GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos, SUM(ahs.precoFinal) AS precoTotal, SUM(ahs.tempoEstimado) AS tempoTotal, a.statusAgendamento FROM Agendamento a JOIN Unidade u ON a.Unidade_idUnidade = u.idUnidade JOIN Barbeiro b ON a.Barbeiro_idBarbeiro = b.idBarbeiro JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento JOIN Servico s ON ahs.Servico_idServico = s.idServico WHERE a.Cliente_idCliente = ? AND a.statusAgendamento <> 'Finalizado' AND (a.data > CURDATE() OR (a.data = CURDATE() AND a.hora >= CURTIME())) GROUP BY a.idAgendamento, a.data, a.hora, u.idUnidade, u.nomeUnidade, b.idBarbeiro, b.nomeBarbeiro, a.statusAgendamento ORDER BY a.data ASC, a.hora ASC LIMIT 1")){
    $stNext->bind_param('i', $idCliente);
    $stNext->execute(); $rN = $stNext->get_result(); $prox = $rN->fetch_assoc(); $stNext->close();
}
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.dashboard_title') ?> | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body>
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
    <div class="row mb-3">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-welcome-text"><?= t('user.hello') ?> <?= htmlspecialchars($primeiroNome) ?></span>
            </div>
        </div>
    </div>
    <!-- Seu próximo horário -->
    <div class="row g-4 mb-2">
        <div class="col-12">
            <div class="dashboard-card p-3 next-card">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-lightning-charge"></i> <?= t('user.next_appt') ?></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if($prox): ?><div class="next-meta"><?= bb_format_date($prox['data']) ?> · <?= bb_format_time($prox['hora']) ?></div><?php endif; ?>
                        <?php if($prox): ?><span class="badge bg-warning text-dark fw-semibold" title="<?= t('user.cancel_policy') ?>"><?= t('user.cancel_policy') ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?php if($prox): ?>
                            <div><i class="bi bi-person-badge"></i> <?= htmlspecialchars($prox['nomeBarbeiro']) ?></div>
                            <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($prox['nomeUnidade']) ?></div>
                            <?php
                                $proxSrvCsv = (string)($prox['servicos'] ?? '');
                                $proxSrvArr = array_map('trim', explode(',', $proxSrvCsv));
                                $proxSrvArr = array_filter($proxSrvArr, fn($s)=>$s!=='');
                                $proxSrvArrLoc = array_map('bb_service_display', $proxSrvArr);
                                $proxSrvCsvLoc = implode(', ', $proxSrvArrLoc);
                            ?>
                            <div class="text-truncate" style="max-width: 360px;"><i class="bi bi-scissors"></i> <?= htmlspecialchars($proxSrvCsvLoc) ?></div>
                            <div><i class="bi bi-clock"></i> <?= bb_format_minutes((int)$prox['tempoTotal']) ?></div>
                            <div><i class="bi bi-cash"></i> <?= bb_format_currency_local((float)$prox['precoTotal']) ?></div>
                        <?php else: ?>
                            <div class="text-muted"><?= t('user.no_upcoming') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if($prox): ?>
                            <a href="agendamentos_usuario.php" class="dashboard-action dashboard-btn-small" aria-label="<?= t('user.manage') ?>"><i class="bi bi-pencil"></i> <?= t('user.manage') ?></a>
                            <a href="../agendamento.php?unidade=<?= (int)$prox['unidadeId'] ?>&barbeiro=<?= (int)$prox['barbeiroId'] ?>" class="dashboard-action dashboard-btn-small" aria-label="<?= t('user.reschedule') ?>"><i class="bi bi-arrow-repeat"></i> <?= t('user.reschedule') ?></a>
                            <form method="post" action="../cancelar.php" class="d-inline form-cancelar">
                                <input type="hidden" name="idAgendamento" value="<?= (int)$prox['idAgendamento'] ?>">
                                <button type="button" class="dashboard-action dashboard-btn-small btn-open-cancelar" aria-label="<?= t('user.cancel') ?>"><i class="bi bi-x-circle"></i> <?= t('user.cancel') ?></button>
                            </form>
                        <?php else: ?>
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-plus-circle"></i> <?= t('user.schedule_now') ?></a>
                            <?php if (!empty($prefill['unidadeId']) && !empty($prefill['barbeiroId']) && !empty($prefill['servicoId'])): ?>
                            <a href="../agendamento.php?unidade=<?= (int)$prefill['unidadeId'] ?>&barbeiro=<?= (int)$prefill['barbeiroId'] ?>&servico=<?= (int)$prefill['servicoId'] ?>" class="dashboard-action dashboard-btn-small"><i class="bi bi-arrow-repeat"></i> <?= t('user.repeat_last') ?></a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de confirmação de cancelamento / Cancel confirmation -->
    <div class="modal fade" id="modalCancelarCli" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> <?= t('sched.cancel') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"><?= t('sched.confirm_cancel') ?></div>
                <div class="modal-footer border-secondary d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i> <?= t('sched.back') ?></button>
                    <button type="button" class="btn btn-danger" id="btnConfirmCancelarCli"><i class="bi bi-x"></i> <?= t('sched.cancel') ?></button>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-event"></i> <?= t('user.my_appointments') ?></div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small btn-novo-agendamento"><i class="bi bi-plus-circle"></i> <?= t('user.new') ?></a>
                        </div>
                        <div class="col-auto">
                            <a href="agendamentos_usuario.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-pencil"></i> <?= t('user.edit') ?></a>
                        </div>
                        <?php if (!empty($prefill['unidadeId']) && !empty($prefill['barbeiroId']) && !empty($prefill['servicoId'])): ?>
                        <div class="col-auto">
                            <a href="../agendamento.php?unidade=<?= (int)$prefill['unidadeId'] ?>&barbeiro=<?= (int)$prefill['barbeiroId'] ?>&servico=<?= (int)$prefill['servicoId'] ?>" class="dashboard-action dashboard-btn-small"><i class="bi bi-arrow-repeat"></i> <?= t('user.repeat_last') ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive flex-fill table-container-fix">
                <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                    <thead>
                        <tr>
                            <th><?= t('sched.date') ?></th>
                            <th><?= t('sched.time') ?></th>
                            <th><?= t('sched.unit') ?></th>
                            <th><?= t('sched.barber') ?></th>
                            <th><?= t('sched.service') ?></th>
                            <th><?= t('user.total') ?></th>
                            <th><?= t('sched.duration') ?></th>
                            <th><?= t('user.status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td title="<?= bb_format_date($row['data']) ?>"><?= bb_format_date($row['data']) ?></td>
                                    <td title="<?= bb_format_time($row['hora']) ?>"><?= bb_format_time($row['hora']) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeUnidade']) ?>"><?= substr(htmlspecialchars($row['nomeUnidade']), 0, 8) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeBarbeiro']) ?>"><?= substr(htmlspecialchars($row['nomeBarbeiro']), 0, 10) ?></td>
                                    <?php
                                        $rowSrvCsv = (string)($row['servicos'] ?? '');
                                        $rowSrvArr = array_map('trim', explode(',', $rowSrvCsv));
                                        $rowSrvArr = array_filter($rowSrvArr, fn($s)=>$s!=='');
                                        $rowSrvArrLoc = array_map('bb_service_display', $rowSrvArr);
                                        $rowSrvCsvLoc = implode(', ', $rowSrvArrLoc);
                                    ?>
                                    <td title="<?= htmlspecialchars($rowSrvCsvLoc) ?>"><?= substr(htmlspecialchars($rowSrvCsvLoc), 0, 12) ?></td>
                                    <td title="<?= bb_format_currency_local((float)$row['precoTotal']) ?>"><?= bb_format_currency_local((float)$row['precoTotal']) ?></td>
                                    <td title="<?= bb_format_minutes((int)$row['tempoTotal']) ?>"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                    <td title="<?= htmlspecialchars($row['statusAgendamento']) ?>"><?= htmlspecialchars(bb_status_label($row['statusAgendamento'])) ?></td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-warning"><?= t('user.no_active') ?> <a href="../agendamento.php" class="dashboard-action dashboard-btn-small"><?= t('user.schedule_now') ?></a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <!-- Card Perfil e Info -->
        <div class="col-12 col-lg-5 d-flex flex-column gap-4">
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-circle"></i> <?= t('user.profile') ?></div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b><?= t('user.name') ?>:</b> <?= htmlspecialchars($nomeCompleto) ?></div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b><?= t('user.email') ?>:</b> <?= htmlspecialchars($email) ?></div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b><?= t('user.phone') ?>:</b> <?= htmlspecialchars($telefone) ?></div>
                <a href="editar_perfil.php" class="dashboard-action mt-2 w-100"><i class="bi bi-pencil-square"></i> <?= t('user.edit_profile') ?></a>
                <a href="../logout.php" class="dashboard-action mt-2 w-100 dashboard-btn-logout"><i class="bi bi-box-arrow-right"></i> <?= t('user.logout') ?></a>
            </div>
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-info-circle"></i> <?= t('user.useful_info') ?></div>
                <ul class="dashboard-info-list fs-5 fs-md-6 fs-lg-5">
                    <li><?= t('user.tip1') ?></li>
                    <li><?= t('user.tip2') ?></li>
                    <li><?= t('user.tip3') ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Toast container for limit messages -->
<div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toast-limit-container" style="z-index:1080;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showLimitToast(message){
    const container = document.getElementById('toast-limit-container');
    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-bg-warning border-0';
    toastEl.setAttribute('role','alert');
    toastEl.setAttribute('aria-live','assertive');
    toastEl.setAttribute('aria-atomic','true');
    toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4500 });
    toast.show();
}

// Intercepta clique em "Novo" para checar limite antes de navegar
document.querySelectorAll('.btn-novo-agendamento').forEach((el)=>{
    el.addEventListener('click', async (e)=>{
        e.preventDefault();
        const href = el.getAttribute('href');
        try {
            const res = await fetch('../includes/check_limit.php', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.allow) {
                window.location.href = href;
            } else {
                const fallback = <?= json_encode(t('user.limit_reached')) ?>;
                const msg = data.message ? data.message.replace(/<[^>]+>/g, '').trim() : fallback;
                showLimitToast(msg);
            }
        } catch (err) {
            // Se houver erro na checagem, seguimos para a página como fallback
            window.location.href = href;
        }
    });
});

// Cancelamento (abrir modal e confirmar)
document.querySelectorAll('.btn-open-cancelar').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const modal = new bootstrap.Modal(document.getElementById('modalCancelarCli'));
    // guarda o form mais próximo para submit ao confirmar
    window.__formCancel = btn.closest('form');
    modal.show();
  });
});

document.getElementById('btnConfirmCancelarCli')?.addEventListener('click', ()=>{
  if (window.__formCancel) window.__formCancel.submit();
});
</script>
</body>
</html>