<?php
// /app/deposito/public/users.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin']); // admin gestiona, supervisor solo ve
$app = require __DIR__ . '/../config/app.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// helper de rol (si tu bootstrap no trae, hardcodeá true/false)
$is_admin = function_exists('user_has_role') ? user_has_role('admin') : true;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  .table-tight td, .table-tight th { padding:.45rem .5rem; }
  .toast-container{position:fixed;top:1rem;right:1rem;z-index:2000;}
  .badge-role{ background:#f6f7f9; border:1px solid #eef0f2; color:#555; }
  .muted{ color:#6c757d; }
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
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h5 mb-0">Usuarios</h1>
      <div class="text-muted small">ABM y gestión de permisos</div>
    </div>
    <?php if ($is_admin): ?>
      <button class="btn btn-dark btn-sm" onclick="openCreate()">Nuevo usuario</button>
    <?php endif; ?>
  </div>

  <div class="row g-2 align-items-end mb-3">
    <div class="col-sm-6 col-md-5">
      <label class="form-label">Buscar</label>
      <input type="text" id="q" class="form-control" placeholder="Nombre, email o username" oninput="loadListDebounced()">
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th style="width:80px">ID</th>
          <th style="width:160px">Usuario</th>
          <th>Nombre</th>
          <th style="width:220px">Email</th>
          <th style="width:140px">Teléfono</th>
          <th style="width:120px">Rol</th>
          <th style="width:110px" class="text-center">Estado</th>
          <th style="width:300px" class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody id="tb"></tbody>
    </table>
  </div>
</div>

<!-- Modal alta/edición -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" onsubmit="return saveUser()">
      <div class="modal-header">
        <h5 class="modal-title" id="umTitle">Usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="umId">
        <div class="mb-2">
          <label class="form-label">Usuario</label>
          <input type="text" id="umUsername" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Nombre</label>
          <input type="text" id="umNombre" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" id="umEmail" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Teléfono</label>
          <input type="text" id="umTelefono" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Rol</label>
          <select id="umRole" class="form-select" required>
            <option value="deposito">Depósito</option>
            <option value="supervisor">Supervisor</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="mb-2" id="umPassRow">
          <label class="form-label">Password</label>
          <input type="password" id="umPassword" class="form-control" minlength="6" placeholder="Mínimo 6 caracteres">
          <div class="form-text">Para alta es obligatorio. En edición, dejá vacío si no querés cambiarla.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-dark" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal reset password -->
<div class="modal fade" id="passModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" onsubmit="return doResetPass()">
      <div class="modal-header">
        <h5 class="modal-title">Resetear contraseña</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rpId">
        <div class="mb-2">
          <label class="form-label">Nueva contraseña</label>
          <input type="password" id="rpPass" class="form-control" minlength="6" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-dark" type="submit">Confirmar</button>
      </div>
    </form>
  </div>
</div>

<!-- Toasts -->
<div class="toast-container"></div>

<script>
const API = '/app/deposito/api/users_api.php';
const IS_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;

function showToast(msg,type='success'){
  const cont=document.querySelector('.toast-container');
  const div=document.createElement('div');
  div.className='toast align-items-center text-bg-'+(type==='error'?'danger':(type==='warn'?'warning':'success'))+' border-0';
  div.role='alert';
  div.innerHTML='<div class="d-flex"><div class="toast-body">'+msg+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  cont.appendChild(div);
  const t=new bootstrap.Toast(div,{delay:3000}); t.show();
  div.addEventListener('hidden.bs.toast',()=>div.remove());
}

function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));}

async function loadList(){
  const q=document.getElementById('q').value.trim();
  const url=new URL(API, location.origin); url.searchParams.set('action','list'); if(q) url.searchParams.set('q',q);
  const js=await (await fetch(url)).json().catch(()=>({ok:false,items:[]}));
  const tb=document.getElementById('tb'); tb.innerHTML='';
  if(!js.ok){ tb.innerHTML='<tr><td colspan="8" class="text-center text-danger">Error al cargar usuarios</td></tr>'; return; }
  if(!js.items.length){ tb.innerHTML='<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>'; return; }

  tb.innerHTML = js.items.map(u=>{
    const roleBadge = `<span class="badge badge-role">${esc(u.role)}</span>`;
    const state = +u.is_activo===1 ? '<span class="badge bg-success">activo</span>' : '<span class="badge bg-secondary">inactivo</span>';
    const actions = IS_ADMIN ? `
      <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary" onclick="openEdit(${u.id})">Editar</button>
        <button class="btn btn-sm btn-outline-warning" onclick="openReset(${u.id})">Reset Pass</button>
        <button class="btn btn-sm btn-outline-${+u.is_activo===1?'danger':'success'}" onclick="toggleUser(${u.id})">${+u.is_activo===1?'Desactivar':'Activar'}</button>
      </div>` : '<span class="muted">—</span>';

    return `<tr>
      <td class="text-muted">#${u.id}</td>
      <td>${esc(u.username||'')}</td>
      <td>${esc(u.nombre||'')}</td>
      <td>${esc(u.email||'')}</td>
      <td>${esc(u.telefono||'')}</td>
      <td>${roleBadge}</td>
      <td class="text-center">${state}</td>
      <td class="text-end">${actions}</td>
    </tr>`;
  }).join('');
}
let listTimer=null; function loadListDebounced(){ clearTimeout(listTimer); listTimer=setTimeout(loadList,180); }

/* ==== Alta / Edición ==== */
function openCreate(){
  if(!IS_ADMIN){ showToast('Solo admin puede modificar','error'); return; }
  document.getElementById('umTitle').textContent='Nuevo usuario';
  document.getElementById('umId').value='';
  document.getElementById('umUsername').value='';
  document.getElementById('umNombre').value='';
  document.getElementById('umEmail').value='';
  document.getElementById('umTelefono').value='';
  document.getElementById('umRole').value='deposito';
  document.getElementById('umPassword').value='';
  document.getElementById('umPassRow').style.display='';
  new bootstrap.Modal('#userModal').show();
}
async function openEdit(id){
  const url=new URL(API, location.origin); url.searchParams.set('action','get'); url.searchParams.set('id',id);
  const js=await (await fetch(url)).json().catch(()=>({ok:false}));
  if(!js.ok){ showToast('No se pudo obtener el usuario','error'); return; }
  document.getElementById('umTitle').textContent='Editar usuario';
  document.getElementById('umId').value=js.data.id;
  document.getElementById('umUsername').value=js.data.username||'';
  document.getElementById('umNombre').value=js.data.nombre||'';
  document.getElementById('umEmail').value=js.data.email||'';
  document.getElementById('umTelefono').value=js.data.telefono||'';
  document.getElementById('umRole').value=js.data.role||'deposito';
  document.getElementById('umPassword').value='';
  document.getElementById('umPassRow').style.display='none';
  new bootstrap.Modal('#userModal').show();
}
async function saveUser(){
  if(!IS_ADMIN){ showToast('Solo admin puede modificar','error'); return false; }
  const id = document.getElementById('umId').value;
  const fd = new FormData();
  if(id){
    fd.append('action','update');
    fd.append('id', id);
  }else{
    fd.append('action','create');
    const pass = document.getElementById('umPassword').value;
    if(!pass || pass.length<6){ showToast('Password mínimo 6 caracteres','error'); return false; }
    fd.append('password', pass);
  }
  fd.append('username', document.getElementById('umUsername').value.trim());
  fd.append('nombre',   document.getElementById('umNombre').value.trim());
  fd.append('email',    document.getElementById('umEmail').value.trim());
  fd.append('telefono', document.getElementById('umTelefono').value.trim());
  fd.append('role',     document.getElementById('umRole').value);

  const js = await (await fetch(API, {method:'POST', body: fd})).json().catch(()=>({ok:false}));
  if(js.ok){
    showToast('Guardado correctamente');
    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
    loadList();
  }else{
    showToast(js.error||'No se pudo guardar','error');
  }
  return false;
}

/* ==== Activar/Desactivar ==== */
async function toggleUser(id){
  if(!IS_ADMIN){ showToast('Solo admin puede modificar','error'); return; }
  const fd=new FormData(); fd.append('action','toggle'); fd.append('id', id);
  const js=await (await fetch(API,{method:'POST', body: fd})).json().catch(()=>({ok:false}));
  if(js.ok){ showToast('Estado actualizado'); loadList(); } else { showToast(js.error||'Error','error'); }
}

/* ==== Reset Password ==== */
function openReset(id){
  if(!IS_ADMIN){ showToast('Solo admin puede modificar','error'); return; }
  document.getElementById('rpId').value=id;
  document.getElementById('rpPass').value='';
  new bootstrap.Modal('#passModal').show();
}
async function doResetPass(){
  if(!IS_ADMIN){ showToast('Solo admin puede modificar','error'); return false; }
  const id=document.getElementById('rpId').value;
  const pw=document.getElementById('rpPass').value;
  if(!pw || pw.length<6){ showToast('Password mínimo 6 caracteres','error'); return false; }
  const fd=new FormData(); fd.append('action','reset_password'); fd.append('id',id); fd.append('password',pw);
  const js=await (await fetch(API,{method:'POST', body: fd})).json().catch(()=>({ok:false}));
  if(js.ok){ showToast('Contraseña actualizada'); bootstrap.Modal.getInstance(document.getElementById('passModal')).hide(); }
  else{ showToast(js.error||'No se pudo actualizar','error'); }
  return false;
}

document.addEventListener('DOMContentLoaded', loadList);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>