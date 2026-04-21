<?php
// /app/deposito/public/movements.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$sql = "
  SELECT
    s.id AS sale_id, s.fecha, s.deposit_id,
    i.product_id, i.lot_id, i.cantidad,
    p.nombre AS producto, p.presentacion,
    l.nro_lote
  FROM depo_sale_items i
  JOIN depo_sales s   ON s.id = i.sale_id
  JOIN depo_products p ON p.id = i.product_id
  LEFT JOIN depo_lots l ON l.id = i.lot_id
  WHERE s.fecha BETWEEN ? AND ?
  ORDER BY s.fecha DESC, i.id DESC
  LIMIT 1000
";
$rows = DB::all($sql, [$from.' 00:00:00', $to.' 23:59:59']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Movimientos | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>.table-tight td,.table-tight th{padding:.45rem .5rem}</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=$app['APP_NAME']?></a>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/dashboard.php">Volver</a>
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/logout.php">Salir</a>
  </div>
</nav>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h5 mb-0">Movimientos</h1>
      <div class="text-muted small">Salidas por ventas</div>
    </div>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-sm-3 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control" value="<?=h($from)?>">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control" value="<?=h($to)?>">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label d-block">&nbsp;</label>
      <button class="btn btn-outline-primary w-100">Aplicar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th style="width:110px">Fecha</th>
          <th style="width:90px">Remito</th>
          <th>Producto</th>
          <th style="width:160px">Lote</th>
          <th style="width:140px" class="text-end">Cantidad</th>
          <th style="width:180px" class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= date('d/m/Y H:i', strtotime((string)$r['fecha'])) ?></td>
            <td class="text-muted">#<?= (int)$r['sale_id'] ?></td>
            <td>
              <?= h($r['producto']) ?>
              <?= $r['presentacion'] ? '<div class="text-muted small">'.h($r['presentacion']).'</div>' : '' ?>
            </td>
            <td><?= $r['lot_id'] ? h($r['nro_lote'] ?: ('#'.$r['lot_id'])) : '—' ?></td>
            <td class="text-end"><?= number_format((float)$r['cantidad'], 3, ',', '.') ?></td>
            <td class="text-end">
              <div class="btn-group">
                <a class="btn btn-sm btn-outline-secondary" target="_blank"
                   href="/app/deposito/public/sale_remito.php?id=<?= (int)$r['sale_id'] ?>">Ver remito</a>
                <a class="btn btn-sm btn-dark" target="_blank"
                   href="/app/deposito/public/sale_remito_pdf.php?id=<?= (int)$r['sale_id'] ?>">Imprimir/Descargar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
