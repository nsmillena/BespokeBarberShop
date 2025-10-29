<?php
session_start();
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/config.php';

// Recebe 'credential' via POST ou GET (GIS pode usar redirect/popup)
$cred = $_POST['credential'] ?? $_GET['credential'] ?? '';
if (empty($cred)) { http_response_code(400); exit('Token ausente'); }
if (empty(GOOGLE_CLIENT_ID)) { http_response_code(500); exit('GOOGLE_CLIENT_ID não configurado'); }

// Validate id_token with Google tokeninfo endpoint
$infoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($cred);
$resp = @file_get_contents($infoUrl);
if ($resp === false) {
    // Fallback com cURL se allow_url_fopen estiver desabilitado
    if (function_exists('curl_init')) {
        $ch = curl_init($infoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $httpCode !== 200) { http_response_code(401); exit('Falha ao validar token'); }
    } else {
        http_response_code(401); exit('Falha ao validar token');
    }
}
$data = json_decode($resp, true);
// Tratar email_verified como boolean/numérico/string
$emailVerified = false;
if ($data && isset($data['email_verified'])) {
    $ev = $data['email_verified'];
    $emailVerified = ($ev === true || $ev === 'true' || $ev === 1 || $ev === '1');
}
if (!$data || ($data['aud'] ?? '') !== GOOGLE_CLIENT_ID || !$emailVerified) {
    http_response_code(401); exit('Token inválido');
}

$email = strtolower($data['email']);
$nome  = $data['name'] ?? 'Usuário';

$bd = new Banco();
$conn = $bd->getConexao();

// Busca por usuário em ordem de privilégio: Admin > Barbeiro > Cliente
$map = [
  ['table'=>'Administrador','colEmail'=>'emailAdmin','colId'=>'idAdministrador','colPass'=>'senhaAdmin','papel'=>'admin'],
  ['table'=>'Barbeiro','colEmail'=>'emailBarbeiro','colId'=>'idBarbeiro','colPass'=>'senhaBarbeiro','papel'=>'barbeiro'],
  ['table'=>'Cliente','colEmail'=>'emailCliente','colId'=>'idCliente','colPass'=>'senhaCliente','papel'=>'cliente']
];

$found = null;
foreach($map as $m){
    $stmt = $conn->prepare("SELECT {$m['colId']} AS id FROM {$m['table']} WHERE {$m['colEmail']}=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute(); $r = $stmt->get_result();
    if($row = $r->fetch_assoc()){ $found = ['id'=>$row['id'], 'papel'=>$m['papel']]; $stmt->close(); break; }
    $stmt->close();
}

if(!$found){
    if (!defined('GOOGLE_AUTO_CREATE_CLIENTE') || GOOGLE_AUTO_CREATE_CLIENTE !== true) {
        http_response_code(403);
        exit('Conta não autorizada. Entre em contato para habilitar seu acesso.');
    }
    // Cria cliente automaticamente com senha randômica
    // Para contas criadas via Google, iniciamos SEM senha definida (string vazia)
    // Assim, o usuário poderá "Definir senha" no perfil sem precisar informar a atual
    $hash = '';
    $tel  = '0000000000';
    $stmt = $conn->prepare("INSERT INTO Cliente (nomeCliente, emailCliente, telefoneCliente, senhaCliente) VALUES (?,?,?,?)");
    $stmt->bind_param('ssss', $nome, $email, $tel, $hash);
    if ($stmt->execute()) {
        $found = ['id'=>$conn->insert_id, 'papel'=>'cliente'];
    }
    $stmt->close();
}

if(!$found){ http_response_code(500); exit('Não foi possível criar ou localizar a conta'); }

$_SESSION['usuario_id'] = (int)$found['id'];
$_SESSION['papel'] = $found['papel'];

// Redireciona
if ($found['papel'] === 'admin') {
    header('Location: admin/index_admin.php');
} elseif ($found['papel'] === 'barbeiro') {
    header('Location: barbeiro/index_barbeiro.php');
} else {
    header('Location: usuario/index_usuario.php');
}
exit;
?>