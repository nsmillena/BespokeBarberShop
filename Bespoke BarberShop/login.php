<?php
session_start();
include "includes/db.php";

$bd = new Banco();
$conn = $bd->getConexao();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Verifica nas três tabelas
    $sqls = [
        "Administrador" => "SELECT idAdministrador AS id, senhaAdmin AS senha, 'admin' AS papel FROM Administrador WHERE emailAdmin = ?",
        "Barbeiro"      => "SELECT idBarbeiro AS id, senhaBarbeiro AS senha, 'barbeiro' AS papel FROM Barbeiro WHERE emailBarbeiro = ?",
        "Cliente"       => "SELECT idCliente AS id, senhaCliente AS senha, 'cliente' AS papel FROM Cliente WHERE emailCliente = ?"
    ];

    foreach ($sqls as $tipo => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($senha === $row['senha']) {
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['papel'] = $row['papel'];

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
}
?>
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="login.css"> 
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
              <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite sua senha" required>
            </div>
            <button type="submit" class="btn btn-login w-100 fw-bold">Entrar</button>
          </form>

          <p class="text-center link-container">
            Ainda não tem conta? <a href="cadastro.php" class="link-cadastro">Cadastre-se</a>
          </p>
          <p class="text-center link-container">
            <a href="index.php" class="link-site">Voltar ao Site</a>
          </p>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
