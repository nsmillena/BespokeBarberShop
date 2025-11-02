<?php
session_start();
include "includes/db.php";
include_once "includes/helpers.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $idCliente = $_SESSION['usuario_id'];
  $unidadeId = $_POST['unidade'] ?? '';
  $data = preg_match('/\d{2}\/\d{2}\/\d{4}/', $_POST['data']) ? DateTime::createFromFormat('d/m/Y', $_POST['data'])->format('Y-m-d') : $_POST['data'];
  $hora = $_POST['hora'] ?? '';
  $barbeiroId = $_POST['barbeiro_id'] ?? null;
  $servicoId = $_POST['servico_id'] ?? null;
  $precoBrl = isset($_POST['preco_brl']) ? (float)$_POST['preco_brl'] : (float)0;
  $duracao = $_POST['duracao'] ?? '';
  $duracaoFmt = bb_format_minutes($duracao);

  $bd = new Banco();
  $conn = $bd->getConexao();

  // Regra: cada usuário pode ter no máximo 5 agendamentos ativos (Agendado)
  $stmtCount = $conn->prepare("SELECT COUNT(*) AS qtd FROM Agendamento WHERE Cliente_idCliente = ? AND statusAgendamento = 'Agendado'");
  $stmtCount->bind_param("i", $idCliente);
  $stmtCount->execute();
  $resCount = $stmtCount->get_result();
  $rowCount = $resCount->fetch_assoc();
  $ativosQtd = (int)($rowCount['qtd'] ?? 0);
  $stmtCount->close();
}
  if ($ativosQtd >= 5) {
    $msg = "<div class='alert alert-warning text-center'>" . t('user.limit_reached') . "</div>";
  } else if ($barbeiroId && $unidadeId && $servicoId) {
    // 1) Verifica folga semanal e férias (dia indisponível)
    // Folga semanal ativa neste dia
    $qf = $conn->prepare("SELECT 1 FROM FolgaSemanal WHERE Barbeiro_idBarbeiro=? AND weekday = DAYOFWEEK(?) AND inicio <= ? AND (fim IS NULL OR fim >= ?) LIMIT 1");
    $qf->bind_param("isss", $barbeiroId, $data, $data, $data);
    $qf->execute(); $qf->store_result(); $isDayOff = $qf->num_rows > 0; $qf->close();
    if ($isDayOff) {
      $msg = "<div class='alert alert-danger text-center'>" . t('sched.day_off') . "</div>";
    }
    // Férias
    if (empty($msg)) {
      $qv = $conn->prepare("SELECT 1 FROM FeriasBarbeiro WHERE Barbeiro_idBarbeiro=? AND inicio <= ? AND fim >= ? LIMIT 1");
      $qv->bind_param("iss", $barbeiroId, $data, $data);
      $qv->execute(); $qv->store_result(); $inVacation = $qv->num_rows > 0; $qv->close();
      if ($inVacation) {
        $msg = "<div class='alert alert-danger text-center'>" . t('sched.vacation') . "</div>";
      }
    }
    
    if (empty($msg)) {
    // Bloqueio de horário: verifica indisponibilidade do barbeiro
    // Determina janela do atendimento considerando a duração
    $duracaoF = intval($duracao);
    if ($duracaoF <= 0) { $duracaoF = 30; }
    $ini = DateTime::createFromFormat('H:i', substr($hora,0,5));
    if (!$ini) { $ini = DateTime::createFromFormat('H:i:s', $hora); }
    $fim = clone $ini; $fim->modify('+' . $duracaoF . ' minutes');
    $horaInicioStr = $ini->format('H:i:s');
    $horaFimStr = $fim->format('H:i:s');

    // Checa overlap com BloqueioHorario (qualquer interseção)
    $qBloq = $conn->prepare("SELECT 1 FROM BloqueioHorario WHERE Barbeiro_idBarbeiro=? AND data=? AND NOT (horaFim <= ? OR horaInicio >= ?) LIMIT 1");
    $qBloq->bind_param("isss", $barbeiroId, $data, $horaInicioStr, $horaFimStr);
    $qBloq->execute();
    $qBloq->store_result();
    $temBloqueio = $qBloq->num_rows > 0; $qBloq->close();
    if ($temBloqueio) {
      $msg = "<div class='alert alert-danger text-center'>" . t('sched.unavailable_time') . "</div>";
    } else {
    $status = 'Agendado';
    $stmt = $conn->prepare("INSERT INTO Agendamento (Cliente_idCliente, data, hora, Barbeiro_idBarbeiro, Unidade_idUnidade, statusAgendamento) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $idCliente, $data, $hora, $barbeiroId, $unidadeId, $status);
    try {
      if ($stmt->execute()) {
        $idAgendamento = $conn->insert_id;
        // Associar serviço ao agendamento
        $stmtServ = $conn->prepare("INSERT INTO Agendamento_has_Servico (Agendamento_idAgendamento, Servico_idServico, precoFinal, tempoEstimado) VALUES (?, ?, ?, ?)");
        $precoF = (float)$precoBrl; // sempre BRL
        $stmtServ->bind_param("iidd", $idAgendamento, $servicoId, $precoF, $duracaoF);
        $stmtServ->execute();
        $stmtServ->close();
        $msg = "<div class='alert alert-success text-center'>" . t('sched.success_booked') . " <a href='usuario/agendamentos_usuario.php'>" . t('user.my_appointments') . "</a></div>";
      }
    } catch (mysqli_sql_exception $e) {
      if (strpos($e->getMessage(), 'uk_Barbeiro_Horario') !== false) {
        $msg = "<div class='alert alert-danger text-center'>" . t('sched.already_booked') . "</div>";
      } else {
        $msg = "<div class='alert alert-danger text-center'>" . t('user.action_failed') . "</div>";
      }
    }
    if (isset($stmt)) { $stmt->close(); }
    }
  } else {
    $msg = "<div class='alert alert-danger text-center'>" . t('common.incomplete_data') . "</div>";
  }
  // $conn->close(); // Mover para depois da exibição dos detalhes
} else {
  header("Location: agendamento.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('sched.summary_title') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
<style>
  body { 
    background: linear-gradient(135deg, #0c0c0c 0%, #1a1a1a 50%, #0f0f0f 100%);
    color: #f5f5f5; 
    font-family: 'Montserrat', sans-serif; 
  }
  header { 
    background: linear-gradient(90deg, #111, #1a1a1a); 
    padding: 25px 20px; 
    text-align: center; 
    border-bottom: 3px solid #daa520; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
    border-radius: 0 0 15px 15px;
  }
  header h1 { color: #daa520; }

  /* Card visual mais alinhado ao tema escuro/dourado */
  .card {
    background: linear-gradient(145deg, #2a2a2a 0%, #1f1f1f 100%);
    border: 2px solid #daa520;
    color: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 2px 8px rgba(218,165,32,0.1), inset 0 1px 0 rgba(255,255,255,0.1);
    position: relative;
  }
  .card-title { color: #daa520; font-weight: 600; }
  .top-right-action { position: absolute; top: 16px; right: 16px; }

  /* Alerts */
  .alert { border-radius: 12px; border: 1px solid transparent; box-shadow: 0 6px 18px rgba(0,0,0,0.35); }
  .alert-success { 
    background: linear-gradient(145deg, #17321d 0%, #1e3a24 100%);
    border-color: rgba(218,165,32,0.35);
    color: #d9f2e3;
  }
  .alert-warning { 
    background: linear-gradient(145deg, #3d2f13 0%, #2f2510 100%);
    border-color: rgba(218,165,32,0.45);
    color: #ffe7a3;
  }
  .alert-danger { 
    background: linear-gradient(145deg, #3a1717 0%, #2a1111 100%);
    border-color: rgba(255, 80, 80, 0.5);
    color: #ffd9d9;
  }
  .alert a { color: #f0d58a; text-decoration: underline; }

  .btn i { margin-right: 0.45rem; }
  .btn-secondary, .btn-outline-warning { padding: 0.7rem 1.1rem; font-weight: 600; font-size: 1rem; }
  .btn-danger { padding: 0.7rem 1.1rem; font-weight: 600; font-size: 1rem; }
  .btn-wide { min-width: 220px; }

  @media(max-width: 576px){
    .card-body p { font-size: 0.95rem; }
    .btn-custom { font-size: 0.9rem; padding: 8px 18px; }
    .btn-secondary { font-size: 0.9rem; padding: 8px 18px; }
    header h1 { font-size: 1.6rem; }
    .btn i { margin-right: 0.35rem; }
  }
</style>
</head>
<body>


<main class="container py-5">
    <header>
  <h1><?= t('sched.summary_title') ?></h1>
    </header>

    <main class="container py-5">
      <?php if (isset($msg)) echo $msg; ?>
      <div class="card p-4 mt-4">
        <a href="usuario/index_usuario.php" class="btn btn-outline-warning top-right-action d-flex align-items-center"><i class="bi bi-box-arrow-left"></i> <?= t('sched.back_panel') ?></a>
        <h2 class="card-title mb-4"><?= t('sched.details_title') ?></h2>
        <div class="card-body">
          <p><i class="bi bi-shop"></i> <strong><?= t('sched.unit') ?>:</strong> <span>
            <?php
            // Buscar nome da unidade
            $nomeUnidade = '';
            if (!empty($unidadeId)) {
              $stmtU = $conn->prepare("SELECT nomeUnidade, endereco FROM Unidade WHERE idUnidade = ?");
              $stmtU->bind_param("i", $unidadeId);
              $stmtU->execute();
              $resU = $stmtU->get_result();
              if ($rowU = $resU->fetch_assoc()) {
                $nomeUnidade = $rowU['nomeUnidade'] . ' - ' . $rowU['endereco'];
              }
              $stmtU->close();
            }
            echo htmlspecialchars($nomeUnidade);
            ?>
          </span></p>
          <p><i class="bi bi-calendar-event"></i> <strong><?= t('sched.date') ?>:</strong> <span><?php echo htmlspecialchars(bb_format_date($data)); ?></span></p>
          <p><i class="bi bi-clock"></i> <strong><?= t('sched.time') ?>:</strong> <span><?php echo htmlspecialchars(bb_format_time($hora)); ?></span></p>
          <p><i class="bi bi-scissors"></i> <strong><?= t('sched.barber') ?>:</strong> <span>
            <?php
            // Buscar nome do barbeiro
            $nomeBarbeiro = '';
            if (!empty($barbeiroId)) {
              $stmtB = $conn->prepare("SELECT nomeBarbeiro FROM Barbeiro WHERE idBarbeiro = ?");
              $stmtB->bind_param("i", $barbeiroId);
              $stmtB->execute();
              $resB = $stmtB->get_result();
              if ($rowB = $resB->fetch_assoc()) {
                $nomeBarbeiro = $rowB['nomeBarbeiro'];
              }
              $stmtB->close();
            }
            echo htmlspecialchars($nomeBarbeiro);
            ?>
          </span></p>
          <p><i class="bi bi-star"></i> <strong><?= t('sched.service') ?>:</strong> <span>
            <?php
            // Buscar nome do serviço
            $nomeServico = '';
            if (!empty($servicoId)) {
              $stmtS = $conn->prepare("SELECT nomeServico FROM Servico WHERE idServico = ?");
              $stmtS->bind_param("i", $servicoId);
              $stmtS->execute();
              $resS = $stmtS->get_result();
              if ($rowS = $resS->fetch_assoc()) {
                $nomeServico = bb_service_display($rowS['nomeServico']);
              }
              $stmtS->close();
            }
            echo htmlspecialchars($nomeServico);
            ?>
          </span></p>
          <p><i class="bi bi-cash-coin"></i> <strong><?= t('sched.value') ?>:</strong> <span><?php echo htmlspecialchars(bb_format_currency_local($precoBrl)); ?></span></p>
          <p><i class="bi bi-hourglass-split"></i> <strong><?= t('sched.duration') ?>:</strong> <span><?php echo htmlspecialchars($duracaoFmt); ?></span></p>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
          <a href="agendamento.php" class="btn btn-secondary btn-wide d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-left-circle"></i> <?= t('sched.reschedule') ?>
          </a>
          <?php if (isset($idAgendamento)): ?>
          <form method="POST" action="cancelar.php" onsubmit="return confirm('<?= t('sched.confirm_cancel') ?>');" class="d-inline-block ms-md-auto">
            <input type="hidden" name="idAgendamento" value="<?= $idAgendamento ?>">
            <button type="submit" class="btn btn-danger btn-wide d-flex align-items-center justify-content-center">
              <i class="bi bi-x-circle"></i> <?= t('sched.cancel') ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
  </div>
  <?php $conn->close(); ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>