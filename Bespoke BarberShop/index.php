<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bespoke BarberShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Yellowtail&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Arapey&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Narrow&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@55&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  </head>

  <script>
document.addEventListener("DOMContentLoaded", function () {
    const elements = document.querySelectorAll(".scroll-anim");

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("show"); // Aparece
            } else {
                entry.target.classList.remove("show"); // Some quando sai da tela
            }
        });
    }, { threshold: 0.6 }); // 20% visível já ativa

    elements.forEach(el => observer.observe(el));
});
</script>
  <body>
    
   <nav class="navbar sticky-top navbar-expand-lg" style="background-color: black;">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">
      <img src="imagens/Logo.jpeg" alt="Bootstrap" class="logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    
    <div class="offcanvas offcanvas-end" style="background-color: black;" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title text-white" id="offcanvasNavbarLabel">Menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body d-flex flex-column justify-content-between">

        
    <ul class="navbar-nav d-none d-lg-flex gap-5">
      <li class="nav-item">
        <a class="nav-link" href="#Inicio">Início</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#SobreNos">Sobre Nós</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#Espaco">Espaço</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          Serviços
        </a>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#">Cabelo</a></li>
        <li><a class="dropdown-item" href="#">Barba</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#">Inspirações</a></li>
      </ul>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#Localizacao">Localização</a>
      </li>
    </ul>

    <ul class="navbar-nav d-lg-none flex-column gap-2">
      <li class="nav-item">
        <a class="nav-link" href="#Inicio">Início</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#SobreNos">Sobre Nós</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#Espaco">Espaço</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          Serviços
        </a>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#">Cabelo</a></li>
          <li><a class="dropdown-item" href="#">Barba</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="#">Inspirações</a></li>
        </ul>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#Localizacao">Localização</a>
      </li>
    </ul>

        
        <div class="d-lg-none d-flex flex-column gap-3 mt-4">
          <a href="login.php" class="btn btn-outline-warning fw-bold btn-lg">
            Login
          </a>
          <a href="cadastro.php" class="btn btn-warning fw-bold text-dark btn-lg">
            Cadastro
          </a>
        </div>
      </div>
    </div>

    
    <div class="d-none d-lg-flex gap-2 ms-auto ">
      <a href="login.php" class="btn btn-outline-warning fw-bold px-3">
        Login
      </a>
      <a href="cadastro.php" class="btn btn-warning fw-bold text-dark px-3">
        Cadastro
      </a>
    </div>
  </div>
</nav>


    <a href="login.php" class="botao_agen"><span>Marque já seu horário</span></a>
    <div class="container" style="background-image: url(imagens/Fundo.jpeg);" id="Inicio">
        <h1 class="title scroll-anim">Bespoke BarberShop</h1>
        <p class="subtitle scroll-anim">A barbearia pensada para o seu conforto.</p>
            <div class="divider"></div>
            
    </div>
    <hr>
    <div class="container" style="background-image: url(imagens/WhatsApp\ Image\ 2024-09-21\ at\ 20.05.33.jpeg);" id="SobreNos">
        
    </div>
    <hr>
    <div class="container" style="background-image: url(imagens/image\ 1.png);" id="Espaco">
        
    </div>
    <hr>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    
  </body>
</html>