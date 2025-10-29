<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include_once "../includes/db.php";
include_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = $_SESSION['usuario_id'];
// Buscar ID da unidade do admin (para vínculo de serviços)
$sqlUni = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sqlUni->bind_param("i", $admin_id);
$sqlUni->execute();
$sqlUni->bind_result($unidade_id);
$sqlUni->fetch();
$sqlUni->close();

$sucesso = '';
$erro = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Falha de segurança. Atualize a página e tente novamente.'));
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'adicionar') {
        $nome = trim($_POST['nome']);
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (empty($nome) || $preco <= 0 || $duracao <= 0) {
            header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Todos os campos são obrigatórios e devem ter valores válidos.'));
            exit;
        } else {
            // descricaoServico é NOT NULL no schema; garantir string vazia ao menos
            $insert = $conn->prepare("INSERT INTO Servico (nomeServico, descricaoServico, duracaoPadrao, precoServico) VALUES (?, ?, ?, ?)");
            // preco is DECIMAL, bind as double 'd'
            $insert->bind_param("ssid", $nome, $descricao, $duracao, $preco);
            
            if ($insert->execute()) {
                header('Location: gerenciar_servicos.php?ok=1&msg=' . urlencode('Serviço adicionado com sucesso!'));
                exit;
            } else {
                header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Erro ao adicionar serviço.'));
                exit;
            }
            $insert->close();
        }
    } elseif ($acao === 'editar') {
        $id = intval($_POST['id']);
        $nome = trim($_POST['nome']);
        $preco = floatval($_POST['preco']);
        $duracao = intval($_POST['duracao']);
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (empty($nome) || $preco <= 0 || $duracao <= 0) {
            header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Todos os campos são obrigatórios e devem ter valores válidos.'));
            exit;
        } else {
            $update = $conn->prepare("UPDATE Servico SET nomeServico = ?, descricaoServico = ?, duracaoPadrao = ?, precoServico = ? WHERE idServico = ?");
            $update->bind_param("ssidi", $nome, $descricao, $duracao, $preco, $id);
            
            if ($update->execute()) {
                header('Location: gerenciar_servicos.php?ok=1&msg=' . urlencode('Serviço atualizado com sucesso!'));
                exit;
            } else {
                header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Erro ao atualizar serviço.'));
                exit;
            }
            $update->close();
        }
    } elseif ($acao === 'deletar') {
        $id = intval($_POST['id']);
        
        // Verificar se há agendamentos usando este serviço (via tabela de associação)
        $check = $conn->prepare("SELECT COUNT(*) FROM Agendamento_has_Servico WHERE Servico_idServico = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
        
        if ($count > 0) {
            header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Não é possível deletar este serviço pois há agendamentos vinculados a ele.'));
            exit;
        } else {
            $delete = $conn->prepare("DELETE FROM Servico WHERE idServico = ?");
            $delete->bind_param("i", $id);
            
            if ($delete->execute()) {
                header('Location: gerenciar_servicos.php?ok=1&msg=' . urlencode('Serviço deletado com sucesso!'));
                exit;
            } else {
                header('Location: gerenciar_servicos.php?ok=0&msg=' . urlencode('Erro ao deletar serviço.'));
                exit;
            }
            $delete->close();
        }
    } elseif ($acao === 'vincular_unidade' && !empty($unidade_id)) {
        // Atualizar vinculação de serviços à unidade do admin
        $selecionados = isset($_POST['servicos_unidade']) && is_array($_POST['servicos_unidade'])
            ? array_map('intval', $_POST['servicos_unidade']) : [];

        // Buscar atuais
        $atuais = [];
        $resV = $conn->prepare("SELECT Servico_idServico FROM Unidade_has_Servico WHERE Unidade_idUnidade = ?");
        $resV->bind_param("i", $unidade_id);
        $resV->execute();
        $r = $resV->get_result();
        while ($row = $r->fetch_assoc()) { $atuais[] = (int)$row['Servico_idServico']; }
        $resV->close();

        $add = array_values(array_diff($selecionados, $atuais));
        $rem = array_values(array_diff($atuais, $selecionados));

        // Inserir novos
        if (!empty($add)) {
            $ins = $conn->prepare("INSERT INTO Unidade_has_Servico (Unidade_idUnidade, Servico_idServico) VALUES (?, ?)");
            foreach ($add as $sid) { $ins->bind_param("ii", $unidade_id, $sid); $ins->execute(); }
            $ins->close();
        }
        // Remover desmarcados
        if (!empty($rem)) {
            $del = $conn->prepare("DELETE FROM Unidade_has_Servico WHERE Unidade_idUnidade = ? AND Servico_idServico = ?");
            foreach ($rem as $sid) { $del->bind_param("ii", $unidade_id, $sid); $del->execute(); }
            $del->close();
        }
        header('Location: gerenciar_servicos.php?ok=1&msg=' . urlencode('Vinculações de serviços atualizadas.'));
        exit;
    }
}

// Buscar todos os serviços
$servicos = [];
$result = $conn->query("SELECT idServico, nomeServico, descricaoServico, precoServico, duracaoPadrao FROM Servico ORDER BY nomeServico");
while ($row = $result->fetch_assoc()) {
    $servicos[] = $row;
}
// Buscar serviços vinculados à unidade (se houver)
$servicosUnidade = [];
if (!empty($unidade_id)) {
    $resSu = $conn->prepare("SELECT Servico_idServico FROM Unidade_has_Servico WHERE Unidade_idUnidade = ?");
    $resSu->bind_param("i", $unidade_id);
    $resSu->execute();
    $rsu = $resSu->get_result();
    while ($row = $rsu->fetch_assoc()) { $servicosUnidade[] = (int)$row['Servico_idServico']; }
    $resSu->close();
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
        <!-- Toast container -->
        <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>
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
                    <!-- Barra de busca -->
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-dark text-warning border-secondary"><i class="bi bi-search"></i></span>
                        <input type="text" id="filtro-servicos" class="form-control bg-dark text-light border-secondary" placeholder="Filtrar serviços por nome ou descrição...">
                    </div>

                    <div class="table-responsive">
                        <table id="tabela-servicos" class="table table-dark table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome do Serviço</th>
                                    <th>Preço</th>
                                    <th>Duração</th>
                                    <th>Descrição</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servicos as $servico): ?>
                                    <tr>
                                        <td><?= $servico['idServico'] ?></td>
                                        <td><?= htmlspecialchars($servico['nomeServico']) ?></td>
                                        <td>R$ <?= number_format($servico['precoServico'], 2, ',', '.') ?></td>
                                        <td><?= bb_format_minutes((int)$servico['duracaoPadrao']) ?></td>
                                        <td class="text-truncate" style="max-width:280px;" title="<?= htmlspecialchars($servico['descricaoServico']) ?>"><?= htmlspecialchars($servico['descricaoServico']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm me-1" onclick="editarServico(<?= $servico['idServico'] ?>, '<?= htmlspecialchars($servico['nomeServico']) ?>', <?= $servico['precoServico'] ?>, <?= (int)$servico['duracaoPadrao'] ?>, '<?= htmlspecialchars($servico['descricaoServico']) ?>')">
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
                                        <td colspan="6" class="text-center">Nenhum serviço cadastrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($unidade_id)): ?>
                    <hr class="my-4" style="border-color: rgba(218,165,32,0.3);">
                    <h5 class="dashboard-section-title"><i class="bi bi-building"></i> Serviços desta unidade</h5>
                    <form method="POST" class="mb-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="acao" value="vincular_unidade">
                        <div class="row g-2">
                            <?php foreach ($servicos as $servico): $sid=(int)$servico['idServico']; $chk=in_array($sid, $servicosUnidade); ?>
                                <div class="col-12 col-md-6 col-lg-4">
                                    <div class="form-check text-light">
                                        <input class="form-check-input" type="checkbox" id="su_<?= $sid ?>" name="servicos_unidade[]" value="<?= $sid ?>" <?= $chk? 'checked' : '' ?>>
                                        <label class="form-check-label" for="su_<?= $sid ?>"><?= htmlspecialchars($servico['nomeServico']) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="dashboard-action"><i class="bi bi-save"></i> Salvar vinculações</button>
                        </div>
                    </form>
                    <?php endif; ?>
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição do Serviço</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="2" placeholder="Ex.: Corte com acabamento e lavagem"></textarea>
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                        <div class="mb-3">
                            <label for="editDescricao" class="form-label">Descrição do Serviço</label>
                            <textarea class="form-control" id="editDescricao" name="descricao" rows="2"></textarea>
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
        // Toasts
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

        // Filtro da tabela
        const filtro = document.getElementById('filtro-servicos');
        const tabela = document.getElementById('tabela-servicos');
        if (filtro && tabela) {
            filtro.addEventListener('input', () => {
                const q = filtro.value.toLowerCase();
                tabela.querySelectorAll('tbody tr').forEach(tr => {
                    const nome = (tr.children[1]?.textContent || '').toLowerCase();
                    const desc = (tr.children[4]?.textContent || '').toLowerCase();
                    tr.style.display = (nome.includes(q) || desc.includes(q)) ? '' : 'none';
                });
            });
        }
    </script>
    <script>
        function editarServico(id, nome, preco, duracao, descricao) {
            document.getElementById('editId').value = id;
            document.getElementById('editNome').value = nome;
            document.getElementById('editPreco').value = preco;
            document.getElementById('editDuracao').value = duracao;
            document.getElementById('editDescricao').value = descricao || '';
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
        
        .table-dark { --bs-table-bg: rgba(255,255,255,0.05); }
        .table-responsive{ overflow-x:auto !important; }
        .table{ min-width: 800px; table-layout: auto; }
        .table td:last-child{ white-space: nowrap; }
        
        /* Alerts kept minimal; toasts are primary feedback */
    </style>
    <?php @include_once("../Footer/footer.html"); ?>
</body>
</html>