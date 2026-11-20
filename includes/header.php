<?php
$__u = usuario_actual();
$__titulo = isset($titulo) ? $titulo . ' · NEXUS' : 'NEXUS · Organiza tu día';
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/icono-32.png">
<link rel="apple-touch-icon" href="assets/img/icono-180.png">
<title><?= e($__titulo) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script src="assets/js/app.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>
<script src="assets/js/cursor.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/cursor.js') ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="topbar">
  <a class="logo" href="<?= $__u ? 'dashboard.php' : 'index.php' ?>"><img class="logo-img" src="assets/img/logo.png" alt="N" width="53" height="30"><span class="logo-txt">EXUS</span></a>
  <nav>
    <?php if ($__u): ?>
      <span class="who">Hola, <?= e(explode(' ', $__u['nombre'])[0]) ?></span>
      <a href="dashboard.php">Mi día</a>
      <a class="btn btn-ghost btn-sm" href="logout.php">Salir</a>
    <?php else: ?>
      <a href="login.php">Entrar</a>
      <a class="btn btn-sm" href="registro.php">Crear cuenta</a>
    <?php endif; ?>
  </nav>
</header>
