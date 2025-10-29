<?php
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

$msg = null; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        header('Location: bloqueios.php?ok=0&msg=' . urlencode('Falha de segurança. Atualize a página e tente novamente.'));
        exit;
    }
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'add') {
        $data = $_POST['data'] ?? '';
        $horaIni = $_POST['horaInicio'] ?? '';
        $horaFim = $_POST['horaFim'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        // Validar
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        $hi = DateTime::createFromFormat('H:i', $horaIni) ?: DateTime::createFromFormat('H:i:s', $horaIni);
        $hf = DateTime::createFromFormat('H:i', $horaFim) ?: DateTime::createFromFormat('H:i:s', $horaFim);
        if (!$dt || !$hi || !$hf) {
            header('Location: bloqueios.php?ok=0&msg=' . urlencode('Dados inválidos de data/horário.'));
            exit;
        }
        if ($hi >= $hf) {
            header('Location: bloqueios.php?ok=0&msg=' . urlencode('Hora inicial deve ser menor que a final.'));
            exit;
        }
        // Evita sobreposição com outro bloqueio
        $q = $conn->prepare("SELECT 1 FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? AND NOT (horaFim <= ? OR horaInicio >= ?) LIMIT 1");
        $dstr = $dt->format('Y-m-d'); $histr = $hi->format('H:i:s'); $hfstr = $hf->format('H:i:s');
        $q->bind_param('isss', $idBarbeiro, $dstr, $histr, $hfstr);
        $q->execute(); $q->store_result();
        if ($q->num_rows > 0) {
            $q->close();
            header('Location: bloqueios.php?ok=0&msg=' . urlencode('Já existe um bloqueio nesse intervalo.'));
            exit;
        }
        $q->close();
        $ins = $conn->prepare("INSERT INTO BloqueioHorario (Barbeiro_idBarbeiro, data, horaInicio, horaFim, motivo) VALUES (?,?,?,?,?)");
        $ins->bind_param('issss', $idBarbeiro, $dstr, $histr, $hfstr, $motivo);
        if ($ins->execute()) {
            header('Location: bloqueios.php?ok=1&msg=' . urlencode('Bloqueio criado.'));
            exit;
        } else {
            header('Location: bloqueios.php?ok=0&msg=' . urlencode('Falha ao criar bloqueio.'));
            exit;
        }
    } elseif ($acao === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        $del = $conn->prepare("DELETE FROM BloqueioHorario WHERE idBloqueio=? AND Barbeiro_idBarbeiro=?");
        $del->bind_param('ii', $id, $idBarbeiro);
        if ($del->execute()) {
            header('Location: bloqueios.php?ok=1&msg=' . urlencode('Bloqueio removido.'));
            exit;
        } else {
            header('Location: bloqueios.php?ok=0&msg=' . urlencode('Falha ao remover bloqueio.'));
            exit;
        }
    }
}

// Listar somente bloqueios que ainda não terminaram (próximos ou em andamento)
$bloqueios = [];
$qr = $conn->prepare(
    "SELECT idBloqueio, data, horaInicio, horaFim, motivo\n"
  . "FROM BloqueioHorario\n"
  . "WHERE Barbeiro_idBarbeiro=?\n"
  . "  AND (data > CURDATE() OR (data = CURDATE() AND horaFim > CURTIME()))\n"
  . "ORDER BY data ASC, horaInicio ASC"
);
$qr->bind_param('i', $idBarbeiro);
$qr->execute();
$rs = $qr->get_result();
while ($row = $rs->fetch_assoc()) { $bloqueios[] = $row; }
$qr->close();

$ok = isset($_GET['ok']) ? $_GET['ok'] : null;
$msg = isset($_GET['msg']) ? $_GET['msg'] : null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bloqueios de horário | Barbeiro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="dashboard_barbeiro.css">
</head>
<body class="dashboard-barbeiro-novo">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="dashboard-section-title mb-0"><i class="bi bi-calendar-x"></i> Bloquear horários</h2>
    <a href="index_barbeiro.php" class="dashboard-action"><i class="bi bi-arrow-left"></i> Voltar</a>
  </div>

  <?php if ($msg !== null): ?>
    <div class="alert alert-<?= ($ok === '1') ? 'success' : 'danger' ?> py-2" role="alert">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="dashboard-card p-3 mb-4">
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="acao" value="add">
      <div class="col-12 col-md-3">
        <label class="form-label">Data</label>
        <input type="date" name="data" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Início</label>
        <input type="time" name="horaInicio" class="form-control" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">Fim</label>
        <input type="time" name="horaFim" class="form-control" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Motivo (opcional)</label>
        <input type="text" name="motivo" class="form-control" placeholder="Ex: almoço, manutenção...">
      </div>
      <div class="col-12">
        <button type="submit" class="dashboard-action"><i class="bi bi-plus-circle"></i> Adicionar bloqueio</button>
      </div>
    </form>
  </div>

  <div class="dashboard-card p-3">
    <h5 class="mb-3"><i class="bi bi-list-ul"></i> Meus bloqueios</h5>
    <div class="table-responsive">
      <table class="table table-dark table-striped align-middle">
        <thead>
          <tr>
            <th>Data</th>
            <th>Início</th>
            <th>Fim</th>
            <th>Motivo</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (count($bloqueios) > 0): foreach ($bloqueios as $b): ?>
          <tr>
            <td><?= date('d/m/Y', strtotime($b['data'])) ?></td>
            <td><?= substr($b['horaInicio'], 0, 5) ?></td>
            <td><?= substr($b['horaFim'], 0, 5) ?></td>
            <td><?= htmlspecialchars($b['motivo'] ?? '') ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remover este bloqueio?');" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="acao" value="del">
                <input type="hidden" name="id" value="<?= (int)$b['idBloqueio'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Remover</button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted">Nenhum bloqueio cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
