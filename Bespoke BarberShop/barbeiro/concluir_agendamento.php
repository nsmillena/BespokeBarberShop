<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index_barbeiro.php');
    exit;
}

include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();

$idBarbeiro = (int)$_SESSION['usuario_id'];
$idAgendamento = isset($_POST['idAgendamento']) ? (int)$_POST['idAgendamento'] : 0;

if ($idAgendamento <= 0) {
    header('Location: index_barbeiro.php?ok=0&msg=' . urlencode('Agendamento inválido.'));
    exit;
}

// Verificar se o agendamento pertence ao barbeiro e está em estado Agendado
$stmt = $conn->prepare("SELECT statusAgendamento FROM Agendamento WHERE idAgendamento = ? AND Barbeiro_idBarbeiro = ?");
$stmt->bind_param("ii", $idAgendamento, $idBarbeiro);
$stmt->execute();
$stmt->bind_result($statusAtual);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: index_barbeiro.php?ok=0&msg=' . urlencode('Agendamento não encontrado.'));
    exit;
}
$stmt->close();

if ($statusAtual !== 'Agendado') {
    header('Location: index_barbeiro.php?ok=0&msg=' . urlencode('Este agendamento não pode ser concluído.'));
    exit;
}

// Atualizar status para Finalizado
$upd = $conn->prepare("UPDATE Agendamento SET statusAgendamento = 'Finalizado' WHERE idAgendamento = ? AND Barbeiro_idBarbeiro = ?");
$upd->bind_param("ii", $idAgendamento, $idBarbeiro);
if ($upd->execute()) {
    $upd->close();
    header('Location: index_barbeiro.php?ok=1&msg=' . urlencode('Agendamento concluído com sucesso.'));
    exit;
} else {
    $erro = $conn->error;
    $upd->close();
    header('Location: index_barbeiro.php?ok=0&msg=' . urlencode('Falha ao concluir: ' . $erro));
    exit;
}
