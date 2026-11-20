<?php
require_once __DIR__ . '/auth.php';
if (is_file(__DIR__ . '/ia_clave.php')) require_once __DIR__ . '/ia_clave.php';

/* PUNTO DE CONEXIÓN CON LA IA. Mientras no esté conectada devuelve null y NEXUS usa su
   intérprete local (la app no depende de la IA). Cuando se conecte, debe devolver un arreglo
   con las claves que quiera sobreescribir: titulo, duracion (min), prioridad (baja|media|alta). */
function ia_interpretar(string $texto, array $ctx = []): ?array {
    // Clave en config.php: define('GROQ_API_KEY', 'gsk_...');  (solo en el servidor, nunca en el JS)
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '' || !function_exists('curl_init')) return null;
    $modelo = defined('GROQ_MODEL') ? GROQ_MODEL : 'openai/gpt-oss-20b';   // llama-3.1-8b-instant fue retirado por Groq
    $url = defined('GROQ_URL') ? GROQ_URL : 'https://api.groq.com/openai/v1/chat/completions';
    $msgs = [
        ['role' => 'system', 'content' => 'Extraes los datos de una tarea escrita en español. Responde SOLO un JSON con las claves "titulo" (texto corto y limpio, sin muletillas), "duracion" (minutos como número, o null si no se dice) y "prioridad" ("baja", "media", "alta" o null si no se dice). No inventes datos que el usuario no dijo.'],
        ['role' => 'user', 'content' => mb_substr($texto, 0, 500)],
    ];
    $res = null;
    foreach ([true, false] as $conRazon) {          // 1º con razonamiento bajo (más rápido); si el modelo no lo acepta, 2º sin él
        $body = ['model' => $modelo, 'temperature' => 0, 'max_tokens' => 600, 'messages' => $msgs];
        if ($conRazon) $body['reasoning_effort'] = 'low';
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY]]);
        $res = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($res !== false && $http === 200) break;
        $res = null;
        if ($http !== 400) break;
    }
    if ($res === null) return null;                 // si Groq falla, NEXUS usa su intérprete local
    $txt = (string)(json_decode($res, true)['choices'][0]['message']['content'] ?? '');
    if (!preg_match('/\{.*\}/s', $txt, $mm)) return null;
    $j = json_decode($mm[0], true);
    if (!is_array($j)) return null;
    $o = [];
    if (!empty($j['titulo']) && is_string($j['titulo'])) $o['titulo'] = mb_substr(trim($j['titulo']), 0, 150);
    if (isset($j['duracion']) && is_numeric($j['duracion']) && $j['duracion'] > 0) $o['duracion'] = (int)$j['duracion'];
    if (in_array($j['prioridad'] ?? null, ['baja', 'media', 'alta'], true)) $o['prioridad'] = $j['prioridad'];
    return $o ?: null;
}

/* ---------- Planes con IA (estudio y ahorro) ---------- */
/* Llamada a Groq que devuelve el texto crudo (o null si falla). Prueba el modelo grande y, si no responde, el pequeño. */
function ia_llamar(array $msgs, int $max = 2500, ?array $modelos = null): ?string {
    $GLOBALS['nx_ia_error'] = '';
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') { $GLOBALS['nx_ia_error'] = 'no hay clave de Groq'; return null; }
    if (!function_exists('curl_init')) { $GLOBALS['nx_ia_error'] = 'cURL no disponible'; return null; }
    $url = defined('GROQ_URL') ? GROQ_URL : 'https://api.groq.com/openai/v1/chat/completions';
    if ($modelos === null) $modelos = [defined('GROQ_MODEL_CHAT') ? GROQ_MODEL_CHAT : 'openai/gpt-oss-20b', 'openai/gpt-oss-120b'];
    @set_time_limit(90);
    foreach (array_values(array_unique($modelos)) as $modelo) {
        foreach ([true, false] as $conRazon) {
            $body = ['model' => $modelo, 'temperature' => 0.4, 'max_tokens' => $max, 'messages' => $msgs];
            if ($conRazon) $body['reasoning_effort'] = 'low';
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY]]);
            $res = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
            if ($res === false) { $GLOBALS['nx_ia_error'] = $modelo . ': sin conexión (' . $cerr . ')'; break; }   // probar el otro modelo
            if ($http === 200) {
                $txt = trim((string)(json_decode($res, true)['choices'][0]['message']['content'] ?? ''));
                if ($txt !== '') { $GLOBALS['nx_ia_error'] = ''; return $txt; }
                $GLOBALS['nx_ia_error'] = $modelo . ': respuesta vacía';
                continue;                                     // vacío (se gastó pensando): reintenta sin razonamiento
            }
            $GLOBALS['nx_ia_error'] = $modelo . ': HTTP ' . $http . ' ' . mb_substr((string)(json_decode($res, true)['error']['message'] ?? $res), 0, 120);
            if ($http !== 400) break;                         // 429, 404, 5xx...: probar el otro modelo
        }
    }
    return null;
}

/* Igual que ia_llamar pero devuelve texto plano limpio (para los planes). */
function ia_texto(array $msgs, int $max = 1500): ?string {
    $t = ia_llamar($msgs, $max);
    if ($t === null) return null;
    $t = trim(preg_replace('/[*#`]+/u', '', $t));
    return $t !== '' ? mb_substr($t, 0, 2500) : null;
}

/* Resumen de los datos reales del usuario para que la IA no invente. */
function nx_contexto(int $uid): string {
    $hoy = date('Y-m-d'); $l = [];
    $l[] = 'Fecha: ' . $hoy . ', hora actual: ' . date('H:i') . ' (Colombia).';
    $pres = (float)nx_q('SELECT presupuesto_diario FROM usuarios WHERE id=?', [$uid])->fetchColumn();
    $l[] = 'Presupuesto diario: ' . ($pres > 0 ? dinero($pres) : 'no lo ha dicho') . '.';
    $g = (float)nx_q('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE usuario_id=? AND fecha=?', [$uid, $hoy])->fetchColumn();
    $l[] = 'Gastado hoy: ' . dinero($g) . '.';
    $cat = nx_q('SELECT categoria, SUM(monto) m FROM gastos WHERE usuario_id=? AND fecha>=? GROUP BY categoria ORDER BY m DESC', [$uid, date('Y-m-d', strtotime('-6 days'))])->fetchAll();
    $l[] = 'Gastos de los últimos 7 días por categoría: ' . ($cat ? implode(', ', array_map(function ($c) { return $c['categoria'] . ' ' . dinero($c['m']); }, $cat)) : 'ninguno registrado') . '.';
    $ts = nx_q("SELECT titulo, duracion_min, prioridad FROM tareas WHERE usuario_id=? AND fecha=? AND estado='pendiente' ORDER BY FIELD(prioridad,'alta','media','baja'), id", [$uid, $hoy])->fetchAll();
    $l[] = 'Tareas pendientes hoy: ' . ($ts ? implode('; ', array_map(function ($t) { return $t['titulo'] . ' (' . $t['duracion_min'] . ' min, prioridad ' . $t['prioridad'] . ')'; }, $ts)) : 'ninguna') . '.';
    $ms = nx_q("SELECT titulo, progreso FROM metas WHERE usuario_id=? AND estado='activa' ORDER BY id", [$uid])->fetchAll();
    $l[] = 'Metas activas: ' . ($ms ? implode('; ', array_map(function ($m) { return $m['titulo'] . ' (' . $m['progreso'] . '%)'; }, $ms)) : 'ninguna') . '.';
    $hs = nx_q('SELECT nombre FROM habitos WHERE usuario_id=? AND activo=1', [$uid])->fetchAll(PDO::FETCH_COLUMN);
    $l[] = 'Hábitos: ' . ($hs ? implode(', ', $hs) : 'ninguno') . '.';
    return implode("\n", $l);
}

/* ---------- Chat con IA: conversa, enseña, arma planes y guarda cosas cuando se lo piden ---------- */
function nx_parse_ia(string $raw): array {
    $resp = ''; $acs = []; $j = null;
    if (preg_match('/\{.*\}/s', $raw, $m)) $j = json_decode($m[0], true);
    if (is_array($j) && isset($j['respuesta'])) {
        $resp = (string)$j['respuesta']; $acs = is_array($j['acciones'] ?? null) ? $j['acciones'] : [];
    } else {                                                  // JSON mal formado: rescatar lo que se pueda
        if (preg_match('/"respuesta"\s*:\s*"(.*?)"\s*(,\s*"acciones"|\})/s', $raw, $m)) $resp = stripcslashes($m[1]);
        if (preg_match('/"acciones"\s*:\s*(\[.*\])/s', $raw, $m)) { $a = json_decode($m[1], true); if (is_array($a)) $acs = $a; }
        if ($resp === '' && !preg_match('/^\s*[\{`]/', $raw)) $resp = $raw;   // contestó en texto normal
    }
    $resp = trim(preg_replace(['/\*\*|__|`/u', '/^#+\s*/mu'], ['', ''], $resp));
    return [mb_substr($resp, 0, 3500), $acs];
}

function nx_aplicar_acciones(int $uid, array $acs): array {
    $notas = []; $ref = false; $hoy = date('Y-m-d');
    foreach (array_slice($acs, 0, 10) as $a) {
        if (!is_array($a)) continue;
        switch ($a['tipo'] ?? '') {
            case 'tarea':
                $tit = mb_substr(trim((string)($a['titulo'] ?? '')), 0, 150);
                if (mb_strlen($tit) < 2) break;
                $dur = max(5, min(600, (int)($a['duracion'] ?? 30) ?: 30));
                $pr = in_array($a['prioridad'] ?? '', ['baja', 'media', 'alta'], true) ? $a['prioridad'] : 'media';
                $f = mb_strtolower(trim((string)($a['fecha'] ?? 'hoy'))); $fecha = $hoy;
                if (in_array($f, ['mañana', 'manana'], true)) $fecha = date('Y-m-d', strtotime('+1 day'));
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false && $f >= $hoy && $f <= date('Y-m-d', strtotime('+60 days'))) $fecha = $f;
                if (nx_q("SELECT id FROM tareas WHERE usuario_id=? AND fecha=? AND titulo=? AND estado='pendiente'", [$uid, $fecha, $tit])->fetch()) break;
                nx_q('INSERT INTO tareas (usuario_id,titulo,fecha,duracion_min,prioridad) VALUES (?,?,?,?,?)', [$uid, $tit, $fecha, $dur, $pr]);
                $notas[] = '✔ Guardé la tarea «' . $tit . '» (' . $dur . ' min, ' . $pr . ($fecha !== $hoy ? ', ' . $fecha : '') . ')'; $ref = true; break;
            case 'gasto':
                $m = (float)($a['monto'] ?? 0);
                if ($m <= 0 || $m > 50000000) break;
                $d = mb_substr(trim((string)($a['descripcion'] ?? '')), 0, 120) ?: 'Gasto';
                $c = in_array($a['categoria'] ?? '', ['comida', 'transporte', 'estudio', 'ocio', 'otros'], true) ? $a['categoria'] : nx_categoria(mb_strtolower($d));
                nx_q('INSERT INTO gastos (usuario_id,monto,fecha,descripcion,categoria) VALUES (?,?,?,?,?)', [$uid, $m, $hoy, $d, $c]);
                $notas[] = '✔ Registré el gasto de ' . dinero($m) . ' en «' . $d . '»'; $ref = true; break;
            case 'presupuesto':
                $m = (float)($a['monto'] ?? 0);
                if ($m <= 0 || $m > 50000000) break;
                nx_q('UPDATE usuarios SET presupuesto_diario=? WHERE id=?', [$m, $uid]);
                $notas[] = '✔ Tu presupuesto de hoy quedó en ' . dinero($m); $ref = true; break;
            case 'meta':
                $tit = mb_substr(trim((string)($a['titulo'] ?? '')), 0, 120);
                if (mb_strlen($tit) < 2) break;
                nx_q("INSERT INTO metas (usuario_id,titulo,estado,progreso) VALUES (?,?,'activa',0)", [$uid, $tit]);
                $notas[] = '✔ Creé la meta «' . $tit . '»'; $ref = true; break;
        }
    }
    return [$notas, $ref];
}

function nx_ia_chat(array $u, string $in): ?array {
    $uid = (int)$u['id'];
    try { $ctx = nx_contexto($uid); } catch (Throwable $e) { $GLOBALS['nx_ia_error'] = 'contexto: ' . mb_substr($e->getMessage(), 0, 150); return null; }
    $nombre = explode(' ', $u['nombre'])[0];
    $sis = 'Eres NEXUS, el asistente personal de ' . $nombre . ', un estudiante en Colombia. Haces tres cosas: '
         . '(1) ORGANIZAR: armar planes del día, planes de estudio y planes de ahorro de plata con horas y montos concretos. '
         . '(2) AYUDAR A ESTUDIAR DE VERDAD: explicar temas paso a paso con ejemplos, hacer resúmenes y guías, poner preguntas de práctica (y corregir sus respuestas explicando el error), crear tarjetas de repaso y recomendar técnicas de estudio. '
         . '(3) LLEVAR SUS REGISTROS: tareas, gastos, presupuesto y metas. '
         . "\n\nESTILO: español cercano y motivador. Responde con sustancia a lo que pidió: si pide explicar un tema, explícalo de verdad (no solo lo anotes); si pide un plan, dalo completo con bloques de hora. "
         . 'Si no sabes algo o no estás seguro, dilo en vez de inventar. No inventes datos del usuario; si falta algo clave (fecha del examen, horas libres, materia) asume algo razonable, dilo en una línea y haz UNA pregunta corta. '
         . 'Longitud: normalmente 6 a 20 líneas, hasta 30 si es una explicación o una guía. '
         . 'FORMATO DEL TEXTO: texto plano, SIN markdown (nada de ** ni # ni tablas ni bloques de código). Usa líneas cortas, guiones "-" o numeración "1.". Matemáticas en texto plano (x^2, raíz(x), 3/4). Montos en pesos colombianos con punto de miles ($12.000).'
         . "\n\nACCIONES (guardar cosas): inclúyelas SOLO cuando el usuario lo pide o lo confirma claramente (\"agrega...\", \"gasté...\", \"tengo \$... hoy\", \"sí, guárdalo\"). Un plan que tú propones NO se guarda solo: ofrece \"¿Lo guardo en tus tareas?\" y, cuando diga que sí, crea una acción \"tarea\" por cada bloque del plan que acabas de dar (mira la conversación anterior). "
         . 'El sistema confirma por su cuenta lo guardado; tú solo di brevemente qué vas a guardar. Si falta la duración o la prioridad de una tarea, usa una razonable en vez de preguntar.'
         . "\n\nFORMATO DE SALIDA: responde SIEMPRE un único objeto JSON válido, sin nada fuera de él y sin bloques de código:\n"
         . '{"respuesta":"texto para el usuario (usa \\n para los saltos de línea)","acciones":[]}' . "\n"
         . 'Acciones posibles dentro de "acciones":' . "\n"
         . '{"tipo":"tarea","titulo":"...","duracion":90,"prioridad":"alta|media|baja","fecha":"hoy|mañana|AAAA-MM-DD"}' . "\n"
         . '{"tipo":"gasto","monto":12000,"descripcion":"almuerzo","categoria":"comida|transporte|estudio|ocio|otros"}' . "\n"
         . '{"tipo":"presupuesto","monto":40000}' . "\n"
         . '{"tipo":"meta","titulo":"..."}' . "\n"
         . 'Los montos son pesos colombianos, número sin puntos ni símbolos.'
         . "\n\nDATOS ACTUALES DEL USUARIO:\n" . $ctx;
    $msgs = [['role' => 'system', 'content' => $sis]];
    foreach (array_slice($_SESSION['chat'] ?? [], -10) as $m) $msgs[] = ['role' => ($m['r'] ?? '') === 'tu' ? 'user' : 'assistant', 'content' => mb_substr((string)($m['t'] ?? ''), 0, 700)];
    $msgs[] = ['role' => 'user', 'content' => mb_substr($in, 0, 1000)];
    $raw = ia_llamar($msgs, 2500);
    if ($raw === null) return null;
    [$resp, $acs] = nx_parse_ia($raw);
    if ($resp === '') { $GLOBALS['nx_ia_error'] = 'la IA contestó pero no pude leer su respuesta'; return null; }
    unset($_SESSION['pend']);                                  // la IA lleva la conversación, no preguntas pendientes viejas
    [$notas, $ref] = nx_aplicar_acciones($uid, $acs);
    $out = [$resp]; if ($notas) $out[] = implode("\n", $notas);
    return ['msgs' => $out, 'refrescar' => $ref];
}

/* Plan de estudio o de ahorro armado por la IA con los datos del usuario. */
function nx_plan_ia(string $tipo, array $u, string $pedido): array {
    $uid = (int)$u['id']; $ctx = nx_contexto($uid);
    $base = 'Eres NEXUS, un asistente que ayuda a estudiantes colombianos a organizar su día y su plata. Responde en español, cercano y claro. '
          . 'IMPORTANTE: texto plano, SIN markdown (nada de asteriscos, almohadillas, tablas ni negritas); usa líneas cortas y guiones simples "-". Máximo 14 líneas. '
          . 'Usa SOLO los datos del usuario que se te dan; si falta algo clave, haz una suposición razonable, dila en una línea y termina con UNA pregunta corta para afinar el plan. Montos en pesos colombianos con punto de miles (ej: $12.000).';
    if ($tipo === 'estudio') {
        $sis = $base . ' Tarea: arma un PLAN DE ESTUDIO realista para hoy y los próximos días. Usa bloques con hora (ej: 19:00-19:50), técnica 50 min de estudio + 10 de descanso, '
             . 'prioriza lo urgente y lo que tenga fecha cercana, reparte por materia/tarea, e incluye un repaso corto al final. Respeta la hora actual: no programes en el pasado.';
    } else {
        $sis = $base . ' Tarea: arma un PLAN DE AHORRO realista. Calcula con el presupuesto y los gastos reales: cuánto puede ahorrar por día y por semana, en qué categorías recortar con ejemplos concretos, '
             . 'una meta de ahorro con fecha estimada si el usuario mencionó algo que quiere comprar, y 2 hábitos fáciles. No inventes ingresos que no se dieron.';
    }
    $msgs = [['role' => 'system', 'content' => $sis . "\n\nDATOS DEL USUARIO:\n" . $ctx]];
    foreach (array_slice($_SESSION['chat'] ?? [], -4) as $m) $msgs[] = ['role' => $m['r'] === 'tu' ? 'user' : 'assistant', 'content' => mb_substr((string)$m['t'], 0, 400)];
    $msgs[] = ['role' => 'user', 'content' => mb_substr($pedido, 0, 500)];
    $r = ia_texto($msgs);
    if ($r !== null) return ['msgs' => [$r], 'refrescar' => false];

    // Plan B si la IA no responde
    if ($tipo === 'estudio') {
        $p = nx_plan($uid); array_unshift($p['msgs'], 'La IA no respondió en este momento, te muestro tu plan básico de hoy:'); return $p;
    }
    $pres = (float)nx_q('SELECT presupuesto_diario FROM usuarios WHERE id=?', [$uid])->fetchColumn();
    if ($pres <= 0) return ['msgs' => ['La IA no respondió ahora. Mientras tanto dime cuánta plata tienes por día (ej: «tengo $40.000 hoy») y armo tu plan de ahorro.'], 'refrescar' => false];
    return ['msgs' => ['La IA no respondió ahora. Regla simple: de tus ' . dinero($pres) . ' diarios, intenta guardar el 20% (' . dinero($pres * 0.2) . ' al día, ' . dinero($pres * 0.2 * 7) . ' a la semana). Pídeme de nuevo «plan de ahorro» en un momento.'], 'refrescar' => false];
}

function nx_q(string $s, array $p = []) { $st = db()->prepare($s); $st->execute($p); return $st; }

function nx_duracion(string $t): ?int {
    if (preg_match('/media hora/u', $t)) return 30;
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:horas?|hrs?|h)\b/u', $t, $m)) return (int)round((float)str_replace(',', '.', $m[1]) * 60);
    if (preg_match('/(\d+)\s*(?:minutos?|mins?)\b/u', $t, $m)) return (int)$m[1];
    if (preg_match('/\b(una|dos|tres|cuatro) horas?\b/u', $t, $m)) return ['una' => 60, 'dos' => 120, 'tres' => 180, 'cuatro' => 240][$m[1]];
    return null;
}
function nx_monto(string $t): ?float {
    if (preg_match('/\$\s*([\d.,]+)\s*(mil|k)?/iu', $t, $m) || preg_match('/([\d.,]+)\s*(mil|k|pesos|lucas)\b/iu', $t, $m)) {
        $n = (float)str_replace([',', '.'], '', $m[1]);
        if (!empty($m[2]) && in_array(mb_strtolower($m[2]), ['mil', 'k', 'lucas'], true) && $n < 1000) $n *= 1000;
        return $n > 0 ? $n : null;
    }
    return null;
}
function nx_prioridad(string $t): ?string {
    if (preg_match('/urgente|importante|prioridad alta|\balta\b|cuanto antes/u', $t)) return 'alta';
    if (preg_match('/\bbaja\b|sin afán|sin prisa|cuando pueda|poco importante/u', $t)) return 'baja';
    if (preg_match('/\bmedia\b|normal/u', $t)) return 'media';
    return null;
}
function nx_titulo(string $t): string {
    $t = preg_replace('/^(hola|hey|ey|buenas)[,!\s]*/iu', '', $t);
    $t = preg_replace('/\b(tengo que|tengo|debo|necesito|quiero|voy a|hay que|me toca|recuérdame|agrega|añade)\b\s*/iu', '', $t);
    $t = preg_replace('/(?:por|durante|de)?\s*(?:media hora|\d+(?:[.,]\d+)?\s*(?:horas?|hrs?|h|minutos?|mins?)\b|(?:una|dos|tres|cuatro)\s+horas?)/iu', '', $t);
    $t = preg_replace('/\b(es\s+)?(urgente|importante|prioridad (?:alta|media|baja))\b/iu', '', $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t), " ,.;:!¡¿?");
    $t = preg_replace('/^(de|por|durante)\s+/iu', '', $t);
    return mb_substr(mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1), 0, 150);
}
function nx_categoria(string $t): string {
    if (preg_match('/almuerzo|desayuno|cena|comida|café|mecato|restaurante/u', $t)) return 'comida';
    if (preg_match('/bus|taxi|pasaje|transporte|uber|gasolina|moto/u', $t)) return 'transporte';
    if (preg_match('/libro|curso|universidad|matr[ií]cula|copias|estudio/u', $t)) return 'estudio';
    if (preg_match('/cine|fiesta|salida|juego|netflix|ocio/u', $t)) return 'ocio';
    return 'otros';
}
function nx_hecho(array $p, int $uid): array {
    $out = [];
    if (empty($p['titulo'])) { $_SESSION['pend'] = $p + ['espera' => 'titulo']; return ['msgs' => ['¿Qué es lo que tienes que hacer? Cuéntame la tarea.'], 'refrescar' => false]; }
    if (empty($p['duracion'])) { $_SESSION['pend'] = array_merge($p, ['espera' => 'duracion']); return ['msgs' => ['¿Cuánto tiempo calculas para «' . $p['titulo'] . '»? (ej: 45 minutos, 2 horas)'], 'refrescar' => false]; }
    if (empty($p['prioridad'])) { $_SESSION['pend'] = array_merge($p, ['espera' => 'prioridad']); return ['msgs' => ['¿Qué tan importante es «' . $p['titulo'] . '»: alta, media o baja?'], 'refrescar' => false]; }
    nx_q('INSERT INTO tareas (usuario_id,titulo,fecha,duracion_min,prioridad) VALUES (?,?,?,?,?)', [$uid, $p['titulo'], date('Y-m-d'), max(5, min(600, (int)$p['duracion'])), $p['prioridad']]);
    unset($_SESSION['pend']);
    $out[] = 'Listo, agregué «' . $p['titulo'] . '» (' . (int)$p['duracion'] . ' min, prioridad ' . $p['prioridad'] . ').';
    $pres = (float)nx_q('SELECT presupuesto_diario FROM usuarios WHERE id=?', [$uid])->fetchColumn();
    if ($pres <= 0 && empty($_SESSION['pidio_presu'])) {
        $_SESSION['pidio_presu'] = 1; $_SESSION['pend'] = ['espera' => 'presupuesto'];
        $out[] = '¿Cuánta plata tienes para gastar hoy? (ej: $40.000)';
    } else $out[] = '¿Algo más para hoy? Cuando termines, dime «arma mi plan».';
    return ['msgs' => $out, 'refrescar' => true];
}
function nx_plan(int $uid): array {
    $ts = nx_q("SELECT titulo,duracion_min FROM tareas WHERE usuario_id=? AND fecha=? AND estado='pendiente' ORDER BY FIELD(prioridad,'alta','media','baja'), id", [$uid, date('Y-m-d')])->fetchAll();
    if (!$ts) return ['msgs' => ['Aún no tengo tareas tuyas pendientes para hoy. Cuéntame qué tienes que hacer (ej: «estudiar cálculo 2 horas»).'], 'refrescar' => false];
    $c = (int)(ceil(max(time(), strtotime('06:00')) / 300) * 300); $fin = strtotime('22:00'); $l = []; $no = [];
    foreach ($ts as $t) {
        $d = (int)$t['duracion_min'] * 60;
        if ($c + $d > $fin) { $no[] = $t['titulo']; continue; }
        $l[] = date('H:i', $c) . '–' . date('H:i', $c + $d) . '  ' . $t['titulo'] . ' (' . (int)$t['duracion_min'] . ' min)';
        $c += $d + 600;
    }
    $out = ["Este es tu plan de hoy (con 10 min de descanso entre tareas):\n" . implode("\n", $l)];
    if ($no) $out[] = 'No alcanzan hoy: ' . implode(', ', $no) . '. ¿Quieres dejarlas para mañana o bajarles el tiempo?';
    $pres = (float)nx_q('SELECT presupuesto_diario FROM usuarios WHERE id=?', [$uid])->fetchColumn();
    $g = (float)nx_q('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE usuario_id=? AND fecha=?', [$uid, date('Y-m-d')])->fetchColumn();
    if ($pres > 0) $out[] = 'Te quedan ' . dinero($pres - $g) . ' para gastar hoy.';
    return ['msgs' => $out, 'refrescar' => false];
}
function nx_responder(array $u, string $in): array {
    $t = mb_strtolower(trim($in)); $uid = (int)$u['id']; $p = $_SESSION['pend'] ?? null;
    if ($p && preg_match('/^(cancela|olv[ií]dalo|d[eé]jalo)/u', $t)) { unset($_SESSION['pend']); return ['msgs' => ['Listo, lo dejamos así. ¿Qué más hacemos?'], 'refrescar' => false]; }

    if ($p) {   // 1) Estoy esperando la respuesta a una pregunta
        $nose = (bool)preg_match('/no s[eé]|normal|cualquiera|t[uú] decide/u', $t);
        switch ($p['espera']) {
            case 'presupuesto':
                $m = nx_monto($t) ?? (preg_match('/^[\d.,]+$/', $t) ? (float)str_replace([',', '.'], '', $t) : null);
                unset($_SESSION['pend']);
                if (!$m) return ['msgs' => ['Sin problema, lo dejamos para después. Cuando quieras dime «tengo $40.000 hoy».'], 'refrescar' => false];
                nx_q('UPDATE usuarios SET presupuesto_diario=? WHERE id=?', [$m, $uid]);
                return ['msgs' => ['Anotado: tienes ' . dinero($m) . ' para hoy. ¿Algo más? Si no, dime «arma mi plan».'], 'refrescar' => true];
            case 'duracion':
                $d = nx_duracion($t);
                if ($d === null && preg_match('/^\d+$/', $t)) { $n = (int)$t; $d = $n <= 8 ? $n * 60 : $n; }
                if ($d === null && $nose) $d = 30;
                if ($d === null) return ['msgs' => ['Dime el tiempo así: «45 minutos» o «2 horas».'], 'refrescar' => false];
                $p['duracion'] = $d; break;
            case 'prioridad':
                $pr = nx_prioridad($t) ?? ($nose ? 'media' : null);
                if (!$pr) return ['msgs' => ['¿Es de prioridad alta, media o baja?'], 'refrescar' => false];
                $p['prioridad'] = $pr; break;
            case 'titulo': $p['titulo'] = nx_titulo($in); $p['duracion'] = $p['duracion'] ?? nx_duracion($t); break;
        }
        unset($p['espera']); return nx_hecho($p, $uid);
    }
    if (preg_match('/plan(es)? de estudio|horario de estudio|plan para estudiar|plan de estudios|c[oó]mo (debo |puedo |hago para )?estudiar|organiza.*estudi/u', $t)) return nx_plan_ia('estudio', $u, $in);
    if (preg_match('/plan de ahorro|ahorrar|ahorro|c[oó]mo ahorro|consejos? (de|para) (plata|dinero|gastos?)|recortar gastos|en qu[eé] (estoy )?gasto/u', $t)) return nx_plan_ia('ahorro', $u, $in);
    if (preg_match('/(arma|organiza|ordena|genera|hazme|dame|crea).*(plan|d[ií]a)|^plan\b|mi plan/u', $t)) return nx_plan($uid);
    if (preg_match('/\b(gast[eé]|pagu[eé]|compr[eé])\b/u', $t) && ($m = nx_monto($t))) {
        $d = trim(preg_replace('/^.*?\b(?:gast[eé]|pagu[eé]|compr[eé])\b\s*/u', '', preg_replace('/\$\s*[\d.,]+\s*(mil|k)?|[\d.,]+\s*(mil|k|pesos|lucas)\b/iu', '', $t)), " ,.");
        $d = trim(preg_replace('/^(en|de|por)\s+/u', '', $d)); $d = $d !== '' ? mb_substr($d, 0, 120) : 'Gasto';
        nx_q('INSERT INTO gastos (usuario_id,monto,fecha,descripcion,categoria) VALUES (?,?,?,?,?)', [$uid, $m, date('Y-m-d'), $d, nx_categoria($t)]);
        return ['msgs' => ['Registré el gasto de ' . dinero($m) . ' en «' . $d . '».'], 'refrescar' => true];
    }
    if (($m = nx_monto($t)) && preg_match('/tengo|presupuesto|disponible|para hoy|cuento con/u', $t)) {
        nx_q('UPDATE usuarios SET presupuesto_diario=? WHERE id=?', [$m, $uid]);
        return ['msgs' => ['Perfecto, tu presupuesto de hoy queda en ' . dinero($m) . '.'], 'refrescar' => true];
    }
    if (preg_match('/^(hola|hey|ey|buenas|buenos d[ií]as|buen d[ií]a|qu[eé] m[aá]s)[\s!.,?]*$/u', $t))
        return ['msgs' => ['¡Hola, ' . explode(' ', $u['nombre'])[0] . '! ¿Qué tienes que hacer hoy? Cuéntame y te hago las preguntas que falten.'], 'refrescar' => false];
    if (preg_match('/ayuda|qu[eé] puedes|c[oó]mo funcion/u', $t))
        return ['msgs' => ["Puedo:\n• Guardar tareas («estudiar cálculo 2 horas») y preguntarte lo que falte\n• Anotar gastos («gasté 12 mil en almuerzo»)\n• Guardar tu presupuesto («tengo \$40.000 hoy»)\n• Armar tu plan del día («arma mi plan»)\n• Plan de estudio con IA («hazme un plan de estudio»)\n• Plan de ahorro con IA («plan de ahorro»)\nTambién puedes dictarme con el micrófono."], 'refrescar' => false];

    $x = ia_interpretar($in, []) ?? [];   // 3) Tarea nueva
    $p = array_merge(['titulo' => nx_titulo($in), 'duracion' => nx_duracion($t), 'prioridad' => nx_prioridad($t)], array_filter($x));
    if (mb_strlen($p['titulo']) < 2) return ['msgs' => ['No te entendí bien. Cuéntame qué tienes que hacer, por ejemplo «estudiar cálculo 2 horas».'], 'refrescar' => false];
    return nx_hecho($p, $uid);
}
