<?php
// Retorna barbeiros de uma unidade em JSON
if (!isset($_GET['unidade_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Unidade não informada"]);
    exit;
}
include "db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$unidade_id = intval($_GET['unidade_id']);
$stmt = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade = ?");
$stmt->bind_param("i", $unidade_id);
$stmt->execute();
$result = $stmt->get_result();
$barbeiros = [];
while ($row = $result->fetch_assoc()) {
    $barbeiros[] = $row;
}
header('Content-Type: application/json');
echo json_encode($barbeiros);
