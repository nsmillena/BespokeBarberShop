<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = $_SESSION['usuario_id'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Buscar dados atuais do admin
$sql = $conn->prepare("SELECT nomeAdmin, emailAdmin, telefoneAdmin FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($nome_atual, $email_atual, $telefone_atual);
$sql->fetch();
$sql->close();

$sucesso = false;
$erro = '';

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
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    
    if (empty($nome) || empty($email) || empty($telefone)) {
        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Nome, email e telefone são obrigatórios.'));
        exit;
    } else {
        // Verificar se o email já existe (exceto o próprio)
        $check = $conn->prepare("SELECT idAdministrador FROM Administrador WHERE emailAdmin = ? AND idAdministrador != ?");
        $check->bind_param("si", $email, $admin_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $check->close();
            header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Este email já está sendo usado por outro administrador.'));
            exit;
        } else {
            $update_fields = "nomeAdmin = ?, emailAdmin = ?, telefoneAdmin = ?";
            $params = [$nome, $email, $telefone];
            $types = "sss";
            
            // Se informou senha atual e nova senha
            if (!empty($senha_atual) && !empty($nova_senha)) {
                if ($nova_senha !== $confirma_senha) {
                    $check->close();
                    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Confirmação de senha não confere.'));
                    exit;
                }
                // Critérios de força: min 8, 1 maiúscula, 1 minúscula, 1 dígito, 1 especial, diferente da atual
                $temMaiuscula = preg_match('/[A-Z]/', $nova_senha);
                $temMinuscula = preg_match('/[a-z]/', $nova_senha);
                $temDigito    = preg_match('/\d/', $nova_senha);
                $temEspecial  = preg_match('/[^A-Za-z0-9]/', $nova_senha);
                if (strlen($nova_senha) < 8 || !$temMaiuscula || !$temMinuscula || !$temDigito || !$temEspecial) {
                    $check->close();
                    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('A nova senha deve ter no mínimo 8 caracteres e incluir maiúscula, minúscula, dígito e símbolo.'));
                    exit;
                }
                // Verificar senha atual (compatível com hash e possível legado em texto plano)
                $check_pass = $conn->prepare("SELECT senhaAdmin FROM Administrador WHERE idAdministrador = ?");
                $check_pass->bind_param("i", $admin_id);
                $check_pass->execute();
                $check_pass->bind_result($senha_hash);
                $check_pass->fetch();
                $check_pass->close();

                $senhaConfere = false;
                if (!empty($senha_hash)) {
                    // Tenta verificar como hash
                    if (password_verify($senha_atual, $senha_hash)) {
                        $senhaConfere = true;
                    } else {
                        // Fallback: se banco tiver senha antiga em texto plano
                        if (hash_equals((string)$senha_hash, (string)$senha_atual)) {
                            $senhaConfere = true;
                        }
                    }
                }

                if ($senhaConfere) {
                    if (hash_equals((string)$nova_senha, (string)$senha_atual)) {
                        $check->close();
                        header('Location: editar_perfil.php?ok=0&msg=' . urlencode('A nova senha deve ser diferente da senha atual.'));
                        exit;
                    }
                    $update_fields .= ", senhaAdmin = ?";
                    $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $types .= "s";
                } else {
                    $check->close();
                    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Senha atual incorreta.'));
                    exit;
                }
            }
            
            if (empty($erro)) {
                $params[] = $admin_id;
                $types .= "i";
                
                $update = $conn->prepare("UPDATE Administrador SET $update_fields WHERE idAdministrador = ?");
                $update->bind_param($types, ...$params);
                
                if ($update->execute()) {
                    $update->close();
                    $check->close();
                    // Redirecionar com toast
                    header('Location: editar_perfil.php?ok=1&msg=' . urlencode('Perfil atualizado com sucesso!'));
                    exit;
                } else {
                    $update->close();
                    $check->close();
                    header('Location: editar_perfil.php?ok=0&msg=' . urlencode('Erro ao atualizar perfil. Tente novamente.'));
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil - Admin</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="container py-5">
        <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="dashboard-title mb-0"><i class="bi bi-person-circle"></i> Editar Perfil</h2>
                        <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($nome_atual) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email_atual) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($telefone_atual) ?>" required>
                        </div>
                        
                        <hr class="my-4" style="border-color: rgba(218,165,32,0.3);">
                        
                        <h5 class="text-light mb-3">Alterar Senha (opcional)</h5>
                        
                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                            <small class="text-muted">Deixe em branco se não quiser alterar a senha</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                            <small class="text-muted">Mínimo 8, com maiúscula, minúscula, número e símbolo.</small>
                        </div>

                        <div class="mb-4">
                            <label for="confirma_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirma_senha" name="confirma_senha">
                        </div>
                        
                        <button type="submit" class="dashboard-action w-100">
                            <i class="bi bi-check-circle"></i> Atualizar Perfil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getParam(name){ const url = new URL(window.location.href); return url.searchParams.get(name); }
        function showToast(message, ok){
            const container = document.getElementById('toast-msg-container'); if(!container || !message) return;
            const el = document.createElement('div');
            el.className = `toast align-items-center ${ok==='1' ? 'text-bg-success' : 'text-bg-danger'} border-0`;
            el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); el.setAttribute('aria-atomic','true');
            el.innerHTML = `<div class=\"d-flex\"><div class=\"toast-body\">${message}</div><button type=\"button\" class=\"btn-close btn-close-white me-2 m-auto\" data-bs-dismiss=\"toast\" aria-label=\"Close\"></button></div>`;
            container.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
        }
        const msg = getParam('msg'); const ok = getParam('ok'); if (msg) { try { showToast(decodeURIComponent(msg), ok); } catch(_) { showToast(msg, ok); } }
    </script>
    <style>
        .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(218,165,32,0.3);
            color: #fff;
            border-radius: 8px;
            padding: 0.8rem;
        }
        
        .form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: #daa520;
            box-shadow: 0 0 0 0.2rem rgba(218,165,32,0.25);
            color: #fff;
        }
        
        .form-control::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .form-label {
            color: #daa520;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        /* Feedback via toasts; alerts removidos */
        
        .text-muted {
            color: rgba(255,255,255,0.6) !important;
        }
    </style>
    <?php @include_once("../Footer/footer.html"); ?>
</body>
</html>