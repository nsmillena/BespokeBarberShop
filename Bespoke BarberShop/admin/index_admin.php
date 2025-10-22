
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

// Buscar serviços
$servicos = [];
$resServ = $conn->query("SELECT idServico, nomeServico FROM Servico");
while($row = $resServ->fetch_assoc()) {
    $servicos[] = $row;
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
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card dashboard-welcome-card">
                    <span class="dashboard-welcome-text fs-1 fs-md-2 fs-lg-1">Bem-vindo, Administrador <?= htmlspecialchars($nome_admin) ?>!</span>
                    <div class="mt-2 fs-4 fs-md-5 fs-lg-4">Unidade: <span style="color:#daa520; font-weight:600;"><?= htmlspecialchars($unidade_nome) ?></span></div>
                </div>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <!-- Card de barbeiros -->
            <div class="col-12 col-lg-7 d-flex align-items-stretch">
                <div class="dashboard-card p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="dashboard-section-title mb-0 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-person-badge"></i> Barbeiros da Unidade</div>
                        <div class="row gx-2">
                            <div class="col-auto">
                                <a href="cadastrar_barbeiro.php" class="dashboard-action dashboard-btn-small"><i class="bi bi-person-plus"></i> Cadastrar</a>
                            </div>
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
                    
                    <div class="dashboard-section-title mb-2 mt-3 fs-3 fs-md-4 fs-lg-3"><i class="bi bi-gear"></i> Serviços Disponíveis</div>
                    <div class="flex-fill">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach($servicos as $serv): ?>
                                <li class="list-group-item bg-transparent text-light border-0 ps-0 fs-5 fs-md-6 fs-lg-5"><i class="bi bi-check2-circle"></i> <?= htmlspecialchars($serv['nomeServico']) ?></li>
                            <?php endforeach; ?>
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
</body>
</html>