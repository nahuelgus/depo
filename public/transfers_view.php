<?php
// /app/deposito/public/transfers_view.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if($id<=0){ header('Location: /app/deposito/public/transfers_list.php'); exit; }

$head = DB::all("
  SELECT t.*, do.nombre AS dep_origen, dd.nombre AS dep_destino,
         u.username, u.nombre AS user_name
  FROM depo_transfers t
  JOIN depo_deposits do ON do.id=t.from_deposit_id
  JOIN depo_deposits dd ON dd.id=t.to_deposit_id
  LEFT JOIN depo_users u ON u.id=t.user_id
  WHERE t.id = ?
  LIMIT 1
",[$id]);

if(!count($head)){ header('Location: /app/deposito/public/transfers_list.php'); exit; }
$h = $head[0];

$items = DB::all("
  SELECT ti.product_id, ti.lot_id, ti.cantidad,
         p.nombre AS producto, p.barcode,
         l.nro_lote, l.vto
  FROM depo_transfer_items ti
  JOIN depo_products p ON p.id=ti.product_id
  LEFT JOIN depo_lots l ON l.id=ti.lot_id
  WHERE ti.transfer_id=?
  ORDER BY ti.id ASC
",[$id]);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Transferencia #<?=h($id)?> | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=$app['APP_NAME']?></a>
  <div class="d-flex gap-2">
    <a href="/app/deposito/public/transfers_list.php" class="btn btn-sm btn-outline-secondary">Volver</a>
    <a href="/app/deposito/public/logout.php" class="btn btn-sm btn-outline-danger">Salir</a>
  </div>
</nav>

<div class="container py-4">
  <h1 class="h5 mb-3">Transferencia #<?=h($id)?></h1>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="small text-muted">Fecha</div>
      <div class="fw-semibold"><?=h(substr($h['fecha'],0,16))?></div>
    </div>
    <div class="col-md-4">
      <div class="small text-muted">Origen → Destino</div>
      <div class="fw-semibold"><?=h($h['dep_origen'])?> → <?=h($h['dep_destino'])?></div>
    </div>
    <div class="col-md-4">
      <div class="small text-muted">Usuario</div>
      <div class="fw-semibold"><?=h($h['user_name'] ?: $h['username'] ?: '-')?></div>
    </div>
    <div class="col-12">
      <div class="small text-muted">Observación</div>
      <div class="fw-semibold"><?=h($h['observacion'])?></div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Producto</th>
          <th>Lote</th>
          <th>Vencimiento</th>
          <th class="text-end">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?=h($it['producto'])?></div>
              <div class="small text-muted"><?=h($it['barcode'])?></div>
            </td>
            <td><?=h($it['nro_lote'] ?: ('#'.$it['lot_id']))?></td>
            <td><?=h($it['vto'] ?: '-')?></td>
            <td class="text-end"><?=number_format((float)$it['cantidad'],3,',','.')?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!count($items)): ?>
          <tr><td colspan="4" class="text-muted text-center py-4">Sin ítems</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>