<?php
// /app/deposito/public/transfer_new.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
require_once __DIR__ . '/../lib/stock.php';

$depos = DB::all("SELECT id, nombre FROM depo_deposits WHERE is_activo=1 ORDER BY id ASC");
$prods = DB::all("SELECT id, nombre, barcode, requiere_lote, requiere_vto FROM depo_products WHERE is_activo=1 ORDER BY nombre ASC");
$app   = require __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Transferencia | <?=$app['APP_NAME']?></title>
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
  <h1 class="h5 mb-1">Nueva transferencia</h1>
  <div class="text-muted small mb-3">Mové stock de un depósito a otro. Si el producto requiere lote/vto, completalos en la grilla.</div>

  <form method="post" onsubmit="return enviarTransfer()">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" class="form-control" value="<?=date('Y-m-d')?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Documento (opcional)</label>
        <input type="text" name="documento" class="form-control" placeholder="Interno / Remito interno">
      </div>
      <div class="col-md-3">
        <label class="form-label">Depósito origen</label>
        <select name="deposito_origen" class="form-select" required>
          <?php foreach($depos as $d): ?>
            <option value="<?=$d['id']?>"><?=$d['nombre']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Depósito destino</label>
        <select name="deposito_destino" class="form-select" required>
          <?php foreach($depos as $d): ?>
            <option value="<?=$d['id']?>"><?=$d['nombre']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Observación</label>
        <input type="text" name="observacion" class="form-control" placeholder="Motivo / referencia">
      </div>
    </div>

    <hr class="my-3">

    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Producto (scanner EAN o buscar)</label>
        <input id="scan" class="form-control" placeholder="Escaneá el EAN y Enter, o escribí para buscar">
        <div class="form-text">Enter agrega. Si requiere lote/vto, completalos en la grilla.</div>
      </div>
      <div class="col-md-5">
        <label class="form-label">&nbsp;</label>
        <select id="selProd" class="form-select">
          <option value="">— Buscar producto —</option>
          <?php foreach($prods as $p): ?>
            <option value="<?=$p['id']?>"
                    data-rl="<?=$p['requiere_lote']?'1':'0'?>"
                    data-rv="<?=$p['requiere_vto']?'1':'0'?>">
              <?=htmlspecialchars($p['nombre'])?> [<?=$p['id']?>]
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="button" id="btnAdd" class="btn btn-dark w-100">Agregar</button>
      </div>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle" id="tb">
        <thead>
          <tr>
            <th style="width:30%">Producto</th>
            <th style="width:14%">Lote</th>
            <th style="width:14%">Vto</th>
            <th style="width:12%">Cant</th>
            <th style="width:12%">Obs</th>
            <th style="width:8%"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button type="submit" class="btn btn-dark">Confirmar transferencia</button>
      <a href="/app/deposito/public/dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>

    <input type="hidden" name="items_json" id="items_json">
  </form>
</div>

<script>
const prods = <?=json_encode($prods, JSON_UNESCAPED_UNICODE)?>;

document.getElementById('scan').addEventListener('keydown', e=>{
  if(e.key==='Enter'){
    e.preventDefault();
    const code = e.target.value.trim();
    if(!code) return;
    const p = prods.find(x=>String(x.barcode||'')===code);
    if(p){ pushRow(p); e.target.value=''; }
    else { alert('No se encontró producto con ese código'); }
  }
});
document.getElementById('btnAdd').addEventListener('click', ()=>{
  const sel = document.getElementById('selProd');
  const id  = parseInt(sel.value||'0');
  if(!id) return;
  const p = prods.find(x=>x.id==id);
  if(p) pushRow(p);
});

function pushRow(p){
  const tb = document.querySelector('#tb tbody');
  const tr = document.createElement('tr');
  const reqL = p.requiere_lote ? 'obligatorio' : '(opcional)';
  tr.innerHTML = `
    <td data-id="${p.id}">${escapeHtml(p.nombre)} <span class="text-muted small">${p.barcode||''}</span></td>
    <td><input class="form-control form-control-sm" placeholder="${reqL}"></td>
    <td><input type="date" class="form-control form-control-sm" ${p.requiere_vto?'required':''}></td>
    <td><input type="number" step="0.001" class="form-control form-control-sm" value="1" required></td>
    <td><input type="text" class="form-control form-control-sm" placeholder="Obs ítem (opcional)"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Quitar</button></td>
  `;
  tb.appendChild(tr);
}

function enviarTransfer(){
  const rows = Array.from(document.querySelectorAll('#tb tbody tr'));
  if(rows.length===0){ alert('Agregá al menos un producto'); return false; }

  const items = rows.map(tr=>{
    const tds = tr.querySelectorAll('td');
    return {
      product_id: parseInt(tds[0].dataset.id),
      nro_lote: tds[1].querySelector('input').value.trim(),
      vto: tds[2].querySelector('input').value || null,
      cantidad: parseFloat(tds[3].querySelector('input').value || '0'),
      obs: tds[4].querySelector('input').value.trim()
    };
  });
  // validación básica
  if(items.some(i => !i.cantidad || i.cantidad<=0)){
    alert('La cantidad debe ser mayor a 0.');
    return false;
  }
  document.getElementById('items_json').value = JSON.stringify(items);
  return true;
}

function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>