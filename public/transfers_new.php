<?php
// /app/deposito/public/transfers_new.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

$app = require __DIR__ . '/../config/app.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// depósitos activos
$depos = DB::all("SELECT id, nombre FROM depo_deposits WHERE is_activo=1 ORDER BY id ASC");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nueva transferencia | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  .readonly { background:#f8f9fa; }
  .loteSel option.small { font-size:.9rem; }
  .muted { color:#6c757d }
  .table thead th { white-space:nowrap }
  .help { font-size:.875rem; color:#6c757d }
</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=$app['APP_NAME']?></a>
  <div class="d-flex gap-2">
    <a href="/app/deposito/public/transfers_list.php" class="btn btn-sm btn-outline-secondary">Historial</a>
    <a href="/app/deposito/public/logout.php" class="btn btn-sm btn-outline-danger">Salir</a>
  </div>
</nav>


<div class="container py-4">
  <h1 class="h5 mb-3">Nueva transferencia</h1>

  <!-- Paso 1: elegir depósito origen -->
  <div class="row g-3 mb-2">
    <div class="col-md-4">
      <label class="form-label">Origen</label>
      <select id="dep_origen" class="form-select">
        <option value="">Seleccionar depósito…</option>
        <?php foreach($depos as $d): ?>
          <option value="<?=$d['id']?>"><?=h($d['nombre'])?></option>
        <?php endforeach; ?>
      </select>
      <div class="help">Primero seleccioná desde dónde sacar el stock.</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">Destino</label>
      <select id="dep_destino" class="form-select" disabled>
        <option value="">Seleccionar depósito…</option>
        <?php foreach($depos as $d): ?>
          <option value="<?=$d['id']?>"><?=h($d['nombre'])?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Fecha</label>
      <div class="input-group">
        <input id="fecha" type="date" class="form-control" value="<?=date('Y-m-d')?>">
        <span class="input-group-text">📅</span>
      </div>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">Observación</label>
    <input id="obs" class="form-control" placeholder="Opcional" maxlength="250">
  </div>

  <!-- Paso 2: buscar productos del depósito origen -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-md-6">
          <label class="form-label">Buscar producto</label>
          <input id="buscador" class="form-control" placeholder="Escribí nombre o código…" disabled>
          <div id="sugerencias" class="list-group position-absolute w-50" style="z-index:20"></div>
          <div class="help">Se habilita luego de elegir Origen.</div>
        </div>
        <div class="col-md-6">
          <div class="help mt-4">Al seleccionar un producto se cargarán los lotes con disponibilidad en el depósito origen.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabla de items -->
  <div class="table-responsive">
    <table id="tb" class="table table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:34%">Producto</th>
          <th style="width:30%">Lote (vto – disponible)</th>
          <th style="width:16%">Cant.</th>
          <th style="width:8%"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-primary" id="btnConfirm" disabled>Confirmar transferencia</button>
    <a class="btn btn-outline-secondary" href="/app/deposito/public/dashboard.php">Cancelar</a>
  </div>
</div>

<script>
// Endpoints existentes
const API_SEARCH = '/app/deposito/api/products_search.php?action=search';
const API_STOCK  = '/app/deposito/api/stock_by_product.php';
const API_POST   = '/app/deposito/api/transfers_api.php?action=create';

const depOrigen   = document.getElementById('dep_origen');
const depDestino  = document.getElementById('dep_destino');
const buscador    = document.getElementById('buscador');
const sugerencias = document.getElementById('sugerencias');
const tbody       = document.querySelector('#tb tbody');
const btnConfirm  = document.getElementById('btnConfirm');

function toast(msg,type='info'){
  // mínimo toast inline
  const el = document.createElement('div');
  el.className = 'alert ' + (type==='error'?'alert-danger':(type==='ok'?'alert-success':'alert-info'));
  el.textContent = msg;
  document.body.appendChild(el);
  Object.assign(el.style,{position:'fixed',right:'16px',bottom:'16px',maxWidth:'420px',zIndex:9999});
  setTimeout(()=> el.remove(), 2200);
}

// ——— Habilitar UI según origen/destino ———
depOrigen.addEventListener('change', ()=>{
  // limpiar filas si cambia el origen
  tbody.innerHTML = '';
  const ok = !!depOrigen.value;
  buscador.disabled = !ok;
  depDestino.disabled = !ok;
  checkReady();
});
depDestino.addEventListener('change', checkReady);
function checkReady(){
  const ready = !!depOrigen.value && !!depDestino.value && depOrigen.value !== depDestino.value;
  btnConfirm.disabled = !ready || tbody.children.length===0;
}

// ——— Autocomplete productos ———
let searchTimer=null;
buscador.addEventListener('input', ()=>{
  sugerencias.innerHTML='';
  if(buscador.value.trim().length<2) return;
  if(searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(doSearch, 200);
});
document.addEventListener('click', (e)=>{
  if(!sugerencias.contains(e.target) && e.target!==buscador){ sugerencias.innerHTML=''; }
});
async function doSearch(){
  const q = buscador.value.trim();
  if(!q) return;
  const url = new URL(API_SEARCH, location.origin);
  url.searchParams.set('q', q);
  try{
    const js = await (await fetch(url)).json();
    sugerencias.innerHTML = '';
    (js.items||[]).forEach(p=>{
      const a = document.createElement('a');
      a.href='#'; a.className='list-group-item list-group-item-action';
      a.textContent = p.nombre + (p.barcode?` · ${p.barcode}`:'');
      a.addEventListener('click', (ev)=>{ ev.preventDefault(); addRow(p); sugerencias.innerHTML=''; buscador.value=''; });
      sugerencias.appendChild(a);
    });
  }catch(e){
    console.error(e);
  }
}

// ——— Agregar fila de transferencia (producto + lotes del Origen) ———
async function addRow(p){
  const origin = parseInt(depOrigen.value||'0',10);
  if(!origin){ toast('Elegí un depósito de origen primero','error'); return; }

  // Traemos lotes y disponible desde el depósito origen
  const url = new URL(API_STOCK, location.origin);
  url.searchParams.set('product_id', p.id);
  url.searchParams.set('deposit_id', origin);

  let lots=[];
  try{
    const rs = await (await fetch(url)).json();
    lots = rs.ok ? (rs.items||[]) : [];
  }catch(e){
    console.error(e);
  }

  // Crear fila
  const tr = document.createElement('tr');
  tr.dataset.pid = p.id;

  // Columna producto (readonly)
  const tdP = document.createElement('td');
  tdP.innerHTML = `<div class="fw-semibold">${escapeHtml(p.nombre)}</div>
                   <div class="small muted">${p.barcode?escapeHtml(p.barcode):''}</div>`;
  tr.appendChild(tdP);

  // Columna lote
  const tdL = document.createElement('td');
  const sel = document.createElement('select');
  sel.className='form-select loteSel';
  sel.innerHTML = `<option value="">(seleccionar)</option>` +
    lots.map(l=>{
      // nro lote (si no hay, usamos #id), vto y disponible
      const label = `#${l.lot_id}` + (l.nro_lote?` · ${l.nro_lote}`:'') + (l.vto?` · vto ${l.vto}`:'') + ` · disp ${l.cantidad}`;
      return `<option value="${l.lot_id}" data-disp="${l.cantidad}">${label}</option>`;
    }).join('');
  tdL.appendChild(sel);
  tr.appendChild(tdL);

  // Columna cantidad
  const tdC = document.createElement('td');
  const qty = document.createElement('input');
  qty.type='number'; qty.min='0'; qty.step='0.001';
  qty.className='form-control qty';
  qty.placeholder='0';
  qty.disabled = true;
  tdC.appendChild(qty);
  tr.appendChild(tdC);

  // Columna acciones
  const tdX = document.createElement('td');
  const btnX = document.createElement('button');
  btnX.type='button'; btnX.className='btn btn-outline-danger';
  btnX.textContent='✕';
  btnX.addEventListener('click', ()=>{ tr.remove(); checkReady(); });
  tdX.appendChild(btnX);
  tr.appendChild(tdX);

  // Eventos lote → habilitar qty con máximo
  sel.addEventListener('change', ()=>{
    const disp = parseFloat(sel.selectedOptions[0]?.dataset?.disp || '0');
    qty.disabled = !sel.value;
    qty.value = '';
    qty.max = disp>0? String(disp) : '';
    if(disp===0) toast('El lote seleccionado no tiene disponibilidad','error');
  });

  tbody.appendChild(tr);
  checkReady();
}

function escapeHtml(s){ return (s??'').toString().replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])); }

// ——— Confirmar transferencia ———
document.getElementById('btnConfirm').addEventListener('click', async ()=>{
  const dep_o = parseInt(depOrigen.value||'0',10);
  const dep_d = parseInt(depDestino.value||'0',10);
  if(!dep_o || !dep_d){ toast('Seleccioná origen y destino','error'); return; }
  if(dep_o===dep_d){ toast('Origen y destino no pueden ser iguales','error'); return; }

  const rows=[...tbody.querySelectorAll('tr')];
  if(!rows.length){ toast('Agregá al menos un producto','error'); return; }

  const items=[];
  for(const tr of rows){
    const pid=parseInt(tr.dataset.pid||'0',10);
    const lotSel = tr.querySelector('.loteSel');
    const lot_id = lotSel.value==='' ? null : parseInt(lotSel.value,10);
    const disp = parseFloat(lotSel.selectedOptions[0]?.dataset?.disp || '0');
    const qty = parseFloat(tr.querySelector('.qty').value||'0');
    if(!pid){ toast('Fila sin producto','error'); return; }
    if(lotSel.value==='' || isNaN(qty) || qty<=0){ toast('Completá lote y cantidad','error'); return; }
    if(qty > disp){ toast('Cantidad mayor al disponible del lote','error'); return; }
    items.push({ product_id:pid, lot_id:lot_id, cantidad:qty });
  }

  const fd = new FormData();
  fd.append('action','create');
  // Enviamos AMBOS juegos de parámetros para máxima compatibilidad con el backend
  fd.append('deposit_origen_id', dep_o);
  fd.append('deposit_destino_id', dep_d);
  fd.append('from_deposit_id', dep_o);
  fd.append('to_deposit_id', dep_d);

  fd.append('fecha', document.getElementById('fecha').value);
  fd.append('obs', document.getElementById('obs').value);
  fd.append('items_json', JSON.stringify(items));

  try{
    const r = await fetch(API_POST, { method:'POST', body:fd });
    const js = await r.json();
    console.log('transfer debug:', js);
    if(js.ok){
      toast('Transferencia confirmada','ok');
      setTimeout(()=> location.href='/app/deposito/public/stock.php', 700);
    }else{
      toast(js.detail || js.error || 'Error en la transferencia','error');
    }
  }catch(e){
    console.error(e);
    toast('Error de red','error');
  }
});
</script>
</body>
</html>