<?php
// /app/deposito/public/sales_list.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']);
$app = require __DIR__ . '/../config/app.php';

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? $today;
$client_id = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int)$_GET['client_id'] : null;

$params = [ $from.' 00:00:00', $to.' 23:59:59' ];
$sql = "
  SELECT s.id, s.fecha, s.total, s.subtotal, s.iva_total, s.status,
         c.razon_social
  FROM depo_sales s
  LEFT JOIN depo_clients c ON c.id = s.client_id
  WHERE s.fecha BETWEEN ? AND ?
";
if ($client_id) {
  $sql .= " AND s.client_id = ? ";
  $params[] = $client_id;
}
$sql .= " ORDER BY s.fecha DESC, s.id DESC LIMIT 500";

$rows = DB::all($sql, $params);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ventas | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
<style>
  .table-tight td, .table-tight th { padding:.45rem .5rem; }
  .toast-container{position:fixed;top:1rem;right:1rem;z-index:2000;}
  /* Estilos para anuladas */
  tr.anulada td { color:#888; text-decoration: line-through; }
  tr.anulada .badge { background:#dc3545 !important; color:#fff !important; text-decoration:none; }
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
      <h1 class="h5 mb-0">Ventas</h1>
      <div class="text-muted small">Listado de remitos</div>
    </div>
    <a class="btn btn-dark btn-sm" href="/app/deposito/public/sales_new.php">Nueva venta</a>
  </div>

  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3" method="get">
    <div class="col-sm-3">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control" value="<?=h($from)?>">
    </div>
    <div class="col-sm-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control" value="<?=h($to)?>">
    </div>
    <div class="col-sm-3">
      <label class="form-label">Cliente (ID)</label>
      <input type="number" name="client_id" class="form-control" value="<?= $client_id ?: '' ?>" placeholder="Opcional">
    </div>
    <div class="col-sm-3 d-flex gap-2">
      <button class="btn btn-outline-primary">Aplicar</button>
      <a class="btn btn-outline-secondary" href="/app/deposito/public/sales_list.php">Limpiar</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle table-tight">
      <thead class="table-light">
        <tr>
          <th style="width:90px">ID</th>
          <th style="width:160px">Fecha</th>
          <th>Cliente</th>
          <th style="width:120px" class="text-end">Subtotal</th>
          <th style="width:90px" class="text-end">IVA</th>
          <th style="width:120px" class="text-end">Total</th>
          <th style="width:110px" class="text-center">Estado</th>
          <th style="width:260px" class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody id="tblBody">
        <?php foreach($rows as $r): 
          $isAnulada = strtolower((string)$r['status']) === 'anulada';
        ?>
          <tr data-id="<?= (int)$r['id'] ?>" data-status="<?= h($r['status']) ?>" class="<?= $isAnulada ? 'anulada' : '' ?>">
            <td class="text-muted">#<?= (int)$r['id'] ?></td>
            <td><?= date('d/m/Y H:i', strtotime((string)$r['fecha'])) ?></td>
            <td><?= h($r['razon_social'] ?? '—') ?></td>
            <td class="text-end">$ <?= number_format((float)$r['subtotal'], 2, ',', '.') ?></td>
            <td class="text-end">$ <?= number_format((float)$r['iva_total'], 2, ',', '.') ?></td>
            <td class="text-end fw-semibold">$ <?= number_format((float)$r['total'], 2, ',', '.') ?></td>
            <td class="text-center"><span class="badge bg-light text-dark"><?= h($r['status']) ?></span></td>
            <td class="text-end">
              <div class="btn-group">
                <a class="btn btn-sm btn-outline-secondary" target="_blank"
                   href="/app/deposito/public/sale_remito.php?id=<?= (int)$r['id'] ?>">Ver</a>
                <a class="btn btn-sm btn-dark" target="_blank"
                   href="/app/deposito/public/sale_remito_pdf.php?id=<?= (int)$r['id'] ?>">Imprimir/Descargar</a>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="anularVenta(<?= (int)$r['id'] ?>)">Anular</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!count($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Toasts -->
<div class="toast-container"></div>

<script>
const API_SALES = '/app/deposito/api/sales_api.php';

function showToast(msg,type='success'){
  const cont=document.querySelector('.toast-container');
  const div=document.createElement('div');
  div.className='toast align-items-center text-bg-'+(type==='error'?'danger':(type==='warn'?'warning':'success'))+' border-0';
  div.role='alert';
  div.innerHTML='<div class="d-flex"><div class="toast-body">'+msg+'</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  cont.appendChild(div);
  const t=new bootstrap.Toast(div,{delay:3000});
  t.show();
  div.addEventListener('hidden.bs.toast',()=>div.remove());
}

async function anularVenta(id){
  const tr = document.querySelector(`tr[data-id="${id}"]`);
  if(!tr) return;
  const status = (tr.dataset.status||'').toLowerCase();
  if(status==='anulada'){ showToast('La venta ya está anulada','warn'); return; }

  const motivo = prompt('Confirmá para ANULAR la venta #'+id+'.\nOpcional: escribí un motivo o dejá vacío.');
  if(motivo===null) return;

  try{
    const fd = new FormData();
    fd.append('sale_id', id);
    if(motivo) fd.append('reason', motivo);

    const res = await fetch(API_SALES+'?action=cancel', { method:'POST', body: fd });
    const js  = await res.json().catch(()=>({ok:false,error:'server_error'}));

    if(js.ok){
      showToast('Venta #'+id+' anulada correctamente');
      tr.dataset.status='anulada';
      tr.classList.add('anulada');
      const badge = tr.querySelector('td:nth-child(7) .badge');
      badge.textContent='anulada';
      badge.classList.remove('bg-light','text-dark');
      badge.classList.add('bg-danger','text-white');
    }else{
      showToast('No se pudo anular: '+(js.detail||js.error||'error'),'error');
    }
  }catch(e){
    showToast('Error de red al anular','error');
  }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>