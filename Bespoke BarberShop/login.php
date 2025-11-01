<?php
session_start();
include "includes/db.php";
@include_once "includes/config.php";
@include_once "includes/helpers.php";

$bd = new Banco();
$conn = $bd->getConexao();

// Detecta suporte às colunas de reset/troca de senha no Barbeiro
$hasResetCols = true;
try {
  @$conn->query("SELECT `deveTrocarSenha`, `senhaTempExpiraEm` FROM Barbeiro LIMIT 0");
} catch (Throwable $e) {
  $hasResetCols = false;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Verificação de Captcha (hCaptcha preferencial; fallback reCAPTCHA)
    $captchaErr = null;
    if (!bb_verify_captcha($_POST, $_SERVER['REMOTE_ADDR'] ?? null, $captchaErr)) {
      $erro = $captchaErr ?: 'Falha na verificação do captcha. Tente novamente.';
    }

    // Verifica nas três tabelas
  $sqls = [
    "Administrador" => "SELECT idAdministrador AS id, senhaAdmin AS senha, 'admin' AS papel FROM Administrador WHERE emailAdmin = ?",
    // Para barbeiro, busca flags se a tabela suportar; senão, consulta básica
    "Barbeiro"      => $hasResetCols
      ? "SELECT idBarbeiro AS id, senhaBarbeiro AS senha, 'barbeiro' AS papel, deveTrocarSenha, senhaTempExpiraEm FROM Barbeiro WHERE emailBarbeiro = ?"
      : "SELECT idBarbeiro AS id, senhaBarbeiro AS senha, 'barbeiro' AS papel FROM Barbeiro WHERE emailBarbeiro = ?",
    "Cliente"       => "SELECT idCliente AS id, senhaCliente AS senha, 'cliente' AS papel FROM Cliente WHERE emailCliente = ?"
  ];

  if (!isset($erro)) {
  foreach ($sqls as $tipo => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
      $hash = (string)$row['senha'];
      $ok = false;
      // Primeiro tenta verificar como hash moderno
      if (!empty($hash) && strlen($hash) > 20 && password_get_info($hash)['algo'] !== 0) {
        $ok = password_verify($senha, $hash);
      }
      // Fallback para senhas legadas em texto puro
      if (!$ok) { $ok = hash_equals($hash, $senha); }

    if ($ok) {
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['papel'] = $row['papel'];
        // Regras especiais para barbeiro: expiração/forçar troca (somente se colunas existirem)
        if ($row['papel'] === 'barbeiro' && $hasResetCols) {
          $must = isset($row['deveTrocarSenha']) ? (int)$row['deveTrocarSenha'] : 0;
          $expira = $row['senhaTempExpiraEm'] ?? null;
          if (!empty($expira)) {
            $agora = new DateTime('now');
            $dtExpira = new DateTime($expira);
            if ($agora > $dtExpira) {
              // Expirou: limpar sessão e mostrar erro
              session_unset(); session_destroy();
              $erro = "Sua senha temporária expirou. Solicite um novo reset ao administrador.";
              break; // sai do foreach
            }
          }
          if ($must === 1) {
            $_SESSION['must_change_password'] = 1;
            header("Location: barbeiro/editar_perfil.php?ok=0&msg=" . urlencode('Troca de senha obrigatória no primeiro acesso. Informe a senha temporária como atual e defina uma nova.'));
            exit;
          }
        }
        // Redireciona conforme papel
        if ($row['papel'] === 'admin') {
          header("Location: admin/index_admin.php");
        } elseif ($row['papel'] === 'barbeiro') {
          header("Location: barbeiro/index_barbeiro.php");
        } else {
          header("Location: usuario/index_usuario.php");
        }
        exit;
            }
        }
    }

  $erro = "E-mail ou senha inválidos!";
  } // end if !isset($erro)
}
?>
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="login.css"> 
    <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
      <script src="https://www.google.com/recaptcha/api.js?hl=pt-BR" async defer></script>
    <?php endif; ?>
  </head>
  <body class="login-body">

    <div class="container d-flex justify-content-center align-items-center vh-100">
      <div class="card-container">
        <div class="card login-card p-4">
          <h3 class="text-center login-title mb-4">Login</h3>

          <?php if (isset($erro)): ?>
            <div class="alert alert-danger text-center"><?= $erro ?></div>
          <?php endif; ?>

          <form method="POST" action="">
            <div class="mb-3">
              <label for="email" class="form-label">E-mail</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            <div class="mb-3">
              <label for="senha" class="form-label">Senha</label>
              <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required data-bb-toggle="1">
            </div>
            <?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY !== ''): ?>
              <div class="captcha-wrap mt-1 mb-2 d-flex justify-content-center">
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY) ?>" data-theme="light"></div>
              </div>
            <?php endif; ?>
            <?php if (defined('SHOW_FORGOT_PASSWORD') && SHOW_FORGOT_PASSWORD): ?>
              <div class="d-block text-center mt-1 mb-2">
                <a href="recuperar.php" class="link-site small">Esqueceu a senha?</a>
              </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-login w-100 fw-bold">Entrar</button>
          </form>

          <?php if ((defined('SHOW_GOOGLE_BUTTON') && SHOW_GOOGLE_BUTTON) && (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '')): ?>
            <?php
              // Constrói URL absoluta para o handler do Google
              $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
              $dir  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
              $base = (defined('APP_BASE_URL') && APP_BASE_URL !== '') ? APP_BASE_URL : ($scheme.'://'.$host.$dir);
              $gLoginUri = $base.'/oauth_google.php';
            ?>
            <div class="auth-divider d-flex align-items-center mt-3">
              <hr class="flex-grow-1">
              <span class="px-2">ou</span>
              <hr class="flex-grow-1">
            </div>
            <div class="mt-2 d-flex justify-content-center">
              <div id="g_id_onload"
                   data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                   data-context="signin"
                   data-ux_mode="popup"
                   data-callback="onGoogleSignIn"
                   data-auto_select="false"
                   data-itp_support="true">
              </div>
              <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="outline" data-text="continue_with" data-size="medium" data-logo_alignment="left"></div>
            </div>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <script>
              function onGoogleSignIn(response){
                try {
                  var form = document.createElement('form');
                  form.method = 'POST';
                  form.action = '<?= htmlspecialchars($gLoginUri) ?>';
                  var input = document.createElement('input');
                  input.type = 'hidden';
                  input.name = 'credential';
                  input.value = response.credential;
                  form.appendChild(input);
                  document.body.appendChild(form);
                  form.submit();
                } catch(e) {
                  console.error('Google sign-in error', e);
                }
              }
            </script>
          <?php endif; ?>

          <p class="text-center link-container" style="margin-top: 1rem;">
            Ainda não tem conta? <a href="cadastro.php" class="link-cadastro">Cadastre-se</a>
          </p>
          <p class="text-center link-container">
            <a href="index.php" class="link-site">Voltar ao Site</a>
          </p>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/password-strength.js"></script>
  </body>
</html>
