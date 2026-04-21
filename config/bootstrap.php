<?php
// bootstrap común
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

$app = require __DIR__ . '/app.php';

// Cargar credenciales DB generadas por /install
$dbFile = __DIR__ . '/db.local.php';
if (!file_exists($dbFile)) {
  // Si no existe, redirigir al instalador
  if (PHP_SAPI !== 'cli') {
    header('Location: /app/deposito/install/');
    exit;
  }
}
$db = require $dbFile;

// Helpers base
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

DB::init($db['dsn'], $db['user'], $db['pass']);
