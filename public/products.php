<?php
// /app/deposito/public/products.php
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito','ventas']);

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  // Guardar alta/edición
  $data = [
    'barcode'   => trim($_POST['barcode'] ?? ''),
    'nombre'    => trim($_POST['nombre'] ?? ''),
    'marca_id'  => $_POST['marca_id'] ? (int)$_POST['marca_id'] : null,
    'categoria_id'=> $_POST['categoria_id'] ? (int)$_POST['categoria_id'] : null,
    'unidad_id' => $_POST['unidad_id'] ? (int)$_POST['unidad_id'] : null,
    'presentacion'=> trim($_POST['presentacion'] ?? ''),
    'costo'     => (float)($_POST['costo'] ?? 0),
    'precio'    => (float)($_POST['precio'] ?? 0),
    'iva_pct'   => ($_POST['iva_pct'] === '' ? null : (float)$_POST['iva_pct']),
    'stock_min' => (float)($_POST['stock_min'] ?? 0),
    'requiere_lote'=> isset($_POST['requiere_lote']) ? 1 : 0,
    'requiere_vto' => isset($_POST['requiere_vto']) ? 1 : 0,
    'alerta_vto_dias' => (int)($_POST['alerta_vto_dias'] ?? 30),
    'is_activo' => isset($_POST['is_activo']) ? 1 : 0,
  ];
  if ($id>0) {
    DB::exec("UPDATE depo_products SET barcode=?, nombre=?, marca_id=?, categoria_id=?, unidad_id=?, presentacion=?, costo=?, precio=?, iva_pct=?, stock_min=?, requiere_lote=?, requiere_vto=?, alerta_vto_dias=?, is_activo=? WHERE id=?",
      [$data['barcode'] ?: null, $data['nombre'], $data['marca_id'], $data['categoria_id'], $data['unidad_id'],
       $data['presentacion'] ?: null, $data['costo'], $data['precio'], $data['iva_pct'], $data['stock_min'],
       $data['requiere_lote'], $data['requiere_vto'], $data['alerta_vto_dias'], $data['is_activo'], $id]);
  } else {
    DB::exec("INSERT INTO depo_products (barcode,nombre,marca_id,categoria_id,unidad_id,presentacion,costo,precio,iva_pct,stock_min,requiere_lote,requiere_vto,alerta_vto_dias,is_activo)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
      [$data['barcode'] ?: null, $data['nombre'], $data['marca_id'], $data['categoria_id'], $data['unidad_id'],
       $data['presentacion'] ?: null, $data['costo'], $data['precio'], $data['iva_pct'], $data['stock_min'],
       $data['requiere_lote'], $data['requiere_vto'], $data['alerta_vto_dias'], $data['is_activo']]);
    $id = (int)DB::lastId();
  }
  header('Location: /app/deposito/public/products.php?saved=1');
  exit;
}

if ($action==='toggle' && $id>0) {
  DB::exec("UPDATE depo_products SET is_activo = 1 - is_activo WHERE id=?", [$id]);
  header('Location: /app/deposito/public/products.php');
  exit;
}

// datos auxiliares
$marcas = DB::all("SELECT id,nombre FROM depo_brands ORDER BY nombre");
$cats   = DB::all("SELECT id,nombre FROM depo_categories ORDER BY nombre");
$units  = DB::all("SELECT id,nombre FROM depo_units ORDER BY nombre");

// edición
$edit = ['id'=>0,'barcode'=>'','nombre'=>'','marca_id'=>null,'categoria_id'=>null,'unidad_id'=>null,'presentacion'=>'','costo'=>0,'precio'=>0,'iva_pct'=>null,'stock_min'=>0,'requiere_lote'=>1,'requiere_vto'=>1,'alerta_vto_dias'=>30,'is_activo'=>1];
if ($action==='edit' && $id>0) {
  $row = DB::one("SELECT * FROM depo_products WHERE id=?", [$id]);
  if ($row) $edit = $row;
}

// listado (búsqueda simple)
$q = trim($_GET['q'] ?? '');
$rows = DB::all("
  SELECT p.*
  FROM depo_products p
  WHERE (? = '' OR p.nombre LIKE CONCAT('%',?,'%') OR p.barcode LIKE CONCAT('%',?,'%'))
  ORDER BY p.is_activo DESC, p.nombre ASC
  LIMIT 200
", [$q,$q,$q]);

$app = require __DIR__ . '/../config/app.php';
$u = current_user();
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Productos | <?=$app['APP_NAME']?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
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
  <div class="row">
    <div class="col-lg-7">
      <form class="d-flex mb-3" method="get">
        <input class="form-control me-2" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Buscar por nombre o código de barras">
        <button class="btn btn-dark">Buscar</button>
      </form>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr>
            <th>Activo</th><th>Nombre</th><th>EAN</th><th>Precio</th><th>IVA</th><th>Min</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr class="<?=($r['is_activo']?'':'table-secondary')?>">
              <td>
                <a class="btn btn-sm <?=($r['is_activo']?'btn-success':'btn-outline-secondary')?>" href="?action=toggle&id=<?=$r['id']?>">
                  <?=($r['is_activo']?'Sí':'No')?>
                </a>
              </td>
              <td><?=htmlspecialchars($r['nombre'])?></td>
              <td><?=htmlspecialchars($r['barcode']??'')?></td>
              <td><?=number_format($r['precio'],2,',','.')?></td>
              <td><?=($r['iva_pct']===null?'—':$r['iva_pct'].'%')?></td>
              <td><?=number_format($r['stock_min'],3,',','.')?></td>
              <td><a class="btn btn-sm btn-outline-dark" href="?action=edit&id=<?=$r['id']?>">Editar</a></td>
            </tr>
          <?php endforeach; if(!$rows): ?>
            <tr><td colspan="7" class="text-muted">Sin productos</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><?=($edit['id']?'Editar producto':'Nuevo producto')?></div>
        <form class="card-body" method="post">
          <div class="mb-2"><label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required value="<?=htmlspecialchars($edit['nombre'])?>">
          </div>
          <div class="mb-2"><label class="form-label">Código de barras</label>
            <input class="form-control" name="barcode" value="<?=htmlspecialchars($edit['barcode']??'')?>" placeholder="Soporta lectora (enter)">
          </div>
            <div class="col"><label class="form-label">Categoría</label>
              <select class="form-select" name="categoria_id">
                <option value="">—</option>
                <?php foreach($cats as $c): ?>
                  <option value="<?=$c['id']?>" <?=$edit['categoria_id']==$c['id']?'selected':''?>><?=htmlspecialchars($c['nombre'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col"><label class="form-label">Unidad</label>
              <select class="form-select" name="unidad_id">
                <option value="">—</option>
                <?php foreach($units as $un): ?>
                  <option value="<?=$un['id']?>" <?=$edit['unidad_id']==$un['id']?'selected':''?>><?=htmlspecialchars($un['nombre'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-6"><label class="form-label">Presentación</label><input class="form-control" name="presentacion" value="<?=htmlspecialchars($edit['presentacion']??'')?>"></div>
            <div class="col-3"><label class="form-label">Costo</label><input class="form-control" type="number" step="0.0001" name="costo" value="<?=$edit['costo']?>"></div>
            <div class="col-3"><label class="form-label">Precio</label><input class="form-control" type="number" step="0.0001" name="precio" value="<?=$edit['precio']?>"></div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-3"><label class="form-label">IVA %</label><input class="form-control" type="number" step="0.01" name="iva_pct" value="<?=($edit['iva_pct']===null?'':$edit['iva_pct'])?>" placeholder="vacío = sin IVA"></div>
            <div class="col-3"><label class="form-label">Stock mín.</label><input class="form-control" type="number" step="0.001" name="stock_min" value="<?=$edit['stock_min']?>"></div>
            <div class="col-3"><label class="form-label">Alerta Vto</label>
              <select class="form-select" name="alerta_vto_dias">
                <?php foreach([30,60,90] as $d): ?>
                  <option value="<?=$d?>" <?=$edit['alerta_vto_dias']==$d?'selected':''?>><?=$d?> días</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-3 d-flex align-items-end">
              <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="requiere_lote" <?=$edit['requiere_lote']?'checked':''?>>
                <label class="form-check-label">Lote</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requiere_vto" <?=$edit['requiere_vto']?'checked':''?>>
                <label class="form-check-label">Vto</label>
              </div>
            </div>
          </div>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="is_activo" <?=$edit['is_activo']?'checked':''?>>
            <label class="form-check-label">Activo</label>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-dark">Guardar</button>
            <a class="btn btn-outline-secondary" href="/app/deposito/public/products.php">Cancelar</a>
          </div>
        </form>
      </div>
     
    </div>
  </div>
</div>
</body></html>