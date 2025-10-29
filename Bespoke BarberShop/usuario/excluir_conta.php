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
        $alert = 'Falha de segurança. Atualize a página e tente novamente.';
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
                $alert = 'Erro ao excluir conta. Tente novamente.';
            }
        } else {
            $alert = 'Senha incorreta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Conta | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body style="background:#181818;">
<div class="container py-5">
    <div class="dashboard-card mx-auto" style="max-width:460px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="dashboard-section-title mb-0 text-danger"><i class="bi bi-trash"></i> Excluir Conta</h2>
            <a href="editar_perfil.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    <p class="text-warning">Esta ação é permanente e removerá seus agendamentos associados. <?php if($noPassword){ echo 'Sua conta foi criada com Google e não possui senha: deixe o campo abaixo em branco para confirmar.'; } else { echo 'Confirme sua senha para prosseguir.'; } ?></p>
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alertType ?> py-2"><?= htmlspecialchars($alert) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($emailAtual) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" placeholder="<?= $noPassword ? 'Deixe em branco para confirmar' : 'Digite sua senha' ?>" <?= $noPassword ? '' : 'required' ?> data-bb-toggle="1">
            </div>
            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Confirmar exclusão</button>
        </form>
    </div>
</div>
<script src="../js/password-strength.js"></script>
</body>
</html>
