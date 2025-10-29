<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/mailer.php';
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/helpers.php';

$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = (int)$_SESSION['usuario_id'];
$admin_email = '';
$admin_nome = '';
if ($st = $conn->prepare('SELECT nomeAdmin, emailAdmin FROM Administrador WHERE idAdministrador = ?')) {
    $st->bind_param('i', $admin_id);
    $st->execute();
    $st->bind_result($admin_nome, $admin_email);
    $st->fetch();
    $st->close();
}

$msg = null; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Informe um e-mail válido.';
    } else {
        $html = '<p>Teste de envio de e-mail do Bespoke BarberShop.</p>'
              . '<ul>'
              . '<li>Data/Hora: '.date('d/m/Y H:i:s').'</li>'
              . '<li>Remetente: '.htmlspecialchars((defined('MAIL_FROM_NAME')?MAIL_FROM_NAME:'Bespoke Barbership').' <'.(defined('MAIL_FROM')?MAIL_FROM:'no-reply@bespokebarber.local').'>').'</li>'
              . '<li>SMTP: '.(defined('SMTP_ENABLE') && SMTP_ENABLE ? (SMTP_HOST.':'.SMTP_PORT.'/'.SMTP_SECURE) : 'desativado').'</li>'
              . '</ul>';
        $ok = bb_send_mail($to, 'Teste SMTP - Bespoke BarberShop', $html);
        $msg = $ok ? 'E-mail de teste enviado. Verifique sua caixa de entrada (e SPAM).' : 'Falha ao enviar e-mail. Veja o arquivo banco/mail_outbox.log para detalhes.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste de E-mail (SMTP)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-4">
    <h1 class="h3 mb-3"><i class="bi bi-envelope-check"></i> Teste de E-mail (SMTP)</h1>

    <?php if ($msg): ?>
      <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?>" role="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="card bg-secondary-subtle text-dark">
        <div class="card-body">
            <form method="post" class="row gy-3">
                <div class="col-12">
                    <label for="to" class="form-label">Enviar para</label>
                    <input type="email" class="form-control" id="to" name="to" placeholder="destinatario@exemplo.com" value="<?= htmlspecialchars($admin_email ?: '') ?>" required>
                    <div class="form-text">Padrão: seu e-mail de administrador (<?= htmlspecialchars($admin_email ?: 'não encontrado') ?>)</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Enviar e-mail de teste</button>
                    <a href="index_admin.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-arrow-left"></i> Voltar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-4">
        <h2 class="h5">Como isso funciona?</h2>
        <ol class="mb-0">
            <li>Edite <code>includes/config.local.php</code> (copie do <code>config.local.sample.php</code>) com as credenciais SMTP reais.</li>
            <li>Este arquivo não é commitado (veja <code>.gitignore</code>).</li>
            <li>Volte aqui e envie um e-mail de teste. Se falhar, veja <code>banco/mail_outbox.log</code>.</li>
        </ol>
    </div>
</div>
</body>
</html>
