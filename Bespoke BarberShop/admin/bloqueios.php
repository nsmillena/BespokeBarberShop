<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
require_once "../includes/i18n.php";
require_once "../includes/format.php";
require_once "../includes/helpers.php";
$bd = new Banco();
$conn = $bd->getConexao();
$admin_id = (int)$_SESSION['usuario_id'];
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

function table_exists(mysqli $conn, string $table): bool {
  try {
    $dbRes = $conn->query("SELECT DATABASE() AS db");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null; $schema = $dbRow ? $dbRow['db'] : null;
    if (!$schema) return false;
    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
    $stmt->bind_param('ss', $schema, $table);
    $stmt->execute(); $r=$stmt->get_result(); $ok = $r && $r->num_rows>0; $stmt->close();
    return $ok;
  } catch (Throwable $e) { return false; }
}

$bloqReady = table_exists($conn, 'BloqueioHorario');

// Buscar unidade do admin
$sql = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador = ?");
$sql->bind_param("i", $admin_id);
$sql->execute();
$sql->bind_result($unidade_id);
$sql->fetch();
$sql->close();
if (empty($unidade_id)) {
  header('Location: index_admin.php?ok=0&msg=' . urlencode(t('admin.select_barber_manage')));
    exit;
}

// Barbeiros da unidade
$barbeiros = [];
$qr = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade=? ORDER BY nomeBarbeiro");
$qr->bind_param('i', $unidade_id);
$qr->execute();
$rs = $qr->get_result();
while($row = $rs->fetch_assoc()) { $barbeiros[] = $row; }
$qr->close();

$barbeiroSel = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;

// Verifica se barbeiro selecionado pertence à unidade
if ($barbeiroSel) {
    $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
    $chk->bind_param('ii', $barbeiroSel, $unidade_id);
    $chk->execute();
    $okBarb = $chk->get_result()->num_rows > 0; $chk->close();
    if (!$okBarb) { $barbeiroSel = 0; }
}

// Se a tabela não existe, bloqueia ações de POST e mostra alerta amigável
if (!$bloqReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('admin.migration_needed_blocks')));
  exit;
}

// POST: add/remover bloqueio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bloqReady) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
    header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('user.security_fail')));
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    $bId = (int)($_POST['barbeiro'] ?? 0);
    // garantir que pertence à unidade do admin
    $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
    $chk->bind_param('ii', $bId, $unidade_id);
    $chk->execute(); $okBarb = $chk->get_result()->num_rows > 0; $chk->close();
  if (!$okBarb) { header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('admin.invalid_barber'))); exit; }

    if ($acao === 'add') {
        $data = $_POST['data'] ?? '';
        $horaIni = $_POST['horaInicio'] ?? '';
        $horaFim = $_POST['horaFim'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        $hi = DateTime::createFromFormat('H:i', $horaIni) ?: DateTime::createFromFormat('H:i:s', $horaIni);
        $hf = DateTime::createFromFormat('H:i', $horaFim) ?: DateTime::createFromFormat('H:i:s', $horaFim);
  if (!$dt || !$hi || !$hf) { header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('common.invalid_datetime'))); exit; }
  if ($hi >= $hf) { header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('common.time_start_before_end'))); exit; }
        // sobreposição
        $q = $conn->prepare("SELECT 1 FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? AND NOT (horaFim <= ? OR horaInicio >= ?) LIMIT 1");
        $dstr = $dt->format('Y-m-d'); $histr = $hi->format('H:i:s'); $hfstr = $hf->format('H:i:s');
        $q->bind_param('isss', $bId, $dstr, $histr, $hfstr);
        $q->execute(); $q->store_result();
  if ($q->num_rows > 0) { $q->close(); header('Location: bloqueios.php?ok=0&msg=' . urlencode(t('admin.block_overlap'))); exit; }
        $q->close();
        $ins = $conn->prepare("INSERT INTO BloqueioHorario (Barbeiro_idBarbeiro, data, horaInicio, horaFim, motivo) VALUES (?,?,?,?,?)");
        $ins->bind_param('issss', $bId, $dstr, $histr, $hfstr, $motivo);
    if ($ins->execute()) {
      header('Location: bloqueios.php?barbeiro=' . $bId . '&ok=1&msg=' . urlencode(t('admin.block_created')));
            exit;
    } else { header('Location: bloqueios.php?barbeiro=' . $bId . '&ok=0&msg=' . urlencode(t('admin.block_create_fail'))); exit; }
    } elseif ($acao === 'del') {
        $idBloq = (int)($_POST['id'] ?? 0);
        // segurança: só apagar se pertence ao barbeiro da unidade
        $del = $conn->prepare("DELETE FROM BloqueioHorario WHERE idBloqueio=? AND Barbeiro_idBarbeiro=?");
        $del->bind_param('ii', $idBloq, $bId);
    if ($del->execute()) {
      header('Location: bloqueios.php?barbeiro=' . $bId . '&ok=1&msg=' . urlencode(t('admin.block_removed')));
            exit;
    } else { header('Location: bloqueios.php?barbeiro=' . $bId . '&ok=0&msg=' . urlencode(t('admin.block_remove_fail'))); exit; }
    }
}

$ok = $_GET['ok'] ?? null; $msg = $_GET['msg'] ?? null;
$bloqueios = [];
if ($barbeiroSel && $bloqReady) {
    // Listar somente bloqueios futuros ou em andamento (oculta bloqueios encerrados)
    $qr2 = $conn->prepare(
        "SELECT idBloqueio, data, horaInicio, horaFim, motivo\n"
      . "FROM BloqueioHorario\n"
      . "WHERE Barbeiro_idBarbeiro=?\n"
      . "  AND (data > CURDATE() OR (data = CURDATE() AND horaFim > CURTIME()))\n"
      . "ORDER BY data ASC, horaInicio ASC"
    );
    $qr2->bind_param('i', $barbeiroSel);
    $qr2->execute();
    $rs2 = $qr2->get_result();
    while ($row = $rs2->fetch_assoc()) { $bloqueios[] = $row; }
    $qr2->close();
}
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-BR' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('admin.blocks_title') ?> | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="dashboard_admin.css">
</head>
<body class="dashboard-admin">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="dashboard-section-title mb-0"><i class="bi bi-calendar-x"></i> <?= t('admin.blocks_title') ?></h2>
    <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> <?= t('common.back') ?></a>
  </div>

  <?php if ($msg !== null): ?>
    <div class="alert alert-<?= ($ok === '1') ? 'success' : 'danger' ?> py-2" role="alert">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (!$bloqReady): ?>
    <div class="alert alert-warning"><?= t('admin.migration_needed_blocks') ?></div>
  <?php endif; ?>

  <div class="dashboard-card p-3 mb-4">
    <form method="GET" class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label"><?= t('admin.choose_barber') ?></label>
        <select name="barbeiro" class="form-select" onchange="this.form.submit()" required>
          <option value=""><?= t('common.select') ?></option>
          <?php foreach($barbeiros as $b): $sel = ($barbeiroSel === (int)$b['idBarbeiro']) ? 'selected' : ''; ?>
            <option value="<?= (int)$b['idBarbeiro'] ?>" <?= $sel ?>><?= htmlspecialchars($b['nomeBarbeiro']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if ($barbeiroSel): ?>
  <div class="dashboard-card p-3 mb-4">
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="acao" value="add">
      <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
      <div class="col-12 col-md-3">
        <label class="form-label"><?= t('admin.data') ?></label>
        <input type="date" name="data" class="form-control" placeholder="<?= bb_is_en() ? 'YYYY-MM-DD' : 'AAAA-MM-DD' ?>" required <?= !$bloqReady?'disabled':'' ?> >
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label"><?= t('admin.start') ?></label>
        <input type="time" name="horaInicio" class="form-control" placeholder="HH:MM" required <?= !$bloqReady?'disabled':'' ?> >
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label"><?= t('admin.end') ?></label>
        <input type="time" name="horaFim" class="form-control" placeholder="HH:MM" required <?= !$bloqReady?'disabled':'' ?> >
      </div>
      <div class="col-12 col-md-3">
  <label class="form-label"><?= t('admin.reason_optional') ?></label>
  <input type="text" name="motivo" class="form-control" placeholder="<?= t('admin.reason_placeholder') ?>" <?= !$bloqReady?'disabled':'' ?> >
      </div>
      <div class="col-12">
        <button type="submit" class="dashboard-action" <?= !$bloqReady?'disabled':'' ?> ><i class="bi bi-plus-circle"></i> <?= t('admin.add_block') ?></button>
      </div>
    </form>
  </div>

  <div class="dashboard-card p-3">
    <h5 class="mb-3"><i class="bi bi-list-ul"></i> <?= t('admin.blocks_of_selected') ?></h5>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle">
        <thead><tr><th><?= t('admin.data') ?></th><th><?= t('admin.start') ?></th><th><?= t('admin.end') ?></th><th><?= t('admin.description') ?? 'Motivo' ?></th><th><?= t('common.actions') ?></th></tr></thead>
        <tbody>
          <?php if ($bloqReady && count($bloqueios) > 0): foreach ($bloqueios as $bl): ?>
            <tr>
              <td><?= bb_format_date($bl['data']) ?></td>
              <td><?= bb_format_time($bl['horaInicio']) ?></td>
              <td><?= bb_format_time($bl['horaFim']) ?></td>
              <td><?= htmlspecialchars($bl['motivo'] ?? '') ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('<?= t('admin.confirm_remove_block') ?>');" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="acao" value="del">
                  <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
                  <input type="hidden" name="id" value="<?= (int)$bl['idBloqueio'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> <?= t('common.remove') ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-muted"><?= $bloqReady? t('admin.no_blocks') : t('admin.awaiting_migration') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
    <div class="alert alert-warning"><?= t('admin.select_barber_manage') ?></div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/date-mask.js"></script>
</body>
</html>
