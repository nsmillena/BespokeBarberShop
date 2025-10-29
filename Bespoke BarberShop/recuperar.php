<?php
session_start();
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/helpers.php';

$msg = null; $ok = false;
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $email = trim(strtolower($_POST['email'] ?? ''));
  // Valida Captcha (hCaptcha preferencial; fallback reCAPTCHA)
  $captchaErr = null;
  $captchaOk = bb_verify_captcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null, $captchaErr);
  if (!$captchaOk) {
    $msg = $captchaErr ?: 'Falha na verificação do captcha. Tente novamente.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $msg = 'Informe um e-mail válido.';
    } else {
        $bd = new Banco(); $conn = $bd->getConexao();
        // Descobrir papel pela existência do email
        $roles = [
          ['table'=>'Administrador','colEmail'=>'emailAdmin','papel'=>'admin'],
          ['table'=>'Barbeiro','colEmail'=>'emailBarbeiro','papel'=>'barbeiro'],
          ['table'=>'Cliente','colEmail'=>'emailCliente','papel'=>'cliente']
        ];
        $papel = null;
        foreach($roles as $r){
            $st = $conn->prepare("SELECT 1 FROM {$r['table']} WHERE {$r['colEmail']}=? LIMIT 1");
            $st->bind_param('s', $email); $st->execute(); $res=$st->get_result();
            if ($res->fetch_row()){ $papel=$r['papel']; $st->close(); break; }
            $st->close();
        }
    if (!$papel){ $msg = 'Se o e-mail existir em nossa base, você receberá as instruções.'; $ok=true; }
        else {
            $token = bin2hex(random_bytes(32));
            $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            // invalida tokens anteriores
            $conn->query("UPDATE PasswordReset SET used=1 WHERE email='".$conn->real_escape_string($email)."' AND papel='".$papel."'");
            $st = $conn->prepare("INSERT INTO PasswordReset (email,papel,token,expires_at) VALUES (?,?,?,?)");
            $st->bind_param('ssss', $email, $papel, $token, $expires); $st->execute(); $st->close();

            // Monta link
            $base = APP_BASE_URL;
            if (!$base){
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base = $scheme.'://'.$host.rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            }
            $link = $base.'/resetar.php?token='.$token;
            $html = '<p>Recebemos um pedido para redefinir sua senha.</p><p>Clique no link abaixo para continuar (válido por 1 hora):</p><p><a href="'.$link.'">'.$link.'</a></p><p>Se você não solicitou, ignore este e-mail.</p>';
      bb_send_mail($email, 'Redefinir senha - Bespoke BarberShop', $html);
      $msg = 'Se o e-mail existir em nossa base, você receberá as instruções.'; $ok=true;
      // Em desenvolvimento/local, mostra o link diretamente para facilitar testes
      $hostLower = strtolower($_SERVER['HTTP_HOST'] ?? '');
      $isLocal = (str_starts_with($hostLower,'localhost') || str_starts_with($hostLower,'127.0.0.1'));
      if ($isLocal) {
        $msg .= ' (Dev: link direto: <a href="'.htmlspecialchars($link).'">abrir</a>)';
      }
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar senha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
  <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js?hl=pt-BR" async defer></script>
  <?php endif; ?>
</head>
<body class="login-body">
  <div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card-container">
      <div class="card login-card p-4">
        <h3 class="text-center login-title mb-4">Recuperar Senha</h3>

        <?php if ($msg): ?>
          <div class="alert <?= $ok?'alert-success':'alert-danger' ?> text-center"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-3">
            <label class="form-label" for="rec_email">Seu e-mail</label>
            <input type="email" id="rec_email" name="email" class="form-control" placeholder="Digite seu e-mail" required>
          </div>
          <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
            <div class="captcha-wrap mt-2 mb-4 d-flex justify-content-center">
              <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>" data-theme="light"></div>
            </div>
          <?php endif; ?>
          <button class="btn btn-login w-100 fw-bold" type="submit">Enviar link</button>
        </form>

        <p class="text-center link-container" style="margin-top: 1rem;">
          <a href="login.php" class="link-site">Voltar ao login</a>
        </p>
      </div>
    </div>
  </div>
</body>
</html>