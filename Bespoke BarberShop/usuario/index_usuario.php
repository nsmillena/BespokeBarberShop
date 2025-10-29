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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body>
<div class="container py-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-welcome-text">Olá, <?= htmlspecialchars($primeiroNome) ?></span>
            </div>
        </div>
    </div>
    <!-- Seu próximo horário -->
    <div class="row g-4 mb-2">
        <div class="col-12">
            <div class="dashboard-card p-3 next-card">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-lightning-charge"></i> Seu próximo horário</div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if($prox): ?><div class="next-meta">Em <?= date('d/m', strtotime($prox['data'])) ?> às <?= substr($prox['hora'],0,5) ?></div><?php endif; ?>
                        <?php if($prox): ?><span class="badge bg-warning text-dark fw-semibold" title="Política de cancelamento">Cancelamento até 2h antes</span><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?php if($prox): ?>
                            <div><i class="bi bi-person-badge"></i> <?= htmlspecialchars($prox['nomeBarbeiro']) ?></div>
                            <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($prox['nomeUnidade']) ?></div>
                            <div class="text-truncate" style="max-width: 360px;"><i class="bi bi-scissors"></i> <?= htmlspecialchars($prox['servicos']) ?></div>
                            <div><i class="bi bi-clock"></i> <?= bb_format_minutes((int)$prox['tempoTotal']) ?></div>
                            <div><i class="bi bi-cash"></i> R$ <?= number_format($prox['precoTotal'], 2, ',', '.') ?></div>
                        <?php else: ?>
                            <div class="text-muted">Você não tem um próximo horário marcado.</div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if($prox): ?>
                            <a href="agendamentos_usuario.php" class="dashboard-action dashboard-btn-small" aria-label="Gerenciar agendamentos"><i class="bi bi-pencil"></i> Gerenciar</a>
                            <a href="../agendamento.php?unidade=<?= (int)$prox['unidadeId'] ?>&barbeiro=<?= (int)$prox['barbeiroId'] ?>" class="dashboard-action dashboard-btn-small" aria-label="Reagendar"><i class="bi bi-arrow-repeat"></i> Reagendar</a>
                            <form method="post" action="../cancelar.php" class="d-inline form-cancelar">
                                <input type="hidden" name="idAgendamento" value="<?= (int)$prox['idAgendamento'] ?>">
                                <button type="button" class="dashboard-action dashboard-btn-small btn-open-cancelar" aria-label="Cancelar agendamento"><i class="bi bi-x-circle"></i> Cancelar</button>
                            </form>
                        <?php else: ?>
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-plus-circle"></i> Agendar agora</a>
                            <?php if (!empty($prefill['unidadeId']) && !empty($prefill['barbeiroId']) && !empty($prefill['servicoId'])): ?>
                            <a href="../agendamento.php?unidade=<?= (int)$prefill['unidadeId'] ?>&barbeiro=<?= (int)$prefill['barbeiroId'] ?>&servico=<?= (int)$prefill['servicoId'] ?>" class="dashboard-action dashboard-btn-small"><i class="bi bi-arrow-repeat"></i> Repetir último</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de confirmação de cancelamento -->
    <div class="modal fade" id="modalCancelarCli" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> Confirmar cancelamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">Deseja cancelar este agendamento?</div>
                <div class="modal-footer border-secondary d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-arrow-left"></i> Voltar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmCancelarCli"><i class="bi bi-x"></i> Cancelar agendamento</button>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-event"></i> Meus Agendamentos</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small btn-novo-agendamento"><i class="bi bi-plus-circle"></i> Novo</a>
                        </div>
                        <div class="col-auto">
                            <a href="agendamentos_usuario.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-pencil"></i> Editar</a>
                        </div>
                        <?php if (!empty($prefill['unidadeId']) && !empty($prefill['barbeiroId']) && !empty($prefill['servicoId'])): ?>
                        <div class="col-auto">
                            <a href="../agendamento.php?unidade=<?= (int)$prefill['unidadeId'] ?>&barbeiro=<?= (int)$prefill['barbeiroId'] ?>&servico=<?= (int)$prefill['servicoId'] ?>" class="dashboard-action dashboard-btn-small"><i class="bi bi-arrow-repeat"></i> Repetir último serviço</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive flex-fill table-container-fix">
                <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Unidade</th>
                            <th>Barbeiro</th>
                            <th>Serviços</th>
                            <th>Total</th>
                            <th>Duração</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                    <td title="<?= $row['hora'] ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeUnidade']) ?>"><?= substr(htmlspecialchars($row['nomeUnidade']), 0, 8) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeBarbeiro']) ?>"><?= substr(htmlspecialchars($row['nomeBarbeiro']), 0, 10) ?></td>
                                    <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= substr(htmlspecialchars($row['servicos']), 0, 12) ?></td>
                                    <td title="R$ <?= number_format($row['precoTotal'],2,',','.') ?>">R$ <?= number_format($row['precoTotal'],0,',','.') ?></td>
                                    <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                    <td title="<?= htmlspecialchars($row['statusAgendamento']) ?>"><?= substr(htmlspecialchars($row['statusAgendamento']), 0, 6) ?></td>
                                </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-warning">Nenhum agendamento encontrado. <a href="../agendamento.php" class="dashboard-action dashboard-btn-small">Agende agora</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <!-- Card Perfil e Info -->
        <div class="col-12 col-lg-5 d-flex flex-column gap-4">
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-circle"></i> Meu Perfil</div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b>Nome:</b> <?= htmlspecialchars($nomeCompleto) ?></div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b>Email:</b> <?= htmlspecialchars($email) ?></div>
                <div class="mb-1 fs-5 fs-md-6 fs-lg-5"><b>Telefone:</b> <?= htmlspecialchars($telefone) ?></div>
                <a href="editar_perfil.php" class="dashboard-action mt-2 w-100"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                <a href="../logout.php" class="dashboard-action mt-2 w-100 dashboard-btn-logout"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-info-circle"></i> Informações úteis</div>
                <ul class="dashboard-info-list fs-5 fs-md-6 fs-lg-5">
                    <li>Você pode cancelar agendamentos neste painel.</li>
                    <li>Chegue com 5 minutos de antecedência para garantir seu horário.</li>
                    <li>Em caso de dúvidas, entre em contato pelo WhatsApp da barbearia.</li>
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
                const msg = data.message ? data.message.replace(/<[^>]+>/g, '').trim() : 'Você atingiu o limite de 5 agendamentos ativos. Cancele um agendamento existente antes de criar um novo.';
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