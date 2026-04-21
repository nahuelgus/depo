<?php
// /app/deposito/public/sales_new.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';

$depos = DB::all("SELECT id, nombre FROM depo_deposits WHERE is_activo=1 ORDER BY id ASC");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nueva venta | <?=$app['APP_NAME']?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  #acList .active{background:#0d6efd;color:#fff;}
  .toast-container{position:fixed;top:1rem;right:1rem;z-index:2000;}
  .dropdown-menu.show{display:block;position:absolute;z-index:1055;max-height:260px;overflow:auto;}
  .small-muted{font-size:.85rem;color:#6c757d;}
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

<div class="container py-4">
  <h1 class="h5 mb-1">🧾 Nueva venta</h1>
  <div class="alert alert-warning py-2 mb-3" role="alert" style="font-size:.95rem;">
    <strong>Importante:</strong> No se puede vender productos de <u>diferentes depósitos</u>. Realice 2 o más remitos.
  </div>

<form id="frmVenta" onsubmit="return enviarVenta(event)">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" class="form-control" value="<?=date('Y-m-d')?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Depósito</label>
        <select name="deposit_id" id="deposit_id" class="form-select">
          <?php foreach($depos as $d): ?>
            <option value="<?=$d['id']?>"><?=$d['nombre']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Cliente -->
      <div class="col-md-6">
        <label class="form-label">Cliente</label>
        <div class="position-relative">
          <input type="text" id="cliSearch" class="form-control" placeholder="Buscar cliente (razón social / doc / email / tel / referente)">
          <div id="cliResults" class="dropdown-menu" style="display:none;"></div>
          <input type="hidden" id="client_id" name="client_id">
        </div>
        <div class="small-muted mt-1">Si no seleccionás un cliente, podés completar abajo “Cliente manual”.</div>
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-md-6">
        <label class="form-label">Cliente manual (opcional)</label>
        <input type="text" id="cliente_manual" class="form-control" placeholder="Ej.: Razón social y/o contacto">
      </div>
      <div class="col-md-6">
        <label class="form-label">Notas / Observación</label>
        <textarea id="obs" name="obs" class="form-control" rows="2" placeholder="Observaciones para el remito..."></textarea>
      </div>
    </div>

    <hr>

    <!-- Buscador con autocompletado de productos -->
    <div class="row g-2 align-items-end">
      <div class="col-md-7">
        <label class="form-label">Producto</label>
        <input type="text" id="scan" class="form-control" placeholder="EAN / Nombre / Marca...">
        <div id="acList" class="list-group position-absolute w-50 d-none" style="z-index:20"></div>
      </div>
      <div class="col-md-5 d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="scan.value=''; scan.focus()">Limpiar</button>
      </div>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle" id="tb">
        <thead class="table-light">
          <tr>
            <th>Producto</th>
            <th style="width:220px">Lote / Vto / Disp.</th>
            <th style="width:120px">Cant.</th>
            <th style="width:140px">Precio (unidad)</th>
            <th style="width:110px">% Desc.</th>
            <th style="width:120px" class="text-end">Importe</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <td colspan="4"></td>
            <td class="text-end fw-semibold">Subtotal</td>
            <td class="text-end" id="td_subtotal">0.00</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4"></td>
            <td class="text-end fw-semibold">IVA</td>
            <td class="text-end" id="td_iva">0.00</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4"></td>
            <td class="text-end fw-semibold">TOTAL</td>
            <td class="text-end" id="td_total">0.00</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button type="button" class="btn btn-dark" onclick="enviarVenta(event)">Confirmar venta</button>
      <a href="/app/deposito/public/dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>

    <!-- Indicaciones para evitar fallos en el remito -->
    <div class="mt-3 small text-muted">
      <ul class="mb-0">
        <li>🧱 Verificá que el navegador permita <strong>ventanas emergentes</strong>; el remito se abre en una pestaña nueva.</li>
        <li>🏷️ Si el producto requiere <strong>lote</strong>, elegí uno con disponibilidad; cantidades no pueden superar el “Disp.”</li>
        <li>🏬 Confirmá el <strong>Depósito</strong> correcto antes de agregar productos.</li>
        <li>🗓️ Chequeá la <strong>fecha</strong> y completá <strong>Notas</strong> si necesitás aclaraciones en el remito.</li>
        <li>👤 Si no cargás un cliente, usá “<strong>Cliente manual</strong>”.</li>
      </ul>
    </div>

    <input type="hidden" name="items_json" id="items_json">
  </form>
</div>

<!-- Toast container -->
<div class="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_SEARCH_PROD = '/app/deposito/api/products_search.php';
const API_LOTS        = '/app/deposito/api/lots_by_product.php';
const API_CLIENTES    = '/app/deposito/api/clientes_api.php';

const scan   = document.getElementById('scan');
const acList = document.getElementById('acList');
let typeTimer=null, currentIndex=-1, currentItems=[], cache=new Map();

document.addEventListener('DOMContentLoaded', ()=> scan.focus() );

function showToast(msg,type='success'){
  const cont=document.querySelector('.toast-container');
  const div=document.createElement('div');
  div.className='toast align-items-center text-bg-'+(type==='error'?'danger':'success')+' border-0';
  div.role='alert';
  div.innerHTML='<div class="d-flex"><div class="toast-body">'+msg+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  cont.appendChild(div);
  const t=new bootstrap.Toast(div,{delay:3000});
  t.show();
  div.addEventListener('hidden.bs.toast',()=>div.remove());
}

/* =======================
   Autocomplete PRODUCTOS
   ======================= */
function renderList(items){
  currentItems = items; currentIndex=-1;
  acList.innerHTML='';
  if(!items.length){ acList.classList.add('d-none'); return; }
  items.forEach((p,i)=>{
    const btn=document.createElement('button'); btn.type='button';
    btn.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
    btn.dataset.index=i;
    btn.innerHTML='<span>'+escapeHtml(p.nombre)+' <span class="text-muted small">'+(p.presentacion||'')+'</span></span><span class="badge bg-light text-dark">'+(p.barcode||'')+'</span>';
    btn.addEventListener('click',()=>addByIndex(i));
    acList.appendChild(btn);
  });
  acList.classList.remove('d-none');
}
async function fetchProducts(q){
  const key=q.trim().toLowerCase(); if(cache.has(key)) return cache.get(key);
  const url=new URL(API_SEARCH_PROD,location.origin); url.searchParams.set('q',q); url.searchParams.set('limit','20');
  const res=await fetch(url,{credentials:'same-origin'}); const data=await res.json().catch(()=>({ok:false,items:[]}));
  const items=data.ok?data.items:[]; cache.set(key,items); return items;
}
scan.addEventListener('input',()=>{
  const q=scan.value.trim(); clearTimeout(typeTimer); if(!q){hideList();return;}
  typeTimer=setTimeout(async()=>renderList(await fetchProducts(q)),220);
});
scan.addEventListener('keydown',async e=>{
  const q=scan.value.trim();
  if(e.key==='ArrowDown'){move(1);e.preventDefault();return;}
  if(e.key==='ArrowUp'){move(-1);e.preventDefault();return;}
  if(e.key==='Escape'){hideList();return;}
  if(e.key==='Enter'){e.preventDefault();
    if(currentItems.length && currentIndex>=0){addByIndex(currentIndex);return;}
    if(!q) return; const items=await fetchProducts(q);
    if(items.length){ addProduct(items[0]); } else showToast('No se encontró producto','error');
  }
});
function move(d){ if(!currentItems.length) return; currentIndex+=d; if(currentIndex<0) currentIndex=currentItems.length-1; if(currentIndex>=currentItems.length) currentIndex=0;
  [...acList.children].forEach((el,i)=>{ el.classList.toggle('active',i===currentIndex); });
}
function hideList(){ acList.classList.add('d-none'); acList.innerHTML=''; }
function addByIndex(i){ addProduct(currentItems[i]); }

/* ==============
   Lotes Producto
   ============== */
function renderLoteSelect(lots, required){
  // ¿hay disponibilidad?
  const hasDisp = (lots||[]).some(l => (parseFloat(l.disponible ?? 0) || 0) > 0);
  if (!hasDisp){
    return `<select class="form-select form-select-sm loteSel" disabled>
      <option value="">No disponible</option>
    </select>`;
  }
  const opts=(lots||[]).map(l=>`
    <option value="${l.lot_id ?? ''}" data-disp="${l.disponible ?? 0}">
      ${(l.nro_lote||'(sin lote)')} ${l.vto? '— Vto:'+l.vto:''} — Disp:${l.disponible??0}
    </option>`).join('');
  if(!required){
    return `<select class="form-select form-select-sm loteSel">
      <option value="">(sin lote)</option>${opts}</select>`;
  }
  return `<select class="form-select form-select-sm loteSel" required>${opts}</select>`;
}
function enforceQtyLimit(sel, qtyInput){
  const setMax=()=>{
    const disp=parseFloat(sel.selectedOptions[0]?.dataset?.disp||'0')||0;
    qtyInput.max=disp||''; if(disp && parseFloat(qtyInput.value||'0')>disp){
      qtyInput.value=disp; qtyInput.dispatchEvent(new Event('input'));
    }
  };
  sel.addEventListener('change',setMax); setMax();
}

/* =========================
   Agregar producto a tabla
   ========================= */
async function addProduct(p){
  hideList(); scan.value=''; scan.focus();
  const depositId=document.getElementById('deposit_id')?.value||'';
  let lots=[];
  try{
    const url=new URL(API_LOTS,location.origin);
    url.searchParams.set('product_id',p.id);
    if(depositId) url.searchParams.set('deposit_id',depositId);
    const res=await fetch(url); const data=await res.json();
    if(data.ok) lots=data.items||[];
  }catch(e){ showToast('Error al consultar lotes','error'); }

  // Si no hay disponibilidad en este depósito → alert y no agregamos
  const totalDisp = (lots||[]).reduce((a,l)=>a + (parseFloat(l.disponible ?? 0) || 0), 0);
  if (totalDisp <= 0){
    alert('Este producto no tiene stock disponible en el depósito seleccionado.');
    return;
  }

  const tb=document.querySelector('#tb tbody'); const tr=document.createElement('tr');
  tr.innerHTML=`
    <td data-id="${p.id}" data-iva="${p.iva_pct||''}">
      ${escapeHtml(p.nombre)} ${p.presentacion?'<span class="text-muted small">'+escapeHtml(p.presentacion)+'</span>':''}
      ${p.barcode?'<div class="small-muted">'+escapeHtml(p.barcode)+'</div>':''}
    </td>
    <td>${renderLoteSelect(lots,!!p.requiere_lote)}</td>
    <td><input type="number" step="0.001" class="form-control form-control-sm qty" value="1" required></td>
    <td><input type="number" step="0.0001" class="form-control form-control-sm price" value="${(p.precio||0)}"></td>
    <td><input type="number" step="0.01" class="form-control form-control-sm desc" value="0"></td>
    <td class="importe text-end">0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcDoc();">Quitar</button></td>
  `;
  tb.appendChild(tr);

  const sel=tr.querySelector('select.loteSel'); const qty=tr.querySelector('input.qty');
  if(sel && !sel.disabled){ enforceQtyLimit(sel,qty); sel.addEventListener('change',()=>recalcDoc()); }
  tr.querySelectorAll('input').forEach(i=> i.addEventListener('input',()=>recalcDoc()));
  recalcDoc();
}

/* ============
   Recalcular
   ============ */
function recalcDoc(){
  let subtotal=0,iva=0;
  document.querySelectorAll('#tb tbody tr').forEach(tr=>{
    const q=parseFloat(tr.querySelector('.qty')?.value||'0');
    const pr=parseFloat(tr.querySelector('.price')?.value||'0');
    const dc=parseFloat(tr.querySelector('.desc')?.value||'0');
    let base=q*pr; if(dc>0) base=base*(1-(dc/100));
    tr.querySelector('.importe').textContent=(base.toFixed(2));
    subtotal+=base;
    const iva_pct=parseFloat(tr.querySelector('td[data-iva]')?.dataset.iva||'0');
    iva+=base*(iva_pct/100);
  });
  document.getElementById('td_subtotal').textContent=subtotal.toFixed(2);
  document.getElementById('td_iva').textContent=iva.toFixed(2);
  document.getElementById('td_total').textContent=(subtotal+iva).toFixed(2);
}

/* ==================
   Clientes (search)
   ================== */
const $cliSearch  = document.getElementById('cliSearch');
const $cliResults = document.getElementById('cliResults');
const $clientId   = document.getElementById('client_id');

function pickClient(c){
  $clientId.value = c.id || '';
  // mostramos el nombre elegido dentro del input de búsqueda
  $cliSearch.value = (c.razon_social || '') + (c.doc ? ' — ' + c.doc : '');
  $cliResults.style.display='none'; $cliResults.innerHTML='';
}
$cliSearch.addEventListener('input', function(){
  clearTimeout(window._cliT);
  const q = this.value.trim();
  if (q.length < 2){ $cliResults.style.display='none'; $cliResults.innerHTML=''; $clientId.value=''; return; }
  window._cliT = setTimeout(async ()=>{
    const url = new URL(API_CLIENTES, location.origin);
    url.searchParams.set('action','search'); url.searchParams.set('q', q);
    const js = await (await fetch(url)).json().catch(()=>({ok:false,items:[]}));
    if (!js.ok || !js.items.length){ $cliResults.style.display='none'; $cliResults.innerHTML=''; return; }
    $cliResults.innerHTML = js.items.map(c=>`
      <button class="dropdown-item" type="button" data-id="${c.id}">
        ${escapeHtml(c.razon_social)}${c.doc ? ' — '+escapeHtml(c.doc): ''}${c.email ? ' — '+escapeHtml(c.email): ''}
      </button>`).join('');
    $cliResults.style.display = 'block';
  }, 200);
});
$cliResults.addEventListener('click', async (e)=>{
  const btn = e.target.closest('button.dropdown-item'); if(!btn) return;
  const url = new URL(API_CLIENTES, location.origin);
  url.searchParams.set('action','get'); url.searchParams.set('id', btn.dataset.id);
  const js = await (await fetch(url)).json().catch(()=>({ok:false}));
  if(js.ok && js.data){ pickClient(js.data); }
});
$cliSearch.addEventListener('change', ()=>{
  if ($cliSearch.value.trim()==='') $clientId.value='';
});

/* ===============
   Enviar venta
   =============== */
async function enviarVenta(ev){
  if (ev) ev.preventDefault();        // <- frena el submit nativo

  // Abrimos la ventana del remito *antes* del await para evitar bloqueo de popups
  let remitoWin = window.open('about:blank','_blank');

  const rows = [...document.querySelectorAll('#tb tbody tr')];
  if (!rows.length){
    showToast('Agregá al menos un producto','error');
    if (remitoWin) remitoWin.close();
    return false;
  }

  const items = rows.map(tr=>{
    const loteSel = tr.querySelector('select.loteSel');
    const rawLot  = loteSel ? (loteSel.value ?? '') : '';
    return {
      product_id: parseInt(tr.querySelector('td').dataset.id),
      lote_id:    rawLot !== '' ? parseInt(rawLot) : null,
      cantidad:   parseFloat(tr.querySelector('.qty')?.value || '0'),
      precio:     parseFloat(tr.querySelector('.price')?.value || '0'),
      desc_pct:   parseFloat(tr.querySelector('.desc')?.value || '0')
    };
  });
  document.getElementById('items_json').value = JSON.stringify(items);

  // Si no se seleccionó cliente, inyecto "Cliente manual" en obs
  const clientIdVal = document.getElementById('client_id')?.value || '';
  const cm          = document.getElementById('cliente_manual')?.value.trim() || '';
  const obsEl       = document.getElementById('obs');
  if (!clientIdVal && cm){
    obsEl.value = ('Cliente: '+cm + (obsEl.value ? ' — '+obsEl.value : ''));
  }

  const fd  = new FormData(document.getElementById('frmVenta'));  // <- tomamos el form por id
  const res = await fetch('/app/deposito/api/sales_api.php?action=create', { method:'POST', body: fd });
  const js  = await res.json().catch(()=>({ ok:false, error:'server_error' }));

  if (js.ok && js.sale_id){
    showToast('Venta cargada con éxito');
    if (remitoWin) remitoWin.location.href = '/app/deposito/public/sale_remito.php?id='+js.sale_id;
    setTimeout(()=>location.reload(), 800);
  } else {
    if (remitoWin) remitoWin.close();
    showToast('Error: '+(js.detail || js.error || 'server_error'), 'error'); // <- muestra stock insuficiente, etc.
  }
  return false;
}

function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>