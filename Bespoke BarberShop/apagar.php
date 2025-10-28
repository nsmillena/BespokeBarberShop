<?php
session_start();
include "includes/db.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['idAgendamento'])) {
    $idAgendamento = intval($_POST['idAgendamento']);
    $idCliente = intval($_SESSION['usuario_id']);
    $bd = new Banco();
    $conn = $bd->getConexao();

    // Valida que o agendamento pertence ao cliente
    $stmtChk = $conn->prepare("SELECT idAgendamento FROM Agendamento WHERE idAgendamento = ? AND Cliente_idCliente = ?");
    $stmtChk->bind_param("ii", $idAgendamento, $idCliente);
    $stmtChk->execute();
    $resChk = $stmtChk->get_result();
    if (!$resChk || $resChk->num_rows === 0) {
        header("Location: usuario/agendamentos_usuario.php?erro=1");
        exit;
    }
    $stmtChk->close();

    // Cria tabela de ocultação se não existir
    $conn->query("CREATE TABLE IF NOT EXISTS AgendamentoOcultoCliente (
        Cliente_id INT NOT NULL,
        Agendamento_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (Cliente_id, Agendamento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Marca como oculto para este cliente (sem remover do banco)
    $stmtIns = $conn->prepare("INSERT IGNORE INTO AgendamentoOcultoCliente (Cliente_id, Agendamento_id) VALUES (?, ?)");
    $stmtIns->bind_param("ii", $idCliente, $idAgendamento);
    if ($stmtIns->execute()) {
        header("Location: usuario/agendamentos_usuario.php?confirmado=1");
        exit;
    } else {
        header("Location: usuario/agendamentos_usuario.php?erro=1");
        exit;
    }
} else {
    header("Location: usuario/agendamentos_usuario.php");
    exit;
}
?>
