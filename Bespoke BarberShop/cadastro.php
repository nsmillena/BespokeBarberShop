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
    $erro = "As senhas não coincidem!";
  } elseif (strlen($senha) < 8 || !$temMaiuscula || !$temMinuscula || !$temDigito || !$temEspecial) {
    $erro = "A senha deve ter no mínimo 8 caracteres e incluir maiúscula, minúscula, número e símbolo.";
  } else {
        $bd = new Banco();
        $conn = $bd->getConexao();

        // Verifica se o e-mail já está cadastrado
        $stmt = $conn->prepare("SELECT idCliente FROM Cliente WHERE emailCliente = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = "E-mail já cadastrado!";
        } else {
            // Armazenar senha como hash seguro (compatível com login.php que aceita hash e legado)
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO Cliente (nomeCliente, emailCliente, telefoneCliente, senhaCliente) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $telefone, $senhaHash);

      if ($stmt->execute()) {
                $sucesso = "Cadastro realizado com sucesso! <a href='login.php'>Faça login</a>";
            } else {
                $erro = "Erro ao cadastrar. Tente novamente.";
            }
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro - Bespoke BarberShop</title>
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
          <h3 class="text-center cadastro-title mb-4">Cadastro</h3>

          <?php if ($erro): ?>
            <div class="alert alert-danger text-center"><?= $erro ?></div>
          <?php endif; ?>
          <?php if ($sucesso): ?>
            <div class="alert alert-success text-center"><?= $sucesso ?></div>
          <?php endif; ?>

          <form method="POST" action="" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="nome" class="form-label">Nome Completo</label>
              <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite seu nome" required>
              <div class="invalid-feedback">Informe seu nome completo.</div>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">E-mail</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required>
              <div class="invalid-feedback">Informe um e-mail válido.</div>
            </div>
            <div class="mb-3">
              <label for="telefone" class="form-label">Telefone</label>
              <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(xx) xxxxx-xxxx">
            </div>
            <div class="mb-3">
              <label for="senha" class="form-label">Senha</label>
              <input type="password" class="form-control bb-password" id="senha" name="senha" placeholder="Crie uma senha" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="Mínimo 8, com maiúscula, minúscula, número e símbolo.">
              <div class="invalid-feedback">Senha inválida. Siga os requisitos acima.</div>
            </div>
            <div class="mb-3">
              <label for="confirmar" class="form-label">Confirmar Senha</label>
              <input type="password" class="form-control bb-password-confirm" id="confirmar" name="confirmar" placeholder="Repita a senha" required data-match="#senha">
              <div class="invalid-feedback">Repita a mesma senha.</div>
            </div>
            <button type="submit" class="btn btn-cadastrar w-100 fw-bold">Cadastrar</button>
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
              <span class="px-2">ou</span>
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
            Já tem conta? <a href="login.php" class="link-login">Faça login</a>
          </p>
          <p class="text-center mt-3">
            <a href="index.php" class="link-site">Voltar ao Site</a>
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