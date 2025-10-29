<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['papel'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
include_once "../includes/db.php";
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

$hasFolga = table_exists($conn, 'FolgaSemanal');
$hasFerias = table_exists($conn, 'FeriasBarbeiro');
$tablesReady = $hasFolga && $hasFerias;

// Unidade do admin
$qU = $conn->prepare("SELECT Unidade_idUnidade FROM Administrador WHERE idAdministrador=?");
$qU->bind_param('i', $admin_id);
$qU->execute(); $qU->bind_result($unidade_id); $qU->fetch(); $qU->close();
if (empty($unidade_id)) { header('Location: index_admin.php?ok=0&msg=' . urlencode('Associe uma unidade ao admin para gerenciar escalas.')); exit; }

// Barbeiros da unidade
$barbeiros = [];
$bq = $conn->prepare("SELECT idBarbeiro, nomeBarbeiro FROM Barbeiro WHERE Unidade_idUnidade=? ORDER BY nomeBarbeiro");
$bq->bind_param('i', $unidade_id);
$bq->execute(); $brs = $bq->get_result();
while($row = $brs->fetch_assoc()){ $barbeiros[]=$row; }
$bq->close();

$barbeiroSel = isset($_GET['barbeiro']) ? (int)$_GET['barbeiro'] : 0;
if ($barbeiroSel) {
  $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
  $chk->bind_param('ii', $barbeiroSel, $unidade_id);
  $chk->execute(); $ok = $chk->get_result()->num_rows>0; $chk->close();
  if(!$ok){ $barbeiroSel = 0; }
}

// Bloqueia POST se tabelas não existem
if ($_SERVER['REQUEST_METHOD']==='POST' && !$tablesReady){
  header('Location: escalas.php?barbeiro='.(int)($_POST['barbeiro']??0).'&ok=0&msg=' . urlencode('Funcionalidade indisponível: execute a migração banco/migracao_2025_10.sql para criar as tabelas FolgaSemanal e FeriasBarbeiro.'));
  exit;
}

// POST (somente com tabelas prontas)
if ($_SERVER['REQUEST_METHOD']==='POST' && $tablesReady){
  $t = $_POST['csrf_token'] ?? ''; if (!hash_equals($_SESSION['csrf_token'],$t)) { header('Location: escalas.php?ok=0&msg=' . urlencode('Falha de segurança.')); exit; }
  $acao = $_POST['acao'] ?? '';
  $bId = (int)($_POST['barbeiro'] ?? 0);
  $chk = $conn->prepare("SELECT 1 FROM Barbeiro WHERE idBarbeiro=? AND Unidade_idUnidade=?");
  $chk->bind_param('ii', $bId, $unidade_id); $chk->execute(); $ok = $chk->get_result()->num_rows>0; $chk->close();
  if(!$ok){ header('Location: escalas.php?ok=0&msg=' . urlencode('Barbeiro inválido.')); exit; }

  if ($acao==='add_folga'){
    $weekday = (int)($_POST['weekday'] ?? 0);
    $inicio = $_POST['inicio'] ?? '';
    $fim = $_POST['fim'] ?? '';
    $dIni = DateTime::createFromFormat('Y-m-d',$inicio);
    $dFim = $fim ? DateTime::createFromFormat('Y-m-d',$fim) : null;
    if (!$dIni || ($fim && !$dFim)) { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Datas inválidas.')); exit; }
    if ($dFim && $dFim < $dIni) { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Data final não pode ser menor que a inicial.')); exit; }
    if ($weekday < 1 || $weekday > 7) { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Dia da semana inválido.')); exit; }
    // sobreposição no mesmo weekday
    $q = $conn->prepare("SELECT 1 FROM FolgaSemanal WHERE Barbeiro_idBarbeiro=? AND weekday=? AND NOT (COALESCE(fim,'2999-12-31') < ? OR inicio > COALESCE(?, '2999-12-31')) LIMIT 1");
    $iniStr = $dIni->format('Y-m-d'); $fimStr = $dFim ? $dFim->format('Y-m-d') : null;
    $q->bind_param('iiss', $bId, $weekday, $iniStr, $fimStr); $q->execute(); $q->store_result();
    if ($q->num_rows>0){ $q->close(); header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Já existe folga semanal neste dia no período informado.')); exit; }
    $q->close();
    $ins = $conn->prepare("INSERT INTO FolgaSemanal (Barbeiro_idBarbeiro, weekday, inicio, fim) VALUES (?,?,?,?)");
    $ins->bind_param('iiss', $bId, $weekday, $iniStr, $fimStr);
    if ($ins->execute()) { header('Location: escalas.php?barbeiro='.$bId.'&ok=1&msg=' . urlencode('Folga semanal cadastrada.')); exit; }
    else { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Falha ao cadastrar folga.')); exit; }
  }
  if ($acao==='del_folga'){
    $id = (int)($_POST['id'] ?? 0);
    $del = $conn->prepare("DELETE FROM FolgaSemanal WHERE idFolga=? AND Barbeiro_idBarbeiro=?");
    $del->bind_param('ii', $id, $bId);
    if ($del->execute()) { header('Location: escalas.php?barbeiro='.$bId.'&ok=1&msg=' . urlencode('Folga removida.')); exit; }
    else { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Falha ao remover folga.')); exit; }
  }
  if ($acao==='add_ferias'){
    $inicio = $_POST['inicio'] ?? '';
    $fim = $_POST['fim'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $dIni = DateTime::createFromFormat('Y-m-d',$inicio); $dFim = DateTime::createFromFormat('Y-m-d',$fim);
    if (!$dIni || !$dFim || $dFim < $dIni) { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Período de férias inválido.')); exit; }
    // sobreposição de férias
    $q = $conn->prepare("SELECT 1 FROM FeriasBarbeiro WHERE Barbeiro_idBarbeiro=? AND NOT (fim < ? OR inicio > ?) LIMIT 1");
    $iniStr = $dIni->format('Y-m-d'); $fimStr = $dFim->format('Y-m-d');
    $q->bind_param('iss', $bId, $iniStr, $fimStr); $q->execute(); $q->store_result();
    if ($q->num_rows>0){ $q->close(); header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Já existem férias cadastradas neste período.')); exit; }
    $q->close();
    $ins = $conn->prepare("INSERT INTO FeriasBarbeiro (Barbeiro_idBarbeiro, inicio, fim, motivo) VALUES (?,?,?,?)");
    $ins->bind_param('isss', $bId, $iniStr, $fimStr, $motivo);
    if ($ins->execute()) { header('Location: escalas.php?barbeiro='.$bId.'&ok=1&msg=' . urlencode('Férias cadastradas.')); exit; }
    else { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Falha ao cadastrar férias.')); exit; }
  }
  if ($acao==='del_ferias'){
    $id = (int)($_POST['id'] ?? 0);
    $del = $conn->prepare("DELETE FROM FeriasBarbeiro WHERE idFerias=? AND Barbeiro_idBarbeiro=?");
    $del->bind_param('ii', $id, $bId);
    if ($del->execute()) { header('Location: escalas.php?barbeiro='.$bId.'&ok=1&msg=' . urlencode('Férias removidas.')); exit; }
    else { header('Location: escalas.php?barbeiro='.$bId.'&ok=0&msg=' . urlencode('Falha ao remover férias.')); exit; }
  }
}

// Listas do barbeiro selecionado
$folgas = []; $ferias = [];
if ($barbeiroSel && $tablesReady){
  $q1 = $conn->prepare("SELECT idFolga, weekday, inicio, fim FROM FolgaSemanal WHERE Barbeiro_idBarbeiro=? ORDER BY inicio DESC");
  $q1->bind_param('i', $barbeiroSel); $q1->execute(); $r1 = $q1->get_result();
  while($row=$r1->fetch_assoc()){ $folgas[]=$row; } $q1->close();
  $q2 = $conn->prepare("SELECT idFerias, inicio, fim, motivo FROM FeriasBarbeiro WHERE Barbeiro_idBarbeiro=? ORDER BY inicio DESC");
  $q2->bind_param('i', $barbeiroSel); $q2->execute(); $r2 = $q2->get_result();
  while($row=$r2->fetch_assoc()){ $ferias[]=$row; } $q2->close();
}
$ok = $_GET['ok'] ?? null; $msg = $_GET['msg'] ?? null;
$weekdayNames = [1=>'Domingo',2=>'Segunda',3=>'Terça',4=>'Quarta',5=>'Quinta',6=>'Sexta',7=>'Sábado'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Escalas | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="dashboard_admin.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="dashboard-admin">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="dashboard-section-title mb-0"><i class="bi bi-calendar3"></i> Escalas (6x1 e Férias)</h2>
    <a href="index_admin.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if ($msg !== null): ?>
    <div class="alert alert-<?= ($ok === '1') ? 'success' : 'danger' ?> py-2" role="alert">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (!$tablesReady): ?>
    <div class="alert alert-warning">Funcionalidade indisponível: execute a migração <code>banco/migracao_2025_10.sql</code> (phpMyAdmin → Importar) para criar as tabelas <code>FolgaSemanal</code> e <code>FeriasBarbeiro</code>.</div>
  <?php endif; ?>

  <div class="dashboard-card p-3 mb-4">
    <form method="GET" class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Barbeiro</label>
        <select name="barbeiro" class="form-select" onchange="this.form.submit()" required>
          <option value="">Selecione</option>
          <?php foreach($barbeiros as $b): $sel = ($barbeiroSel === (int)$b['idBarbeiro']) ? 'selected' : ''; ?>
            <option value="<?= (int)$b['idBarbeiro'] ?>" <?= $sel ?>><?= htmlspecialchars($b['nomeBarbeiro']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if ($barbeiroSel): ?>
  <div class="row g-4">
    <div class="col-12 col-lg-6">
      <div class="dashboard-card p-3 h-100">
        <h5 class="mb-3"><i class="bi bi-calendar-week"></i> Folga semanal (6x1)</h5>
        <?php if ($tablesReady): ?>
        <form method="POST" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="acao" value="add_folga">
          <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
          <div class="col-12 col-sm-4">
            <label class="form-label">Dia da semana</label>
            <select name="weekday" class="form-select" required>
              <?php foreach($weekdayNames as $k=>$n): ?>
                <option value="<?= (int)$k ?>"><?= htmlspecialchars($n) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-sm-4">
            <label class="form-label">Início</label>
            <input type="date" name="inicio" class="form-control" required>
          </div>
          <div class="col-6 col-sm-4">
            <label class="form-label">Fim (opcional)</label>
            <input type="date" name="fim" class="form-control">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Adicionar folga</button>
          </div>
        </form>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-dark table-striped align-middle dashboard-table">
            <thead><tr><th>Dia</th><th>Início</th><th>Fim</th><th>Ações</th></tr></thead>
            <tbody>
              <?php if ($tablesReady && count($folgas)>0): foreach($folgas as $f): ?>
                <tr>
                  <td><?= htmlspecialchars($weekdayNames[(int)$f['weekday']] ?? (string)$f['weekday']) ?></td>
                  <td><?= date('d/m/Y', strtotime($f['inicio'])) ?></td>
                  <td><?= $f['fim'] ? date('d/m/Y', strtotime($f['fim'])) : '—' ?></td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Remover esta folga?');" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="acao" value="del_folga">
                      <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
                      <input type="hidden" name="id" value="<?= (int)$f['idFolga'] ?>">
                      <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted">Nenhuma folga semanal cadastrada.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="dashboard-card p-3 h-100">
        <h5 class="mb-3"><i class="bi bi-airplane"></i> Férias</h5>
        <?php if ($tablesReady): ?>
        <form method="POST" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="acao" value="add_ferias">
          <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
          <div class="col-6">
            <label class="form-label">Início</label>
            <input type="date" name="inicio" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">Fim</label>
            <input type="date" name="fim" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Motivo (opcional)</label>
            <input type="text" name="motivo" class="form-control" maxlength="150" placeholder="Ex.: férias, atestado, treinamento...">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Adicionar férias</button>
          </div>
        </form>
        <?php endif; ?>

        <div class="table-responsive">
          <table class="table table-dark table-striped align-middle dashboard-table">
            <thead><tr><th>Início</th><th>Fim</th><th>Motivo</th><th>Ações</th></tr></thead>
            <tbody>
              <?php if ($tablesReady && count($ferias)>0): foreach($ferias as $f): ?>
                <tr>
                  <td><?= date('d/m/Y', strtotime($f['inicio'])) ?></td>
                  <td><?= date('d/m/Y', strtotime($f['fim'])) ?></td>
                  <td><?= htmlspecialchars($f['motivo'] ?? '') ?></td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Remover este período de férias?');" style="display:inline-block;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="acao" value="del_ferias">
                      <input type="hidden" name="barbeiro" value="<?= (int)$barbeiroSel ?>">
                      <input type="hidden" name="id" value="<?= (int)$f['idFerias'] ?>">
                      <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted">Nenhum período de férias cadastrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php @include_once("../Footer/footer.html"); ?>
</body>
</html>