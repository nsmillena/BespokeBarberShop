<?php
session_start();
include "../includes/db.php";
include_once "../includes/helpers.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = $_SESSION['usuario_id'];

// Agendamentos ativos (esconde finalizados), agregando serviços/valores por agendamento
$stmtAtivos = $conn->prepare("
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
");
$stmtAtivos->bind_param("ii", $idCliente, $idCliente);
$stmtAtivos->execute();
$ativos = $stmtAtivos->get_result();

// Histórico (apenas finalizados) - últimos 5
$stmtHist = $conn->prepare("
SELECT 
    a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro,
    GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
    SUM(ahs.precoFinal) AS precoTotal,
    SUM(ahs.tempoEstimado) AS tempoTotal
FROM Agendamento a
JOIN Unidade u ON a.Unidade_idUnidade = u.idUnidade
JOIN Barbeiro b ON a.Barbeiro_idBarbeiro = b.idBarbeiro
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Cliente_idCliente = ?
  AND a.statusAgendamento = 'Finalizado'
    AND NOT EXISTS (
        SELECT 1 FROM AgendamentoOcultoCliente aoc
        WHERE aoc.Cliente_id = ? AND aoc.Agendamento_id = a.idAgendamento
    )
GROUP BY a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro
ORDER BY a.data DESC, a.hora DESC
LIMIT 5
");
$stmtHist->bind_param("ii", $idCliente, $idCliente);
$stmtHist->execute();
$historico = $stmtHist->get_result();

// Buscar último finalizado para rebook
$stmtUlt = $conn->prepare("SELECT a.Unidade_idUnidade AS unidadeId, a.Barbeiro_idBarbeiro AS barbeiroId, (SELECT ahs.Servico_idServico FROM Agendamento_has_Servico ahs WHERE ahs.Agendamento_idAgendamento=a.idAgendamento LIMIT 1) AS servicoId FROM Agendamento a WHERE a.Cliente_idCliente = ? AND a.statusAgendamento='Finalizado' ORDER BY a.data DESC, a.hora DESC LIMIT 1");
$stmtUlt->bind_param("i", $idCliente);
$stmtUlt->execute();
$prefill = $stmtUlt->get_result()->fetch_assoc();
$stmtUlt->close();

$ok = isset($_GET['ok']) ? $_GET['ok'] : null;
$msg = isset($_GET['msg']) ? $_GET['msg'] : null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Agendamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
    <style>
      /* Ajuste local: garantir que a tabela permita scroll horizontal em telas pequenas */
      .table-responsive, .table-container-fix { overflow-x: auto !important; }
    </style>
    </head>
<body class="dashboard-user">
<div class="container py-4">
    <div class="row mb-3">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-welcome-text"><i class="bi bi-calendar-event"></i> Meus Agendamentos</span>
            </div>
        </div>
    </div>

    <?php if ($msg !== null): ?>
        <div class="alert alert-<?= ($ok === '1') ? 'success' : 'danger' ?> py-2" role="alert">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
    <div class="col-12 col-lg-12 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-5 fs-md-4 fs-lg-3"><i class="bi bi-hourglass-split"></i> Agendados e Pendentes</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small btn-novo-agendamento"><i class="bi bi-plus-circle"></i> Novo</a>
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
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($ativos->num_rows > 0): ?>
                            <?php while($row = $ativos->fetch_assoc()): ?>
                                <tr>
                                    <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                    <td title="<?= htmlspecialchars($row['hora']) ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeUnidade']) ?>"><?= substr(htmlspecialchars($row['nomeUnidade']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeBarbeiro']) ?>"><?= substr(htmlspecialchars($row['nomeBarbeiro']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= substr(htmlspecialchars($row['servicos']), 0, 24) ?></td>
                                    <td title="R$ <?= number_format($row['precoTotal'],2,',','.') ?>">R$ <?= number_format($row['precoTotal'],0,',','.') ?></td>
                                    <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                    <td title="<?= htmlspecialchars($row['statusAgendamento']) ?>"><?= substr(htmlspecialchars($row['statusAgendamento']), 0, 10) ?></td>
                                    <td>
                                        <?php if($row['statusAgendamento'] === 'Agendado'): ?>
                                            <form method="POST" action="../cancelar.php" onsubmit="return confirm('Tem certeza que deseja cancelar este agendamento?');" class="d-inline">
                                                <input type="hidden" name="idAgendamento" value="<?= (int)$row['idAgendamento'] ?>">
                                                <button type="submit" class="dashboard-action dashboard-btn-small"><i class="bi bi-x-circle"></i> Cancelar</button>
                                            </form>
                                        <?php elseif($row['statusAgendamento'] === 'Cancelado'): ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="../agendamento.php" class="btn btn-warning btn-sm">Reagendar</a>
                                                    <form method="POST" action="../apagar.php" onsubmit="return confirm('Confirmar cancelamento deste agendamento? Ele não aparecerá mais para você.');" style="display:inline;">
                                                        <input type="hidden" name="idAgendamento" value="<?= $row['idAgendamento'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Confirmar</button>
                                                    </form>
                                                </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center text-warning">Nenhum agendamento ativo encontrado. <a href="../agendamento.php" class="dashboard-action dashboard-btn-small">Agende agora</a></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Histórico de finalizados -->
    <div class="col-12 col-lg-12 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-5 fs-md-4 fs-lg-3"><i class="bi bi-clock-history"></i> Histórico recente (Finalizados)</div>
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
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($historico->num_rows > 0): ?>
                            <?php while($row = $historico->fetch_assoc()): ?>
                                <tr>
                                    <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                    <td title="<?= htmlspecialchars($row['hora']) ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeUnidade']) ?>"><?= substr(htmlspecialchars($row['nomeUnidade']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeBarbeiro']) ?>"><?= substr(htmlspecialchars($row['nomeBarbeiro']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= substr(htmlspecialchars($row['servicos']), 0, 24) ?></td>
                                    <td title="R$ <?= number_format($row['precoTotal'],2,',','.') ?>">R$ <?= number_format($row['precoTotal'],0,',','.') ?></td>
                                    <td title="<?= (int)$row['tempoTotal'] ?> minutos"><?= bb_format_minutes((int)$row['tempoTotal']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted">Sem histórico recente.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="index_usuario.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-arrow-left"></i> Voltar</a>
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
    toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`;
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
            window.location.href = href; // fallback
        }
    });
});
</script>
</body>
</html>