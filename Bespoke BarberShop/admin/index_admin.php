
<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Conexão e busca do nome do admin e unidade
include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = $_SESSION['usuario_id'];
$nome_admin = 'Administrador';
$unidade_nome = '';
$unidade_id = null;

$sql = $conn->prepare("SELECT nomeAdmin, Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($nome_admin, $unidade_id);
if ($sql->fetch()) {
    $sql->close(); // FECHA ANTES DE ABRIR OUTRO STATEMENT
    // Buscar nome da unidade
    $sql2 = $conn->prepare("SELECT nomeUnidade FROM Unidade WHERE idUnidade = ?");
    $sql2->bind_param("i", $unidade_id);
    $sql2->execute();
    $sql2->bind_result($unidade_nome);
    $sql2->fetch();
    $sql2->close();
} else {
    $sql->close();
}

// Buscar barbeiros da unidade
$barbeiros = [];
if (!empty($unidade_id)) {
    $sql3 = $conn->prepare("SELECT nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade = ? AND statusBarbeiro = 'Ativo'");
    $sql3->bind_param("i", $unidade_id);
    $sql3->execute();
    $result = $sql3->get_result();
    while ($row = $result->fetch_assoc()) {
        $barbeiros[] = $row['nomeBarbeiro'];
    }
    $sql3->close();
}

// Buscar serviços da UNIDADE (quando vinculados); se não houver unidade, mostrar catálogo global
$servicos = [];
if (!empty($unidade_id)) {
    $resServ = $conn->prepare("SELECT s.idServico, s.nomeServico FROM Unidade_has_Servico uhs JOIN Servico s ON s.idServico = uhs.Servico_idServico WHERE uhs.Unidade_idUnidade = ? ORDER BY s.nomeServico");
    $resServ->bind_param("i", $unidade_id);
    $resServ->execute();
    $rs = $resServ->get_result();
    while($row = $rs->fetch_assoc()) { $servicos[] = $row; }
    $resServ->close();
} else {
    $rs2 = $conn->query("SELECT idServico, nomeServico FROM Servico ORDER BY nomeServico");
    while($row = $rs2->fetch_assoc()) { $servicos[] = $row; }
}

// Próximos agendamentos da unidade
$proxAg = [];
if (!empty($unidade_id)) {
    $stmtAg = $conn->prepare("
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
    WHERE a.Unidade_idUnidade = ? AND a.data >= CURDATE() AND a.statusAgendamento = 'Agendado'
    GROUP BY a.idAgendamento, a.data, a.hora, a.statusAgendamento, c.nomeCliente, b.nomeBarbeiro
    ORDER BY a.data ASC, a.hora ASC
    LIMIT 8
    ");
    $stmtAg->bind_param("i", $unidade_id);
    $stmtAg->execute();
    $rAg = $stmtAg->get_result();
    while($row = $rAg->fetch_assoc()) { $proxAg[] = $row; }
    $stmtAg->close();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Administrador</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="container py-4">
        <!-- Toast container -->
        <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card dashboard-welcome-card">
                    <span class="dashboard-welcome-text fs-1 fs-md-2 fs-lg-1">Bem-vindo, Administrador <?= htmlspecialchars($nome_admin) ?>!</span>
                    <div class="mt-2 fs-4 fs-md-5 fs-lg-4">Unidade: <span style="color:#daa520; font-weight:600;"><?= htmlspecialchars($unidade_nome) ?></span></div>
                </div>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <?php if (empty($unidade_id)): ?>
            <div class="col-12">
                <div class="alert alert-warning border-0" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> Este administrador ainda não está vinculado a uma unidade. Associe uma unidade para habilitar o gerenciamento de barbeiros e serviços.
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($unidade_id)): ?>
            <!-- Card Agendamentos da Unidade -->
            <div class="col-12 col-lg-12 d-flex align-items-stretch">
                <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-calendar-week"></i> Próximos Atendimentos da Unidade</div>
                        <div class="row gx-2">
                            <div class="col-auto">
                                <?php $d1 = date('Y-m-d'); $d2 = date('Y-m-d', strtotime('+30 days')); ?>
                                <a href="relatorios.php?status=Agendado&inicio=<?= $d1 ?>&fim=<?= $d2 ?>" class="dashboard-action dashboard-btn-small" title="Ver próximos 30 dias">
                                    <i class="bi bi-eye"></i> Ver todos
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-container-fix flex-fill">
                        <table class="table table-dark table-striped table-sm align-middle mb-0 dashboard-table">
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
                                <?php if (count($proxAg) > 0): foreach($proxAg as $row): ?>
                                    <tr>
                                        <td title="<?= date('d/m/Y', strtotime($row['data'])) ?>"><?= date('d/m', strtotime($row['data'])) ?></td>
                                        <td><?= substr($row['hora'],0,5) ?></td>
                                        <td><?= htmlspecialchars($row['nomeCliente']) ?></td>
                                        <td><?= htmlspecialchars($row['nomeBarbeiro']) ?></td>
                                        <td title="<?= htmlspecialchars($row['servicos']) ?>"><?= htmlspecialchars(strlen($row['servicos'])>28 ? substr($row['servicos'],0,28).'…' : $row['servicos']) ?></td>
                                        <td>R$ <?= number_format($row['precoTotal'], 2, ',', '.') ?></td>
                                        <td><?= (int)$row['tempoTotal'] ?> min</td>
                                        <td><?= htmlspecialchars($row['statusAgendamento']) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="8" class="text-center text-muted">Sem atendimentos futuros.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- Card de barbeiros -->
            <div class="col-12 col-lg-7 d-flex align-items-stretch">
                <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-badge"></i> Barbeiros da Unidade</div>
                        <div class="row gx-2">
                            <div class="col-auto">
                                <a href="cadastrar_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-person-plus"></i> Cadastrar</a>
                            </div>
                            <?php if (!empty($unidade_id)): ?>
                            <div class="col-auto">
                                <a href="gerenciar_barbeiros.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-people"></i> Gerenciar</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-fill">
                        <ul class="list-group list-group-flush mb-3">
                            <?php if (count($barbeiros) > 0): foreach($barbeiros as $b): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0 fs-5 fs-md-6 fs-lg-5"><i class="bi bi-scissors"></i> <?= htmlspecialchars($b) ?></li>
                            <?php endforeach; else: ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0">Nenhum barbeiro cadastrado.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="dashboard-section-title mb-2 mt-3 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-gear"></i> Serviços Disponíveis <?= !empty($unidade_id) ? 'da Unidade' : '(Catálogo)' ?></div>
                    <div class="flex-fill">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($servicos as $serv): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0 fs-5 fs-md-6 fs-lg-5"><i class="bi bi-check2-circle"></i> <?= htmlspecialchars($serv['nomeServico']) ?></li>
                            <?php endforeach; ?>
                            <?php if (empty($servicos)): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0">Nenhum serviço vinculado à unidade. <a href="gerenciar_servicos.php" class="text-warning">Vincular agora</a>.</li>
                            <?php endif; ?>
                        </ul>
                        <a href="gerenciar_servicos.php" class="dashboard-action w-100"><i class="bi bi-pencil-square"></i> Gerenciar Serviços</a>
                    </div>
                </div>
            </div>

            <!-- Card de perfil e ações -->
            <div class="col-12 col-lg-5 d-flex flex-column gap-4">
                <div class="dashboard-card p-3 flex-fill mb-0">
                    <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-circle"></i> Meu Perfil</div>
                    <div class="mb-2 fs-5 fs-md-6 fs-lg-5">Gerencie seus dados de acesso e informações pessoais.</div>
                    <a href="editar_perfil.php" class="dashboard-action mt-2 w-100"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                    <a href="../logout.php" class="dashboard-action mt-2 w-100 dashboard-btn-logout"><i class="bi bi-box-arrow-right"></i> Sair</a>
                </div>
                
                <div class="dashboard-card p-3 flex-fill mb-0">
                    <div class="dashboard-section-title mb-2 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-info-circle"></i> Informações Importantes</div>
                    <ul class="dashboard-info-list fs-5 fs-md-6 fs-lg-5">
                        <li>Gerencie barbeiros e serviços da sua unidade.</li>
                        <li>Monitore a qualidade do atendimento.</li>
                        <li>Mantenha os dados sempre atualizados.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toasts
        function getParam(name){ const url = new URL(window.location.href); return url.searchParams.get(name); }
        function showToast(message, ok){
            const container = document.getElementById('toast-msg-container'); if(!container || !message) return;
            const el = document.createElement('div');
            el.className = `toast align-items-center ${ok==='1' ? 'text-bg-success' : 'text-bg-danger'} border-0`;
            el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); el.setAttribute('aria-atomic','true');
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`;
            container.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
        }
        const msg = getParam('msg'); const ok = getParam('ok'); if (msg) { try { showToast(decodeURIComponent(msg), ok); } catch(_) { showToast(msg, ok); } }
    </script>
    <?php @include_once("../Footer/footer.html"); ?>
</body>
</html>