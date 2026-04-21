<?php
// Instalador simple: crea config/db.local.php, permite setear pass admin y datos básicos.
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $host = trim($_POST['host'] ?? 'localhost');
  $db   = trim($_POST['db']   ?? '');
  $user = trim($_POST['user'] ?? '');
  $pass = trim($_POST['pass'] ?? '');
  $adminPass = $_POST['admin_pass'] ?? '';
  $company   = [
    'nombre'    => trim($_POST['company_nombre'] ?? ''),
    'cuit'      => trim($_POST['company_cuit'] ?? ''),
    'domicilio' => trim($_POST['company_domicilio'] ?? ''),
    'ciudad'    => trim($_POST['company_ciudad'] ?? ''),
    'telefono'  => trim($_POST['company_tel'] ?? ''),
  ];
  $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET NAMES utf8mb4; SET time_zone='-03:00'");

    // Guardar archivo de config
    $cfg = "<?php\nreturn [\n  'dsn' => '".addslashes($dsn)."',\n  'user' => '".addslashes($user)."',\n  'pass' => '".addslashes($pass)."',\n];\n";
    $path = __DIR__ . '/../config/db.local.php';
    if (!@file_put_contents($path, $cfg)) {
      throw new RuntimeException("No pude escribir config/db.local.php. Ver permisos.");
    }

    // Actualizar contraseña del admin si se ingresó
    if (strlen($adminPass) >= 6) {
      $hash = password_hash($adminPass, PASSWORD_BCRYPT);
      $pdo->prepare("UPDATE depo_users SET password_hash=? WHERE username='admin'")->execute([$hash]);
    }

    // Actualizar datos de empresa
    $stmt = $pdo->prepare("INSERT INTO depo_settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ([
      'company.nombre'   => $company['nombre'],
      'company.cuit'     => $company['cuit'],
      'company.domicilio'=> $company['domicilio'],
      'company.ciudad'   => $company['ciudad'],
      'company.telefono' => $company['telefono'],
    ] as $k=>$v) { $stmt->execute([$k,$v]); }

    header('Location: /app/deposito/public/login.php?ok=1');
    exit;
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalación | Sistema de Depósito</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-4">
  <h1 class="h3 mb-3">Instalación</h1>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>
  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Host</label>
      <input name="host" class="form-control" value="<?=htmlspecialchars($_POST['host'] ?? 'localhost')?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Base</label>
      <input name="db" class="form-control" value="<?=htmlspecialchars($_POST['db'] ?? 'a0020675_depo')?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Usuario DB</label>
      <input name="user" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Clave DB</label>
      <input name="pass" type="password" class="form-control">
    </div>

    <hr class="mt-4">

    <div class="col-md-6">
      <label class="form-label">Nueva clave para admin</label>
      <input name="admin_pass" type="password" class="form-control" placeholder="min 6 caracteres">
      <div class="form-text">Usuario admin ya existe; acá cambiás la clave.</div>
    </div>

    <div class="col-12"><h2 class="h5 mt-4">Datos de la empresa</h2></div>
    <div class="col-md-6"><label class="form-label">Nombre</label><input name="company_nombre" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">CUIT</label><input name="company_cuit" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Domicilio</label><input name="company_domicilio" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Ciudad</label><input name="company_ciudad" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Teléfono</label><input name="company_tel" class="form-control"></div>

    <div class="col-12 d-flex gap-2 mt-3">
      <button class="btn btn-primary">Guardar e iniciar</button>
      <a href="/app/deposito/public/login.php" class="btn btn-outline-secondary">Ya tengo config</a>
    </div>
    <p class="text-muted small mt-4">Impulsado por edesign.ar</p>
  </form>
</div>
</body></html>
