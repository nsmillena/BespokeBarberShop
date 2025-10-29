<?php
// Página de edição de perfil do usuário
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
$includeCfg = __DIR__.'/../includes/config.php'; if (file_exists($includeCfg)) require_once $includeCfg;
$includeMailer = __DIR__.'/../includes/mailer.php'; if (file_exists($includeMailer)) require_once $includeMailer;
$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = $_SESSION['usuario_id'];

// Helpers
function bb_pw_is_strong($pw){
    return strlen($pw) >= 8 && preg_match('/[a-z]/',$pw) && preg_match('/[A-Z]/',$pw) && preg_match('/\d/',$pw) && preg_match('/[^A-Za-z0-9]/',$pw);
}

$alert = null; $alertType = 'success';
// Descobre se o cliente já tem senha definida (string vazia significa conta criada via Google sem senha)
$st0 = $conn->prepare("SELECT senhaCliente FROM Cliente WHERE idCliente=?");
$st0->bind_param("i", $idCliente);
$st0->execute();
$st0->bind_result($hashInicial);
$st0->fetch();
$st0->close();
$noPassword = ($hashInicial === null || $hashInicial === '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Enviar link de redefinição (perfil logado)
    if (isset($_POST['send_reset'])) {
        $stE = $conn->prepare("SELECT nomeCliente, emailCliente FROM Cliente WHERE idCliente=?");
        $stE->bind_param("i", $idCliente);
        $stE->execute();
        $stE->bind_result($nomeAlvo, $emailAlvo);
        $stE->fetch();
        $stE->close();

        if ($emailAlvo) {
            $papel = 'cliente';
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $conn->query("UPDATE PasswordReset SET used=1 WHERE email='".$conn->real_escape_string($emailAlvo)."' AND papel='".$papel."'");
            $st = $conn->prepare("INSERT INTO PasswordReset (email,papel,token,expires_at) VALUES (?,?,?,?)");
            $st->bind_param('ssss', $emailAlvo, $papel, $token, $expires);
            $st->execute();
            $st->close();

            $base = defined('APP_BASE_URL') ? APP_BASE_URL : '';
            if (!$base){
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base = $scheme.'://'.$host.rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            }
            $link = $base.'/resetar.php?token='.$token;
            $html = '<p>Olá '.htmlspecialchars($nomeAlvo).',</p><p>Recebemos um pedido para definir/alterar sua senha.</p><p>Clique no link abaixo (válido por 1 hora):</p><p><a href="'.$link.'">'.$link.'</a></p><p>Se você não solicitou, ignore este e-mail.</p>';
            bb_send_mail($emailAlvo, 'Definir/Redefinir senha - Bespoke BarberShop', $html);
            $alert = 'Se o e-mail existir em nossa base, enviamos o link para redefinir a senha.';
            $alertType = 'success';
            $hostLower = strtolower($_SERVER['HTTP_HOST'] ?? '');
            $isLocal = (function_exists('str_starts_with') && (str_starts_with($hostLower,'localhost') || str_starts_with($hostLower,'127.0.0.1')));
            if ($isLocal) { $alert .= ' (Dev: <a href="'.htmlspecialchars($link).'">abrir link direto</a>)'; }
        } else {
            $alert = 'Não foi possível localizar seu e-mail.';
            $alertType = 'danger';
        }
    } else {
        // 2) Atualização de dados e (opcionalmente) senha
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');

        // E-mail único
        $stChk = $conn->prepare("SELECT idCliente FROM Cliente WHERE emailCliente=? AND idCliente<>?");
        $stChk->bind_param("si", $email, $idCliente);
        $stChk->execute();
        $stChk->store_result();
        if ($stChk->num_rows > 0) {
            $stChk->close();
            $alert = 'Este e-mail já está em uso.';
            $alertType = 'danger';
        } else {
            $stChk->close();
            // Atualiza dados básicos
            $stmt = $conn->prepare("UPDATE Cliente SET nomeCliente=?, emailCliente=?, telefoneCliente=? WHERE idCliente=?");
            $stmt->bind_param("sssi", $nome, $email, $telefone, $idCliente);
            $stmt->execute();
            $stmt->close();

            // Senha
            $senhaAtual = $_POST['senha_atual'] ?? '';
            $novaSenha = $_POST['nova_senha'] ?? '';
            $confirmaSenha = $_POST['confirmar_senha'] ?? '';
            $temCamposSenha = ($senhaAtual !== '' || $novaSenha !== '' || $confirmaSenha !== '');
            if ($temCamposSenha) {
                if ($novaSenha === '' || $confirmaSenha === '' || (!$noPassword && $senhaAtual === '')) {
                    $alert = 'Preencha todos os campos de senha para alterar a senha.';
                    $alertType = 'danger';
                } elseif ($novaSenha !== $confirmaSenha) {
                    $alert = 'As senhas novas não conferem.';
                    $alertType = 'danger';
                } elseif (!bb_pw_is_strong($novaSenha)) {
                    $alert = 'A nova senha não atende aos requisitos mínimos.';
                    $alertType = 'danger';
                } else {
                    // Buscar hash atual
                    $stPw = $conn->prepare("SELECT senhaCliente FROM Cliente WHERE idCliente=?");
                    $stPw->bind_param("i", $idCliente);
                    $stPw->execute();
                    $stPw->bind_result($hashAtual);
                    $stPw->fetch();
                    $stPw->close();

                    $ok = false;
                    if ($hashAtual === '' || $hashAtual === null) {
                        $ok = true; // conta Google sem senha definida
                    } else {
                        if (!empty($hashAtual) && strlen($hashAtual) > 20 && password_get_info($hashAtual)['algo'] !== 0) {
                            $ok = password_verify($senhaAtual, $hashAtual);
                        }
                        if (!$ok) { $ok = hash_equals((string)$hashAtual, (string)$senhaAtual); }
                    }

                    if ($ok) {
                        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                        $up = $conn->prepare("UPDATE Cliente SET senhaCliente=? WHERE idCliente=?");
                        $up->bind_param("si", $novoHash, $idCliente);
                        $up->execute();
                        $up->close();
                        $alert = 'Senha atualizada com sucesso.';
                        $alertType = 'success';
                    } else {
                        $alert = 'Senha atual incorreta.';
                        $alertType = 'danger';
                    }
                }
            } else {
                $alert = 'Perfil atualizado com sucesso.';
                $alertType = 'success';
            }
        }
    }
}
$stmt = $conn->prepare("SELECT nomeCliente, emailCliente, telefoneCliente FROM Cliente WHERE idCliente = ?");
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$stmt->bind_result($nome, $email, $telefone);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body style="background:#181818;">
<div class="container py-5">
    <div class="dashboard-card mx-auto" style="max-width:420px;">
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-pencil-square"></i> Editar Perfil</h2>
        <?php if ($noPassword): ?>
            <div class="alert alert-warning py-2">
                <i class="bi bi-info-circle"></i> Sua conta foi criada com Google e ainda não tem senha. Defina uma senha abaixo para também conseguir entrar por e-mail e senha.
            </div>
        <?php endif; ?>
        <?php if ($alert): ?>
            <div class="alert alert-<?= $alertType ?> py-2"><?= htmlspecialchars($alert) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="tel" name="telefone" class="form-control bb-phone" value="<?= htmlspecialchars($telefone) ?>" required>
            </div>
            <hr class="border-secondary">
            <h5 class="mb-2" style="color:#daa520;"><i class="bi bi-shield-lock"></i> <?= $noPassword ? 'Definir senha' : 'Alterar senha' ?></h5>
            <div class="mb-3">
                <label class="form-label">Senha atual</label>
                <input type="password" name="senha_atual" class="form-control" placeholder="<?= $noPassword ? 'Conta criada via Google — deixe em branco' : 'Digite sua senha atual' ?>" data-bb-toggle="1" <?= $noPassword ? '' : '' ?>>
            </div>
            <div class="mb-3">
                <label class="form-label">Nova senha</label>
                <input type="password" name="nova_senha" class="form-control bb-password" placeholder="Nova senha (mín. 8, maiúscula, minúscula, número e símbolo)">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmar nova senha</label>
                <input type="password" name="confirmar_senha" class="form-control bb-password-confirm" placeholder="Confirme a nova senha">
            </div>
            <button type="submit" class="dashboard-action w-100">Salvar Alterações</button>
            <a href="index_usuario.php" class="btn btn-link w-100 mt-2" style="color:#daa520;">Cancelar</a>
            <div class="text-center mt-2">
                <form method="post" class="d-inline">
                    <input type="hidden" name="send_reset" value="1">
                    <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-envelope"></i> Enviar link de redefinição por e-mail</button>
                </form>
            </div>
            <hr class="border-secondary">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Zona de risco</span>
                <a href="excluir_conta.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Excluir minha conta</a>
            </div>
        </form>
    </div>
</div>
<script src="../js/phone-mask.js"></script>
<script src="../js/password-strength.js"></script>
</body>
</html>
