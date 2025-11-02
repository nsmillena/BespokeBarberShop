<?php include __DIR__ . '/includes/db.php'; ?>
<!doctype html>
<html lang="<?= bb_is_en() ? 'en' : 'pt-br' ?>">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('home.hero_title') ?></title>
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
              <a class="nav-link" href="#Inicio"><?= t('nav.home') ?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#SobreNos"><?= t('nav.about') ?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#Espaco"><?= t('nav.space') ?></a>
            </li>

            <li class="nav-item">
              <a class="nav-link" href="#Localizacao"><?= t('nav.location') ?></a>
            </li>
          </ul>

          <ul class="navbar-nav d-lg-none flex-column gap-2">
            <li class="nav-item">
              <a class="nav-link" href="#Inicio"><?= t('nav.home') ?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#SobreNos"><?= t('nav.about') ?></a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#Espaco"><?= t('nav.space') ?></a>
            </li>

            <li class="nav-item">
              <a class="nav-link" href="#Localizacao"><?= t('nav.location') ?></a>
            </li>
          </ul>


          <div class="d-lg-none d-flex flex-column gap-3 mt-4">
            <a href="login.php" class="btn btn-outline-warning fw-bold btn-lg">
              <?= t('nav.login') ?>
            </a>
            <a href="cadastro.php" class="btn btn-warning fw-bold text-dark btn-lg">
              <?= t('nav.signup') ?>
            </a>
            <div class="text-center mt-2">
              <span class="text-white-50 me-2"><?= t('nav.language') ?>:</span>
              <a class="link-warning me-2" href="includes/locale.php?set=pt_BR&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">🇧🇷 <?= t('nav.pt') ?></a>
              <a class="link-warning" href="includes/locale.php?set=en_US&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">🇺🇸 <?= t('nav.en') ?></a>
            </div>
          </div>
        </div>
      </div>


      <div class="d-none d-lg-flex gap-2 ms-auto ">
        <div class="btn-group me-2" role="group" aria-label="<?= t('nav.language') ?>">
          <a class="btn btn-outline-warning <?= bb_is_en() ? '' : 'active' ?>" href="includes/locale.php?set=pt_BR&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">🇧🇷 <?= t('nav.pt') ?></a>
          <a class="btn btn-outline-warning <?= bb_is_en() ? 'active' : '' ?>" href="includes/locale.php?set=en_US&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">🇺🇸 <?= t('nav.en') ?></a>
        </div>
        <a href="login.php" class="btn btn-outline-warning fw-bold px-3">
          <?= t('nav.login') ?>
        </a>
        <a href="cadastro.php" class="btn btn-warning fw-bold text-dark px-3">
          <?= t('nav.signup') ?>
        </a>
      </div>
    </div>
  </nav>



  <a href="login.php" class="botao_agen"><span><?= t('home.cta') ?></span></a>
  <div class="container" style="background-image: url(imagens/Fundo.jpeg);" id="Inicio">
  <h1 class="title scroll-anim"><?= t('home.hero_title') ?></h1>
  <p class="subtitle scroll-anim"><?= t('home.hero_subtitle') ?></p>
    <div class="divider"></div>

  </div>
  <hr>
  <div class="container" style="background-image: url(imagens/fundoSobreNos.png);" id="SobreNos">
  <h1 class="titleSobreNos scroll-anim"><?= t('home.about_title') ?></h1>
    <div class="divider"></div>
    <div class="text scroll-anim">
  <p> <?= t('home.about_p1') ?> </p>
  <p> <?= t('home.about_p2') ?> </p>
  <p> <?= t('home.about_p3') ?> </p>
    </div>
  </div>
  <hr>

  <div class="container-fluid py-5" id="Espaco" style="background-image: url('imagens/image 3.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    <div class="container">
      <div class="row gy-4 align-items-center">

        <!-- Texto -->
        <div class="col-12 col-lg-6 text-center text-lg-start scroll-anim">
          <h1 class="text-warning fw-bold mb-4" style="font-family: 'Yellowtail', cursive; font-size: 3.5rem;">
            <?= t('home.space_title') ?>
          </h1>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;"><?= t('home.space_p1') ?></p>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;"><?= t('home.space_p2') ?></p>
          <p class="text-white fs-5" style="font-family: 'Arapey', serif;"><?= t('home.space_p3') ?></p>
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
      
      <!-- Footer: Site Map / Support / Social -->
      <div class="col-lg-4 col-md-12 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4"><?= t('footer.sitemap') ?></h5>
        <a href="#Inicio" class="d-block mb-2"><?= t('nav.home') ?></a>
        <a href="#SobreNos" class="d-block mb-2"><?= t('nav.about') ?></a>
        <a href="#Espaco" class="d-block mb-2"><?= t('nav.space') ?></a>
        <a href="#Localizacao" class="d-block mb-2"><?= t('nav.location') ?></a>
      </div>

      <!-- Support -->
      <div class="col-lg-4 col-md-6 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4 pb-1"><?= t('footer.support') ?></h5>
        <p class="mb-1"><?= t('footer.phone') ?></p>
        <p class="mb-3">(00) 0 0000-0000</p>
        <p class="mb-1"><?= t('footer.email') ?></p>
        <p>contato@bespokebarbershop.com.br</p>
      </div>

      <!-- Social -->
      <div class="col-lg-4 col-md-6 mb-4 mb-md-0 text-center">
        <h5 class="text-uppercase mb-4"><?= t('footer.social') ?></h5>

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
      Copyright © <?= date('Y') ?> Bespoke Barber Cia. <?= t('footer.rights') ?>
    </p>
  </div>
</footer>


  
</body>

</html>