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
    <title>Meus Agendamentos - Barbeiro</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='container py-5'>
    <h2 class='mb-4'>Agendamentos para você</h2>
    <table class='table table-dark table-striped'>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Serviço</th>
                <th>Preço</th>
                <th>Duração</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                <td><?= $row['hora'] ?></td>
                <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                <td><?= htmlspecialchars($row['nomeServico']) ?></td>
                <td>R$ <?= number_format($row['precoFinal'],2,',','.') ?></td>
                <td><?= $row['tempoEstimado'] ?> min</td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <a href='index.php' class='btn btn-secondary'>Voltar</a>
</body>
</html>