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
  document.addEventListener("DOMContentLoaded", function() {
    const elements = document.querySelectorAll(".scroll-anim");

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("show");
        } else {
          entry.target.classList.remove("show");
        }
      });
    }, {
      threshold: 0.6
    });

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
  <div class="container" style="background-image: url(imagens/fundoSobreNos.png);" id="SobreNos">
    <h1 class="titleSobreNos scroll-anim">Nós Somos Bespoke</h1>
    <div class="divider"></div>
    <div class="text scroll-anim">
      <p> Nós sabemos o quão importante é o cuidado com a nossa aparência e como precisamos de profissionais de confiança e um ambiente confortável para isso. </p>
      <p> Com isso em mente, criamos a Bespoke BarberShop, a sua barbearia favorita que conta com profissionais extremamente qualificados para te o fornecer cuidado que você merece. </p>
      <p> Oferecemos um ambiente descontraído onde você pode assistir o jogo do seu time, jogar uma sinuca ou tomar uma cervejinha, existe algo melhor? </p>
    </div>
  </div>
  <hr>

  <div class="container-fluid py-5" id="Espaco" style="background-image: url('imagens/image 3.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="container">
      <div class="row gy-4 align-items-center">

        <!-- Texto -->
        <div class="col-12 col-lg-6 text-center text-lg-start scroll-anim">
          <h1 class="text-warning fw-bold mb-4" style="font-family: 'Yellowtail', cursive; font-size: 3.5rem;">
            COMO É O NOSSO ESPAÇO?
          </h1>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;">
            Sem filas, estresse ou tédio, a Bespoke conta com um espaço ideal para o seu tempo sagrado conosco.
          </p>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;">
            Um ambiente entre amigos te proporciona a confiança em nossos profissionais e um momento de relaxamento enquanto cuidamos da sua aparência.
          </p>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;">
            Desfrute de um lugar tranquilo com música boa, cerveja gelada, conversas engraçadas e entretenimento. Estamos te esperando!
          </p>
        </div>

        <div class="col-12 col-lg-6 text-center">
          <img src="imagens/Frame 21.png" alt="Espaço" class="img-fluid rounded-3 shadow-lg frame21">
        </div>
      </div>
    </div>
  </div>


  <hr>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>


<footer class="text-white text-center text-lg-start" style="background-color: #000;">
  <div class="footer-container p-4">
    <div class="row mt-4">
      
      <!-- Mapa do Site -->
      <div class="col-lg-4 col-md-12 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4">MAPA DO SITE</h5>
        <a href="#Inicio" class="d-block mb-2">Início</a>
        <a href="#SobreNos" class="d-block mb-2">Sobre Nós</a>
        <a href="#Espaco" class="d-block mb-2">Espaço</a>
        <a href="#Localizacao" class="d-block mb-2">Localização</a>
      </div>

      <!-- Atendimento -->
      <div class="col-lg-4 col-md-6 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4 pb-1">ATENDIMENTO</h5>
        <p class="mb-1">Telefone</p>
        <p class="mb-3">(00) 0 0000-0000</p>
        <p class="mb-1">E-mail</p>
        <p>contato@bespokebarbershop.com.br</p>
      </div>

      <!-- Redes Sociais -->
      <div class="col-lg-4 col-md-6 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4">REDES SOCIAIS</h5>

        <p class="d-flex align-items-center justify-content-center mb-2">
          <img src="imagens/Facebook.png" alt="Facebook" class="me-2" style="width:20px; height:20px;">
          Bespoke BS
        </p>
        <p class="d-flex align-items-center justify-content-center mb-2">
          <img src="imagens/Instagram.png" alt="Instagram" class="me-2" style="width:20px; height:20px;">
          bespoke_barbershop
        </p>
        <p class="d-flex align-items-center justify-content-center mb-2">
          <img src="imagens/Tiktok.png" alt="TikTok" class="me-2" style="width:20px; height:20px;">
          bespokebarbershop
        </p>
        <p class="d-flex align-items-center justify-content-center mb-2">
          <img src="imagens/Whatsapp.png" alt="Whatsapp" class="me-2" style="width:20px; height:20px;">
          (00) 0 0000-0000
        </p>
      </div>

    </div>
  </div>

  <!-- Copyright -->
  <div class="text-center p-3" style="background-color: rgba(0, 0, 0, 0.2);">
    <p class="d-flex align-items-center justify-content-center mb-2">
      <img src="imagens/LogoBespoke.png" alt="LogoBespoke" class="me-2" style="width:100px; height:100px;">
      Copyright © 2024 Bespoke Barber Cia. Todos os direitos reservados.
    </p>
  </div>
</footer>


  
</body>

</html>