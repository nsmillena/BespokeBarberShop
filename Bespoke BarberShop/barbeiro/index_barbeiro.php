<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idBarbeiro = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT nomeBarbeiro, emailBarbeiro, telefoneBarbeiro FROM Barbeiro WHERE idBarbeiro = ?");
$stmt->bind_param("i", $idBarbeiro);
$stmt->execute();
$stmt->bind_result($nomeCompleto, $email, $telefone);
$stmt->fetch();
$stmt->close();
$primeiroNome = explode(' ', trim($nomeCompleto))[0];

// Buscar próximos agendamentos (>= hoje), agregados
$dataHoje = date('Y-m-d');
$stmt2 = $conn->prepare("
SELECT
    a.idAgendamento, a.data, a.hora, c.nomeCliente,
    GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
    SUM(ahs.precoFinal) AS precoTotal,
    SUM(ahs.tempoEstimado) AS tempoTotal,
    a.statusAgendamento
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ?
    AND a.data >= ?
    AND a.statusAgendamento = 'Agendado'
GROUP BY a.idAgendamento, a.data, a.hora, c.nomeCliente, a.statusAgendamento
ORDER BY a.data ASC, a.hora ASC
LIMIT 5
");
$stmt2->bind_param("is", $idBarbeiro, $dataHoje);
$stmt2->execute();
$resultHoje = $stmt2->get_result();
$temAgendamentosHoje = $resultHoje->num_rows > 0;

// Histórico recente (Finalizados) - últimos 5
$stmtHist = $conn->prepare("
SELECT
        a.idAgendamento, a.data, a.hora, c.nomeCliente,
        GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
        SUM(ahs.precoFinal) AS precoTotal,
        SUM(ahs.tempoEstimado) AS tempoTotal
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ?
    AND a.statusAgendamento = 'Finalizado'
    AND a.data >= ?
GROUP BY a.idAgendamento, a.data, a.hora, c.nomeCliente
ORDER BY a.data DESC, a.hora DESC
LIMIT 5
");
$cutoff3 = date('Y-m-d', strtotime('-3 days'));
$stmtHist->bind_param("is", $idBarbeiro, $cutoff3);
$stmtHist->execute();
$resultHist = $stmtHist->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Barbeiro | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_barbeiro.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-barbeiro-novo">
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-title fs-1 fs-md-2 fs-lg-1"><i class="bi bi-scissors"></i> Bem-vindo, Barbeiro <?= htmlspecialchars($primeiroNome) ?>!</span>
            </div>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-week"></i> Próximos Atendimentos</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="agendamentos_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-eye"></i> Ver Todos</a>
                        </div>
                    </div>
                </div>
                <?php if($temAgendamentosHoje): ?>
                <!-- Toast container for feedback messages -->
                <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toast-msg-container" style="z-index:1080;"></div>
                <div class="table-responsive flex-fill mb-2 table-container-fix">
                    <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviços</th>
                                <th>Total</th>
                                <th>Duração</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $resultHoje->fetch_assoc()) { ?>
                                <tr>
                                    <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                    <td title="<?= htmlspecialchars($row['hora']) ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeCliente']) ?>"><?= substr(htmlspecialchars($row['nomeCliente']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= substr(htmlspecialchars($row['servicos']), 0, 22) ?></td>
                                    <td title="R$ <?= number_format($row['precoTotal'],2,',','.') ?>">R$ <?= number_format($row['precoTotal'],0,',','.') ?></td>
                                    <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= (int)$row['tempoTotal'] ?>m</td>
                                    <td title="<?= htmlspecialchars($row['statusAgendamento']) ?>"><?= substr(htmlspecialchars($row['statusAgendamento']), 0, 8) ?></td>
                                    <td>
                                        <?php if ($row['statusAgendamento'] === 'Agendado'): ?>
                                            <form method="post" action="concluir_agendamento.php" class="d-inline form-concluir">
                                                <input type="hidden" name="idAgendamento" value="<?= (int)$row['idAgendamento'] ?>">
                                                <button type="button" class="dashboard-action dashboard-btn-small btn-open-concluir btn-concluir"><i class="bi bi-check2-circle"></i> Concluir</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success">Finalizado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="flex-fill">
                    <div class="text-center text-warning mb-3">
                        <p>Nenhum atendimento futuro encontrado.</p>
                    </div>
                </div>
                <?php endif; ?>
                <ul class="dashboard-info-list text-warning mb-2 fs-5 fs-md-6 fs-lg-5" style="font-size:1rem;">
                    <li>Visualize todos os seus atendimentos e detalhes dos clientes.</li>
                    <li>Mantenha-se atento aos horários e serviços marcados.</li>
                </ul>
            </div>
        </div>
        <!-- Histórico recente -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-clock-history"></i> Histórico recente de atendimentos</div>
                </div>
                <div class="table-responsive flex-fill mb-2 table-container-fix">
                    <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviços</th>
                                <th>Total</th>
                                <th>Duração</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultHist->num_rows > 0): ?>
                                <?php while($row = $resultHist->fetch_assoc()) { ?>
                                    <tr>
                                        <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                        <td title="<?= htmlspecialchars($row['hora']) ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                        <td title="<?= htmlspecialchars($row['nomeCliente']) ?>"><?= substr(htmlspecialchars($row['nomeCliente']), 0, 12) ?></td>
                                        <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= substr(htmlspecialchars($row['servicos']), 0, 22) ?></td>
                                        <td title="R$ <?= number_format($row['precoTotal'],2,',','.') ?>">R$ <?= number_format($row['precoTotal'],0,',','.') ?></td>
                                        <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= (int)$row['tempoTotal'] ?>m</td>
                                        <td><span class="badge bg-success">Finalizado</span></td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted">Sem finalizações recentes.</td></tr>
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
                    <li>Consulte seus agendamentos para se organizar melhor.</li>
                    <li>Em caso de dúvidas, entre em contato com a administração.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<!-- Modal de confirmação de conclusão -->
<div class="modal fade" id="modalConcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-check2-circle"></i> Confirmar conclusão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Deseja marcar este agendamento como concluído?
            </div>
            <div class="modal-footer border-secondary d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmConcluir"><i class="bi bi-check"></i> Confirmar</button>
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
    if (formToSubmit) { formToSubmit.submit(); }
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
    toastEl.innerHTML = `<div class=\"d-flex\"><div class=\"toast-body\">${message}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`;
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