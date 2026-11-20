<?php
require_once __DIR__ . '/config.php';

/* Conexión única a MySQL (PDO). */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (DB_PASS === 'PON_AQUI_TU_CLAVE_MYSQL') {
        throw new RuntimeException('Falta configurar la contraseña de MySQL en includes/config.php (DB_PASS).');
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
