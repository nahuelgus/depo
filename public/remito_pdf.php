<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','ventas','supervisor','deposito']);

/* ====== helpers ====== */
function try_require_dompdf(): bool {
  // 1) Composer completo
$paths = [
    __DIR__ . '/../vendor/autoload.php',             // Composer
    __DIR__ . '/../vendor/dompdf/autoload.inc.php',  // dompdf suelto
    __DIR__ . '/../vendor/dompdf/autoload.php',
];

  foreach ($paths as $p) {
    if (is_file($p)) { require_once $p; return true; }
  }
  return false;
}
function html_error($msg) {
  http_response_code(500);
  echo "<!doctype html><meta charset='utf-8'><body style='font-family:system-ui;padding:24px'>
  <h1>Error al generar PDF</h1><p>$msg</p>
  <p>Probá ver el remito en HTML: <a href='remito_pdf.php?id=".intval($_GET['id']??0)."&view=html'>abrir</a></p>
  </body>";
  exit;
}

/* ====== datos ====== */
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { html_error('Falta ID de remito.'); }

$rem = DB::one("SELECT r.id, r.fecha, r.numero, r.destinatario, r.domicilio, d.nombre AS deposito
                FROM depo_remitos r
                JOIN depo_deposits d ON d.id=r.deposit_id
                WHERE r.id=?", [$id]);
if (!$rem) { html_error('Remito inexistente.'); }

$items = DB::all("
  SELECT ri.cantidad, p.nombre AS producto, l.nro_lote
  FROM depo_remito_items ri
  JOIN depo_products p ON p.id=ri.product_id
  LEFT JOIN depo_lots l ON l.id=ri.lot_id
  WHERE ri.remito_id=?", [$id]);

$company = [
  'nombre'    => DB::one("SELECT `value` FROM depo_settings WHERE `key`='company.nombre'")['value'] ?? '',
  'cuit'      => DB::one("SELECT `value` FROM depo_settings WHERE `key`='company.cuit'")['value'] ?? '',
  'domicilio' => DB::one("SELECT `value` FROM depo_settings WHERE `key`='company.domicilio'")['value'] ?? '',
  'ciudad'    => DB::one("SELECT `value` FROM depo_settings WHERE `key`='company.ciudad'")['value'] ?? '',
  'telefono'  => DB::one("SELECT `value` FROM depo_settings WHERE `key`='company.telefono'")['value'] ?? '',
];

/* ====== HTML del remito (sirve para PDF o fallback) ====== */
ob_start();
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<style>
*{font-family: DejaVu Sans, sans-serif;}
body{font-size:12px;}
h1{font-size:18px;margin:0 0 6px 0;}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #000;padding:4px}
.header td{border:none;padding:2px}
.small{font-size:11px} .right{text-align:right}
</style>
</head><body>
<table class="header"><tr>
<td><strong><?=htmlspecialchars($company['nombre'])?></strong><br>
CUIT: <?=htmlspecialchars($company['cuit'])?><br>
<?=htmlspecialchars($company['domicilio'])?> - <?=htmlspecialchars($company['ciudad'])?><br>
Tel: <?=htmlspecialchars($company['telefono'])?></td>
<td class="right"><h1>REMITO</h1>
N° <?=htmlspecialchars($rem['numero'] ?? str_pad($rem['id'],6,'0',STR_PAD_LEFT))?><br>
Fecha: <?=date('d/m/Y H:i', strtotime($rem['fecha']))?><br>
Depósito: <?=htmlspecialchars($rem['deposito'])?></td>
</tr></table>

<table class="header" style="margin-top:6px"><tr>
<td><strong>Destinatario:</strong> <?=htmlspecialchars($rem['destinatario'] ?? '')?></td>
<td><strong>Domicilio:</strong> <?=htmlspecialchars($rem['domicilio'] ?? '')?></td>
</tr></table>

<table style="margin-top:8px">
<thead><tr><th style="width:60%">Producto</th><th style="width:20%">Lote</th><th style="width:20%">Cantidad</th></tr></thead>
<tbody>
<?php if(!$items): ?>
  <tr><td colspan="3">Sin ítems</td></tr>
<?php else: foreach($items as $it): ?>
  <tr>
    <td><?=htmlspecialchars($it['producto'])?></td>
    <td><?=htmlspecialchars($it['nro_lote'] ?? 's/lote')?></td>
    <td class="right"><?=number_format($it['cantidad'],3,',','.')?></td>
  </tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<table class="header" style="margin-top:20px"><tr>
<td style="height:70px;vertical-align:bottom">Firma: _____________________________</td>
<td style="height:70px;vertical-align:bottom">Aclaración: ________________________</td>
</tr></table>

<p class="small" style="margin-top:10px">Sistema de Remitos Impulsado por edesign.ar</p>
</body></html>
<?php
$html = ob_get_clean();

/* ====== Si pidieron solo ver HTML (debug/fallback) ====== */
if (isset($_GET['view']) && $_GET['view']==='html') {
  header('Content-Type: text/html; charset=utf-8');
  echo $html; exit;
}

/* ====== Generar PDF con Dompdf ====== */
if (!try_require_dompdf()) {
  html_error('No pude cargar Dompdf. Subí <code>/app/deposito/vendor/</code> con <code>autoload.php</code> (Composer) o la carpeta <code>dompdf/</code>.');
}

if (!class_exists(\Dompdf\Dompdf::class)) {
  html_error('Dompdf no está disponible aunque se incluyó el autoloader.');
}

$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>true]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('remito_'.$id.'.pdf', ['Attachment'=>false]);