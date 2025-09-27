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
</head>
<body>
    <h1>Bem-vindo, Barbeiro!</h1>
    <p>Seu ID: <?= $_SESSION['usuario_id'] ?></p>
    <a href="agendamentos.php" class="btn btn-primary">Ver meus agendamentos</a>
    <br><br>
    <a href="../logout.php">Sair</a>
</body>
</html>