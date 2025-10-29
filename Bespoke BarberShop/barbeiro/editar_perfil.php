<?php
// Página de edição de perfil do barbeiro
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'barbeiro') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idBarbeiro = (int)$_SESSION['usuario_id'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Carregar dados atuais
$stmt = $conn->prepare("SELECT nomeBarbeiro, emailBarbeiro, telefoneBarbeiro, senhaBarbeiro, deveTrocarSenha, senhaTempExpiraEm FROM Barbeiro WHERE idBarbeiro = ?");
$stmt->bind_param("i", $idBarbeiro);
$stmt->execute();
$stmt->bind_result($nomeAtual, $emailAtual, $telefoneAtual, $senhaHashAtual, $deveTrocarSenha, $senhaTempExpiraEm);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Falha de segurança. Atualize a página e tente novamente.'));
        exit;
    }

    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma = $_POST['confirma_senha'] ?? '';

    if (empty($nome) || empty($email) || empty($telefone)) {
        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Nome, email e telefone são obrigatórios.'));
        exit;
    }

    // Email único
    $chk = $conn->prepare("SELECT idBarbeiro FROM Barbeiro WHERE emailBarbeiro=? AND idBarbeiro != ?");
    $chk->bind_param("si", $email, $idBarbeiro);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Este e-mail já está em uso.'));
        exit;
    }

    $update_fields = "nomeBarbeiro = ?, emailBarbeiro = ?, telefoneBarbeiro = ?";
    $params = [$nome, $email, $telefone];
    $types = "sss";

  // Se for obrigatório trocar a senha, exigir nova senha
  if ((int)$deveTrocarSenha === 1 && (empty($senha_atual) || empty($nova_senha))) {
    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('É obrigatório definir uma nova senha neste primeiro acesso. Informe a senha atual temporária e a nova.'));
    exit;
  }

  if (!empty($senha_atual) && !empty($nova_senha)) {
        if ($nova_senha !== $confirma) {
            $chk->close();
            header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Confirmação de senha não confere.'));
            exit;
        }
        $temMaiuscula = preg_match('/[A-Z]/', $nova_senha);
        $temMinuscula = preg_match('/[a-z]/', $nova_senha);
        $temDigito    = preg_match('/\d/', $nova_senha);
        $temEspecial  = preg_match('/[^A-Za-z0-9]/', $nova_senha);
        if (strlen($nova_senha) < 8 || !$temMaiuscula || !$temMinuscula || !$temDigito || !$temEspecial) {
            $chk->close();
            header('Location: editar_perfil.php?ok=0&msg=' . urlencode('A nova senha deve ter no mínimo 8 caracteres e incluir maiúscula, minúscula, dígito e símbolo.'));
            exit;
        }
        // Verificar senha atual (hash ou legado)
        $okSenha = false;
        if (!empty($senhaHashAtual) && strlen($senhaHashAtual) > 20 && password_get_info($senhaHashAtual)['algo'] !== 0) {
            $okSenha = password_verify($senha_atual, $senhaHashAtual);
        }
        if (!$okSenha) { $okSenha = hash_equals((string)$senhaHashAtual, (string)$senha_atual); }
        if (!$okSenha) {
            $chk->close();
            header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Senha atual incorreta.'));
            exit;
        }
        if (hash_equals((string)$nova_senha, (string)$senha_atual)) {
            $chk->close();
            header('Location: editar_perfil.php?ok=0&msg=' . urlencode('A nova senha deve ser diferente da atual.'));
            exit;
        }
    $update_fields .= ", senhaBarbeiro = ?, deveTrocarSenha = 0, senhaTempExpiraEm = NULL";
    $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
    $types .= "s";
    }

    $params[] = $idBarbeiro;
    $types .= "i";
    $upd = $conn->prepare("UPDATE Barbeiro SET $update_fields WHERE idBarbeiro = ?");
    $upd->bind_param($types, ...$params);
  if ($upd->execute()) {
        $upd->close(); $chk->close();
    if (!empty($_SESSION['must_change_password'])) { unset($_SESSION['must_change_password']); }
    header('Location: editar_perfil.php?ok=1&msg=' . urlencode('Perfil atualizado com sucesso.'));
        exit;
    } else {
        $upd->close(); $chk->close();
        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Erro ao atualizar perfil.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Perfil | Barbeiro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="dashboard_barbeiro.css">
</head>
<body class="dashboard-barbeiro-novo">
<div class="container py-5">
  <div class="dashboard-card mx-auto" style="max-width:480px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="dashboard-section-title mb-0"><i class="bi bi-pencil-square"></i> Editar Perfil</h2>
      <a href="index_barbeiro.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- Toast container -->
    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>

    <?php if ((int)$deveTrocarSenha === 1 || (!empty($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1)): ?>
      <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        Por segurança, você precisa alterar sua senha agora. Use a senha temporária como "Senha atual" e defina uma nova senha forte.
      </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="mb-3">
        <label class="form-label">Nome</label>
        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nomeAtual) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($emailAtual) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Telefone</label>
        <input type="tel" name="telefone" class="form-control bb-phone" value="<?= htmlspecialchars($telefoneAtual) ?>" required>
      </div>
      <hr class="my-3" style="border-color: rgba(218,165,32,0.3);">
      <h6 class="text-light">Alterar senha (opcional)</h6>
      <div class="mb-2">
        <label class="form-label">Senha atual</label>
        <input type="password" name="senha_atual" id="senha_atual" class="form-control" data-bb-toggle="1">
      </div>
      <div class="mb-2">
        <label class="form-label">Nova senha</label>
        <input type="password" name="nova_senha" id="nova_senha" class="form-control bb-password" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="Mínimo 8, com maiúscula, minúscula, número e símbolo.">
        <small class="text-muted">Mínimo 8, com maiúscula, minúscula, número e símbolo.</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirmar nova senha</label>
        <input type="password" name="confirma_senha" id="confirma_senha" class="form-control bb-password-confirm" data-match="#nova_senha">
      </div>
      <button type="submit" class="dashboard-action w-100">Salvar</button>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/phone-mask.js"></script>
<script src="../js/password-strength.js"></script>
<script>
function getParam(name){ const url = new URL(window.location.href); return url.searchParams.get(name); }
function showToast(message, ok){
  const container = document.getElementById('toast-msg-container'); if(!container || !message) return;
  const el = document.createElement('div');
  el.className = `toast align-items-center ${ok==='1' ? 'text-bg-success' : 'text-bg-danger'} border-0`;
  el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); el.setAttribute('aria-atomic','true');
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
  container.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
}
const msg = getParam('msg'); const ok = getParam('ok'); if (msg) { try { showToast(decodeURIComponent(msg), ok); } catch(_) { showToast(msg, ok); } }
</script>
</body>
</html>
