<?php
// /app/deposito/public/deposits.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']);
$app = require __DIR__ . '/../config/app.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$is_admin = function_exists('user_has_role') ? user_has_role('admin') : true;
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Depósitos | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>.table-tight td,.table-tight th{padding:.45rem .5rem}.toast-container{position:fixed;top:1rem;right:1rem;z-index:2000}</style>
</head><body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=h($app['APP_NAME'])?></a>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div><h1 class="h5 mb-0">Depósitos</h1><div class="text-muted small">Sucursales / centros de stock</div></div>
    <?php if($is_admin):?><button class="btn btn-dark btn-sm" onclick="openCreate()">Nuevo depósito</button><?php endif;?>
  </div>

  <div class="row g-2 align-items-end mb-3">
    <div class="col-sm-6 col-md-5">
      <label class="form-label">Buscar</label>
      <input type="text" id="q" class="form-control" placeholder="Nombre, ciudad o dirección" oninput="deb(loadList,180)()">
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light"><tr>
        <th style="width:80px">ID</th><th>Nombre</th><th>Dirección</th><th>Ciudad</th><th style="width:140px">Teléfono</th>
        <th style="width:110px" class="text-center">Estado</th><th style="width:240px" class="text-end">Acciones</th>
      </tr></thead>
      <tbody id="tb"></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="depModal" tabindex="-1"><div class="modal-dialog">
  <form class="modal-content" onsubmit="return saveDep()">
    <div class="modal-header"><h5 class="modal-title" id="dmTitle">Depósito</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="dmId">
      <div class="mb-2"><label class="form-label">Nombre</label><input id="dmNombre" class="form-control" required></div>
      <div class="mb-2"><label class="form-label">Dirección</label><input id="dmDireccion" class="form-control"></div>
      <div class="mb-2"><label class="form-label">Ciudad</label><input id="dmCiudad" class="form-control"></div>
      <div class="mb-2"><label class="form-label">Teléfono</label><input id="dmTelefono" class="form-control"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-dark" type="submit">Guardar</button></div>
  </form>
</div></div>

<div class="toast-container"></div>

<script>
const API='/app/deposito/api/deposits_api.php';
const IS_ADMIN=<?= $is_admin?'true':'false' ?>;

function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function toast(m,t='success'){const c=document.querySelector('.toast-container'); const d=document.createElement('div');
d.className='toast align-items-center text-bg-'+(t==='error'?'danger':(t==='warn'?'warning':'success'))+' border-0'; d.role='alert';
d.innerHTML='<div class="d-flex"><div class="toast-body">'+m+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
c.appendChild(d); new bootstrap.Toast(d,{delay:2500}).show(); d.addEventListener('hidden.bs.toast',()=>d.remove());}
const deb=(fn,ms)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)}}

async function loadList(){
  const url=new URL(API,location.origin); url.searchParams.set('action','list');
  const q=document.getElementById('q').value.trim(); if(q) url.searchParams.set('q',q);
  const js=await (await fetch(url)).json().catch(()=>({ok:false,items:[]}));
  const tb=document.getElementById('tb'); tb.innerHTML='';
  if(!js.ok){ tb.innerHTML='<tr><td colspan="7" class="text-center text-danger">Error</td></tr>'; return; }
  if(!js.items.length){ tb.innerHTML='<tr><td colspan="7" class="text-center text-muted">Sin resultados</td></tr>'; return; }
  tb.innerHTML=js.items.map(r=>`
    <tr>
      <td class="text-muted">#${r.id}</td>
      <td>${esc(r.nombre||'')}</td>
      <td>${esc(r.direccion||'')}</td>
      <td>${esc(r.ciudad||'')}</td>
      <td>${esc(r.telefono||'')}</td>
      <td class="text-center">${+r.is_activo===1?'<span class="badge bg-success">activo</span>':'<span class="badge bg-secondary">inactivo</span>'}</td>
      <td class="text-end">
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-secondary" onclick="openEdit(${r.id})">Editar</button>
          ${IS_ADMIN?`<button class="btn btn-sm btn-outline-${+r.is_activo===1?'danger':'success'}" onclick="toggle(${r.id})">${+r.is_activo===1?'Desactivar':'Activar'}</button>`:'<span class="text-muted">—</span>'}
        </div>
      </td>
    </tr>`).join('');
}

function openCreate(){ if(!IS_ADMIN) return; document.getElementById('dmTitle').textContent='Nuevo depósito';
  document.getElementById('dmId').value=''; ['dmNombre','dmDireccion','dmCiudad','dmTelefono'].forEach(i=>document.getElementById(i).value='');
  new bootstrap.Modal('#depModal').show();
}
async function openEdit(id){
  const url=new URL(API,location.origin); url.searchParams.set('action','get'); url.searchParams.set('id',id);
  const js=await (await fetch(url)).json().catch(()=>({ok:false})); if(!js.ok){ toast('No se pudo obtener','error'); return; }
  document.getElementById('dmTitle').textContent='Editar depósito';
  document.getElementById('dmId').value=js.data.id;
  document.getElementById('dmNombre').value=js.data.nombre||'';
  document.getElementById('dmDireccion').value=js.data.direccion||'';
  document.getElementById('dmCiudad').value=js.data.ciudad||'';
  document.getElementById('dmTelefono').value=js.data.telefono||'';
  new bootstrap.Modal('#depModal').show();
}
async function saveDep(){
  const id=document.getElementById('dmId').value; const fd=new FormData();
  fd.append('nombre',document.getElementById('dmNombre').value.trim());
  fd.append('direccion',document.getElementById('dmDireccion').value.trim());
  fd.append('ciudad',document.getElementById('dmCiudad').value.trim());
  fd.append('telefono',document.getElementById('dmTelefono').value.trim());
  fd.append('action', id? 'update':'create'); if(id) fd.append('id',id);
  const js=await (await fetch(API,{method:'POST',body:fd})).json().catch(()=>({ok:false}));
  if(js.ok){ toast('Guardado'); bootstrap.Modal.getInstance(document.getElementById('depModal')).hide(); loadList(); } else { toast(js.error||'Error','error'); }
  return false;
}
async function toggle(id){
  const fd=new FormData(); fd.append('action','toggle'); fd.append('id',id);
  const js=await (await fetch(API,{method:'POST',body:fd})).json().catch(()=>({ok:false}));
  if(js.ok){ toast('Estado actualizado'); loadList(); } else { toast(js.error||'Error','error'); }
}
document.addEventListener('DOMContentLoaded',loadList);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>