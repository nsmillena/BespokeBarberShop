<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = $_SESSION['usuario_id'];

// Buscar unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();
if (empty($unidade_id)) {
    header('Location: index_admin.php?ok=0&msg=' . urlencode('Associe uma unidade ao admin para acessar relatórios.'));
    exit;
}

// Filtros
$hoje = date('Y-m-d');
$defaultStart = date('Y-m-01');
$defaultEnd = date('Y-m-t');
$inicio = isset($_GET['inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['inicio']) ? $_GET['inicio'] : $defaultStart;
$fim = isset($_GET['fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fim']) ? $_GET['fim'] : $defaultEnd;
$status = isset($_GET['status']) ? $_GET['status'] : 'Todos';
$permitidos = ['Agendado','Cancelado','Finalizado','Todos'];
if (!in_array($status, $permitidos)) { $status = 'Todos'; }
$barbeiro = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Barber list for filter (unit only)
$barbeiros = [];
$qb = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade=? ORDER BY nomeBarbeiro");
$qb->bind_param("i", $unidade_id);
$qb->execute();
$rb = $qb->get_result();
while ($row = $rb->fetch_assoc()) { $barbeiros[] = $row; }
$qb->close();

// Build WHERE
$where = "a.Unidade_idUnidade = ? AND a.data BETWEEN ? AND ?";
$params = [$unidade_id, $inicio, $fim];
$types = "iss";
if ($status !== 'Todos') { $where .= " AND a.statusAgendamento = ?"; $params[] = $status; $types .= "s"; }
if ($barbeiro > 0) { $where .= " AND a.Barbeiro_idBarbeiro = ?"; $params[] = $barbeiro; $types .= "i"; }

$sqlRep = "
SELECT a.idAgendamento, a.data, a.hora, a.statusAgendamento,
       c.nomeCliente, b.nomeBarbeiro,
       GROUP_CONCAT(s.nomeServico SEPARATOR ', ') AS servicos,
       SUM(ahs.precoFinal) AS precoTotal,
       SUM(ahs.tempoEstimado) AS tempoTotal
FROM Agendamento a
JOIN Cliente c ON c.idCliente = a.Cliente_idCliente
JOIN Barbeiro b ON b.idBarbeiro = a.Barbeiro_idBarbeiro
JOIN Agendamento_has_Servico ahs ON ahs.Agendamento_idAgendamento = a.idAgendamento
JOIN Servico s ON s.idServico = ahs.Servico_idServico
WHERE $where
GROUP BY a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente, b.nomeBarbeiro
ORDER BY a.data ASC, a.hora ASC
";

$stmt = $conn->prepare($sqlRep);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
$totQtd = 0; $totValor = 0.0; $totTempo = 0;
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $totQtd++;
    $totValor += (float)$r['precoTotal'];
    $totTempo += (int)$r['tempoTotal'];
}
$stmt->close();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_agendamentos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Data','Hora','Cliente','Barbeiro','Serviços','Total','Duração','Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            date('d/m/Y', strtotime($r['data'])),
            substr($r['hora'],0,5),
            $r['nomeCliente'],
            $r['nomeBarbeiro'],
            $r['servicos'],
            number_format($r['precoTotal'], 2, ',', '.'),
            (int)$r['tempoTotal'] . ' min',
            $r['statusAgendamento']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - Admin</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
<div class="container py-5">
    <div class="dashboard-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="dashboard-title mb-0"><i class="bi bi-graph-up"></i> Relatórios da Unidade</h2>
            <div class="d-flex gap-2">
                <a class="dashboard-action" href="index_admin.php"><i class="bi bi-arrow-left"></i> Voltar</a>
                <a class="dashboard-action" href="?inicio=<?= urlencode($inicio) ?>&fim=<?= urlencode($fim) ?>&status=<?= urlencode($status) ?>&barbeiro=<?= (int)$barbeiro ?>&export=csv"><i class="bi bi-download"></i> Exportar CSV</a>
            </div>
        </div>
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-sm-6 col-md-3">
                <label class="form-label">Início</label>
                <input type="date" name="inicio" class="form-control" value="<?= htmlspecialchars($inicio) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label">Fim</label>
                <input type="date" name="fim" class="form-control" value="<?= htmlspecialchars($fim) ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach ($permitidos as $opt): ?>
                        <option value="<?= $opt ?>" <?= $status===$opt?'selected':'' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label">Barbeiro</label>
                <select name="barbeiro" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($barbeiros as $b): ?>
                        <option value="<?= (int)$b['idBarbeiro'] ?>" <?= $barbeiro===(int)$b['idBarbeiro']?'selected':'' ?>><?= htmlspecialchars($b['nomeBarbeiro']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="dashboard-action"><i class="bi bi-funnel"></i> Aplicar Filtros</button>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="p-3 bg-dark rounded">
                    <div class="text-muted">Quantidade</div>
                    <div class="fs-3" style="color:#daa520; font-weight:700;"><?= (int)$totQtd ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 bg-dark rounded">
                    <div class="text-muted">Receita</div>
                    <div class="fs-3" style="color:#daa520; font-weight:700;">R$ <?= number_format($totValor, 2, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 bg-dark rounded">
                    <div class="text-muted">Duração Total</div>
                    <div class="fs-3" style="color:#daa520; font-weight:700;"><?= (int)$totTempo ?> min</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle dashboard-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Barbeiro</th>
                        <th>Serviços</th>
                        <th>Total</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows)>0): foreach ($rows as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                            <td><?= substr($row['hora'],0,5) ?></td>
                            <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                            <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                            <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= htmlspecialchars(strlen($row['servicos'])>32 ? substr($row['servicos'],0,32).'…' : $row['servicos']) ?></td>
                            <td>R$ <?= number_format($row['precoTotal'], 2, ',', '.') ?></td>
                            <td><?= (int)$row['tempoTotal'] ?> min</td>
                            <td><?= htmlspecialchars($row['statusAgendamento']) ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-center text-muted">Sem dados para os filtros selecionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php @include_once("../Footer/footer.html"); ?>
</body>
</html>
