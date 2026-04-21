<?php
// /app/deposito/public/clientes.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clientes | <?=$app['APP_NAME']?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  .table thead th { white-space: nowrap; }
  .modal-header .subtitle { font-size:.875rem; color:#6c757d; }
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
      <h1 class="h5 mb-0">Clientes</h1>
      <div class="text-muted small">Gestioná clientes para remitos/ventas. Buscá por Razón social, Doc, Email, Tel o Referente.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-dark btn-sm" id="btnNuevo">+ Nuevo</button>
    </div>
  </div>

  <!-- Filtros -->
  <div class="row g-2 mb-3">
    <div class="col-md-6">
      <input type="text" id="q" class="form-control" placeholder="Buscar... (mín. 2 caracteres)">
    </div>
    <div class="col-md-6 text-end">
      <button class="btn btn-outline-secondary" id="btnLimpiar">Limpiar</button>
      <button class="btn btn-outline-primary" id="btnBuscar">Buscar</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle" id="grid">
      <thead class="table-light">
        <tr>
          <th style="width:70px">ID</th>
          <th>Razón social</th>
          <th style="width:140px">Doc</th>
          <th style="width:160px">Tel</th>
          <th style="width:200px">Email</th>
          <th>Referente</th>
          <th>Domicilio</th>
          <th style="width:140px">Ciudad</th>
          <th style="width:120px">Creado</th>
          <th style="width:120px"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between mt-2">
    <div class="text-muted small" id="lblTotal">0 registros</div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" id="btnPrev" disabled>&laquo; Anterior</button>
      <button class="btn btn-outline-secondary btn-sm" id="btnNext" disabled>Siguiente &raquo;</button>
    </div>
  </div>
</div>

<!-- Modal Alta/Edición -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="mdTitle">Nuevo cliente</h5>
          <div class="subtitle" id="mdSub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="frmCliente" onsubmit="return false">
          <input type="hidden" id="id" name="id">

          <div class="row g-2">
            <div class="col-md-2">
              <label class="form-label">Tipo ID</label>
              <input type="number" class="form-control" id="tipo_id" name="tipo_id" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Razón social <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="razon_social" name="razon_social" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Doc (CUIT/DNI)</label>
              <input type="text" class="form-control" id="doc" name="doc">
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-md-4">
              <label class="form-label">Tel</label>
              <input type="text" class="form-control" id="tel" name="tel">
            </div>
            <div class="col-md-5">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email">
            </div>
            <div class="col-md-3">
              <label class="form-label">Referente</label>
              <input type="text" class="form-control" id="referente" name="referente">
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-md-6">
              <label class="form-label">Domicilio</label>
              <input type="text" class="form-control" id="domicilio" name="domicilio">
            </div>
            <div class="col-md-4">
              <label class="form-label">Ciudad</label>
              <input type="text" class="form-control" id="ciudad" name="ciudad">
            </div>
            <div class="col-md-2">
              <label class="form-label">ID interno</label>
              <input type="text" class="form-control" id="id_lbl" disabled>
            </div>
          </div>

          <div class="mt-2">
            <label class="form-label">Nota</label>
            <textarea class="form-control" id="nota" name="nota" rows="2"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-dark" id="btnGuardar">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/app/deposito/api/clientes_api.php';
let PAGE = 1, NEXT = null;

// ===== Helpers =====
function esc(s){ return (s??'').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function fmtDate(dt){ if(!dt) return ''; // 2025-10-02 12:34:56 -> 02/10/2025
  const [d, t] = dt.split(' '); const [Y,M,D] = d.split('-'); return `${D}/${M}/${Y}`; }

// ===== Cargar grilla =====
async function load(page=1, q=''){
  PAGE = page;
  const url = new URL(API, location.origin);
  url.searchParams.set(q ? 'action':'action', q ? 'search':'list');
  if (q) url.searchParams.set('q', q);
  url.searchParams.set('page', page);
  url.searchParams.set('limit', 25);

  const res = await fetch(url);
  const js  = await res.json();
  if(!js.ok){ alert('Error al cargar'); return; }

  const items = js.items || [];
  NEXT = js.next || null;

  const tb = document.querySelector('#grid tbody');
  tb.innerHTML = items.map(r=>`
    <tr>
      <td class="text-muted">${r.id}</td>
      <td>${esc(r.razon_social)}</td>
      <td>${esc(r.doc)}</td>
      <td>${esc(r.tel)}</td>
      <td>${esc(r.email)}</td>
      <td>${esc(r.referente)}</td>
      <td>${esc(r.domicilio)}</td>
      <td>${esc(r.ciudad)}</td>
      <td>${fmtDate(r.created_at||'')}</td>
      <td class="text-end">
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-primary" onclick="edit(${r.id})">Editar</button>
          <button class="btn btn-sm btn-outline-danger" onclick="delc(${r.id})">Borrar</button>
        </div>
      </td>
    </tr>
  `).join('');

  document.getElementById('lblTotal').textContent = `${items.length} registros`;
  document.getElementById('btnPrev').disabled = (PAGE<=1);
  document.getElementById('btnNext').disabled = (NEXT===null);
}

// ===== Buscar / paginado =====
document.getElementById('btnBuscar').addEventListener('click', ()=> load(1, document.getElementById('q').value.trim()) );
document.getElementById('btnLimpiar').addEventListener('click', ()=> { document.getElementById('q').value=''; load(1,''); });
document.getElementById('btnPrev').addEventListener('click', ()=> { if(PAGE>1) load(PAGE-1, document.getElementById('q').value.trim()); });
document.getElementById('btnNext').addEventListener('click', ()=> { if(NEXT) load(NEXT, document.getElementById('q').value.trim()); });

// ===== Modal =====
const md = new bootstrap.Modal(document.getElementById('modalCliente'));
document.getElementById('btnNuevo').addEventListener('click', ()=>{
  setForm({});
  document.getElementById('mdTitle').textContent = 'Nuevo cliente';
  document.getElementById('mdSub').textContent   = 'Crear registro';
  md.show();
});

function setForm(r){
  document.getElementById('id').value            = r.id || '';
  document.getElementById('id_lbl').value        = r.id || '';
  document.getElementById('tipo_id').value       = r.tipo_id ?? 0;
  document.getElementById('razon_social').value  = r.razon_social || '';
  document.getElementById('doc').value           = r.doc || '';
  document.getElementById('tel').value           = r.tel || '';
  document.getElementById('email').value         = r.email || '';
  document.getElementById('referente').value     = r.referente || '';
  document.getElementById('domicilio').value     = r.domicilio || '';
  document.getElementById('ciudad').value        = r.ciudad || '';
  document.getElementById('nota').value          = r.nota || '';
}

async function edit(id){
  const url = new URL(API, location.origin);
  url.searchParams.set('action','get'); url.searchParams.set('id', id);
  const js = await (await fetch(url)).json();
  if(!js.ok){ alert('No se pudo obtener el cliente'); return; }
  setForm(js.data||{});
  document.getElementById('mdTitle').textContent = 'Editar cliente';
  document.getElementById('mdSub').textContent   = 'ID ' + (js.data?.id || '');
  md.show();
}

// ===== Guardar =====
document.getElementById('btnGuardar').addEventListener('click', async ()=>{
  const fd = new FormData(document.getElementById('frmCliente'));
  const js = await (await fetch(API+'?action=save', {method:'POST', body:fd})).json();
  if(!js.ok){ alert('No se pudo guardar: '+(js.error||'')); return; }
  md.hide(); load(PAGE, document.getElementById('q').value.trim());
});

// ===== Borrar =====
async function delc(id){
  if(!confirm('¿Eliminar cliente ID '+id+'? Esta acción no se puede deshacer.')) return;
  const fd = new FormData(); fd.append('id', id);
  const js = await (await fetch(API+'?action=delete', {method:'POST', body:fd})).json();
  if(!js.ok){ alert('No se pudo borrar'); return; }
  load(PAGE, document.getElementById('q').value.trim());
}

// Init
load(1,'');
</script>
</body>
</html>