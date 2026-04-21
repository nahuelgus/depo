<?php
// /app/deposito/public/adjust_new.php
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
<title>Ajuste de stock | <?=$app['APP_NAME']?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>#acList .active{background:#0d6efd;color:#fff;}</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=$app['APP_NAME']?></a>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/products.php">Productos</a>
    <a class="btn btn-outline-dark btn-sm" href="/app/deposito/public/logout.php">Salir</a>
  </div>
</nav>

<div class="container py-4">
  <h1 class="h5 mb-1">Ajuste de stock</h1>
  <div class="text-muted small mb-3">Usá este módulo para regularizaciones: inventario, roturas, mermas, etc.</div>

  <form method="post" action="../api/adjust_api.php?action=create" onsubmit="return enviarAjuste()">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" class="form-control" value="<?=date('Y-m-d')?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Depósito</label>
        <select name="deposit_id" class="form-select">
          <?php foreach($depos as $d): ?>
            <option value="<?=$d['id']?>"><?=$d['nombre']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select">
          <option value="positivo">Positivo (+)</option>
          <option value="negativo">Negativo (-)</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Motivo</label>
        <input name="motivo" class="form-control" placeholder="Inventario / Rotura / Merma / Otros">
      </div>
      <div class="col-12">
        <label class="form-label">Observación</label>
        <input name="observacion" class="form-control" placeholder="Detalle adicional (opcional)">
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

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle" id="tb">
        <thead>
          <tr>
            <th style="width:30%">Producto</th>
            <th style="width:14%">Lote</th>
            <th style="width:12%">Vto</th>
            <th style="width:12%">Cant</th>
            <th style="width:8%"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button type="submit" class="btn btn-dark">Confirmar ajuste</button>
      <a href="/app/deposito/public/dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>

    <input type="hidden" name="items_json" id="items_json">
  </form>
</div>

<script>
// ========== Autocomplete ==========
const API_SEARCH = '/app/deposito/api/products_search.php';
const scan   = document.getElementById('scan');
const acList = document.getElementById('acList');
let typeTimer=null, currentIndex=-1, currentItems=[], cache=new Map();
document.addEventListener('DOMContentLoaded', ()=> scan.focus() );

function renderList(items){
  currentItems = items; currentIndex=-1;
  acList.innerHTML='';
  if(!items.length){ acList.classList.add('d-none'); return; }
  items.forEach((p,i)=>{
    const btn=document.createElement('button'); btn.type='button';
    btn.className='list-group-item list-group-item-action d-flex justify-content-between align-items-center';
    btn.dataset.index=i;
    btn.innerHTML='<span>'+escapeHtml(p.nombre)+' <span class="text-muted">['+p.id+']</span></span><small class="text-muted">'+(p.barcode||'')+'</small>';
    btn.addEventListener('click',()=>addByIndex(i));
    acList.appendChild(btn);
  });
  acList.classList.remove('d-none');
}
async function fetchProducts(q){
  const key=q.trim().toLowerCase(); if(cache.has(key)) return cache.get(key);
  const url=new URL(API_SEARCH,location.origin); url.searchParams.set('q',q); url.searchParams.set('limit','20');
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
    if(items.length){ addProduct(items[0]); hideList(); scan.value=''; } else alert('No se encontró producto.');
  }
});
function move(d){ if(!currentItems.length) return; currentIndex+=d; if(currentIndex<0) currentIndex=currentItems.length-1; if(currentIndex>=currentItems.length) currentIndex=0;
  [...acList.children].forEach((el,i)=>{ el.classList.toggle('active',i===currentIndex); if(i===currentIndex) el.scrollIntoView({block:'nearest'}); });
}
function addByIndex(i){ if(i<0||i>=currentItems.length) return; addProduct(currentItems[i]); hideList(); scan.value=''; }
function addProduct(p){
  pushRow({ id:p.id, nombre:p.nombre, barcode:p.barcode, requiere_lote:!!p.requiere_lote, requiere_vto:!!p.requiere_vto });
  setTimeout(()=>{
    const last=document.querySelector('#tb tbody tr:last-child');
    if(!last){ scan.focus(); return; }
    if(p.requiere_lote){ last.querySelector('td:nth-child(2) input')?.focus(); }
    else if(p.requiere_vto){ last.querySelector('td:nth-child(3) input')?.focus(); }
    else { last.querySelector('.qty')?.focus(); }
  },0);
}
function hideList(){ acList.classList.add('d-none'); acList.innerHTML=''; currentItems=[]; currentIndex=-1; }

// ========== Grid ==========
function pushRow(p){
  const tb=document.querySelector('#tb tbody'); const tr=document.createElement('tr');
  tr.innerHTML = `
    <td data-id="${p.id}">${escapeHtml(p.nombre)} <span class="text-muted small">${p.barcode||''}</span></td>
    <td><input class="form-control form-control-sm" placeholder="${p.requiere_lote?'obligatorio':'(opcional)'}" ${p.requiere_lote?'required':''}></td>
    <td><input type="date" class="form-control form-control-sm" ${p.requiere_vto?'required':''}></td>
    <td><input type="number" step="0.001" class="form-control form-control-sm qty" value="1" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Quitar</button></td>
  `;
  tb.appendChild(tr);
}
function enviarAjuste(){
  const rows=[...document.querySelectorAll('#tb tbody tr')]; if(!rows.length){ alert('Agregá al menos un producto'); return false; }
  const items = rows.map(tr=>{
    const tds=tr.querySelectorAll('td');
    return {
      product_id: parseInt(tds[0].dataset.id),
      nro_lote: tds[1].querySelector('input').value.trim(),
      vto: tds[2].querySelector('input').value || null,
      cantidad: parseFloat(tr.querySelector('.qty')?.value || '0')
    };
  });
  if (items.some(i => (i.cantidad||0)<=0)){ alert('Revisá cantidades.'); return false; }
  document.getElementById('items_json').value = JSON.stringify(items);
  return true;
}
function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>
