<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
try {
  $bd = new Banco();
  $conn = $bd->getConexao();
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB error']);
  exit;
}
$barbeiroId = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;
$data = $_GET['data'] ?? '';
if ($barbeiroId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  echo json_encode([]);
  exit;
}
$stmt = $conn->prepare("SELECT horaInicio, horaFim FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? ORDER BY horaInicio");
$stmt->bind_param('is', $barbeiroId, $data);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($row = $res->fetch_assoc()) { $out[] = $row; }
$stmt->close();
echo json_encode($out);
