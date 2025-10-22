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

// Buscar ID da unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();

$sucesso = false;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $senha = $_POST['senha'];
    
    if (empty($nome) || empty($email) || empty($telefone) || empty($senha)) {
        $erro = 'Todos os campos são obrigatórios.';
    } else {
        // Verificar se o email já existe
        $check = $conn->prepare("SELECT idBarbeiro FROM Barbeiro WHERE emailBarbeiro = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $erro = 'Este email já está cadastrado.';
        } else {
            // Inserir novo barbeiro
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO Barbeiro (nomeBarbeiro, emailBarbeiro, telefoneBarbeiro, senhaBarbeiro, statusBarbeiro, Unidade_idUnidade) VALUES (?, ?, ?, ?, 'Ativo', ?)");
            $insert->bind_param("ssssi", $nome, $email, $telefone, $senha_hash, $unidade_id);
            
            if ($insert->execute()) {
                $sucesso = true;
            } else {
                $erro = 'Erro ao cadastrar barbeiro. Tente novamente.';
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Barbeiro - Admin</title>
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
                        <h2 class="dashboard-title mb-0"><i class="bi bi-person-plus"></i> Cadastrar Barbeiro</h2>
                        <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle"></i> Barbeiro cadastrado com sucesso!
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
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        
                        <button type="submit" class="dashboard-action w-100">
                            <i class="bi bi-person-plus"></i> Cadastrar Barbeiro
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
    </style>
</body>
</html>