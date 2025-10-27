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

$stmt = $conn->prepare("
SELECT a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente, s.nomeServico, ahs.precoFinal, ahs.tempoEstimado
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ?
ORDER BY a.data DESC, a.hora DESC
");
SELECT a.data, a.hora, c.nomeCliente, s.nomeServico, ahs.precoFinal, ahs.tempoEstimado
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ?
ORDER BY a.data DESC, a.hora DESC
");
$stmt->bind_param("i", $idBarbeiro);
$stmt->execute();
$result = $stmt->get_result();
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
                <span class="dashboard-title"><i class="bi bi-calendar-week"></i> Todos os seus agendamentos</span>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12">
            <div class="dashboard-card p-3">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-<?php echo (isset($_GET['ok']) && $_GET['ok'] === '1') ? 'success' : 'danger'; ?> py-2" role="alert">
                        <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>
                <div class='table-responsive table-container-fix'>
                    <table class='table table-dark table-striped table-sm align-middle mb-0 dashboard-table'>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Preço</th>
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
                                <td><?= htmlspecialchars($row['nomeServico']) ?></td>
                                <td>R$ <?= number_format($row['precoFinal'],2,',','.') ?></td>
                                <td><?= (int)$row['tempoEstimado'] ?> min</td>
                                <td><?= htmlspecialchars($status) ?></td>
                                <td>
                                    <?php if ($status === 'Agendado' && $idAg > 0): ?>
                                        <form method="post" action="concluir_agendamento.php" class="d-inline">
                                            <input type="hidden" name="idAgendamento" value="<?= $idAg ?>">
                                            <button type="submit" class="dashboard-action dashboard-btn-small"><i class="bi bi-check2-circle"></i> Concluir</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success">Finalizado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href='index_barbeiro.php' class='dashboard-action dashboard-btn-small'><i class="bi bi-arrow-left"></i> Voltar</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>