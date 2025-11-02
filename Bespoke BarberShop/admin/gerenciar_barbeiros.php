<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
$bd = new Banco();
$conn = $bd->getConexao();
require_once "../includes/i18n.php";
require_once "../includes/format.php";
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$admin_id = $_SESSION['usuario_id'];

// Buscar unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();
if (empty($unidade_id)) {
    header('Location: index_admin.php?ok=0&msg=' . urlencode(t('admin.not_linked_unit')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('user.security_fail')));
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'toggle') {
        $id = (int)$_POST['id'];
        $novoStatus = $_POST['status'] === 'Ativo' ? 'Ativo' : 'Inativo';
        $dataRetorno = !empty($_POST['dataRetorno']) ? $_POST['dataRetorno'] : null;
        // Garantir que o barbeiro pertence à unidade
        $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
        $chk->bind_param("ii", $id, $unidade_id);
        $chk->execute();
        $ok = $chk->get_result()->num_rows > 0; $chk->close();
        if (!$ok) { header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.invalid_barber'))); exit; }
        if ($novoStatus === 'Ativo') { $dataRetorno = null; }
        $upd = $conn->prepare("UPDATE Barbeiro SET statusBarbeiro=?, dataRetorno=? WHERE idBarbeiro=?");
        $upd->bind_param("ssi", $novoStatus, $dataRetorno, $id);
        if ($upd->execute()) {
            header('Location: gerenciar_barbeiros.php?ok=1&msg=' . urlencode(t('admin.ok_status_updated')));
            exit;
        } else {
            header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.err_status_update')));
            exit;
        }
    } elseif ($acao === 'excluir') {
        $id = (int)$_POST['id'];
        // Garantir que o barbeiro pertence à unidade
        $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
        $chk->bind_param("ii", $id, $unidade_id);
        $chk->execute();
        $ok = $chk->get_result()->num_rows > 0; $chk->close();
        if (!$ok) { header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.invalid_barber'))); exit; }
        $del = $conn->prepare("DELETE FROM Barbeiro WHERE idBarbeiro=?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            header('Location: gerenciar_barbeiros.php?ok=1&msg=' . urlencode(t('admin.ok_barber_deleted')));
            exit;
        } else {
            header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.err_barber_delete')));
            exit;
        }
    } elseif ($acao === 'reset_pw') {
        $id = (int)$_POST['id'];
        // Garantir que o barbeiro pertence à unidade
        $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
        $chk->bind_param("ii", $id, $unidade_id);
        $chk->execute();
        $ok = $chk->get_result()->num_rows > 0; $chk->close();
    if (!$ok) { header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.invalid_barber'))); exit; }
        // Gerar senha temporária forte
        function bb_gen_temp_pw($len=12){
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@$%!#?&*';
            $max = strlen($alphabet)-1; $pw='';
            for($i=0;$i<$len;$i++){ $pw .= $alphabet[random_int(0,$max)]; }
            return $pw;
        }
        $temp = bb_gen_temp_pw(12);
    $hash = password_hash($temp, PASSWORD_DEFAULT);
    // Define senha temporária, força troca no próximo login e expira em 7 dias
    $up = $conn->prepare("UPDATE Barbeiro SET senhaBarbeiro=?, deveTrocarSenha=1, senhaTempExpiraEm=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE idBarbeiro=?");
    $up->bind_param("si", $hash, $id);
        if ($up->execute()){
            $_SESSION['reset_barber_password_show_once'] = $temp;
            header('Location: gerenciar_barbeiros.php?ok=1&msg=' . urlencode(t('admin.temp_pw_generated')));
            exit;
        } else {
            header('Location: gerenciar_barbeiros.php?ok=0&msg=' . urlencode(t('admin.err_reset_pw')));
            exit;
        }
    }
}

// Listar barbeiros da unidade
$barbeiros = [];
$qr = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro, emailBarbeiro, telefoneBarbeiro, statusBarbeiro, dataRetorno FROM Barbeiro WHERE Unidade_idUnidade=? ORDER BY nomeBarbeiro");
$qr->bind_param("i", $unidade_id);
$qr->execute();
$rs = $qr->get_result();
while ($row = $rs->fetch_assoc()) { $barbeiros[] = $row; }
$qr->close();
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-BR' ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('admin.manage_barbers') ?> - Admin</title>
    <link rel="stylesheet" href="dashboard_admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
<div class="container py-5">
    <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 bb-toast-container" id="toast-msg-container"></div>
    <div class="dashboard-card">
        <?php if (!empty($_SESSION['reset_barber_password_show_once'])): $onePw = $_SESSION['reset_barber_password_show_once']; unset($_SESSION['reset_barber_password_show_once']); ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <strong><?= t('admin.temp_pw_generated') ?></strong>
                    <span id="resetBarberPw" style="font-family:monospace;"><?= htmlspecialchars($onePw) ?></span>
                    <small class="d-block text-muted">&nbsp;</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="copyResetPw()"><i class="bi bi-clipboard"></i> <?= t('admin.copy') ?></button>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="dashboard-title mb-0"><i class="bi bi-people"></i> <?= t('admin.manage_barbers') ?></h2>
            <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> <?= t('common.back') ?></a>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th><?= t('admin.name') ?></th>
                        <th><?= t('admin.email') ?? 'Email' ?></th>
                        <th><?= t('admin.phone') ?></th>
                        <th><?= t('user.status') ?></th>
                        <th><?= t('admin.return') ?></th>
                        <th><?= t('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($barbeiros)>0): foreach ($barbeiros as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['nomeBarbeiro']) ?></td>
                        <td><?= htmlspecialchars($b['emailBarbeiro']) ?></td>
                        <td><?= htmlspecialchars($b['telefoneBarbeiro']) ?></td>
                        <td><?= ($b['statusBarbeiro']==='Ativo') ? t('admin.active') : t('admin.inactive') ?></td>
                        <td><?= $b['dataRetorno'] ? bb_format_date($b['dataRetorno']) : '-' ?></td>
                        <td>
                            <?php if ($b['statusBarbeiro'] === 'Ativo'): ?>
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalInativar" data-id="<?= (int)$b['idBarbeiro'] ?>" data-nome="<?= htmlspecialchars($b['nomeBarbeiro']) ?>"><i class="bi bi-slash-circle"></i> <?= t('admin.deactivate') ?></button>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="acao" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$b['idBarbeiro'] ?>">
                                    <input type="hidden" name="status" value="Ativo">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> <?= t('admin.activate') ?></button>
                                </form>
                            <?php endif; ?>
                            <button class="btn btn-warning btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#modalReset" data-id="<?= (int)$b['idBarbeiro'] ?>" data-nome="<?= htmlspecialchars($b['nomeBarbeiro']) ?>"><i class="bi bi-shield-lock"></i> <?= t('admin.reset_password') ?></button>
                            <button class="btn btn-outline-danger btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#modalExcluir" data-id="<?= (int)$b['idBarbeiro'] ?>" data-nome="<?= htmlspecialchars($b['nomeBarbeiro']) ?>"><i class="bi bi-trash"></i> <?= t('admin.delete') ?></button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center"><?= t('admin.none_found') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Inativar -->
<div class="modal fade" id="modalInativar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
                <h5 class="modal-title text-warning"><i class="bi bi-exclamation-triangle"></i> <?= t('admin.inactivate_barber') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="acao" value="toggle">
          <input type="hidden" name="id" id="inaId">
          <input type="hidden" name="status" value="Inativo">
                    <p><?= t('admin.inform_return_date') ?></p>
          <div class="mb-2">
            <input type="date" class="form-control" name="dataRetorno" id="inaData">
          </div>
        </div>
        <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('common.cancel') ?></button>
                    <button type="submit" class="btn btn-danger"><?= t('admin.confirm_deactivate') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Excluir -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-octagon"></i> <?= t('admin.delete') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" id="excId">
                    <p><?= t('admin.confirm_delete_barber') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('common.cancel') ?></button>
                    <button type="submit" class="btn btn-danger"><?= t('admin.delete') ?></button>
                </div>
            </form>
        </div>
    </div>
    </div>

<!-- Modal Resetar Senha -->
<div class="modal fade" id="modalReset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title text-warning"><i class="bi bi-shield-lock"></i> <?= t('admin.reset_password') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="acao" value="reset_pw">
                    <input type="hidden" name="id" id="resetId">
                    <p>&nbsp;</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('common.cancel') ?></button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-shield-lock"></i> <?= t('admin.reset_password') ?></button>
                </div>
            </form>
        </div>
    </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/date-mask.js"></script>
<script>
// Toasts
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

// Passar dados para modal
const modal = document.getElementById('modalInativar');
modal?.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget; // Button that triggered the modal
  const id = button.getAttribute('data-id');
  document.getElementById('inaId').value = id;
  document.getElementById('inaData').value = '';
});

const modalExc = document.getElementById('modalExcluir');
modalExc?.addEventListener('show.bs.modal', function(event){
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    document.getElementById('excId').value = id;
});

const modalReset = document.getElementById('modalReset');
modalReset?.addEventListener('show.bs.modal', function(event){
    const button = event.relatedTarget;
    const id = button.getAttribute('data-id');
    document.getElementById('resetId').value = id;
});

function copyResetPw(){
    const el = document.getElementById('resetBarberPw'); if(!el) return;
    const range = document.createRange(); range.selectNode(el);
    const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
    try { document.execCommand('copy'); } catch(_){ navigator.clipboard && navigator.clipboard.writeText(el.textContent); }
    sel.removeAllRanges();
}
</script>
<?php @include_once("../Footer/footer.html"); ?>
</body>
</html>
