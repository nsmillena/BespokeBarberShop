<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$response = [ 'allow' => false, 'message' => "" ];

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode($response);
    exit;
}

try {
    $bd = new Banco();
    $conn = $bd->getConexao();
    $idCliente = (int)$_SESSION['usuario_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS qtd FROM Agendamento WHERE Cliente_idCliente = ? AND statusAgendamento = 'Agendado'");
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $res = $stmt->get_result();
    $qtd = 0;
    if ($row = $res->fetch_assoc()) { $qtd = (int)$row['qtd']; }
    $stmt->close();

    if ($qtd >= 5) {
        $response['allow'] = false;
        $response['message'] = "<div class='alert alert-warning text-center'>Você atingiu o limite de 5 agendamentos ativos. Cancele um agendamento existente antes de criar um novo.</div>";
    } else {
        $response['allow'] = true;
    }
    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode($response);
}
?>
