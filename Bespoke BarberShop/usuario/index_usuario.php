<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
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
SELECT a.idAgendamento, a.data, a.hora, u.nomeUnidade, b.nomeBarbeiro, s.nomeServico, ahs.precoFinal, ahs.tempoEstimado, a.statusAgendamento
FROM Agendamento a
JOIN Unidade u ON a.Unidade_idUnidade = u.idUnidade
JOIN Barbeiro b ON a.Barbeiro_idBarbeiro = b.idBarbeiro
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Cliente_idCliente = ?
ORDER BY a.data DESC, a.hora DESC
");
$stmt2->bind_param("i", $idCliente);
$stmt2->execute();
$result = $stmt2->get_result();
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
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card dashboard-welcome-card">
                <span class="dashboard-welcome-text">Olá, <?= htmlspecialchars($primeiroNome) ?></span>
            </div>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-calendar-event"></i> Meus Agendamentos</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="../agendamento.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-plus-circle"></i> Novo</a>
                        </div>
                        <div class="col-auto">
                            <a href="agendamentos_usuario.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-pencil"></i> Editar</a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive flex-fill">
                <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                                    <td><?= $row['hora'] ?></td>
                                    <td><?= htmlspecialchars($row['nomeUnidade']) ?></td>
                                    <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                                    <td><?= htmlspecialchars($row['nomeServico']) ?></td>
                                    <td>R$ <?= number_format($row['precoFinal'],2,',','.') ?></td>
                                    <td><?= $row['tempoEstimado'] ?> min</td>
                                    <td><?= htmlspecialchars($row['statusAgendamento']) ?></td>
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
                <div class="dashboard-section-title mb-2"><i class="bi bi-person-circle"></i> Meu Perfil</div>
                <div class="mb-1"><b>Nome:</b> <?= htmlspecialchars($nomeCompleto) ?></div>
                <div class="mb-1"><b>Email:</b> <?= htmlspecialchars($email) ?></div>
                <div class="mb-1"><b>Telefone:</b> <?= htmlspecialchars($telefone) ?></div>
                <a href="editar_perfil.php" class="dashboard-action mt-2 w-100"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                <a href="../logout.php" class="dashboard-action mt-2 w-100 dashboard-btn-logout"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2"><i class="bi bi-info-circle"></i> Informações úteis</div>
                <ul class="dashboard-info-list">
                    <li>Você pode cancelar agendamentos neste painel.</li>
                    <li>Chegue com 5 minutos de antecedência para garantir seu horário.</li>
                    <li>Em caso de dúvidas, entre em contato pelo WhatsApp da barbearia.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>