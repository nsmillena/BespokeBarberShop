<?php

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
  header h1 { 
    color: #ffcc00; 

    <?php
    // Exibir os dados enviados via POST
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
      /* ...existing code... */
      body { 
        background-color: #111; 
        color: #f5f5f5; 
        font-family: 'Montserrat', sans-serif; 
      }
      /* ...existing code... */
    </style>
    </head>
    <body>
    <header>
      <h1>Resumo do Agendamento</h1>
    </header>

    <main class="container py-5">
      <div id="conteudoResumo" class="fade-in">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'):
          $unidade = $_POST['unidade'] ?? '';
          $data = $_POST['data'] ?? '';
          $hora = $_POST['hora'] ?? '';
          $barbeiro = $_POST['barbeiro'] ?? '';
          $servico = $_POST['servico'] ?? '';
          $preco = $_POST['preco'] ?? '';
          $duracao = $_POST['duracao'] ?? '';
          $endereco = ($unidade === 'matilde') ? 'R. José Mascarenhas, 861' : (($unidade === 'carrao') ? 'R. João Vieira Prioste, 785' : '');
        ?>
        <div class="card p-4">
          <h2 class="card-title mb-4">Detalhes do Agendamento</h2>
          <div class="card-body">
            <p><i class="bi bi-shop"></i> <strong>Unidade:</strong> <span><?php echo ($unidade === 'matilde') ? 'Vila Matilde' : (($unidade === 'carrao') ? 'Vila Carrão' : ''); ?> - <?php echo $endereco; ?></span></p>
            <p><i class="bi bi-calendar-event"></i> <strong>Data:</strong> <span><?php echo htmlspecialchars($data); ?></span></p>
            <p><i class="bi bi-clock"></i> <strong>Hora:</strong> <span><?php echo htmlspecialchars($hora); ?></span></p>
            <p><i class="bi bi-scissors"></i> <strong>Barbeiro:</strong> <span><?php echo htmlspecialchars($barbeiro); ?></span></p>
            <p><i class="bi bi-star"></i> <strong>Serviço:</strong> <span><?php echo htmlspecialchars($servico); ?></span></p>
            <p><i class="bi bi-cash-coin"></i> <strong>Valor:</strong> <span><?php echo htmlspecialchars($preco); ?></span></p>
            <p><i class="bi bi-hourglass-split"></i> <strong>Duração:</strong> <span><?php echo htmlspecialchars($duracao); ?></span></p>
          </div>
          <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
            <a href="agendamento.php" class="btn btn-secondary d-flex align-items-center justify-content-center">
              <i class="bi bi-arrow-left-circle"></i> Reagendar
            </a>
            <button class="btn btn-custom d-flex align-items-center justify-content-center" id="confirmarBtn">
              <i class="bi bi-check-circle"></i> Confirmar
            </button>
          </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning text-center">
          <i class="bi bi-exclamation-triangle"></i> Nenhum agendamento encontrado. 
          <a href="agendamento.php">Clique aqui para agendar</a>.
        </div>
        <?php endif; ?>
      </div>
    </main>
    </body>
    </html>

  @media(max-width: 576px){
    .card-body p { font-size: 0.95rem; }
    .btn-custom { font-size: 0.9rem; padding: 8px 18px; }
    .btn-secondary { font-size: 0.9rem; padding: 8px 18px; }
    header h1 { font-size: 1.6rem; }
  }

  /* Efeito ao confirmar agendamento */
  .btn-confirmed {
    animation: pulseConfirm 0.6s ease forwards;
  }
  @keyframes pulseConfirm {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }
</style>
</head>
<body>
<header>
  <h1>Resumo do Agendamento</h1>
</header>

<main class="container py-5">
  <div id="conteudoResumo" class="fade-in"></div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("conteudoResumo");
  const agendamento = JSON.parse(localStorage.getItem("agendamento"));

  if (agendamento) {
    let endereco = '';
    if(agendamento.unidade==='matilde') endereco = 'R. José Mascarenhas, 861';
    else if(agendamento.unidade==='carrao') endereco = 'R. João Vieira Prioste, 785';

    container.innerHTML = `
      <div class="card p-4">
        <h2 class="card-title mb-4">Detalhes do Agendamento</h2>
        <div class="card-body">
          <p><i class="bi bi-shop"></i> <strong>Unidade:</strong> <span>${agendamento.unidade === 'matilde' ? 'Vila Matilde' : 'Vila Carrão'} - ${endereco}</span></p>
          <p><i class="bi bi-calendar-event"></i> <strong>Data:</strong> <span>${agendamento.data}</span></p>
          <p><i class="bi bi-clock"></i> <strong>Hora:</strong> <span>${agendamento.hora}</span></p>
          <p><i class="bi bi-scissors"></i> <strong>Barbeiro:</strong> <span>${agendamento.barbeiro}</span></p>
          <p><i class="bi bi-star"></i> <strong>Serviço:</strong> <span>${agendamento.servico}</span></p>
          <p><i class="bi bi-cash-coin"></i> <strong>Valor:</strong> <span>${agendamento.preco}</span></p>
          <p><i class="bi bi-hourglass-split"></i> <strong>Duração:</strong> <span>${agendamento.duracao}</span></p>
        </div>
        <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
          <a href="agendamento.html" class="btn btn-secondary d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-left-circle"></i> Reagendar
          </a>
          <button class="btn btn-custom d-flex align-items-center justify-content-center" id="confirmarBtn">
            <i class="bi bi-check-circle"></i> Confirmar
          </button>
        </div>
      </div>
    `;

    const btnConfirm = document.getElementById("confirmarBtn");
    btnConfirm.addEventListener("click", () => {
      btnConfirm.innerHTML = '<i class="bi bi-check2-all"></i> Agendamento Confirmado!';
      btnConfirm.disabled = true;
      btnConfirm.classList.remove("btn-custom");
      btnConfirm.classList.add("btn-success", "btn-confirmed");
    });

  } else {
    container.innerHTML = `
      <div class="alert alert-warning text-center">
        <i class="bi bi-exclamation-triangle"></i> Nenhum agendamento encontrado. 
        <a href="agendamento.html">Clique aqui para agendar</a>.
      </div>
    `;
  }
});
</script>
</body>
</html>