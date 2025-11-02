<?php
// Exclusão de conta do cliente (com confirmação por senha)
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = (int)$_SESSION['usuario_id'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Buscar nome/email para exibir
$stmt = $conn->prepare("SELECT nomeCliente, emailCliente, senhaCliente FROM Cliente WHERE idCliente = ?");
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$stmt->bind_result($nomeAtual, $emailAtual, $hashAtual);
$stmt->fetch();
$stmt->close();

$alert = null; $alertType = 'danger';
$noPassword = ($hashAtual === null || $hashAtual === '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $alert = t('user.security_fail');
    } else {
        $senha = $_POST['senha'] ?? '';
        $ok = false;
        if ($noPassword) {
            // Conta criada via Google sem senha definida: permitir exclusão sem senha
            $ok = ($senha === '');
        } else {
            if (!empty($hashAtual) && strlen($hashAtual) > 20 && password_get_info($hashAtual)['algo'] !== 0) {
                $ok = password_verify($senha, $hashAtual);
            }
            if (!$ok) { $ok = hash_equals((string)$hashAtual, (string)$senha); }
        }
        if ($ok) {
            // Excluir cliente (cascateia agendamentos e vínculos)
            $del = $conn->prepare("DELETE FROM Cliente WHERE idCliente = ?");
            $del->bind_param("i", $idCliente);
            if ($del->execute()) {
                $del->close();
                session_unset(); session_destroy();
                header("Location: ../index.php?msg=" . urlencode('Conta excluída com sucesso.'));
                exit;
            } else {
                $del->close();
                $alert = t('user.delete_error');
            }
        } else {
            $alert = t('user.wrong_password');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('user.delete_account') ?> | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body style="background:#181818;">
<div class="container py-5">
    <div class="dashboard-card mx-auto" style="max-width:460px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="dashboard-section-title mb-0 text-danger"><i class="bi bi-trash"></i> <?= t('user.delete_account') ?></h2>
            <a href="editar_perfil.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> <?= t('user.back') ?></a>
        </div>
    <p class="text-warning">
        <?= t('user.delete_warning_generic') ?>
        <?php if($noPassword){ echo ' '.t('user.delete_warning_google'); } else { echo ' '.t('user.delete_warning_confirm_pw'); } ?>
    </p>
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alertType ?> py-2"><?= htmlspecialchars($alert) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label class="form-label"><?= t('user.email') ?></label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($emailAtual) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= t('auth.password') ?></label>
                <input type="password" name="senha" class="form-control" placeholder="<?= $noPassword ? t('user.leave_blank_confirm') : t('user.enter_password') ?>" <?= $noPassword ? '' : 'required' ?> data-bb-toggle="1">
            </div>
            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> <?= t('user.confirm_delete') ?></button>
        </form>
    </div>
</div>
<script src="../js/password-strength.js"></script>
</body>
</html>
