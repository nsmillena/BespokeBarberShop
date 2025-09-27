<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Usuário</title>
</head>
<body>
    <h1>Bem-vindo, Cliente!</h1>
    <p>Seu ID: <?= $_SESSION['usuario_id'] ?></p>
    <a href="agendamentos.php" class="btn btn-primary">Meus Agendamentos</a>
    <a href="../agendamento.php" class="btn btn-success" style="margin-left:10px;">Novo Agendamento</a>
    <br><br>
    <a href="../logout.php">Sair</a>
</body>
</html>