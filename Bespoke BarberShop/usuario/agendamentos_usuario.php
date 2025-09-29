<?php
session_start();
include "../includes/db.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
}

$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = $_SESSION['usuario_id'];

// Buscar também o idAgendamento e statusAgendamento
$stmt = $conn->prepare("
SELECT a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro, s.nomeServico, ahs.precoFinal, ahs.tempoEstimado, a.statusAgendamento
FROM Agendamento a
JOIN Unidade u ON a.Unidade_idUnidade = u.idUnidade
JOIN Barbeiro b ON a.Barbeiro_idBarbeiro = b.idBarbeiro
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Cliente_idCliente = ?
ORDER BY a.data DESC, a.hora DESC
");
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$result = $stmt->get_result();
$temAgendamentos = $result->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Agendamentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_agendamentos.css">
</head>
<body class="container py-5">
    <h2 class="mb-4">Meus Agendamentos</h2>
    <?php if ($temAgendamentos): ?>
        <table class="table table-dark table-striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Unidade</th>
                    <th>Barbeiro</th>
                    <th>Serviço</th>
                    <th>Preço</th>
                    <th>Duração</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                    <td><?= $row['hora'] ?></td>
                    <td><?= htmlspecialchars($row['nomeUnidade']) ?></td>
                    <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                    <td><?= htmlspecialchars($row['nomeServico']) ?></td>
                    <td>R$ <?= number_format($row['precoFinal'],2,',','.') ?></td>
                    <td><?= $row['tempoEstimado'] ?> min</td>
                    <td><?= htmlspecialchars($row['statusAgendamento']) ?></td>
                    <td>
                        <?php if($row['statusAgendamento'] === 'Agendado'): ?>
                        <form method="POST" action="../cancelar.php" onsubmit="return confirm('Tem certeza que deseja cancelar este agendamento?');">
                            <input type="hidden" name="idAgendamento" value="<?= $row['idAgendamento'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                        </form>
                        <?php elseif($row['statusAgendamento'] === 'Cancelado'): ?>
                            <div class="d-flex flex-column gap-1">
                                <a href="../agendamento.php" class="btn btn-warning btn-sm">Reagendar</a>
                                <form method="POST" action="../apagar.php" onsubmit="return confirm('Deseja apagar este agendamento permanentemente?');" style="display:inline;">
                                    <input type="hidden" name="idAgendamento" value="<?= $row['idAgendamento'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Apagar</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-warning text-center" style="background:#232323; color:#e0b973; border:none; font-size:1.1rem;">
            Nenhum agendamento marcado no momento.
        </div>
    <?php endif; ?>
    <a href="index_usuario.php" class="btn btn-secondary">Voltar</a>
</body>
</html>