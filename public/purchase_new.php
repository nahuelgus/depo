<?php
// /app/deposito/public/purchase_new.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
require_once __DIR__ . '/../lib/stock.php';

$depos = DB::all("SELECT id, nombre FROM depo_deposits WHERE is_activo=1 ORDER BY id ASC");
$app   = require __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recepción | <?=$app['APP_NAME']?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">

<style>
  #acList .active{ background:#0d6efd; color:#fff; }
  .toast-container{ z-index: 1080; }
  .toast .toast-body code{ padding:.15rem .35rem; background:#f1f3f5; border-radius:.25rem; }
</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=$app['APP_NAME']?></a>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/dashboard.php">Volver</a>
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/logout.php">Salir</a>
  </div>
</nav>

<!-- Toasts -->
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="toastOk" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
  <div id="toastErr" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<div class="container py-4">
  <h1 class="h5 mb-1">Recepción de mercadería</h1>
  <div class="text-muted small mb-3">Completá <strong>Cantidad</strong> y el <strong>Total</strong> de cada línea; calculamos el <strong>Costo (unidad)</strong> automáticamente.</div>

  <!-- action apunta al API pero el submit lo maneja fetch -->
  <form id="frmCompra" method="post" action="/app/deposito/api/purchases_api.php?action=create" onsubmit="return enviarCompra(event)">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Proveedor</label>
        <input name="proveedor" id="proveedor" class="form-control" autocomplete="off">
      </div>
      <div class="col-md-3">
        <label class="form-label">Documento</label>
        <input name="documento" id="documento" class="form-control" placeholder="Factura/Remito" autocomplete="off">
      </div>
      <div class="col-md-2">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" id="fecha" class="form-control" value="<?=date('Y-m-d')?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Depósito destino</label>
        <select name="deposit_id" id="deposit_id" class="form-select">
          <?php foreach($depos as $d): ?>
            <option value="<?=$d['id']?>"><?=$d['nombre']?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <hr>

    <!-- Buscador con autocompletado -->
    <div class="row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label">Producto (scanner EAN o buscar)</label>
        <input id="scan" class="form-control" placeholder="Escaneá el EAN y Enter, o escribí para buscar" autocomplete="off">
        <div class="form-text">Enter agrega. Si el producto requiere lote/vto, completalos en la grilla.</div>
        <div id="acWrap" class="position-relative">
          <div id="acList" class="list-group shadow-sm d-none"
               style="position:absolute; z-index:20; width:100%; max-height:260px; overflow:auto;"></div>
        </div>
      </div>
      <div class="col-md-5"></div>
    </div>

    <!-- Grilla -->
    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle" id="tb">
        <thead>
          <tr>
            <th style="width:28%">Producto</th>
            <th style="width:14%">Lote</th>
            <th style="width:12%">Vto</th>
            <th style="width:10%">Cant</th>
            <th style="width:14%">Costo <span class="text-muted small">(unidad)</span></th>
            <th style="width:12%">Total</th>
            <th style="width:8%"></th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="text-end">Importe total del documento</td>
            <td><input type="text" class="form-control form-control-sm" id="docTotal" value="0.00" readonly></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button id="btnConfirmar" type="submit" class="btn btn-dark">
        <span class="btn-text">Confirmar recepción</span>
        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
      </button>
      <a href="/app/deposito/public/dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>

    <input type="hidden" name="items_json" id="items_json">
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =================== Autocompletado AJAX ===================
const API_SEARCH = '/app/deposito/api/products_search.php';
const scan   = document.getElementById('scan');
const acList = document.getElementById('acList');

let typeTimer = null;
let currentIndex = -1;
let currentItems = [];
const cache = new Map();

document.addEventListener('DOMContentLoaded', ()=>{ scan.focus(); });

function renderList(items){
  currentItems = items;
  currentIndex = -1;
  acList.innerHTML = '';
  if (!items.length){ acList.classList.add('d-none'); return; }
  items.forEach((p, i)=>{
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
    btn.dataset.index = i;
    btn.innerHTML = '<span>'+escapeHtml(p.nombre)+' <span class="text-muted">['+p.id+']</span></span>' +
                    '<small class="text-muted">'+(p.barcode||'')+'</small>';
    btn.addEventListener('click', ()=> addByIndex(i));
    acList.appendChild(btn);
  });
  acList.classList.remove('d-none');
}

async function fetchProducts(q){
  const key = q.trim().toLowerCase();
  if (cache.has(key)) return cache.get(key);
  const url = new URL(API_SEARCH, location.origin);
  url.searchParams.set('q', q);
  url.searchParams.set('limit', '20');
  const res = await fetch(url, {credentials:'same-origin'});
  const data = await res.json().catch(()=>({ok:false,items:[]})); 
  const items = data.ok ? data.items : [];
  cache.set(key, items);
  return items;
}

scan.addEventListener('input', ()=>{
  const q = scan.value.trim();
  clearTimeout(typeTimer);
  if (!q){ hideList(); return; }
  typeTimer = setTimeout(async ()=>{
    const items = await fetchProducts(q);
    renderList(items);
  }, 220);
});

scan.addEventListener('keydown', async (e)=>{
  const q = scan.value.trim();
  if (e.key === 'ArrowDown'){ move(1); e.preventDefault(); return; }
  if (e.key === 'ArrowUp'){ move(-1); e.preventDefault(); return; }
  if (e.key === 'Escape'){ hideList(); return; }
  if (e.key === 'Enter'){
    e.preventDefault();
    if (currentItems.length && currentIndex >= 0){ addByIndex(currentIndex); return; }
    if (!q) return;
    const items = await fetchProducts(q);
    if (items.length){ addProduct(items[0]); hideList(); scan.value=''; }
    else { showErr('No se encontró producto con ese código o texto.'); }
  }
});

function move(delta){
  if (!currentItems.length) return;
  currentIndex += delta;
  if (currentIndex < 0) currentIndex = currentItems.length - 1;
  if (currentIndex >= currentItems.length) currentIndex = 0;
  [...acList.children].forEach((el, i)=>{
    el.classList.toggle('active', i===currentIndex);
    if (i===currentIndex) el.scrollIntoView({block:'nearest'});
  });
}

function addByIndex(i){
  if (i<0 || i>=currentItems.length) return;
  addProduct(currentItems[i]);
  hideList(); scan.value='';
}

function addProduct(p){
  pushRow({
    id: p.id,
    nombre: p.nombre,
    barcode: p.barcode,
    requiere_lote: !!p.requiere_lote,
    requiere_vto:  !!p.requiere_vto
  });
  if (typeof recalcDoc === 'function') recalcDoc();
  setTimeout(()=>{
    const last = document.querySelector('#tb tbody tr:last-child');
    if (!last){ scan.focus(); return; }
    if (p.requiere_lote){ last.querySelector('td:nth-child(2) input')?.focus(); }
    else if (p.requiere_vto){ last.querySelector('td:nth-child(3) input')?.focus(); }
    else { last.querySelector('.qty')?.focus(); }
  }, 0);
}

function hideList(){ acList.classList.add('d-none'); acList.innerHTML=''; currentItems=[]; currentIndex=-1; }

// =================== Grilla y cálculos ===================
function pushRow(p){
  const tb = document.querySelector('#tb tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td data-id="${p.id}">${escapeHtml(p.nombre)} <span class="text-muted small">${p.barcode||''}</span></td>
    <td><input class="form-control form-control-sm" placeholder="${p.requiere_lote?'obligatorio':'(opcional)'}" ${p.requiere_lote?'required':''}></td>
    <td><input type="date" class="form-control form-control-sm" ${p.requiere_vto?'required':''}></td>
    <td><input type="number" step="0.001" class="form-control form-control-sm qty" value="1" required></td>
    <td><input type="number" step="0.0001" class="form-control form-control-sm unit" value="0" placeholder="Costo (unidad)" title="Se calcula como Total / Cantidad" readonly></td>
    <td><input type="number" step="0.01" class="form-control form-control-sm total" value="0" placeholder="Total (costo)" title="Costo total de la línea"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcDoc();">Quitar</button></td>
  `;
  tb.appendChild(tr);
  recalcDoc();
}

document.addEventListener('input', function(e){
  if (e.target.closest('#tb')){
    if (e.target.classList.contains('qty') || e.target.classList.contains('total')){
      const tr = e.target.closest('tr'); recalcRow(tr); recalcDoc();
    }
  }
});

function recalcRow(tr){
  const qtyEl   = tr.querySelector('.qty');
  const unitEl  = tr.querySelector('.unit');
  const totalEl = tr.querySelector('.total');
  const q = parseFloat(qtyEl?.value || '0');
  const total = parseFloat(totalEl?.value || '0');
  if (q > 0) {
    const unit = total / q;
    if (isFinite(unit)) unitEl.value = unit.toFixed(4);
  } else {
    unitEl.value = (0).toFixed(4);
  }
}

function recalcDoc(){
  document.querySelectorAll('#tb tbody tr').forEach(recalcRow);
  let suma = 0;
  document.querySelectorAll('#tb tbody tr input.total').forEach(el=>{
    const v = parseFloat(el.value || '0');
    if (!isNaN(v)) suma += v;
  });
  const out = document.getElementById('docTotal');
  if (out) out.value = (Math.round((suma + Number.EPSILON) * 100) / 100).toFixed(2);
}

// =================== Envío via fetch + toasts ===================
async function enviarCompra(ev){
  ev.preventDefault();
  const rows = Array.from(document.querySelectorAll('#tb tbody tr'));
  if(rows.length===0){ showErr('Agregá al menos un producto'); return false; }

  const items = rows.map(tr=>{
    const tds = tr.querySelectorAll('td');
    const loteStr = tds[1].querySelector('input')?.value.trim() || '';
    const vtoStr  = tds[2].querySelector('input')?.value.trim() || '';
    return {
      product_id: parseInt(tds[0].dataset.id),
      cantidad: parseFloat(tr.querySelector('.qty')?.value || '0'),
      costo_unit: parseFloat(tr.querySelector('.unit')?.value || '0'),
      lote: (loteStr || vtoStr) ? { nro_lote: (loteStr||null), vto: (vtoStr||null) } : null
    };
  });

  const hasPositive = items.some(it => (it.cantidad||0) > 0 && (it.costo_unit||0) > 0);
  if (!hasPositive){ showErr('Revisá Cantidad y Total: deben ser mayores a 0.'); return false; }

  // armar FormData
  const form = document.getElementById('frmCompra');
  const fd = new FormData(form);
  fd.set('items_json', JSON.stringify(items));

  // bloquear botón
  const btn = document.getElementById('btnConfirmar');
  btn.disabled = true;
  btn.querySelector('.spinner-border').classList.remove('d-none');

  try{
    const res = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
    const js = await res.json();
    if(js.ok){
      showOk(`Recepción cargada con éxito. ID movimiento: <code>${js.movement_id}</code>`);
      // limpiar grilla y totales; dejar foco en scan para seguir cargando
      document.querySelector('#tb tbody').innerHTML = '';
      document.getElementById('docTotal').value = '0.00';
      scan.value = '';
      scan.focus();
      // Si querés limpiar el N° de documento:
      // document.getElementById('documento').value = '';
    }else{
      showErr(js.detail || js.error || 'Error al guardar');
    }
  }catch(err){
    showErr('No se pudo conectar con el servidor.');
  }finally{
    btn.disabled = false;
    btn.querySelector('.spinner-border').classList.add('d-none');
  }
  return false;
}

// =================== Toast helpers ===================
let toastOk=null, toastErr=null;
document.addEventListener('DOMContentLoaded', ()=>{
  toastOk = new bootstrap.Toast(document.getElementById('toastOk'), {delay: 3500});
  toastErr = new bootstrap.Toast(document.getElementById('toastErr'), {delay: 5000});
});
function showOk(html){ const el=document.querySelector('#toastOk .toast-body'); el.innerHTML=html; toastOk.show(); }
function showErr(html){ const el=document.querySelector('#toastErr .toast-body'); el.innerHTML=html; toastErr.show(); }

function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>