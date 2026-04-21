<?php
// /app/deposito/public/reports.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']);
$app = require __DIR__ . '/../config/app.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Ventas por cliente
$sql1 = "
  SELECT c.id AS client_id, c.razon_social,
         COUNT(s.id) AS remitos,
         SUM(s.subtotal) AS subtotal,
         SUM(s.iva_total) AS iva_total,
         SUM(s.total) AS total
  FROM depo_sales s
  JOIN depo_clients c ON c.id = s.client_id
  WHERE s.fecha BETWEEN ? AND ?
  GROUP BY c.id, c.razon_social
  ORDER BY total DESC
  LIMIT 1000
";
$rep_clientes = DB::all($sql1, [$from.' 00:00:00', $to.' 23:59:59']);

// Ventas por producto
$sql2 = "
  SELECT p.id AS product_id, p.nombre, p.presentacion,
         SUM(i.cantidad) AS qty,
         SUM( (i.cantidad * i.precio_unit) * (1 - COALESCE(i.desc_pct,0)/100) ) AS importe
  FROM depo_sale_items i
  JOIN depo_sales s ON s.id = i.sale_id
  JOIN depo_products p ON p.id = i.product_id
  WHERE s.fecha BETWEEN ? AND ?
  GROUP BY p.id, p.nombre, p.presentacion
  ORDER BY importe DESC
  LIMIT 1000
";
$rep_productos = DB::all($sql2, [$from.' 00:00:00', $to.' 23:59:59']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reportes | <?=h($app['APP_NAME'])?></title>
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
      <h1 class="h5 mb-0">Reportes</h1>
      <div class="text-muted small">Resumenes por cliente y por producto</div>
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

  <div class="row g-4">
    <!-- Ventas por cliente -->
    <div class="col-12">
      <div class="card">
        <div class="card-header py-2"><strong>Ventas por cliente</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle table-tight mb-0">
              <thead class="table-light">
                <tr>
                  <th>Cliente</th>
                  <th style="width:120px" class="text-end">Remitos</th>
                  <th style="width:140px" class="text-end">Subtotal</th>
                  <th style="width:120px" class="text-end">IVA</th>
                  <th style="width:140px" class="text-end">Total</th>
                  <th style="width:150px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rep_clientes as $r): ?>
                  <tr>
                    <td><?= h($r['razon_social']) ?></td>
                    <td class="text-end"><?= (int)$r['remitos'] ?></td>
                    <td class="text-end">$ <?= number_format((float)$r['subtotal'], 2, ',', '.') ?></td>
                    <td class="text-end">$ <?= number_format((float)$r['iva_total'], 2, ',', '.') ?></td>
                    <td class="text-end fw-semibold">$ <?= number_format((float)$r['total'], 2, ',', '.') ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-secondary"
                         href="/app/deposito/public/sales_list.php?client_id=<?= (int)$r['client_id'] ?>&from=<?=h($from)?>&to=<?=h($to)?>">Ver ventas</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!count($rep_clientes)): ?>
                  <tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Ventas por producto -->
    <div class="col-12">
      <div class="card">
        <div class="card-header py-2"><strong>Ventas por producto</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle table-tight mb-0">
              <thead class="table-light">
                <tr>
                  <th>Producto</th>
                  <th style="width:160px" class="text-end">Cantidad</th>
                  <th style="width:160px" class="text-end">Importe</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rep_productos as $r): ?>
                  <tr>
                    <td>
                      <?= h($r['nombre']) ?>
                      <?= $r['presentacion'] ? '<div class="text-muted small">'.h($r['presentacion']).'</div>' : '' ?>
                    </td>
                    <td class="text-end"><?= number_format((float)$r['qty'], 3, ',', '.') ?></td>
                    <td class="text-end fw-semibold">$ <?= number_format((float)$r['importe'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!count($rep_productos)): ?>
                  <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>