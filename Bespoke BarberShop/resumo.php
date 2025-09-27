<?php
session_start();
include "includes/db.php";

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
  $preco = $_POST['preco'] ?? '';
  $duracao = $_POST['duracao'] ?? '';

  $bd = new Banco();
  $conn = $bd->getConexao();

  if ($barbeiroId && $unidadeId && $servicoId) {
    $status = 'Agendado';
  $stmt = $conn->prepare("INSERT INTO Agendamento (Cliente_idCliente, data, hora, Barbeiro_idBarbeiro, Unidade_idUnidade, statusAgendamento) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("isssis", $idCliente, $data, $hora, $barbeiroId, $unidadeId, $status);
  try {
    if ($stmt->execute()) {
      $idAgendamento = $conn->insert_id;
      // Associar serviço ao agendamento
      $stmtServ = $conn->prepare("INSERT INTO Agendamento_has_Servico (Agendamento_idAgendamento, Servico_idServico, precoFinal, tempoEstimado) VALUES (?, ?, ?, ?)");
      $precoF = floatval(str_replace(["R$", " ", ".", ","], ["", "", "", "."], $preco));
      $duracaoF = intval($duracao);
      $stmtServ->bind_param("iidd", $idAgendamento, $servicoId, $precoF, $duracaoF);
      $stmtServ->execute();
      $stmtServ->close();
      $msg = "<div class='alert alert-success text-center'>Agendamento realizado com sucesso! <a href='usuario/agendamentos.php'>Ver meus agendamentos</a></div>";
    }
  } catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'uk_Barbeiro_Horario') !== false) {
      $msg = "<div class='alert alert-danger text-center'>Este horário já está ocupado para o barbeiro escolhido. Por favor, escolha outro horário.</div>";
    } else {
      $msg = "<div class='alert alert-danger text-center'>Erro ao agendar. Tente novamente.</div>";
    }
  }
  $stmt->close();
  } else {
    $msg = "<div class='alert alert-danger text-center'>Dados incompletos. Tente novamente.</div>";
  }
  // $conn->close(); // Mover para depois da exibição dos detalhes
} else {
  header("Location: agendamento.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resumo do Agendamento</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
<style>
body { 
    background-color: #111; 
    color: #f5f5f5; 
    font-family: 'Montserrat', sans-serif; 
  }
  header { 
    background: linear-gradient(90deg, #111, #1a1a1a); 
    padding: 25px 20px; 
    text-align: center; 
    border-bottom: 3px solid #ffcc00; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
    border-radius: 0 0 15px 15px;
  }
  header h1 { color: #ffcc00; }
  @media(max-width: 576px){
    .card-body p { font-size: 0.95rem; }
    .btn-custom { font-size: 0.9rem; padding: 8px 18px; }
    .btn-secondary { font-size: 0.9rem; padding: 8px 18px; }
    header h1 { font-size: 1.6rem; }
  }
  .btn-confirmed { animation: pulseConfirm 0.6s ease forwards; }
  @keyframes pulseConfirm {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }
</style>
</head>
<body>


<main class="container py-5">
    <header>
      <h1>Resumo do Agendamento</h1>
    </header>

    <main class="container py-5">
      <?php if (isset($msg)) echo $msg; ?>
      <div class="card p-4 mt-4">
        <h2 class="card-title mb-4">Detalhes do Agendamento</h2>
        <div class="card-body">
          <p><i class="bi bi-shop"></i> <strong>Unidade:</strong> <span>
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
          <p><i class="bi bi-calendar-event"></i> <strong>Data:</strong> <span><?php echo htmlspecialchars($_POST['data'] ?? ''); ?></span></p>
          <p><i class="bi bi-clock"></i> <strong>Hora:</strong> <span><?php echo htmlspecialchars($hora); ?></span></p>
          <p><i class="bi bi-scissors"></i> <strong>Barbeiro:</strong> <span>
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
          <p><i class="bi bi-star"></i> <strong>Serviço:</strong> <span>
            <?php
            // Buscar nome do serviço
            $nomeServico = '';
            if (!empty($servicoId)) {
              $stmtS = $conn->prepare("SELECT nomeServico FROM Servico WHERE idServico = ?");
              $stmtS->bind_param("i", $servicoId);
              $stmtS->execute();
              $resS = $stmtS->get_result();
              if ($rowS = $resS->fetch_assoc()) {
                $nomeServico = $rowS['nomeServico'];
              }
              $stmtS->close();
            }
            echo htmlspecialchars($nomeServico);
            ?>
          </span></p>
          <p><i class="bi bi-cash-coin"></i> <strong>Valor:</strong> <span><?php echo htmlspecialchars($preco); ?></span></p>
          <p><i class="bi bi-hourglass-split"></i> <strong>Duração:</strong> <span><?php echo htmlspecialchars($duracao); ?></span></p>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
          <a href="agendamento.php" class="btn btn-secondary d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-left-circle"></i> Reagendar
          </a>
          <?php if (isset($idAgendamento)): ?>
          <form method="POST" action="cancelar.php" onsubmit="return confirm('Tem certeza que deseja cancelar este agendamento?');" class="d-inline-block">
            <input type="hidden" name="idAgendamento" value="<?= $idAgendamento ?>">
            <button type="submit" class="btn btn-danger d-flex align-items-center justify-content-center">
              <i class="bi bi-x-circle"></i> Cancelar Agendamento
            </button>
          </form>
          <?php endif; ?>
        </div>
  </div>
  <?php $conn->close(); ?>
  <!-- Removido o card JS duplicado e mensagem de nenhum agendamento -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>