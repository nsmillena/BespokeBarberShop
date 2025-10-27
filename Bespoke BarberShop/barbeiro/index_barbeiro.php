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
WHERE a.Barbeiro_idBarbeiro = ?
    AND a.data = CURDATE()
    AND a.statusAgendamento IN ('Agendado','Finalizado')
ORDER BY a.hora ASC
");
$stmt2->bind_param("i", $idBarbeiro);
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
                <span class="dashboard-title fs-1 fs-md-2 fs-lg-1"><i class="bi bi-scissors"></i> Bem-vindo, Barbeiro <?= htmlspecialchars($primeiroNome) ?>!</span>
            </div>
        </div>
    </div>
    <div class="row g-4 justify-content-center">
        <!-- Card Agendamentos -->
        <div class="col-12 col-lg-7 d-flex align-items-stretch">
            <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-event"></i> Meus Agendamentos de Hoje</div>
                    <div class="row gx-2">
                        <div class="col-auto">
                            <a href="agendamentos_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-eye"></i> Ver Todos</a>
                        </div>
                    </div>
                </div>
                <?php if($temAgendamentosHoje): ?>
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-<?php echo (isset($_GET['ok']) && $_GET['ok'] === '1') ? 'success' : 'danger'; ?> py-2" role="alert">
                        <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive flex-fill mb-2 table-container-fix">
                    <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
                        <thead>
                            <tr>
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
                            <?php while($row = $resultHoje->fetch_assoc()) { ?>
                                <tr>
                                    <td title="<?= htmlspecialchars($row['hora']) ?>"><?= substr($row['hora'], 0, 5) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeCliente']) ?>"><?= substr(htmlspecialchars($row['nomeCliente']), 0, 12) ?></td>
                                    <td title="<?= htmlspecialchars($row['nomeServico']) ?>"><?= substr(htmlspecialchars($row['nomeServico']), 0, 15) ?></td>
                                    <td title="R$ <?= number_format($row['precoFinal'],2,',','.') ?>">R$ <?= number_format($row['precoFinal'],0,',','.') ?></td>
                                    <td title="<?= $row['tempoEstimado'] ?> minutos"><?= $row['tempoEstimado'] ?>m</td>
                                    <td title="<?= htmlspecialchars($row['statusAgendamento']) ?>"><?= substr(htmlspecialchars($row['statusAgendamento']), 0, 8) ?></td>
                                    <td>
                                        <?php if ($row['statusAgendamento'] === 'Agendado'): ?>
                                            <form method="post" action="concluir_agendamento.php" class="d-inline">
                                                <input type="hidden" name="idAgendamento" value="<?= (int)$row['idAgendamento'] ?>">
                                                <button type="submit" class="dashboard-action dashboard-btn-small"><i class="bi bi-check2-circle"></i> Concluir</button>
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
                        <p>Nenhum agendamento para hoje.</p>
                    </div>
                </div>
                <?php endif; ?>
                <ul class="dashboard-info-list text-warning mb-2 fs-5 fs-md-6 fs-lg-5" style="font-size:1rem;">
                    <li>Visualize todos os seus agendamentos e detalhes dos clientes.</li>
                    <li>Mantenha-se atento aos horários e serviços marcados.</li>
                </ul>
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
</body>
</html>