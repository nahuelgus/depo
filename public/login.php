<?php
require_once __DIR__ . '/../config/bootstrap.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';
  if (login($u,$p)) { header('Location: /app/deposito/public/dashboard.php'); exit; }
  $msg = 'Usuario o contraseña inválidos';
}
$app = require __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar | <?=htmlspecialchars($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
</head><body class="bg-white">
<div class="container py-5" style="max-width:480px">
  <h1 class="h3 mb-3"><?=htmlspecialchars($app['APP_NAME'])?></h1>
  <?php if(isset($_GET['ok'])): ?><div class="alert alert-success">Instalación terminada. Ingresá con el admin.</div><?php endif; ?>
  <?php if($msg): ?><div class="alert alert-danger"><?=$msg?></div><?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3">
      <label class="form-label">Usuario</label>
      <input name="username" class="form-control" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Contraseña</label>
      <input name="password" type="password" class="form-control" required>
    </div>
    <button class="btn btn-dark w-100">Ingresar</button>
  </form>
  <p class="text-center text-muted mt-3 small">
    <img src="<?=htmlspecialchars($app['POWERED_LOGO'])?>" alt="edesign" style="height:22px;vertical-align:middle">
    <span class="ms-1"><?=$app['POWERED_BY']?></span>
  </p>
</div>
</body></html>
