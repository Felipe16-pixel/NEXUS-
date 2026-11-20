<?php
require_once 'includes/auth.php';
if (!empty($_SESSION['uid'])) redirect('dashboard.php');
$titulo = null;
require 'includes/header.php';
?>
<noscript><style>.rev{opacity:1!important;transform:none!important}</style></noscript>
<div id="prog" aria-hidden="true"></div>
<div class="land">
<canvas id="red" aria-hidden="true"></canvas><div class="glow" id="glow" aria-hidden="true"></div>
<main class="hero">
  <div>
    <span class="pill"><i class="pulse"></i> Escribe o dicta por voz</span>
    <h1>NEXUS organiza <span class="rota" id="rota">tu día</span>.</h1>
    <p class="lead">Dile qué tienes que hacer, cuánta plata tienes hoy y qué hábitos quieres cumplir. NEXUS arma tu plan, lleva tus gastos y te acompaña con tus metas.</p>
    <div class="cta-row">
      <a class="btn btn-lg" href="registro.php">Empezar gratis</a>
      <a class="btn btn-lg btn-ghost" href="login.php">Ya tengo cuenta</a>
    </div>
  </div>
  <aside class="day" id="demo" aria-label="Demostración interactiva de NEXUS">
    <span class="flo flo1" aria-hidden="true">✓</span><span class="flo flo2" aria-hidden="true">$</span><span class="flo flo3" aria-hidden="true">★</span><span class="flo flo4" aria-hidden="true">↻</span>
    <div class="chips" role="tablist" aria-label="Elige un ejemplo">
      <button type="button" class="on" data-demo="estudio">📚 Estudiar</button>
      <button type="button" data-demo="ahorro">💰 Ahorrar</button>
      <button type="button" data-demo="habitos">🔥 Hábitos</button>
    </div>
    <p class="day-said"><span id="demo-txt"></span><i class="caret"></i></p>
    <ul class="timeline" id="demo-line" aria-live="polite"></ul>
    <p class="day-money">Te quedan <b id="demo-money">$0</b> para gastar hoy.</p>
  </aside>
</main>

<section class="land-sec" id="prueba">
  <h2 class="rev">Pruébalo tú mismo</h2>
  <p class="sub rev">Es una demo: escríbele a NEXUS como si hablaras con un amigo. No se guarda nada.</p>
  <div class="play rev">
    <div class="play-chat">
      <div class="msgs" id="p-msgs" aria-live="polite"></div>
      <div class="chips" id="p-chips"><button type="button" data-say="Tengo que estudiar cálculo">Estudiar cálculo</button><button type="button" data-say="Tengo $40.000 para hoy">Tengo $40.000</button><button type="button" data-say="Gasté 12 mil en almuerzo">Gasté 12 mil en almuerzo</button></div>
      <form id="p-form" class="chat-in" autocomplete="off"><input id="p-text" type="text" maxlength="120" placeholder="Ej: tengo que hacer ejercicio 45 minutos" aria-label="Mensaje de prueba"><button class="btn">Enviar</button></form>
    </div>
    <div class="play-day">
      <h3>Mi día <small id="p-pct">0%</small></h3>
      <div class="bar"><i id="p-bar"></i></div>
      <ul class="tasks" id="p-tasks"></ul>
      <p class="day-money">Te quedan <b id="p-money">$0</b></p>
    </div>
  </div>
</section>

<section class="land-sec">
  <h2 class="rev">Todo tu día en un solo lugar</h2>
  <div class="feats">
    <article class="feat rev"><span>✓</span><h3>Tareas con plan</h3><p>Dile qué tienes pendiente y NEXUS te pregunta lo que falte y las ordena por prioridad.</p></article>
    <article class="feat rev"><span>$</span><h3>Gastos al día</h3><p>Anota «gasté 12 mil en almuerzo» y mira cuánto te queda del presupuesto.</p></article>
    <article class="feat rev"><span>↻</span><h3>Hábitos con racha</h3><p>Marca tus hábitos cada día y ve tus últimos 7 días de un vistazo.</p></article>
    <article class="feat rev"><span>★</span><h3>Metas con progreso</h3><p>Mueve la barra cuando avances y celebra cuando llegues al 100%.</p></article>
  </div>
</section>

<section class="land-sec">
  <h2 class="rev">Así de fácil</h2>
  <ol class="steps">
    <li class="rev"><b>1</b><div><h3>Cuéntale tu día</h3><p>Escribe o habla, como se lo dirías a un amigo.</p></div></li>
    <li class="rev"><b>2</b><div><h3>NEXUS te pregunta</h3><p>Si falta el tiempo o la prioridad, te lo pregunta.</p></div></li>
    <li class="rev"><b>3</b><div><h3>Sigue tu plan</h3><p>Ves tu horario, tus gastos y tu progreso en «Mi día».</p></div></li>
  </ol>
  <div class="band rev"><h2>Empieza a organizar tu día hoy</h2><a class="btn btn-lg" href="registro.php">Crear mi cuenta gratis</a></div>
</section>
</div>
<script src="assets/js/inicio.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/inicio.js') ?>" defer></script>
<?php require 'includes/footer.php'; ?>
