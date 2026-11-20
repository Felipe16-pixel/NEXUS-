<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']), true);
    session_start();
}

/* Escapar texto para mostrarlo en HTML. */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url) { header('Location: ' . $url); exit; }

/* Dinero en pesos: $40.000  /  -$5.000 */
function dinero($n): string {
    $n = (float)$n;
    return ($n < 0 ? '-' : '') . '$' . number_format(abs($n), 0, ',', '.');
}

/* Protección CSRF para formularios y para el chat. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valido(): bool { return hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? '')); }

/* Usuario con sesión iniciada (o null). */
function usuario_actual(): ?array {
    static $u = null;
    if ($u !== null) return $u ?: null;
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) return null;
    $st = db()->prepare('SELECT id, nombre, email, presupuesto_diario FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch() ?: false;
    if (!$u) unset($_SESSION['uid']);
    return $u ?: null;
}

function requiere_login(): array {
    $u = usuario_actual();
    if (!$u) redirect('login.php');
    return $u;
}

function iniciar_sesion(int $id): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($_SESSION['chat'], $_SESSION['pend'], $_SESSION['pidio_presu']);
}
