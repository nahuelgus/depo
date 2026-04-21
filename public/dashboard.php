<?php
// /app/deposito/public/dashboard.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Detección de rol robusta (sin depender de user_has_role)
$sessionRole = strtolower((string)($_SESSION['user']['role'] ?? ''));
if (function_exists('user_has_role')) {
  // Si tu helper existe, lo usamos como fuente de verdad
  $is_admin      = user_has_role('admin');
  $is_supervisor = user_has_role('supervisor');
  $is_deposito   = user_has_role('deposito');
} else {
  $is_admin      = ($sessionRole === 'admin');
  $is_supervisor = ($sessionRole === 'supervisor');
  $is_deposito   = ($sessionRole === 'deposito') || (!$is_admin && !$is_supervisor);
}

// Selector de depósito (navbar)
$depActivo = (int)($_SESSION['deposit_id'] ?? 0);
$deps = DB::all("SELECT id,nombre FROM depo_deposits WHERE is_activo=1 ORDER BY nombre ASC");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  :root{ --card-radius:.9rem; --card-shadow:0 8px 24px rgba(0,0,0,.08); }
  .apps-grid{ display:grid; gap:1rem; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); }
  .app-card{
    position:relative; background:#fff; border:1px solid #e9ecef; border-radius:var(--card-radius);
    padding:1rem; overflow:hidden; transition:.18s ease; transform:translateY(0);
    opacity:0; animation:fadeUp .35s ease forwards;
  }
  .app-card:hover{ transform:translateY(-4px); box-shadow:var(--card-shadow); border-color:#e5e7eb; }
  .app-emoji{
    font-size:1.6rem; line-height:1; width:44px; height:44px; display:grid; place-items:center;
    border-radius:12px; background:#f6f7f9; box-shadow:inset 0 0 0 1px #eef0f2; flex:0 0 44px; animation:pop-in .5s ease both;
  }
  .app-title{ margin:0; font-weight:600; } .app-desc{ margin:0; color:#6c757d; font-size:.875rem; }
  .stretched-link{text-decoration:none;}
  @keyframes pop-in{ from{ transform:scale(.9); opacity:0 } to{ transform:scale(1); opacity:1 } }
  @keyframes fadeUp{ from{ transform:translateY(6px); opacity:0 } to{ transform:translateY(0); opacity:1 } }
  .app-card:nth-child(1){ animation-delay:.02s } .app-card:nth-child(2){ animation-delay:.06s }
  .app-card:nth-child(3){ animation-delay:.10s } .app-card:nth-child(4){ animation-delay:.14s }
  .app-card:nth-child(5){ animation-delay:.18s } .app-card:nth-child(6){ animation-delay:.22s }
  .badge-soft{ background:#f6f7f9; border:1px solid #eef0f2; color:#6c757d; }

  /* Alertas vencimiento */
  .alerts-wrap{ border:1px solid #e9ecef; border-radius:var(--card-radius); background:#fff; }
  .alert-item{ display:flex; gap:.75rem; padding:.75rem 1rem; border-top:1px dashed #eef0f2; align-items:flex-start;}
  .alert-item:first-child{ border-top:none; }
  .alert-emoji{ font-size:1.25rem }
  .alert-title{ margin:0; font-weight:600; }
  .alert-sub{ margin:0; color:#6c757d; font-size:.875rem; }
  .chip{ display:inline-block; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; background:#f6f7f9; border:1px solid #eef0f2; color:#555; }
  .danger{ background:#fff5f5; border-color:#ffe0e0; color:#b42318; }
  .warn{ background:#fff9eb; border-color:#ffe9c2; color:#b26b00; }

  footer.site{ border-top:1px solid #e9ecef; margin-top:2rem; padding-top:1rem;
               display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem;}
  footer.site img.logo{ height:36px; width:auto; border-radius:8px; }
  .brand-wrap{ display:flex; align-items:center; gap:.5rem; }
  .brand-wrap img{ height:28px; width:28px; border-radius:6px; }
</style>
</head>
<body>

<nav class="navbar navbar-light border-bottom px-3">
  <div class="brand-wrap">
    <img src="https://edesign.ar/wp-content/uploads/2025/05/cropped-LOOOGOO-scaled-1-300x300.png" alt="Logo">
    <a class="navbar-brand" href="/app/deposito/public/dashboard.php"><?=h($app['APP_NAME'])?></a>
  </div>
  <div class="ms-auto d-flex align-items-center gap-2">
    <!-- Selector de depósito (para todos los roles) -->
    <select id="navDeposit" class="form-select form-select-sm" style="min-width:220px">
      <?php foreach($deps as $d): ?>
        <option value="<?=$d['id']?>" <?=$depActivo===(int)$d['id']?'selected':''?>><?=h($d['nombre'])?></option>
      <?php endforeach; ?>
    </select>
    <a href="/app/deposito/public/logout.php" class="btn btn-sm btn-outline-danger">Salir</a>
  </div>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h5 mb-1">Panel principal</h1>
      <div class="text-muted small">Accesos rápidos y alertas del depósito.</div>
    </div>
    <span class="badge rounded-pill badge-soft">v1 · activo</span>
  </div>

  <!-- GRID de apps -->
  <div class="apps-grid">
    <!-- SIEMPRE visibles -->
    <div class="app-card">
      <div class="d-flex align-items-center gap-3">
        <div class="app-emoji">🧾</div>
        <div class="min-w-0">
          <p class="app-title">Nueva venta</p>
          <p class="app-desc">Generar remito</p>
        </div>
      </div>
      <a class="stretched-link" href="/app/deposito/public/sales_new.php"></a>
    </div>
<!-- Ingreso de mercadería -->
<div class="app-card">
  <div class="d-flex align-items-center gap-3">
    <div class="app-emoji">📥</div>
    <div class="min-w-0">
      <p class="app-title">Ingreso de mercadería</p>
      <p class="app-desc">Compras / altas de stock</p>
    </div>
  </div>
  <a class="stretched-link" href="/app/deposito/public/purchase_new.php"></a>
</div>

    <div class="app-card">
      <div class="d-flex align-items-center gap-3">
        <div class="app-emoji">👥</div>
        <div class="min-w-0">
          <p class="app-title">Clientes</p>
          <p class="app-desc">ABM y búsqueda</p>
        </div>
      </div>
      <a class="stretched-link" href="/app/deposito/public/clientes.php"></a>
    </div>

    <div class="app-card">
      <div class="d-flex align-items-center gap-3">
        <div class="app-emoji">📦</div>
        <div class="min-w-0">
          <p class="app-title">Productos</p>
          <p class="app-desc">Catálogo</p>
        </div>
      </div>
      <a class="stretched-link" href="/app/deposito/public/products.php"></a>
    </div>
<!-- Stock general -->
<div class="app-card">
  <div class="d-flex align-items-center gap-3">
    <div class="app-emoji">📋</div>
    <div class="min-w-0">
      <p class="app-title">Stock</p>
      <p class="app-desc">Totales y filtros</p>
    </div>
  </div>
  <a class="stretched-link" href="/app/deposito/public/stock.php"></a>
</div>

    <div class="app-card">
      <div class="d-flex align-items-center gap-3">
        <div class="app-emoji">🏷️</div>
        <div class="min-w-0">
          <p class="app-title">Lotes</p>
          <p class="app-desc">Stock por lote</p>
        </div>
      </div>
      <a class="stretched-link" href="/app/deposito/public/lots.php"></a>
    </div>
 <div class="app-card">
    <div class="d-flex align-items-center gap-3">
      <div class="app-emoji">🏷️</div>
      <div class="min-w-0">
        <p class="app-title">Categorías</p>
        <p class="app-desc">ABM de categorías</p>
      </div>
    </div>
    <a class="stretched-link" href="/app/deposito/public/categories.php"></a>
  </div>
    <div class="app-card">
      <div class="d-flex align-items-center gap-3">
        <div class="app-emoji">🔁</div>
        <div class="min-w-0">
          <p class="app-title">Movimientos</p>
          <p class="app-desc">Entradas / Salidas</p>
        </div>
      </div>
      <a class="stretched-link" href="/app/deposito/public/movements.php"></a>
    </div>

    <!-- Admin / Supervisor: tarjetas extra -->
    <?php if (!$is_deposito): ?>
      <div class="app-card">
        <div class="d-flex align-items-center gap-3">
          <div class="app-emoji">🏢</div>
          <div class="min-w-0">
            <p class="app-title">Depósitos</p>
            <p class="app-desc">Sucursales / ABM</p>
          </div>
        </div>
        <a class="stretched-link" href="/app/deposito/public/deposits.php"></a>
      </div>

      <div class="app-card">
        <div class="d-flex align-items-center gap-3">
          <div class="app-emoji">🔄</div>
          <div class="min-w-0">
            <p class="app-title">Transferencias</p>
            <p class="app-desc">Entre depósitos</p>
          </div>
        </div>
        <a class="stretched-link" href="/app/deposito/public/transfers_new.php"></a>
      </div>

      <div class="app-card">
        <div class="d-flex align-items-center gap-3">
          <div class="app-emoji">📊</div>
          <div class="min-w-0">
            <p class="app-title">Ventas</p>
            <p class="app-desc">Listado · Imprimir/Descargar</p>
          </div>
        </div>
        <a class="stretched-link" href="/app/deposito/public/sales_list.php"></a>
      </div>

      <div class="app-card">
        <div class="d-flex align-items-center gap-3">
          <div class="app-emoji">📈</div>
          <div class="min-w-0">
            <p class="app-title">Reportes</p>
            <p class="app-desc">Listados y exportes</p>
          </div>
        </div>
        <a class="stretched-link" href="/app/deposito/public/reports.php"></a>
      </div>

      <div class="app-card">
        <div class="d-flex align-items-center gap-3">
          <div class="app-emoji">🔐</div>
          <div class="min-w-0">
            <p class="app-title">Usuarios</p>
            <p class="app-desc">Alta y edición</p>
          </div>
        </div>
        <a class="stretched-link" href="/app/deposito/public/users.php"></a>
      </div>
    <?php endif; ?>
  </div>

  <!-- ALERTAS: lotes próximos a vencer -->
  <div class="alerts-wrap my-4">
    <div class="d-flex justify-content-between align-items-center p-3">
      <div class="d-flex align-items-center gap-2">
        <span class="alert-emoji">⏰</span>
        <div>
          <div class="fw-semibold">Lotes con vencimiento próximo</div>
          <div class="text-muted small">Dentro de <span id="al_days">30</span> días</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <label class="text-muted small">Días</label>
        <input id="al_inDays" type="number" min="1" max="365" class="form-control form-control-sm" value="30" style="width:90px">
        <button class="btn btn-sm btn-outline-secondary" id="al_reload">Actualizar</button>
      </div>
    </div>
    <div id="alerts_body" class="pb-2"></div>
  </div>

  <!-- Footer -->
  <footer class="site">
    <div class="d-flex align-items-center gap-2">
      <img class="logo" src="https://edesign.ar/wp-content/uploads/2025/05/cropped-LOOOGOO-scaled-1-300x300.png" alt="Logo">
      <span class="text-muted small">Sistema impulsado por <a href="https://edesign.ar" target="_blank" rel="noopener">edesign.ar</a></span>
    </div>
    <a class="btn btn-success" target="_blank" rel="noopener" href="https://wa.me/541158229823">💬 Soporte Técnico</a>
  </footer>
</div>

<script>
// Cambiar depósito activo desde el navbar
document.getElementById('navDeposit')?.addEventListener('change', async (e)=>{
  const fd = new FormData(); fd.append('deposit_id', e.target.value);
  const r  = await fetch('/app/deposito/api/session_api.php?action=set_deposit', { method:'POST', body: fd });
  try{ const js = await r.json(); if(js.ok){ location.reload(); } }catch(_){}
});

const API_ALERTS = '/app/deposito/api/lots_alerts.php';
function daysDiff(dateStr){
  const d = new Date(dateStr+'T00:00:00'); const t = new Date();
  const ms = d - new Date(t.getFullYear(), t.getMonth(), t.getDate());
  return Math.ceil(ms/86400000);
}
function esc(s){ return (s??'').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]||c)); }

async function loadAlerts(days=30){
  document.getElementById('al_days').textContent = days;
  const url = new URL(API_ALERTS, location.origin);
  url.searchParams.set('days', days);
  url.searchParams.set('limit', 12);
  const body = document.getElementById('alerts_body');
  body.innerHTML = '<div class="p-3 text-muted">Cargando…</div>';

  try{
    const js = await (await fetch(url)).json();
    if(!js.ok){ body.innerHTML = '<div class="p-3 text-danger">No se pudieron cargar las alertas.</div>'; return; }

    if(!js.items.length){
      body.innerHTML = '<div class="p-3 text-muted">No hay lotes por vencer en este rango.</div>';
      return;
    }

    body.innerHTML = js.items.map(l=>{
      const dd = daysDiff(l.vto);
      const cls = dd <= 7 ? 'danger' : 'warn';
      return `
        <div class="alert-item">
          <div class="alert-emoji">⚠️</div>
          <div class="flex-grow-1">
            <p class="alert-title mb-1">${esc(l.producto)} ${l.presentacion?'<span class="text-muted">'+esc(l.presentacion)+'</span>':''}</p>
            <p class="alert-sub mb-1">Lote <strong>${esc(l.nro_lote||('#'+l.lot_id))}</strong></p>
          </div>
          <span class="chip ${cls}">${dd} días</span>
        </div>`;
    }).join('');
  }catch(e){
    body.innerHTML = '<div class="p-3 text-danger">Error de red al consultar alertas.</div>';
  }
}

document.getElementById('al_reload').addEventListener('click', ()=>{
  const v = parseInt(document.getElementById('al_inDays').value||'30',10);
  loadAlerts(isNaN(v)?30:v);
});
document.addEventListener('DOMContentLoaded', ()=> loadAlerts(30));
</script>

</body>
</html>