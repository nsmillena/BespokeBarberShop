<?php
include "includes/db.php";
@include_once "includes/config.php";
$sucesso = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar'];

  // Validação de força: mínimo 8, com maiúscula, minúscula, número e símbolo
  $temMaiuscula = preg_match('/[A-Z]/', $senha);
  $temMinuscula = preg_match('/[a-z]/', $senha);
  $temDigito    = preg_match('/\d/', $senha);
  $temEspecial  = preg_match('/[^A-Za-z0-9]/', $senha);

  if ($senha !== $confirmar) {
    $erro = t('signup.err_mismatch');
  } elseif (strlen($senha) < 8 || !$temMaiuscula || !$temMinuscula || !$temDigito || !$temEspecial) {
    $erro = t('signup.err_strength');
  } else {
        $bd = new Banco();
        $conn = $bd->getConexao();

        // Verifica se o e-mail já está cadastrado
        $stmt = $conn->prepare("SELECT idCliente FROM Cliente WHERE emailCliente = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $erro = t('signup.err_email_exists');
        } else {
            // Armazenar senha como hash seguro (compatível com login.php que aceita hash e legado)
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO Cliente (nomeCliente, emailCliente, telefoneCliente, senhaCliente) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $telefone, $senhaHash);

    if ($stmt->execute()) {
        $sucesso = t('signup.success') . " <a href='login.php'>" . t('signup.login') . "</a>";
      } else {
        $erro = t('signup.err_generic');
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!doctype html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('signup.title') ?> - Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="cadastro.css">
    <?php if ((defined('SHOW_GOOGLE_BUTTON') && SHOW_GOOGLE_BUTTON) && (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '')): ?>
      <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
  </head>
  <body class="cadastro-body">

    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
        <div class="card cadastro-card p-4">
          <?php
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $currentUrl = $scheme.'://'.$host.$uri;
          ?>
          <div class="d-flex justify-content-end mb-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= t('nav.language') ?>">
              <a class="btn btn-outline-warning <?= bb_is_en() ? '' : 'active' ?>" href="includes/locale.php?set=pt_BR&redirect=<?= urlencode($currentUrl) ?>"><?= t('nav.pt') ?></a>
              <a class="btn btn-outline-warning <?= bb_is_en() ? 'active' : '' ?>" href="includes/locale.php?set=en_US&redirect=<?= urlencode($currentUrl) ?>"><?= t('nav.en') ?></a>
            </div>
          </div>
          <h3 class="text-center cadastro-title mb-4"><?= t('signup.title') ?></h3>

          <?php if ($erro): ?>
            <div class="alert alert-danger text-center"><?= $erro ?></div>
          <?php endif; ?>
          <?php if ($sucesso): ?>
            <div class="alert alert-success text-center"><?= $sucesso ?></div>
          <?php endif; ?>

          <form method="POST" action="" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="nome" class="form-label"><?= t('signup.name') ?></label>
              <input type="text" class="form-control" id="nome" name="nome" placeholder="<?= t('signup.name') ?>" required>
              <div class="invalid-feedback"><?= t('signup.val_name') ?></div>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label"><?= t('signup.email') ?></label>
              <input type="email" class="form-control" id="email" name="email" placeholder="<?= t('signup.email') ?>" required>
              <div class="invalid-feedback"><?= t('signup.val_email') ?></div>
            </div>
            <div class="mb-3">
              <label for="telefone" class="form-label"><?= t('signup.phone') ?></label>
              <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(xx) xxxxx-xxxx">
            </div>
            <div class="mb-3">
              <label for="senha" class="form-label"><?= t('signup.password') ?></label>
              <input type="password" class="form-control bb-password" id="senha" name="senha" placeholder="<?= t('signup.password_placeholder') ?>" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="<?= t('signup.err_strength') ?>">
              <div class="invalid-feedback"><?= t('signup.val_password') ?></div>
            </div>
            <div class="mb-3">
              <label for="confirmar" class="form-label"><?= t('signup.confirm') ?></label>
              <input type="password" class="form-control bb-password-confirm" id="confirmar" name="confirmar" placeholder="<?= t('signup.confirm_placeholder') ?>" required data-match="#senha">
              <div class="invalid-feedback"><?= t('signup.val_confirm') ?></div>
            </div>
            <button type="submit" class="btn btn-cadastrar w-100 fw-bold"><?= t('signup.create') ?></button>
          </form>

          <?php if ((defined('SHOW_GOOGLE_BUTTON') && SHOW_GOOGLE_BUTTON) && (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '')): ?>
            <?php
              $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
              $dir  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
              $base = (defined('APP_BASE_URL') && APP_BASE_URL !== '') ? APP_BASE_URL : ($scheme.'://'.$host.$dir);
              $gLoginUri = $base.'/oauth_google.php';
            ?>
            <div class="auth-divider d-flex align-items-center my-3">
              <hr class="flex-grow-1">
              <span class="px-2"><?= t('auth.or') ?></span>
              <hr class="flex-grow-1">
            </div>
            <div class="d-flex justify-content-center">
              <div id="g_id_onload"
                   data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                   data-context="signup"
                   data-ux_mode="popup"
                   data-callback="onGoogleSignIn"
                   data-auto_select="false"
                   data-itp_support="true">
              </div>
              <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="outline" data-text="signup_with" data-size="medium" data-logo_alignment="left"></div>
            </div>
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
                } catch(e) { console.error('Google sign-up error', e); }
              }
            </script>
          <?php endif; ?>

          <p class="text-center mt-3">
            <?= t('signup.have_account') ?> <a href="login.php" class="link-login"><?= t('signup.login') ?></a>
          </p>
          <p class="text-center mt-3">
            <a href="index.php" class="link-site"><?= t('auth.back_site') ?></a>
          </p>
          
        </div>
        </div>
      </div>
    </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/phone-mask.js"></script>
  <script src="js/password-strength.js"></script>
  <script>
    (() => {
      const forms = document.querySelectorAll('.needs-validation');
      Array.prototype.forEach.call(forms, form => {
        form.addEventListener('submit', (event) => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();
  </script>
  </body>
</html>