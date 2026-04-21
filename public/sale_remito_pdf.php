<?php
// /app/deposito/public/sale_remito_pdf.php
declare(strict_types=1);

// ================== Bootstrap de la app ==================
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app     = require __DIR__ . '/../config/app.php';
$company = require __DIR__ . '/../config/company.php';

// ================== Cargar Dompdf (busca vendor en varias rutas) ==================
$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php',   // /app/deposito/public/../../vendor
    __DIR__ . '/../vendor/autoload.php',      // /app/deposito/public/../vendor
    __DIR__ . '/../../../vendor/autoload.php' // /app/deposito/public/../../../vendor
];
$autoload = null;
foreach ($autoloadCandidates as $cand) {
    if (file_exists($cand)) { $autoload = $cand; break; }
}
if (!$autoload) {
    http_response_code(500);
    echo "<pre>No se encontró vendor/autoload.php.\nProbé:\n- " . implode("\n- ", $autoloadCandidates) . "\n\nVerificá dónde está la carpeta vendor/ en el servidor.</pre>";
    exit;
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ================== Inputs ==================
$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) {
    http_response_code(400);
    echo "ID inválido (usá ?id=13, por ejemplo).";
    exit;
}

// ================== Datos de la venta ==================
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

// Cliente (si corresponde)
$cliente = null;
if (!empty($sale['client_id'])) {
  $cliente = DB::all("
    SELECT razon_social, doc, domicilio, ciudad, tel, email, referente, nota
    FROM depo_clients
    WHERE id = ?
    LIMIT 1
  ", [(int)$sale['client_id']])[0] ?? null;
}

// Ítems
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

// ================== HTML para el PDF ==================
ob_start();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
  h1 { font-size: 16px; margin: 0 0 5px 0; }
  .small { font-size: 10px; color: #666; }
  .muted { color: #666; }
  .w-100 { width: 100%; }
  .pt-5 { padding-top: 5px; }
  .mt-5 { margin-top: 5px; }
  .mt-10{ margin-top: 10px; }
  .mt-20{ margin-top: 20px; }
  .text-right { text-align: right; }
  .text-center{ text-align: center; }
  .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .table th, .table td { border:1px solid #ccc; padding:4px; vertical-align: top; }
  .table th { background:#f2f2f2; }
  .tfoot td { border-top: 1px solid #999; }
  .mini { font-size: 9px; }
</style>
</head>
<body>

<table class="w-100">
  <tr>
    <td>
      <h1>Remito / Venta #<?=h($sale['id'])?></h1>
      <div class="small">Estado: <?=h($sale['status'])?></div>
      <div class="small">Fecha: <?=date('d/m/Y', strtotime((string)$sale['fecha']))?></div>
    </td>
    <td class="text-right">
      <strong><?=h($company['nombre'])?></strong><br>
      <?=h($company['direccion'])?> · <?=h($company['ciudad'])?><br>
      Cel: <?=h($company['cel'])?> · Email: <?=h($company['email'])?>
    </td>
  </tr>
</table>

<hr>

<!-- Cliente en 2 columnas -->
<table class="w-100 mt-5" style="border-collapse:separate; border-spacing: 0 4px;">
  <tr>
    <td width="50%" valign="top">
      <strong>Cliente</strong><br>
      <?php if ($cliente): ?>
        <?=h($cliente['razon_social'])?><br>
        Doc: <?=h($cliente['doc'] ?: '-')?><br>
        Tel: <?=h($cliente['tel'] ?: '-')?><br>
        Email: <?=h($cliente['email'] ?: '-')?>
      <?php else: ?>
        <span class="small muted">Sin cliente asociado</span>
      <?php endif; ?>
    </td>
    <td width="50%" valign="top">
      <?php if ($cliente): ?>
        Domicilio: <?=h($cliente['domicilio'] ?: '-')?><br>
        Ciudad: <?=h($cliente['ciudad'] ?: '-')?><br>
        <?php if (!empty($cliente['referente'])): ?>
          Referente: <?=h($cliente['referente'])?><br>
        <?php endif; ?>
      <?php endif; ?>
    </td>
  </tr>
</table>

<!-- Observación (fila completa) -->
<div class="mt-10">
  <strong>Observación</strong><br>
  <?=nl2br(h($sale['observacion'] ?? ''))?>
</div>

<!-- Tabla de items full width y responsive (en PDF ocupa ancho completo) -->
<table class="table">
  <thead>
    <tr>
      <th>Producto</th>
      <th>Lote</th>
      <th class="text-right">Cant.</th>
      <th class="text-right">P. Unit.</th>
      <th class="text-right">% Desc.</th>
      <th class="text-right">Importe</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $it):
    $base = (float)$it['cantidad'] * (float)$it['precio_unit'];
    if ((float)$it['desc_pct'] > 0) $base *= (1 - ((float)$it['desc_pct']/100));
    $base = round($base, 2);
  ?>
    <tr>
      <td>
        <?=h($it['producto'])?>
        <?php if ($it['presentacion']): ?>
          <div class="small muted"><?=h($it['presentacion'])?></div>
        <?php endif; ?>
        <?php if ($it['barcode']): ?>
          <div class="small muted"><?=h($it['barcode'])?></div>
        <?php endif; ?>
      </td>
      <td><?= $it['lot_id'] ? h($it['nro_lote'] ?: ('#'.$it['lot_id'])) : '(sin lote)' ?></td>
      <td class="text-right"><?=number_format((float)$it['cantidad'], 3, ',', '.')?></td>
      <td class="text-right">$ <?=number_format((float)$it['precio_unit'], 2, ',', '.')?></td>
      <td class="text-right"><?=number_format((float)$it['desc_pct'], 2, ',', '.')?></td>
      <td class="text-right">$ <?=number_format($base, 2, ',', '.')?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot class="tfoot">
    <tr>
      <td colspan="5" class="text-right"><strong>Subtotal</strong></td>
      <td class="text-right">$ <?=number_format((float)$sale['subtotal'], 2, ',', '.')?></td>
    </tr>
    <tr>
      <td colspan="5" class="text-right"><strong>IVA</strong></td>
      <td class="text-right">$ <?=number_format((float)$sale['iva_total'], 2, ',', '.')?></td>
    </tr>
    <tr>
      <td colspan="5" class="text-right"><strong>TOTAL</strong></td>
      <td class="text-right"><strong>$ <?=number_format((float)$sale['total'], 2, ',', '.')?></strong></td>
    </tr>
  </tfoot>
</table>

<!-- Recibí en conformidad (alineado a la derecha) -->
<div class="mt-20">
  <strong>Recibí en conformidad</strong>
  <table class="w-100 mt-10">
    <tr>
      <td width="70%"></td>
      <td width="30%">
        <div>Fecha de recepción: ____/____/____</div>
        <div class="mt-10">Aclaración: __________________________</div>
        <div class="mt-10">Firma: _______________________________</div>
      </td>
    </tr>
  </table>
</div>

<div class="text-center mini" style="margin-top:30px;">
  Sistema impulsado por <a href="https://edesign.ar">edesign.ar</a>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ================== Render PDF ==================
$options = new Options();
$options->set('isRemoteEnabled', false); // no cargamos recursos externos
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Limpia cualquier salida previa para que no rompa headers
while (ob_get_level() > 0) { ob_end_clean(); }

$dompdf->stream("remito-{$sale_id}.pdf", ["Attachment" => false]);