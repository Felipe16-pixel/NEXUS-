<?php
require_once 'includes/auth.php';
require_once 'includes/esquema.php';
/* El chat también responde desde aquí (dashboard.php), por si el hosting bloquea otros archivos. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'chat') {
    require_once 'includes/chat_handler.php';
    nx_chat_http();
}
nx_mostrar_errores();
$u = requiere_login();
asegurar_esquema();
$hoy = date('Y-m-d');
function q(string $s, array $p = []) { $st = db()->prepare($s); $st->execute($p); return $st; }
function num($v): float { return (float)str_replace(['.', ','], ['', '.'], (string)$v); }
$cats = ['comida','transporte','estudio','ocio','otros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = 'tareas'; $uid = (int)$u['id']; $id = (int)($_POST['id'] ?? 0);
    if (!csrf_valido()) { $_SESSION['flash'] = ['alert', 'La sesión expiró. Inténtalo otra vez.']; }
    else switch ($_POST['accion'] ?? '') {
        case 'nueva_tarea':
            $t = trim($_POST['titulo'] ?? '');
            $dur = max(5, min(600, (int)($_POST['duracion'] ?? 30)));
            $pri = in_array($_POST['prioridad'] ?? '', ['baja','media','alta'], true) ? $_POST['prioridad'] : 'media';
            if ($t === '' || mb_strlen($t) > 150) { $_SESSION['flash'] = ['alert', 'Escribe un título de hasta 150 caracteres.']; break; }
            q('INSERT INTO tareas (usuario_id,titulo,fecha,duracion_min,prioridad) VALUES (?,?,?,?,?)', [$uid,$t,$hoy,$dur,$pri]);
            $_SESSION['flash'] = ['ok', 'Tarea guardada.']; break;
        case 'toggle_tarea': q("UPDATE tareas SET estado=IF(estado='hecha','pendiente','hecha') WHERE id=? AND usuario_id=?", [$id,$uid]); break;
        case 'borrar_tarea': q('DELETE FROM tareas WHERE id=? AND usuario_id=?', [$id,$uid]); break;
        case 'presupuesto': $tab = 'gastos'; q('UPDATE usuarios SET presupuesto_diario=? WHERE id=?', [max(0, num($_POST['presupuesto'] ?? 0)), $uid]); break;
        case 'nuevo_gasto':
            $tab = 'gastos'; $m = num($_POST['monto'] ?? 0); $d = trim($_POST['descripcion'] ?? '');
            $c = in_array($_POST['categoria'] ?? '', $cats, true) ? $_POST['categoria'] : 'otros';
            if ($m <= 0 || $d === '' || mb_strlen($d) > 120) { $_SESSION['flash'] = ['alert', 'Escribe una descripción y un monto mayor a 0.']; break; }
            q('INSERT INTO gastos (usuario_id,monto,fecha,descripcion,categoria) VALUES (?,?,?,?,?)', [$uid,$m,$hoy,$d,$c]);
            $_SESSION['flash'] = ['ok', 'Gasto registrado.']; break;
        case 'borrar_gasto': $tab = 'gastos'; q('DELETE FROM gastos WHERE id=? AND usuario_id=?', [$id,$uid]); break;
        case 'nuevo_habito':
            $tab = 'habitos'; $n = trim($_POST['nombre'] ?? '');
            if ($n === '' || mb_strlen($n) > 80) { $_SESSION['flash'] = ['alert', 'Escribe el nombre del hábito (máx. 80).']; break; }
            q('INSERT INTO habitos (usuario_id,nombre,activo) VALUES (?,?,1)', [$uid,$n]); break;
        case 'check_habito':
            $tab = 'habitos';
            if (q('SELECT id FROM habitos WHERE id=? AND usuario_id=?', [$id,$uid])->fetch()) {
                $del = q('DELETE FROM habito_registros WHERE habito_id=? AND fecha=?', [$id,$hoy]);
                if (!$del->rowCount()) q('INSERT INTO habito_registros (habito_id,usuario_id,fecha) VALUES (?,?,?)', [$id,$uid,$hoy]);
            } break;
        case 'borrar_habito': $tab = 'habitos'; q('UPDATE habitos SET activo=0 WHERE id=? AND usuario_id=?', [$id,$uid]); break;
        case 'nueva_meta':
            $tab = 'metas'; $n = trim($_POST['titulo'] ?? '');
            if ($n === '' || mb_strlen($n) > 120) { $_SESSION['flash'] = ['alert', 'Escribe tu meta (máx. 120).']; break; }
            q("INSERT INTO metas (usuario_id,titulo,estado,progreso) VALUES (?,?,'activa',0)", [$uid,$n]); break;
        case 'progreso_meta': $tab = 'metas'; q('UPDATE metas SET progreso=? WHERE id=? AND usuario_id=?', [max(0, min(100, (int)($_POST['progreso'] ?? 0))),$id,$uid]); break;
        case 'borrar_meta': $tab = 'metas'; q('DELETE FROM metas WHERE id=? AND usuario_id=?', [$id,$uid]); break;
    }
    redirect('dashboard.php#' . $tab);
}
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if (empty($_SESSION['chat'])) $_SESSION['chat'] = [['r' => 'nexus', 't' => 'Hola, ' . explode(' ', $u['nombre'])[0] . '. Cuéntame cómo va tu día: qué tienes que hacer, si gastaste algo o cuánta plata tienes hoy. Te haré las preguntas que falten. También puedes dictármelo con el micrófono 🎤.']];
$chat = $_SESSION['chat'];

$tareas = q("SELECT id,titulo,duracion_min,prioridad,estado FROM tareas WHERE usuario_id=? AND fecha=? ORDER BY estado, FIELD(prioridad,'alta','media','baja'), id", [$u['id'],$hoy])->fetchAll();
$total = count($tareas); $hechas = count(array_filter($tareas, function ($t) { return $t['estado'] === 'hecha'; }));
$pct = $total ? (int)round($hechas * 100 / $total) : 0;
$gastos = q('SELECT id,monto,descripcion,categoria FROM gastos WHERE usuario_id=? AND fecha=? ORDER BY id DESC', [$u['id'],$hoy])->fetchAll();
$gastoHoy = (float)array_sum(array_column($gastos, 'monto'));
$presu = (float)$u['presupuesto_diario']; $disp = $presu - $gastoHoy;
$usado = $presu > 0 ? (int)min(100, round($gastoHoy * 100 / $presu)) : 0;
$habitos = q('SELECT id,nombre FROM habitos WHERE usuario_id=? AND activo=1 ORDER BY id', [$u['id']])->fetchAll();
$reg = [];
foreach (q('SELECT habito_id,fecha FROM habito_registros WHERE usuario_id=? AND fecha>=?', [$u['id'], date('Y-m-d', strtotime('-6 days'))]) as $r) $reg[$r['habito_id']][$r['fecha']] = 1;
$metas = q("SELECT id,titulo,progreso FROM metas WHERE usuario_id=? AND estado='activa' ORDER BY id", [$u['id']])->fetchAll();
$enMarcha = count(array_filter($metas, function ($m) { return (int)$m['progreso'] < 100; }));
$habHoy = count(array_filter($habitos, function ($h) use ($reg, $hoy) { return !empty($reg[$h['id']][$hoy]); }));

$h = (int)date('G'); $saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
$dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado']; $ini = ['D','L','M','M','J','V','S'];
$titulo = 'Mi día'; $bodyClass = 'dash';
function borrar(string $accion, int $id, string $conf = '¿Eliminar?') {
    echo '<form method="post" data-confirm="' . e($conf) . '">' . csrf_field() . '<input type="hidden" name="accion" value="' . $accion . '"><input type="hidden" name="id" value="' . $id . '"><button class="x" aria-label="Eliminar">✕</button></form>';
}
require 'includes/header.php';
?>
<main class="dash-wrap">
  <div class="fondo" aria-hidden="true"><div class="blob b1"></div><div class="blob b2"></div><canvas id="fondo-red"></canvas><div id="fondo-glow"></div></div>
  <div class="hello">
    <div>
      <h1><?= $saludo ?>, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
      <p class="muted"><?= ucfirst($dias[(int)date('w')]) ?> <?= date('d/m/Y') ?> · <span id="reloj"><?= date('H:i') ?></span></p>
    </div>
    <div class="ring" data-pct="<?= $pct ?>" title="Tareas completadas hoy">
      <svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="15.9" class="r-bg"/><circle cx="18" cy="18" r="15.9" class="r-fg" pathLength="100"/></svg>
      <b><?= $pct ?>%</b>
    </div>
  </div>

  <?php if ($flash): ?><div class="<?= $flash[0] ?>" role="status"><?= e($flash[1]) ?></div><?php endif; ?>

  <section class="stats">
    <div class="stat"><span class="n"><?= $total - $hechas ?></span><span>tareas pendientes hoy</span></div>
    <div class="stat"><span class="n <?= $disp < 0 ? 'neg' : '' ?>"><?= dinero($disp) ?></span><span>disponible de <?= dinero($presu) ?></span></div>
    <div class="stat"><span class="n"><?= $habHoy ?>/<?= count($habitos) ?></span><span>hábitos cumplidos hoy</span></div>
    <div class="stat"><span class="n"><?= $enMarcha ?></span><span>metas en marcha</span></div>
  </section>

  <div class="tabs" role="tablist">
    <button data-tab="tareas">✓ Tareas</button><button data-tab="gastos">$ Gastos</button>
    <button data-tab="habitos">↻ Hábitos</button><button data-tab="metas">★ Metas</button>
  </div>

  <section class="panel tabpane" id="tab-tareas">
    <h2>Tareas de hoy <small><?= $hechas ?> de <?= $total ?></small></h2>
    <?php if (!$tareas): ?><p class="empty">Aún no tienes tareas para hoy. Agrega la primera aquí abajo.</p><?php else: ?>
    <ul class="tasks">
    <?php foreach ($tareas as $t): ?>
      <li class="<?= $t['estado'] === 'hecha' ? 'done' : '' ?> pri-<?= e($t['prioridad']) ?>">
        <form method="post" data-auto><?= csrf_field() ?><input type="hidden" name="accion" value="toggle_tarea"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <label class="chk"><input type="checkbox" <?= $t['estado'] === 'hecha' ? 'checked' : '' ?>><span></span></label></form>
        <div><b><?= e($t['titulo']) ?></b><span><?= (int)$t['duracion_min'] ?> min · prioridad <?= e($t['prioridad']) ?></span></div>
        <?php borrar('borrar_tarea', (int)$t['id'], '¿Eliminar esta tarea?'); ?>
      </li>
    <?php endforeach; ?></ul><?php endif; ?>
    <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="nueva_tarea">
      <input type="text" name="titulo" placeholder="Ej: estudiar cálculo" maxlength="150" required aria-label="Título">
      <input type="number" name="duracion" value="30" min="5" max="600" aria-label="Minutos">
      <select name="prioridad" aria-label="Prioridad"><option value="media">Media</option><option value="alta">Alta</option><option value="baja">Baja</option></select>
      <button class="btn">Agregar</button></form>
  </section>

  <section class="panel tabpane" id="tab-gastos">
    <h2>Gastos de hoy <small><?= dinero($gastoHoy) ?> de <?= dinero($presu) ?></small></h2>
    <div class="bar <?= $usado >= 90 ? 'danger' : ($usado >= 70 ? 'warn' : '') ?>"><i data-w="<?= $usado ?>"></i></div>
    <?php if (!$gastos): ?><p class="empty">Sin gastos hoy. Registra el primero abajo.</p><?php else: ?>
    <ul class="tasks"><?php foreach ($gastos as $g): ?>
      <li><div><b><?= e($g['descripcion']) ?></b><span><?= e($g['categoria']) ?></span></div><strong class="money"><?= dinero($g['monto']) ?></strong>
      <?php borrar('borrar_gasto', (int)$g['id'], '¿Eliminar este gasto?'); ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <form class="inline" method="post" id="f-gasto"><?= csrf_field() ?><input type="hidden" name="accion" value="nuevo_gasto">
      <input type="text" name="descripcion" placeholder="Ej: almuerzo" maxlength="120" required aria-label="Descripción">
      <input type="text" name="monto" inputmode="numeric" placeholder="Monto" required aria-label="Monto" data-disp="<?= (int)$disp ?>">
      <select name="categoria" aria-label="Categoría"><?php foreach ($cats as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
      <button class="btn">Registrar</button></form>
    <p class="muted hint" id="preview"></p>
    <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="presupuesto">
      <label class="lbl">Presupuesto diario</label><input type="text" name="presupuesto" inputmode="numeric" value="<?= (int)$presu ?>" aria-label="Presupuesto">
      <button class="btn btn-ghost">Guardar</button></form>
  </section>

  <section class="panel tabpane" id="tab-habitos">
    <h2>Hábitos <small>últimos 7 días</small></h2>
    <?php if (!$habitos): ?><p class="empty">Crea un hábito (caminar, leer, tomar agua) y márcalo cada día.</p><?php else: ?>
    <ul class="tasks"><?php foreach ($habitos as $hb): ?>
      <li><form method="post" data-auto><?= csrf_field() ?><input type="hidden" name="accion" value="check_habito"><input type="hidden" name="id" value="<?= (int)$hb['id'] ?>">
        <label class="chk"><input type="checkbox" <?= !empty($reg[$hb['id']][$hoy]) ? 'checked' : '' ?>><span></span></label></form>
        <div><b><?= e($hb['nombre']) ?></b>
        <span class="dots"><?php for ($i = 6; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i days")); ?><em class="<?= !empty($reg[$hb['id']][$d]) ? 'on' : '' ?>" title="<?= $d ?>"><?= $ini[(int)date('w', strtotime($d))] ?></em><?php endfor; ?></span></div>
        <?php borrar('borrar_habito', (int)$hb['id'], '¿Quitar este hábito?'); ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="nuevo_habito">
      <input type="text" name="nombre" placeholder="Ej: caminar 30 min" maxlength="80" required aria-label="Hábito"><button class="btn">Agregar hábito</button></form>
  </section>

  <section class="panel tabpane" id="tab-metas">
    <h2>Metas</h2>
    <?php if (!$metas): ?><p class="empty">Escribe una meta y ve moviendo la barra de progreso.</p><?php else: ?>
    <ul class="tasks"><?php foreach ($metas as $m): ?>
      <li class="meta"><div><b><?= e($m['titulo']) ?></b><?= (int)$m['progreso'] >= 100 ? ' <span class="tag">¡Lograda!</span>' : '' ?>
        <form method="post" class="slider" data-auto-change><?= csrf_field() ?><input type="hidden" name="accion" value="progreso_meta"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <input type="range" name="progreso" min="0" max="100" step="5" value="<?= (int)$m['progreso'] ?>" aria-label="Progreso"><output><?= (int)$m['progreso'] ?>%</output></form></div>
        <?php borrar('borrar_meta', (int)$m['id'], '¿Eliminar esta meta?'); ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="nueva_meta">
      <input type="text" name="titulo" placeholder="Ej: ahorrar para el portátil" maxlength="120" required aria-label="Meta"><button class="btn">Agregar meta</button></form>
  </section>

  <section class="panel chat" id="chat" data-csrf="<?= e(csrf_token()) ?>">
    <h2>Plan inteligente <small>escribe o dicta por voz</small></h2>
    <div class="msgs" id="msgs" aria-live="polite"><?php foreach ($chat as $m): ?><div class="m <?= $m['r'] === 'tu' ? 'tu' : 'nexus' ?>"><?= e($m['t']) ?></div><?php endforeach; ?></div>
    <div class="chips"><button type="button" data-say="Arma mi plan de hoy">Arma mi plan</button><button type="button" data-say="Hazme un plan de estudio">Plan de estudio</button><button type="button" data-say="Hazme un plan de ahorro">Plan de ahorro</button><button type="button" data-say="Ayúdame a estudiar">Ayúdame a estudiar</button><button type="button" data-say="Tengo $40.000 para hoy">Tengo $40.000 hoy</button><button type="button" data-say="Ayuda">¿Qué puedes hacer?</button></div>
    <form id="chat-form" class="chat-in" autocomplete="off">
      <button type="button" id="mic" class="mic" aria-label="Dictar por voz" title="Dictar por voz">🎤</button>
      <input id="chat-text" type="text" maxlength="500" placeholder="Ej: tengo que estudiar cálculo 2 horas" aria-label="Mensaje para NEXUS">
      <button class="btn">Enviar</button>
      <button type="button" id="voz" class="mic" aria-pressed="false" title="Que NEXUS responda en voz alta">🔇</button>
    </form>
    <p class="muted hint" id="mic-estado"></p>
  </section>
</main>
<script src="assets/js/fondo.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/fondo.js') ?>" defer></script>
<script src="assets/js/panel.js?v=<?= (int)@filemtime(__DIR__ . '/assets/js/panel.js') ?>" defer></script>
<?php require 'includes/footer.php'; ?>
