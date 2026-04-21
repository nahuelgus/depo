<?php
// /app/deposito/public/categories.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']); // solo estos gestionan
$app = require __DIR__ . '/../config/app.php';
function eh($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categorías | <?=eh($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>.table-tight td,.table-tight th{padding:.5rem .6rem}</style>
</head>
<body>
<nav class="navbar navbar-light border-bottom px-3">
  <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=eh($app['APP_NAME'])?></a>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h5 mb-1">Categorías</h1>
      <div class="text-muted small">Crear, renombrar y eliminar (si no están en uso).</div>
    </div>
    <div class="d-flex gap-2">
      <input id="newName" class="form-control form-control-sm" placeholder="Nueva categoría">
      <button class="btn btn-sm btn-dark" onclick="createCat()">Agregar</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered table-tight align-middle">
      <thead class="table-light"><tr><th style="width:80px">ID</th><th>Nombre</th><th style="width:120px"></th></tr></thead>
      <tbody id="tb"></tbody>
    </table>
  </div>
</div>

<script>
const API='/app/deposito/api/categories_api.php';
function esc(s){return (s??'').toString().replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]||c));}

async function load(){
  const js=await (await fetch(API+'?action=list')).json().catch(()=>({ok:false,items:[]}));
  const tb=document.getElementById('tb');
  if(!js.ok){ tb.innerHTML='<tr><td colspan="3" class="text-center text-danger">Error</td></tr>'; return; }
  if(!js.items.length){ tb.innerHTML='<tr><td colspan="3" class="text-center text-muted">Sin categorías</td></tr>'; return; }
  tb.innerHTML = js.items.map(c=>`
    <tr>
      <td class="text-muted">#${c.id}</td>
      <td><input class="form-control form-control-sm" value="${esc(c.nombre)}" data-id="${c.id}"></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick="save(${c.id})">Guardar</button>
        <button class="btn btn-sm btn-outline-danger" onclick="delCat(${c.id})">Borrar</button>
      </td>
    </tr>
  `).join('');
}

async function createCat(){
  const v=document.getElementById('newName').value.trim();
  if(!v) return;
  const fd=new FormData(); fd.append('nombre', v);
  const js=await (await fetch(API+'?action=create',{method:'POST',body:fd})).json().catch(()=>({ok:false}));
  if(js.ok){ document.getElementById('newName').value=''; load(); } else { alert(js.error||'Error'); }
}

async function save(id){
  const inp=document.querySelector(`input[data-id="${id}"]`); const v=inp.value.trim(); if(!v) return;
  const fd=new FormData(); fd.append('id', id); fd.append('nombre', v);
  const js=await (await fetch(API+'?action=update',{method:'POST',body:fd})).json().catch(()=>({ok:false}));
  if(!js.ok) alert(js.error||'Error');
}

async function delCat(id){
  if(!confirm('¿Eliminar categoría? Solo si no está asignada a productos.')) return;
  const fd=new FormData(); fd.append('id', id);
  const js=await (await fetch(API+'?action=delete',{method:'POST',body:fd})).json().catch(()=>({ok:false}));
  if(js.ok){ load(); } else { alert(js.error==='categoria_en_uso'?'No se puede eliminar: está en uso.':(js.error||'Error')); }
}

document.addEventListener('DOMContentLoaded', load);
</script>
</body>
</html>
