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
require_once "../includes/i18n.php";
$bd = new Banco();
$conn = $bd->getConexao();

$idBarbeiro = (int)$_SESSION['usuario_id'];
$idAgendamento = isset($_POST['idAgendamento']) ? (int)$_POST['idAgendamento'] : 0;

// Bloqueia conclusão se for obrigatório trocar a senha
$mustQ = $conn->prepare("SELECT deveTrocarSenha FROM Barbeiro WHERE idBarbeiro=?");
$mustQ->bind_param("i", $idBarbeiro);
$mustQ->execute();
$mustQ->bind_result($must);
$mustQ->fetch();
$mustQ->close();
if ((int)$must === 1) {
    header('Location: editar_perfil.php?ok=0&msg=' . urlencode(t('barber.must_change_required')));
    exit;
}

// destino de retorno (index por padrão)
$returnTo = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index_barbeiro.php';

// Helper to safely append/override query params on return URL
function bb_build_redirect($baseUrl, $params){
    $parts = parse_url($baseUrl);
    $query = [];
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str($parts['query'], $query);
    }
    // override/append new params
    foreach ($params as $k=>$v){ $query[$k] = $v; }
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host     = $parts['host'] ?? '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path     = $parts['path'] ?? '';
    $qs       = http_build_query($query);
    return $scheme . $host . $port . $path . ($qs ? '?' . $qs : '');
}

if ($idAgendamento <= 0) {
    header('Location: ' . bb_build_redirect($returnTo, ['ok'=>'0','msg'=>t('barber.invalid_appointment')]));
    exit;
}

// Verificar se o agendamento pertence ao barbeiro e está em estado Agendado
$stmt = $conn->prepare("SELECT statusAgendamento FROM Agendamento WHERE idAgendamento = ? AND Barbeiro_idBarbeiro = ?");
$stmt->bind_param("ii", $idAgendamento, $idBarbeiro);
$stmt->execute();
$stmt->bind_result($statusAtual);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: ' . bb_build_redirect($returnTo, ['ok'=>'0','msg'=>t('barber.appointment_not_found')]));
    exit;
}
$stmt->close();

if ($statusAtual !== 'Agendado') {
    header('Location: ' . bb_build_redirect($returnTo, ['ok'=>'0','msg'=>t('barber.cannot_complete')]));
    exit;
}

// Atualizar status para Finalizado
$upd = $conn->prepare("UPDATE Agendamento SET statusAgendamento = 'Finalizado' WHERE idAgendamento = ? AND Barbeiro_idBarbeiro = ?");
$upd->bind_param("ii", $idAgendamento, $idBarbeiro);
if ($upd->execute()) {
    $upd->close();
    header('Location: ' . bb_build_redirect($returnTo, ['ok'=>'1','msg'=>t('barber.appointment_completed')]));
    exit;
} else {
    $erro = $conn->error;
    $upd->close();
    header('Location: ' . bb_build_redirect($returnTo, ['ok'=>'0','msg'=>t('barber.fail_complete') . ' ' . $erro]));
    exit;
}
