<?php
session_start();
include "../includes/db.php";

if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}

$bd = new Banco();
$conn = $bd->getConexao();
$idBarbeiro = $_SESSION['usuario_id'];

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
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Agendamentos - Barbeiro</title>
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
            </div>
        </div>
    </div>
    <div class="row g-4">
        <!-- Filtros -->
        <div class="col-12">
            <div class="dashboard-card p-3 mb-0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-funnel"></i> Filtros</div>
                    <button class="dashboard-action dashboard-btn-small" type="button" data-bs-toggle="collapse" data-bs-target="#filtros" aria-expanded="false" aria-controls="filtros">
                        <i class="bi bi-sliders"></i> Filtrar
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
                        <a href="?status=<?= $opt ?>" class="dashboard-action dashboard-btn-small" <?= $active ?>><i class="bi <?= $mk($opt) ?>"></i> <?= $opt ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-2 text-warning" style="font-size:0.95rem;">Filtro atual de atendimentos: <b><?= htmlspecialchars($status) ?></b></div>
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
                            <?php while($row = $result->fetch_assoc()): ?>
                            <?php $status = $row['statusAgendamento']; $idAg = (int)$row['idAgendamento']; ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                                <td><?= substr($row['hora'], 0, 5) ?></td>
                                <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                                <td><?= htmlspecialchars($row['servicos']) ?></td>
                                <td>R$ <?= number_format($row['precoTotal'],2,',','.') ?></td>
                                <td><?= (int)$row['tempoTotal'] ?> min</td>
                                <td><?= htmlspecialchars($status) ?></td>
                                <td>
                                    <?php if ($status === 'Agendado' && $idAg > 0): ?>
                                        <form method="post" action="concluir_agendamento.php" class="d-inline form-concluir" data-id="<?= $idAg ?>">
                                            <input type="hidden" name="idAgendamento" value="<?= $idAg ?>">
                                            <button type="button" class="dashboard-action dashboard-btn-small btn-open-concluir btn-concluir"><i class="bi bi-check2-circle"></i> Concluir</button>
                                        </form>
                                    <?php elseif ($status === 'Cancelado'): ?>
                                        <span class="badge bg-danger">Cancelado</span>
                                    <?php elseif ($status === 'Finalizado'): ?>
                                        <span class="badge bg-success">Finalizado</span>
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
                    <a href='index_barbeiro.php' class='dashboard-action dashboard-btn-small'><i class="bi bi-arrow-left"></i> Voltar ao painel</a>
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