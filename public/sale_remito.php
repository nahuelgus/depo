<?php
// /app/deposito/public/sale_remito.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app     = require __DIR__ . '/../config/app.php';
$company = require __DIR__ . '/../config/company.php';

$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) { http_response_code(400); echo "ID inválido"; exit; }

$sale = DB::all("
  SELECT s.id, s.deposit_id, s.fecha, s.status,
         s.subtotal, s.iva_total, s.total,
         s.observacion, s.client_id,
         d.nombre AS deposito_nombre
  FROM depo_sales s
  JOIN depo_deposits d ON d.id = s.deposit_id
  WHERE s.id = ?
  LIMIT 1
", [$sale_id])[0] ?? null;

if (!$sale) { http_response_code(404); echo "Venta no encontrada"; exit; }

$cliente = null;
if (!empty($sale['client_id'])) {
  $cliente = DB::all("
    SELECT id, razon_social, doc, domicilio, ciudad, tel, email, referente, nota
    FROM depo_clients
    WHERE id = ?
    LIMIT 1
  ", [(int)$sale['client_id']])[0] ?? null;
}

$items = DB::all("
  SELECT i.product_id, i.lot_id, i.cantidad, i.precio_unit, i.desc_pct,
         p.nombre AS producto, p.presentacion, p.barcode,
         l.nro_lote
  FROM depo_sale_items i
  JOIN depo_products p ON p.id = i.product_id
  LEFT JOIN depo_lots l ON l.id = i.lot_id
  WHERE i.sale_id = ?
  ORDER BY i.id ASC
", [$sale_id]);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Remito #<?=h($sale_id)?> | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  @media print { .no-print{display:none!important} body{background:#fff} }
  .small-muted{font-size:.85rem;color:#6c757d}
  .table-tight td,.table-tight th{padding:.35rem .5rem}
  .hr-thin{margin:.5rem 0;opacity:.35}
  footer{font-size:.7rem;text-align:center;color:#6c757d;margin-top:2rem;}
</style>
</head>
<body class="p-3 p-md-4">
<div class="container">
<div class="d-flex justify-content-between align-items-center no-print mb-3">
  <a href="/app/deposito/public/dashboard.php" class="btn btn-outline-secondary btn-sm">Volver</a>
  <a class="btn btn-dark btn-sm" target="_blank"
     href="/app/deposito/public/sale_remito_pdf.php?id=<?= (int)$sale['id'] ?>">Imprimir/Descargar</a>
</div>



  <div class="row">
    <div class="col">
      <h1 class="h5 mb-0">Remito / Venta #<?=h($sale['id'])?></h1>
      <div class="small-muted">Estado: <?=h($sale['status'])?></div>
      <div class="small-muted"><?=date('d/m/Y', strtotime((string)$sale['fecha']))?></div>
    </div>
    <div class="col text-end">
      <div class="fw-semibold"><?=h($company['nombre'])?></div>
      <div class="small-muted"><?=h($company['direccion'])?> · <?=h($company['ciudad'])?></div>
      <div class="small-muted">Cel: <?=h($company['cel'])?></div>
      <div class="small-muted">Email: <?=h($company['email'])?></div>
    </div>
  </div>

  <hr class="hr-thin">

  <div class="row">
    <div class="col-md-7">
      <div class="fw-semibold mb-1">Cliente</div>
      <?php if ($cliente): ?>
        <div><?=h($cliente['razon_social'])?></div>
        <div class="small-muted">Doc: <?=h($cliente['doc'] ?: '-')?></div>
        <div class="small-muted">Tel: <?=h($cliente['tel'] ?: '-')?> · Email: <?=h($cliente['email'] ?: '-')?></div>
        <div class="small-muted">Domicilio: <?=h($cliente['domicilio'] ?: '-')?> · <?=h($cliente['ciudad'] ?: '-')?></div>
        <?php if (!empty($cliente['referente'])): ?>
          <div class="small-muted">Referente: <?=h($cliente['referente'])?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="small-muted">Sin cliente asociado</div>
      <?php endif; ?>
    </div>
    <div class="col-md-5">
      <div class="fw-semibold mb-1">Observación</div>
      <div><?=nl2br(h($sale['observacion'] ?? ''))?></div>
    </div>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th>Producto</th>
          <th style="width:160px">Lote</th>
          <th style="width:90px" class="text-end">Cant.</th>
          <th style="width:110px" class="text-end">P. Unit.</th>
          <th style="width:80px" class="text-end">% Desc.</th>
          <th style="width:120px" class="text-end">Importe</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it):
          $base = (float)$it['cantidad'] * (float)$it['precio_unit'];
          if ((float)$it['desc_pct'] > 0) $base *= (1 - ((float)$it['desc_pct'] / 100));
          $base = round($base, 2);
        ?>
        <tr>
          <td>
            <?=h($it['producto'])?>
            <?= $it['presentacion'] ? '<span class="text-muted small"> '.h($it['presentacion']).'</span>' : '' ?>
            <?= $it['barcode'] ? '<div class="small-muted">'.h($it['barcode']).'</div>' : '' ?>
          </td>
          <td><?= $it['lot_id'] ? h($it['nro_lote'] ?: ('#'.$it['lot_id'])) : '<span class="small-muted">(sin lote)</span>' ?></td>
          <td class="text-end"><?=number_format((float)$it['cantidad'], 3, ',', '.')?></td>
          <td class="text-end">$ <?=number_format((float)$it['precio_unit'], 2, ',', '.')?></td>
          <td class="text-end"><?=number_format((float)$it['desc_pct'], 2, ',', '.')?></td>
          <td class="text-end">$ <?=number_format($base, 2, ',', '.')?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4"></td>
          <td class="text-end fw-semibold">Subtotal</td>
          <td class="text-end">$ <?=number_format((float)$sale['subtotal'], 2, ',', '.')?></td>
        </tr>
        <tr>
          <td colspan="4"></td>
          <td class="text-end fw-semibold">IVA</td>
          <td class="text-end">$ <?=number_format((float)$sale['iva_total'], 2, ',', '.')?></td>
        </tr>
        <tr>
          <td colspan="4"></td>
          <td class="text-end fw-semibold">TOTAL</td>
          <td class="text-end">$ <?=number_format((float)$sale['total'], 2, ',', '.')?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="row mt-4">
    <div class="col-6">
      <div class="small-muted">Aclaración:</div>
      <div style="border-top:1px solid #aaa;height:48px"></div>
    </div>
    <div class="col-6 text-end">
      <div class="small-muted">Firma:</div>
      <div style="border-top:1px solid #aaa;height:48px"></div>
    </div>
  </div>

  <footer>
    Sistema impulsado por <a href="https://edesign.ar" target="_blank">edesign.ar</a>
  </footer>
</div>
</body>
</html>