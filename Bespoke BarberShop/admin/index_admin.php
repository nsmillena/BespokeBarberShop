// Este arquivo será renomeado para index_admin.php
<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Administrador</title>
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="dashboard-card">
        <div class="dashboard-title">Bem-vindo, Administrador!</div>
        <div class="dashboard-actions">
            <!-- Adicione aqui as ações administrativas, ex: Gerenciar Usuários, Serviços, Unidades, etc -->
        </div>
        <div class="dashboard-section-title">Informações</div>
        <p>Seu ID: <?= $_SESSION['usuario_id'] ?></p>
        <a href="../logout.php" class="dashboard-action" style="background:#232323;color:#daa520;"><i class="bi bi-box-arrow-right"></i>Sair</a>
    </div>
</body>
</html>