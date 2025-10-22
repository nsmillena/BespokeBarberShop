<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();

$sucesso = '';
$erro = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $nome = trim($_POST['nome']);
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        
        if (empty($nome) || $preco <= 0 || $duracao <= 0) {
            $erro = 'Todos os campos são obrigatórios e devem ter valores válidos.';
        } else {
            $insert = $conn->prepare("INSERT INTO Servico (nomeServico, precoServico, duracaoServico) VALUES (?, ?, ?)");
            $insert->bind_param("sdi", $nome, $preco, $duracao);
            
            if ($insert->execute()) {
                $sucesso = 'Serviço adicionado com sucesso!';
            } else {
                $erro = 'Erro ao adicionar serviço.';
            }
            $insert->close();
        }
    } elseif ($acao === 'editar') {
        $id = intval($_POST['id']);
        $nome = trim($_POST['nome']);
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        
        if (empty($nome) || $preco <= 0 || $duracao <= 0) {
            $erro = 'Todos os campos são obrigatórios e devem ter valores válidos.';
        } else {
            $update = $conn->prepare("UPDATE Servico SET nomeServico = ?, precoServico = ?, duracaoServico = ? WHERE idServico = ?");
            $update->bind_param("sdii", $nome, $preco, $duracao, $id);
            
            if ($update->execute()) {
                $sucesso = 'Serviço atualizado com sucesso!';
            } else {
                $erro = 'Erro ao atualizar serviço.';
            }
            $update->close();
        }
    } elseif ($acao === 'deletar') {
        $id = intval($_POST['id']);
        
        // Verificar se há agendamentos usando este serviço
        $check = $conn->prepare("SELECT COUNT(*) FROM Agendamento WHERE Servico_idServico = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
        
        if ($count > 0) {
            $erro = 'Não é possível deletar este serviço pois há agendamentos vinculados a ele.';
        } else {
            $delete = $conn->prepare("DELETE FROM Servico WHERE idServico = ?");
            $delete->bind_param("i", $id);
            
            if ($delete->execute()) {
                $sucesso = 'Serviço deletado com sucesso!';
            } else {
                $erro = 'Erro ao deletar serviço.';
            }
            $delete->close();
        }
    }
}

// Buscar todos os serviços
$servicos = [];
$result = $conn->query("SELECT idServico, nomeServico, precoServico, duracaoServico FROM Servico ORDER BY nomeServico");
while ($row = $result->fetch_assoc()) {
    $servicos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Serviços - Admin</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="dashboard-title mb-0"><i class="bi bi-gear"></i> Gerenciar Serviços</h2>
                        <div>
                            <button type="button" class="dashboard-action me-2" data-bs-toggle="modal" data-bs-target="#modalAdicionar">
                                <i class="bi bi-plus-circle"></i> Adicionar Serviço
                            </button>
                            <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
                        </div>
                    </div>
                    
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($sucesso) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($erro): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($erro) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome do Serviço</th>
                                    <th>Preço</th>
                                    <th>Duração (min)</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servicos as $servico): ?>
                                    <tr>
                                        <td><?= $servico['idServico'] ?></td>
                                        <td><?= htmlspecialchars($servico['nomeServico']) ?></td>
                                        <td>R$ <?= number_format($servico['precoServico'], 2, ',', '.') ?></td>
                                        <td><?= $servico['duracaoServico'] ?> min</td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm me-1" onclick="editarServico(<?= $servico['idServico'] ?>, '<?= htmlspecialchars($servico['nomeServico']) ?>', <?= $servico['precoServico'] ?>, <?= $servico['duracaoServico'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deletarServico(<?= $servico['idServico'] ?>, '<?= htmlspecialchars($servico['nomeServico']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($servicos)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Nenhum serviço cadastrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Adicionar -->
    <div class="modal fade" id="modalAdicionar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="bi bi-plus-circle"></i> Adicionar Serviço</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="adicionar">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Serviço</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="preco" class="form-label">Preço (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="preco" name="preco" required>
                        </div>
                        <div class="mb-3">
                            <label for="duracao" class="form-label">Duração (minutos)</label>
                            <input type="number" min="1" class="form-control" id="duracao" name="duracao" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="bi bi-pencil"></i> Editar Serviço</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" id="editId">
                        <div class="mb-3">
                            <label for="editNome" class="form-label">Nome do Serviço</label>
                            <input type="text" class="form-control" id="editNome" name="nome" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPreco" class="form-label">Preço (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="editPreco" name="preco" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDuracao" class="form-label">Duração (minutos)</label>
                            <input type="number" min="1" class="form-control" id="editDuracao" name="duracao" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Deletar -->
    <div class="modal fade" id="modalDeletar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="deletar">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Tem certeza que deseja deletar o serviço <strong id="deleteNome"></strong>?</p>
                        <p class="text-warning">Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Deletar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editarServico(id, nome, preco, duracao) {
            document.getElementById('editId').value = id;
            document.getElementById('editNome').value = nome;
            document.getElementById('editPreco').value = preco;
            document.getElementById('editDuracao').value = duracao;
            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
        
        function deletarServico(id, nome) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNome').textContent = nome;
            new bootstrap.Modal(document.getElementById('modalDeletar')).show();
        }
    </script>
    
    <style>
        .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(218,165,32,0.3);
            color: #fff;
        }
        
        .form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: #daa520;
            box-shadow: 0 0 0 0.2rem rgba(218,165,32,0.25);
            color: #fff;
        }
        
        .form-label {
            color: #daa520;
            font-weight: 500;
        }
        
        .table-dark {
            --bs-table-bg: rgba(255,255,255,0.05);
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