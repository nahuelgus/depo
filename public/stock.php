<?php
// /app/deposito/public/stock.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';
if (!function_exists('eh')){ function eh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

// Filtros
$depActivo = (int)($_SESSION['deposit_id'] ?? 0);
try { $deps = DB::all("SELECT id,nombre FROM depo_deposits WHERE is_activo=1 ORDER BY nombre ASC"); } catch(Throwable $e){ $deps=[]; }
try {
  $hasTbl = DB::all("SHOW TABLES LIKE 'depo_categories'");
  $cats = $hasTbl ? DB::all("SELECT id,nombre FROM depo_categories ORDER BY nombre ASC") : [];
} catch(Throwable $e){ $cats=[]; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stock | <?=eh($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>.table-tight td,.table-tight th{padding:.45rem .5rem}.smallmuted{font-size:.85rem;color:#6c757d}.badge-soft{background:#f6f7f9;border:1px solid #eef0f2;color:#6c757d}</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=eh($app['APP_NAME'])?></a>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h5 mb-0">Stock de productos</h1>
      <div class="text-muted small">Totales por producto, filtrables por depósito y categoría</div>
    </div>
    <span class="badge rounded-pill badge-soft">consulta</span>
  </div>

  <div class="row g-2 align-items-end mb-3">
    <div class="col-sm-4">
      <label class="form-label">Buscar</label>
      <input id="q" class="form-control" placeholder="Nombre o código" oninput="deb(load,180)()">
    </div>
    <div class="col-sm-3">
      <label class="form-label">Categoría</label>
      <select id="cat" class="form-select" onchange="load()">
        <option value="">(Todas)</option>
        <?php foreach($cats as $c): ?><option value="<?=$c['id']?>"><?=eh($c['nombre'])?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-3">
      <label class="form-label">Depósito</label>
      <select id="dep" class="form-select" onchange="load()">
        <option value="">(Todos)</option>
        <?php foreach($deps as $d): ?>
          <option value="<?=$d['id']?>" <?=$depActivo===(int)$d['id']?'selected':''?>><?=eh($d['nombre'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-2">
      <label class="form-label">Solo con stock</label>
      <select id="onlypos" class="form-select" onchange="load()">
        <option value="1" selected>Sí</option>
        <option value="0">No</option>
      </select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th style="width:70px">ID</th>
          <th>Producto</th>
          <th style="width:220px">Categoría</th>
          <th style="width:160px" class="text-end">Stock</th>
          <th style="width:260px">Detalle</th>
        </tr>
      </thead>
      <tbody id="tb"></tbody>
    </table>
  </div>
</div>

<script>
const API='/app/deposito/api/stock_list_api.php';
const deb=(fn,ms)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)}}
function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]||c));}

async function load(){
  const url=new URL(API, location.origin);
  const q=document.getElementById('q').value.trim();
  const cat=document.getElementById('cat').value;
  const dep=document.getElementById('dep').value;
  const only=document.getElementById('onlypos').value || '1';
  if(q)   url.searchParams.set('q', q);
  if(cat) url.searchParams.set('category_id', cat);
  if(dep) url.searchParams.set('deposit_id', dep);
  url.searchParams.set('only_positive', only);

  const tb=document.getElementById('tb');
  tb.innerHTML='<tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>';

  try{
    const js=await (await fetch(url)).json();
    if(!js.ok){ tb.innerHTML='<tr><td colspan="5" class="text-center text-danger">Error al cargar</td></tr>'; return; }
    if(!js.items.length){ tb.innerHTML='<tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>'; return; }

    tb.innerHTML = js.items.map(r=>{
      const det=(r.detalle||[]).map(d=>`${esc(d.deposito)}: ${Number(d.cantidad).toLocaleString('es-AR')}`).join(' · ');
      return `<tr>
        <td class="text-muted">#${r.product_id}</td>
        <td>${esc(r.producto)}</td>
        <td>${esc(r.categoria||'—')}</td>
        <td class="text-end fw-semibold">${Number(r.stock).toLocaleString('es-AR')}</td>
        <td class="smallmuted">${det||'—'}</td>
      </tr>`;
    }).join('');
  }catch(e){
    tb.innerHTML='<tr><td colspan="5" class="text-center text-danger">Error de red</td></tr>';
  }
}
document.addEventListener('DOMContentLoaded', load);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>