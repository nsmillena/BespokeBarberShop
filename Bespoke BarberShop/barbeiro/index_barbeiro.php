<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Barbeiro</title>
    <link rel="stylesheet" href="dashboard_barbeiro.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-barbeiro">
    <div class="dashboard-card">
        <div class="dashboard-title">Bem-vindo, Barbeiro!</div>
        <div class="dashboard-actions">
            <a href="agendamentos_barbeiro.php" class="dashboard-action"><i class="bi bi-calendar-check"></i>Meus Agendamentos</a>
        </div>
        <div class="dashboard-section-title">Informações</div>
        <p>Seu ID: <?= $_SESSION['usuario_id'] ?></p>
        <a href="../logout.php" class="dashboard-action" style="background:#232323;color:#daa520;"><i class="bi bi-box-arrow-right"></i>Sair</a>
    </div>
</body>
</html>