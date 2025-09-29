
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
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="container py-5">
        <div class="row g-4 justify-content-center">
            <!-- Card de boas-vindas -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="dashboard-card text-center h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="dashboard-title mb-2">Bem-vindo, <?= htmlspecialchars($nome_admin) ?>!</div>
                        <div class="mb-2">Unidade: <span style="color:#daa520; font-weight:600;"><?= htmlspecialchars($unidade_nome) ?></span></div>
                    </div>
                    <div class="mt-3">
                        <a href="../logout.php" class="dashboard-action"><i class="bi bi-box-arrow-right"></i> Sair</a>
                    </div>
                </div>
            </div>

            <!-- Card de barbeiros -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="dashboard-card h-100 d-flex flex-column">
                    <div class="dashboard-title mb-2"><i class="bi bi-person-badge"></i> Barbeiros da Unidade</div>
                    <ul class="list-group list-group-flush mb-3">
                        <?php if (count($barbeiros) > 0): foreach($barbeiros as $b): ?>
                            <li class="list-group-item bg-transparent text-light border-0 ps-0"><i class="bi bi-scissors"></i> <?= htmlspecialchars($b) ?></li>
                        <?php endforeach; else: ?>
                            <li class="list-group-item bg-transparent text-light border-0 ps-0">Nenhum barbeiro cadastrado.</li>
                        <?php endif; ?>
                    </ul>
                    <a href="#" class="dashboard-action align-self-start"><i class="bi bi-person-plus"></i> Cadastrar novo barbeiro</a>
                </div>
            </div>

            <!-- Card de perfil -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="dashboard-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="dashboard-title mb-2"><i class="bi bi-person-circle"></i> Meu Perfil</div>
                        <div class="mb-2">Gerencie seus dados de acesso e informações pessoais.</div>
                    </div>
                    <a href="#" class="dashboard-action mt-3"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                </div>
            </div>

            <!-- Card de serviços -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="dashboard-card h-100 d-flex flex-column">
                    <div class="dashboard-title mb-2"><i class="bi bi-gear"></i> Serviços Disponíveis</div>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach($servicos as $serv): ?>
                            <li class="list-group-item bg-transparent text-light border-0 ps-0"><i class="bi bi-check2-circle"></i> <?= htmlspecialchars($serv['nomeServico']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="#" class="dashboard-action align-self-start"><i class="bi bi-pencil-square"></i> Editar Serviços</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>