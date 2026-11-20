<?php
require_once __DIR__ . '/db.php';

/* Si algo falla en la base de datos, muestra el mensaje en vez de un "error 500" mudo. */
function nx_mostrar_errores(): void {
    set_exception_handler(function ($e) {
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;background:#0b1220;color:#e7ecf5;padding:2rem">'
           . '<h1>NEXUS: error</h1><p>Envía este texto para poder arreglarlo:</p>'
           . '<pre style="white-space:pre-wrap;background:#111b30;padding:1rem;border-radius:8px">' . htmlspecialchars($e->getMessage()) . '</pre>';
    });
}

/* Crea todas las tablas que falten (MySQL y MariaDB). Se ejecuta una vez por sesión. */
function asegurar_esquema(): void {
    if (!empty($_SESSION['esquema_ok'])) return;
    $pdo = db();
    $tablas = [
        'CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(80) NOT NULL,
            email VARCHAR(150) NOT NULL,
            clave_hash VARCHAR(255) NOT NULL,
            presupuesto_diario DECIMAL(12,2) NOT NULL DEFAULT 0,
            creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        "CREATE TABLE IF NOT EXISTS tareas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            fecha DATE NOT NULL,
            duracion_min INT NOT NULL DEFAULT 30,
            prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
            estado ENUM('pendiente','hecha') NOT NULL DEFAULT 'pendiente',
            KEY idx_tarea_dia (usuario_id, fecha)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS gastos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            fecha DATE NOT NULL,
            descripcion VARCHAR(120) NOT NULL DEFAULT '',
            categoria VARCHAR(30) NOT NULL DEFAULT 'otros',
            KEY idx_gasto_dia (usuario_id, fecha)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'CREATE TABLE IF NOT EXISTS habitos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            nombre VARCHAR(80) NOT NULL DEFAULT \'\',
            activo TINYINT(1) NOT NULL DEFAULT 1,
            KEY idx_habito_user (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS habito_registros (
            id INT AUTO_INCREMENT PRIMARY KEY, habito_id INT NOT NULL, usuario_id INT NOT NULL, fecha DATE NOT NULL,
            UNIQUE KEY uq_habito_dia (habito_id, fecha)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        "CREATE TABLE IF NOT EXISTS metas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            titulo VARCHAR(120) NOT NULL DEFAULT '',
            estado VARCHAR(20) NOT NULL DEFAULT 'activa',
            progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
            KEY idx_meta_user (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tablas as $sql) $pdo->exec($sql);

    /* Por si alguna tabla vieja ya existía sin estas columnas */
    $cols = [
        ['gastos', 'descripcion', "VARCHAR(120) NOT NULL DEFAULT ''"], ['gastos', 'categoria', "VARCHAR(30) NOT NULL DEFAULT 'otros'"],
        ['habitos', 'nombre', "VARCHAR(80) NOT NULL DEFAULT ''"],
        ['metas', 'titulo', "VARCHAR(120) NOT NULL DEFAULT ''"], ['metas', 'progreso', 'TINYINT UNSIGNED NOT NULL DEFAULT 0'],
    ];
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    foreach ($cols as [$t, $c, $def]) {
        $st->execute([$t, $c]);
        if (!(int)$st->fetchColumn()) $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
    }
    $_SESSION['esquema_ok'] = 1;
}
