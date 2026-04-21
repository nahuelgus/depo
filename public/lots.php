<?php
// /app/deposito/public/lots.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';

$q       = trim((string)($_GET['q'] ?? ''));
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$params  = [];
$where   = [];

if ($q !== '') {
  $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q) . '%';
  $where[] = "(p.nombre LIKE ? OR p.barcode LIKE ? OR l.nro_lote LIKE ?)";
  array_push($params, $like, $like, $like);
}

if ($from !== '') { $where[] = "l.vto >= ?"; $params[] = $from; }
if ($to   !== '') { $where[] = "l.vto <= ?"; $params[] = $to; }

$sql = "
  SELECT l.id AS lot_id, l.product_id, l.nro_lote, l.vto,
         p.nombre AS producto, p.presentacion, p.barcode
  FROM depo_lots l
  JOIN depo_products p ON p.id = l.product_id
";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY l.vto IS NULL, l.vto ASC, l.id DESC LIMIT 500";

$rows = DB::all($sql, $params);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lotes | <?=h($app['APP_NAME'])?></title>
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
      <h1 class="h5 mb-0">Lotes</h1>
      <div class="text-muted small">Stock por lote (vista general)</div>
    </div>
  </div>

  <form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-sm-6 col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" class="form-control" value="<?=h($q)?>" placeholder="Producto / EAN / Nº de lote">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label">Vto desde</label>
      <input type="date" name="from" class="form-control" value="<?=h($from)?>">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label">Vto hasta</label>
      <input type="date" name="to" class="form-control" value="<?=h($to)?>">
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label d-block">&nbsp;</label>
      <button class="btn btn-outline-primary w-100">Aplicar</button>
    </div>
    <div class="col-sm-3 col-md-2">
      <label class="form-label d-block">&nbsp;</label>
      <a class="btn btn-outline-secondary w-100" href="/app/deposito/public/lots.php">Limpiar</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th style="width:90px">Lote ID</th>
          <th>Producto</th>
          <th style="width:200px">Nº de lote</th>
          <th style="width:140px">Vencimiento</th>
          <th style="width:160px">Código barras</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td class="text-muted"><?= (int)$r['lot_id'] ?></td>
            <td>
              <?= h($r['producto']) ?>
              <?= $r['presentacion'] ? '<div class="text-muted small">'.h($r['presentacion']).'</div>' : '' ?>
            </td>
            <td><?= h($r['nro_lote'] ?? '') ?></td>
            <td><?= $r['vto'] ? date('d/m/Y', strtotime((string)$r['vto'])) : '—' ?></td>
            <td><?= h($r['barcode'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($rows)): ?>
          <tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>