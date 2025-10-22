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
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    
    if (empty($nome) || empty($email) || empty($telefone)) {
        $erro = 'Nome, email e telefone são obrigatórios.';
    } else {
        // Verificar se o email já existe (exceto o próprio)
        $check = $conn->prepare("SELECT idAdministrador FROM Administrador WHERE emailAdmin = ? AND idAdministrador != ?");
        $check->bind_param("si", $email, $admin_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $erro = 'Este email já está sendo usado por outro administrador.';
        } else {
            $update_fields = "nomeAdmin = ?, emailAdmin = ?, telefoneAdmin = ?";
            $params = [$nome, $email, $telefone];
            $types = "sss";
            
            // Se informou senha atual e nova senha
            if (!empty($senha_atual) && !empty($nova_senha)) {
                // Verificar senha atual
                $check_pass = $conn->prepare("SELECT senhaAdmin FROM Administrador WHERE idAdministrador = ?");
                $check_pass->bind_param("i", $admin_id);
                $check_pass->execute();
                $check_pass->bind_result($senha_hash);
                $check_pass->fetch();
                $check_pass->close();
                
                if (password_verify($senha_atual, $senha_hash)) {
                    $update_fields .= ", senhaAdmin = ?";
                    $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $types .= "s";
                } else {
                    $erro = 'Senha atual incorreta.';
                }
            }
            
            if (empty($erro)) {
                $params[] = $admin_id;
                $types .= "i";
                
                $update = $conn->prepare("UPDATE Administrador SET $update_fields WHERE idAdministrador = ?");
                $update->bind_param($types, ...$params);
                
                if ($update->execute()) {
                    $sucesso = true;
                    // Atualizar variáveis locais
                    $nome_atual = $nome;
                    $email_atual = $email;
                    $telefone_atual = $telefone;
                } else {
                    $erro = 'Erro ao atualizar perfil. Tente novamente.';
                }
                $update->close();
            }
        }
        $check->close();
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
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="dashboard-title mb-0"><i class="bi bi-person-circle"></i> Editar Perfil</h2>
                        <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle"></i> Perfil atualizado com sucesso!
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
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
                        </div>
                        
                        <button type="submit" class="dashboard-action w-100">
                            <i class="bi bi-check-circle"></i> Atualizar Perfil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
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
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .alert-success {
            background: rgba(40,167,69,0.2);
            color: #28a745;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: rgba(220,53,69,0.2);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }
        
        .text-muted {
            color: rgba(255,255,255,0.6) !important;
        }
    </style>
</body>
</html>