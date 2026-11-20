<?php
require_once 'includes/auth.php';
require_once 'includes/esquema.php';
nx_mostrar_errores();
asegurar_esquema();
if (usuario_actual()) redirect('dashboard.php');

$err = []; $nombre = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim(preg_replace('/\s+/u', ' ', $_POST['nombre'] ?? ''));
    $email  = mb_strtolower(trim($_POST['email'] ?? ''));
    $clave  = (string)($_POST['clave'] ?? '');
    $clave2 = (string)($_POST['clave2'] ?? '');

    if (!csrf_valido()) $err[] = 'La sesión expiró. Inténtalo otra vez.';
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) $err[] = 'Escribe tu nombre (entre 2 y 80 caracteres).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $err[] = 'Escribe un correo válido.';
    if (strlen($clave) < 8) $err[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($clave !== $clave2) $err[] = 'Las contraseñas no coinciden.';

    if (!$err) {
        $st = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $err[] = 'Ese correo ya tiene cuenta. Prueba a entrar.';
        } else {
            try {
                db()->prepare('INSERT INTO usuarios (nombre, email, clave_hash) VALUES (?,?,?)')
                    ->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT)]);
                iniciar_sesion((int)db()->lastInsertId());
                redirect('dashboard.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') $err[] = 'Ese correo ya tiene cuenta. Prueba a entrar.';
                else throw $ex;
            }
        }
    }
}
$titulo = 'Crear cuenta';
require 'includes/header.php';
?>
<main class="auth-wrap">
  <section class="card">
    <h1>Crear cuenta</h1>
    <p class="muted">Gratis, y lista en 20 segundos.</p>
    <?php if ($err): ?><div class="alert" role="alert"><ul><?php foreach ($err as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <label for="nombre">Nombre</label>
      <input id="nombre" type="text" name="nombre" value="<?= e($nombre) ?>" maxlength="80" required autocomplete="name">
      <label for="email">Correo</label>
      <input id="email" type="email" name="email" value="<?= e($email) ?>" maxlength="150" required autocomplete="email">
      <label for="clave">Contraseña</label>
      <input id="clave" type="password" name="clave" minlength="8" required autocomplete="new-password">
      <small>Mínimo 8 caracteres.</small>
      <label for="clave2">Repite la contraseña</label>
      <input id="clave2" type="password" name="clave2" minlength="8" required autocomplete="new-password">
      <button class="btn btn-block">Crear mi cuenta</button>
    </form>
    <p class="alt">¿Ya tienes cuenta? <a href="login.php">Entrar</a></p>
  </section>
</main>
<?php require 'includes/footer.php'; ?>
