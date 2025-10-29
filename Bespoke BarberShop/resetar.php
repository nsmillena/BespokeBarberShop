<?php
session_start();
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/helpers.php';

$bd = new Banco(); $conn = $bd->getConexao();
$token = $_GET['token'] ?? '';
$msg = null; $ok = false; $valid = false; $papel = null; $email = null;
if ($token){
    $st = $conn->prepare("SELECT email,papel,expires_at,used FROM PasswordReset WHERE token=? LIMIT 1");
    $st->bind_param('s', $token); $st->execute(); $res=$st->get_result();
    if ($row = $res->fetch_assoc()){
        if ((int)$row['used']===0 && new DateTime() <= new DateTime($row['expires_at'])){
            $valid = true; $papel=$row['papel']; $email=$row['email'];
        }
    }
    $st->close();
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $token = $_POST['token'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $conf  = $_POST['conf'] ?? '';
    if ($senha !== $conf || strlen($senha) < 6){
        $msg = 'As senhas devem ser iguais e ter pelo menos 6 caracteres.';
    } else {
        $st = $conn->prepare("SELECT email,papel,expires_at,used FROM PasswordReset WHERE token=? LIMIT 1");
        $st->bind_param('s', $token); $st->execute(); $res=$st->get_result(); $st->close();
        if ($row = $res->fetch_assoc()){
            if ((int)$row['used']===0 && new DateTime() <= new DateTime($row['expires_at'])){
                $papel=$row['papel']; $email=$row['email'];
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                if ($papel==='admin'){
                    $st2 = $conn->prepare("UPDATE Administrador SET senhaAdmin=? WHERE emailAdmin=?");
                } elseif ($papel==='barbeiro'){
                    $st2 = $conn->prepare("UPDATE Barbeiro SET senhaBarbeiro=?, deveTrocarSenha=0, senhaTempExpiraEm=NULL WHERE emailBarbeiro=?");
                } else {
                    $st2 = $conn->prepare("UPDATE Cliente SET senhaCliente=? WHERE emailCliente=?");
                }
                $st2->bind_param('ss', $hash, $email); $ok = $st2->execute(); $st2->close();
                if ($ok){
                    $conn->query("UPDATE PasswordReset SET used=1 WHERE token='".$conn->real_escape_string($token)."'");
                    $msg = 'Senha alterada com sucesso. Faça login novamente.';
                } else {
                    $msg = 'Falha ao atualizar a senha.';
                }
            } else { $msg = 'Token inválido ou expirado.'; }
        } else { $msg = 'Token não encontrado.'; }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redefinir senha</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6">
        <div class="card bg-secondary-subtle text-dark">
          <div class="card-body p-4">
            <h4 class="mb-3">Definir nova senha</h4>
            <?php if ($msg): ?>
              <div class="alert <?= $ok?'alert-success':'alert-danger' ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if (!$token || (!$valid && !$ok)): ?>
              <div class="alert alert-warning">Token inválido ou expirado. Solicite um novo <a href="recuperar.php">aqui</a>.</div>
            <?php endif; ?>
            <?php if ($token && ($valid || $ok)): ?>
              <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                  <label class="form-label">Nova senha</label>
                  <input type="password" name="senha" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                  <label class="form-label">Confirmar senha</label>
                  <input type="password" name="conf" class="form-control" required minlength="6">
                </div>
                <button class="btn btn-warning">Salvar</button>
                <a class="btn btn-link" href="login.php">Voltar ao login</a>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>