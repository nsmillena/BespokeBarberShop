<?php
include "includes/db.php";
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
  </head>
  <body class="cadastro-body">

    <div class="container d-flex justify-content-center align-items-center vh-100">
      <div class="card-container">
        <div class="card cadastro-card p-4">
          <h3 class="text-center cadastro-title mb-4">Cadastro</h3>

          <?php if ($erro): ?>
            <div class="alert alert-danger text-center"><?= $erro ?></div>
          <?php endif; ?>
          <?php if ($sucesso): ?>
            <div class="alert alert-success text-center"><?= $sucesso ?></div>
          <?php endif; ?>

          <form method="POST" action="">
            <div class="mb-3">
              <label for="nome" class="form-label">Nome Completo</label>
              <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite seu nome" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">E-mail</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            <div class="mb-3">
              <label for="telefone" class="form-label">Telefone</label>
              <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(xx) xxxxx-xxxx">
            </div>
            <div class="mb-3">
              <label for="senha" class="form-label">Senha</label>
              <input type="password" class="form-control bb-password" id="senha" name="senha" placeholder="Crie uma senha" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="Mínimo 8, com maiúscula, minúscula, número e símbolo.">
              <small class="text-muted">Mínimo 8, com maiúscula, minúscula, número e símbolo.</small>
            </div>
            <div class="mb-3">
              <label for="confirmar" class="form-label">Confirmar Senha</label>
              <input type="password" class="form-control bb-password-confirm" id="confirmar" name="confirmar" placeholder="Repita a senha" required data-match="#senha">
            </div>
            <button type="submit" class="btn btn-cadastrar w-100 fw-bold">Cadastrar</button>
          </form>

          <p class="text-center mt-3">
            Já tem conta? <a href="login.php" class="link-login">Faça login</a>
          </p>
          <p class="text-center mt-3">
            <a href="index.php" class="link-site">Voltar ao Site</a>
          </p>
        </div>
      </div>
    </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/phone-mask.js"></script>
  <script src="js/password-strength.js"></script>
  </body>
</html>