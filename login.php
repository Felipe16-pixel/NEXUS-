<?php
require_once 'includes/auth.php';
require_once 'includes/esquema.php';
nx_mostrar_errores();
asegurar_esquema();
if (usuario_actual()) redirect('dashboard.php');

$err = []; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    if (!csrf_valido()) {
        $err[] = 'La sesión expiró. Inténtalo otra vez.';
    } else {
        $st = db()->prepare('SELECT id, clave_hash FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        // password_verify también se ejecuta si el correo no existe, para no dar pistas
        $hash = $row ? $row['clave_hash'] : '$2y$10$usesomesillystringforsaleeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
        if ($row && password_verify($clave, $hash)) {
            iniciar_sesion((int)$row['id']);
            redirect('dashboard.php');
        }
        usleep(400000);
        $err[] = 'Correo o contraseña incorrectos.';
    }
}
$titulo = 'Entrar';
require 'includes/header.php';
?>
<main class="auth-wrap">
  <section class="card">
    <h1>Entrar</h1>
    <p class="muted">Qué bueno verte de nuevo.</p>
    <?php if ($err): ?><div class="alert" role="alert"><ul><?php foreach ($err as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <label for="email">Correo</label>
      <input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      <label for="clave">Contraseña</label>
      <input id="clave" type="password" name="clave" required autocomplete="current-password">
      <button class="btn btn-block">Entrar</button>
    </form>
    <p class="alt">¿No tienes cuenta? <a href="registro.php">Créala gratis</a></p>
  </section>
</main>
<?php require 'includes/footer.php'; ?>
