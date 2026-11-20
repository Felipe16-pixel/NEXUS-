<?php
require_once __DIR__ . '/asistente.php';
require_once __DIR__ . '/esquema.php';

/* Responde un mensaje del chat. Siempre contesta HTTP 200 con JSON:
   algunos hostings gratis reemplazan los errores 4xx/5xx por su propia página. */
function nx_chat_http(): void {
    ini_set('display_errors', '0');
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) { http_response_code(200); header('Content-Type: application/json; charset=utf-8'); }
            echo json_encode(['error' => 'Error del servidor: ' . mb_substr($e['message'], 0, 160)], JSON_UNESCAPED_UNICODE);
        }
    });
    header('Content-Type: application/json; charset=utf-8');
    $fallo = function (string $m) { http_response_code(200); exit(json_encode(['error' => $m], JSON_UNESCAPED_UNICODE)); };

    try {
        $u = usuario_actual();
        if (!$u) $fallo('Tu sesión expiró, vuelve a entrar');
        $tok = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $tok)) $fallo('Token inválido, recarga la página');
        $in = trim((string)($_POST['mensaje'] ?? ''));
        if ($in === '' || mb_strlen($in) > 1000) $fallo('Mensaje vacío o muy largo');
        asegurar_esquema();
        $r = nx_ia_chat($u, $in);   // IA primero; si Groq no responde, reglas internas
        if ($r === null) {
            $motivo = (string)($GLOBALS['nx_ia_error'] ?? '');
            $r = nx_responder($u, $in);
            if ($motivo !== '') array_unshift($r['msgs'], '(La IA no respondió: ' . $motivo . '. Usé el modo básico.)');
        }
    } catch (Throwable $e) {
        $fallo(mb_substr($e->getMessage(), 0, 200));
    }
    $chat = $_SESSION['chat'] ?? []; $chat[] = ['r' => 'tu', 't' => $in];
    foreach ($r['msgs'] as $m) $chat[] = ['r' => 'nexus', 't' => $m];
    $_SESSION['chat'] = array_slice($chat, -40);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
