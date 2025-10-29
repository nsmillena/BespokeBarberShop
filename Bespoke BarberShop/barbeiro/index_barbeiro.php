<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
include_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idBarbeiro = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT nomeBarbeiro, emailBarbeiro, telefoneBarbeiro, deveTrocarSenha FROM Barbeiro WHERE idBarbeiro = ?");
$stmt->bind_param("i", $idBarbeiro);
$stmt->execute();
$stmt->bind_result($nomeCompleto, $email, $telefone, $deveTrocarSenha);
$stmt->fetch();
$stmt->close();
$primeiroNome = explode(' ', trim($nomeCompleto))[0];

// Se for obrigatório trocar a senha, redireciona para a página de edição
if ((int)$deveTrocarSenha === 1) {
    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Troca de senha obrigatória no primeiro acesso.'));
    exit;
}

// Buscar próximos agendamentos (>= hoje), agregados
$dataHoje = date('Y-m-d');
// KPIs: Hoje (Agendados / Finalizados / Duração total do dia)
$kpiAgHoje = 0; $kpiFinHoje = 0; $durHoje = 0;
// Agendados hoje
if ($stK1 = $conn->prepare("SELECT COUNT(DISTINCT a.idAgendamento) AS qtd, COALESCE(SUM(ahs.tempoEstimado),0) AS dur FROM Agendamento a JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento=a.idAgendamento WHERE a.Barbeiro_idBarbeiro=? AND a.data=? AND a.statusAgendamento='Agendado'")){
    $stK1->bind_param('is', $idBarbeiro, $dataHoje);
    $stK1->execute(); $stK1->bind_result($kpiAgHoje, $durHoje); $stK1->fetch(); $stK1->close();
}
// Finalizados hoje
if ($stK2 = $conn->prepare("SELECT COUNT(DISTINCT a.idAgendamento) AS qtd FROM Agendamento a WHERE a.Barbeiro_idBarbeiro=? AND a.data=? AND a.statusAgendamento='Finalizado'")){
    $stK2->bind_param('is', $idBarbeiro, $dataHoje);
    $stK2->execute(); $stK2->bind_result($kpiFinHoje); $stK2->fetch(); $stK2->close();
}
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

// Próximo atendimento (mais próximo a partir de agora)
$prox = null;
if ($stNext = $conn->prepare("SELECT a.idAgendamento, a.data, a.hora, c.nomeCliente, GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos, SUM(ahs.precoFinal) AS precoTotal, SUM(ahs.tempoEstimado) AS tempoTotal FROM Agendamento a JOIN Cliente c ON c.idCliente=a.Cliente_idCliente JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento=a.idAgendamento JOIN Servico s ON s.idServico=ahs.Servico_idServico WHERE a.Barbeiro_idBarbeiro=? AND a.statusAgendamento='Agendado' AND (a.data > CURDATE() OR (a.data = CURDATE() AND a.hora >= CURTIME())) GROUP BY a.idAgendamento, a.data, a.hora, c.nomeCliente ORDER BY a.data ASC, a.hora ASC LIMIT 1")){
    $stNext->bind_param('i', $idBarbeiro);
    $stNext->execute(); $rNext = $stNext->get_result(); $prox = $rNext->fetch_assoc(); $stNext->close();
}

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
    <!-- Resumo do dia e Próximo atendimento -->
    <div class="row g-4 mb-3">
        <div class="col-12">
            <div class="dashboard-card p-3 kpi-accent summary-today">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-speedometer2"></i> Resumo de hoje</div>
                    <div class="date-chip"><?= date('d/m') ?></div>
                </div>
                <div class="row g-3 kpi-row">
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-top"><i class="bi bi-calendar-day kpi-icon"></i><span class="kpi-label">Agendados</span></div>
                            <div class="kpi-value"><?= (int)$kpiAgHoje ?></div>
                            <div class="kpi-sub">Hoje • <?= date('d/m') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-top"><i class="bi bi-check2-circle kpi-icon"></i><span class="kpi-label">Finalizados</span></div>
                            <div class="kpi-value"><?= (int)$kpiFinHoje ?></div>
                            <div class="kpi-sub">Hoje • <?= date('d/m') ?></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-top"><i class="bi bi-clock-history kpi-icon"></i><span class="kpi-label">Duração</span></div>
                            <div class="kpi-value"><?= ((int)$durHoje > 0 ? bb_format_minutes((int)$durHoje) : '—') ?></div>
                            <div class="kpi-sub">Total de hoje • <?= date('d/m') ?></div>
                        </div>
                    </div>
                </div>
                <hr class="kpi-divider my-3" />
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <div class="next-card p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3 flex-wrap meta-wrap">
                                    <div class="badge bg-warning text-dark fw-bold"><i class="bi bi-lightning-charge"></i> Próximo</div>
                                    <?php if($prox): ?>
                                        <span class="meta-chip"><i class="bi bi-clock"></i> <?= substr($prox['hora'],0,5) ?> • <?= date('d/m', strtotime($prox['data'])) ?></span>
                                        <span class="meta-chip"><i class="bi bi-person"></i> <?= htmlspecialchars($prox['nomeCliente']) ?></span>
                                        <span class="meta-chip text-truncate" style="max-width:320px;"><i class="bi bi-scissors"></i> <?= htmlspecialchars($prox['servicos']) ?></span>
                                    <?php else: ?>
                                        <div class="text-muted">Sem próximo atendimento agendado.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="sticky-mobile">
                                    <?php if($prox): ?>
                                    <form method="post" action="concluir_agendamento.php" class="d-inline">
                                        <input type="hidden" name="idAgendamento" value="<?= (int)$prox['idAgendamento'] ?>">
                                        <button type="submit" class="dashboard-action dashboard-btn-small"><i class="bi bi-check2-circle"></i> Concluir</button>
                                    </form>
                                    <?php else: ?>
                                    <a href="agendamentos_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-eye"></i> Ver agenda</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Toast container for feedback messages (always present) -->
    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" id="toast-msg-container" style="z-index:1080;"></div>
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
                    <div class="row gx-2 align-items-center">
                        <?php $hoje = date('Y-m-d'); $amanha = date('Y-m-d', strtotime('+1 day')); $semIni = $hoje; $semFim = date('Y-m-d', strtotime('+6 days')); ?>
                        <div class="col-auto d-flex gap-2 flex-wrap">
                            <a href="agendamentos_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-eye"></i> Ver Todos</a>
                            <a href="agendamentos_barbeiro.php?inicio=<?= $hoje ?>&fim=<?= $hoje ?>" class="dashboard-action dashboard-btn-small" title="Hoje">Hoje</a>
                            <a href="agendamentos_barbeiro.php?inicio=<?= $amanha ?>&fim=<?= $amanha ?>" class="dashboard-action dashboard-btn-small" title="Amanhã">Amanhã</a>
                            <a href="agendamentos_barbeiro.php?inicio=<?= $semIni ?>&fim=<?= $semFim ?>" class="dashboard-action dashboard-btn-small" title="Semana">Semana</a>
                        </div>
                    </div>
                </div>
                <?php if($temAgendamentosHoje): ?>
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
                                    <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                    <td>
                                        <?php 
                                            $isHoje = (date('Y-m-d', strtotime($row['data'])) === date('Y-m-d'));
                                            $isAtrasado = $isHoje && (substr($row['hora'],0,5) < date('H:i')) && $row['statusAgendamento']==='Agendado';
                                        ?>
                                        <?php if($isAtrasado): ?>
                                            <span class="badge badge-atrasado"><i class="bi bi-exclamation-triangle-fill"></i> Atrasado</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Agendado</span>
                                        <?php endif; ?>
                                    </td>
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
                                        <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
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
                <a href="bloqueios.php" class="dashboard-action mt-2 w-100"><i class="bi bi-calendar-x"></i> Bloquear horários</a>
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