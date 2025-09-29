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

// Buscar agendamentos do dia
$dataHoje = date('Y-m-d');
$stmt2 = $conn->prepare("
SELECT a.idAgendamento, a.data, a.hora, c.nomeCliente, s.nomeServico, ahs.precoFinal, ahs.tempoEstimado, a.statusAgendamento
FROM Agendamento a
JOIN Cliente c ON a.Cliente_idCliente = c.idCliente
JOIN Agendamento_has_Servico ahs ON a.idAgendamento = ahs.Agendamento_idAgendamento
JOIN Servico s ON ahs.Servico_idServico = s.idServico
WHERE a.Barbeiro_idBarbeiro = ? AND a.data = ?
ORDER BY a.hora ASC
");
$stmt2->bind_param("is", $idBarbeiro, $dataHoje);
$stmt2->execute();
$resultHoje = $stmt2->get_result();
$temAgendamentosHoje = $resultHoje->num_rows > 0;
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
                <span class="dashboard-title"><i class="bi bi-scissors"></i> Bem-vindo, Barbeiro <?= htmlspecialchars($primeiroNome) ?>!</span>
            </div>
        </div>
    </div>
    <div class="row g-3">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7">
            <div class="dashboard-card p-3 flex-fill">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0"><i class="bi bi-calendar-event"></i> Meus Agendamentos de Hoje</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="agendamentos_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-pencil"></i> Ver Todos</a>
                        </div>
                    </div>
                </div>
                <?php if($temAgendamentosHoje): ?>
                <div class="table-responsive flex-fill mb-2">
                    <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Preço</th>
                                <th>Duração</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $resultHoje->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['hora']) ?></td>
                                    <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                                    <td><?= htmlspecialchars($row['nomeServico']) ?></td>
                                    <td>R$ <?= number_format($row['precoFinal'],2,',','.') ?></td>
                                    <td><?= $row['tempoEstimado'] ?> min</td>
                                    <td><?= htmlspecialchars($row['statusAgendamento']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <ul class="dashboard-info-list text-warning mb-2" style="font-size:1rem;">
                    <li>Visualize todos os seus agendamentos e detalhes dos clientes.</li>
                    <li>Mantenha-se atento aos horários e serviços marcados.</li>
                </ul>
            </div>
        </div>
        <!-- Card Perfil e Info -->
        <div class="col-12 col-lg-5">
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2"><i class="bi bi-person-circle"></i> Meu Perfil</div>
                <div class="mb-1"><b>Nome:</b> <?= htmlspecialchars($nomeCompleto) ?></div>
                <div class="mb-1"><b>Email:</b> <?= htmlspecialchars($email) ?></div>
                <div class="mb-1"><b>Telefone:</b> <?= htmlspecialchars($telefone) ?></div>
                <a href="editar_perfil.php" class="dashboard-btn-sutil mt-2 w-100"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                <a href="../logout.php" class="dashboard-btn-sutil mt-2 w-100"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
            <div class="dashboard-card p-3 flex-fill mb-0">
                <div class="dashboard-section-title mb-2"><i class="bi bi-info-circle"></i> Informações úteis</div>
                <ul class="dashboard-info-list">
                    <li>Consulte seus agendamentos para se organizar melhor.</li>
                    <li>Em caso de dúvidas, entre em contato com a administração.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>