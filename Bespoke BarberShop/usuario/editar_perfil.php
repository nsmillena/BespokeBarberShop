<?php
// Página de edição de perfil do usuário
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'cliente') {
    header("Location: ../login.php");
    exit;
}
include "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
$idCliente = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $stmt = $conn->prepare("UPDATE Cliente SET nomeCliente=?, emailCliente=?, telefoneCliente=? WHERE idCliente=?");
    $stmt->bind_param("sssi", $nome, $email, $telefone, $idCliente);
    $stmt->execute();
    $stmt->close();
    header("Location: index_usuario.php?edit=ok");
    exit;
}
$stmt = $conn->prepare("SELECT nomeCliente, emailCliente, telefoneCliente FROM Cliente WHERE idCliente = ?");
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$stmt->bind_result($nome, $email, $telefone);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil | Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard_usuario.css">
</head>
<body style="background:#181818;">
<div class="container py-5">
    <div class="dashboard-card mx-auto" style="max-width:420px;">
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-pencil-square"></i> Editar Perfil</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($telefone) ?>" required>
            </div>
            <button type="submit" class="dashboard-action w-100">Salvar Alterações</button>
            <a href="index_usuario.php" class="btn btn-link w-100 mt-2" style="color:#daa520;">Cancelar</a>
        </form>
    </div>
</div>
</body>
</html>
