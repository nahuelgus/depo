<?php
// /app/deposito/public/transfers_list.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
$app = require __DIR__ . '/../config/app.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- helpers ----------
function norm_date(?string $d, string $fallback): string {
  $d = trim((string)$d);
  if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $fallback;
  return $d;
}
function deposit_exists(int $id): bool {
  if ($id<=0) return false;
  $r = DB::all("SELECT 1 FROM depo_deposits WHERE id=? LIMIT 1",[$id]);
  return (bool)($r[0][1] ?? $r[0]['1'] ?? $r);
}

// ---------- filtros ----------
$desde = norm_date($_GET['desde'] ?? null, date('Y-m-01'));
$hasta = norm_date($_GET['hasta'] ?? null, date('Y-m-d'));
$dep   = (int)($_GET['dep'] ?? 0);
$debug = isset($_GET['debug']);

$depos = DB::all("SELECT id,nombre FROM depo_deposits ORDER BY id");

try {
  // si el depósito no existe, ignoro el filtro para no romper
  $useDep = ($dep>0 && deposit_exists($dep));

  $cond = "WHERE t.fecha BETWEEN :d1 AND :d2";
  $params = [':d1'=>"$desde 00:00:00", ':d2'=>"$hasta 23:59:59"];

  if ($useDep) {
    // filtro por origen o destino
    $cond .= " AND (t.from_deposit_id=:dep_f OR t.to_deposit_id=:dep_t)";
    $params[':dep_f'] = $dep;
    $params[':dep_t'] = $dep;
  }

  // consulta robusta
  $rows = DB::all("
    SELECT t.id, t.fecha, t.status, t.observacion,
           do.nombre AS dep_origen, dd.nombre AS dep_destino,
           u.username, u.nombre AS user_name,
           (SELECT COUNT(*) FROM depo_transfer_items ti WHERE ti.transfer_id=t.id) AS items
    FROM depo_transfers t
    JOIN depo_deposits do ON do.id=t.from_deposit_id
    JOIN depo_deposits dd ON dd.id=t.to_deposit_id
    LEFT JOIN depo_users u ON u.id=t.user_id
    $cond
    ORDER BY t.id DESC
  ", $params);

} catch (Throwable $e) {
  if ($debug) {
    // modo diagnóstico (agregar &debug=1 en la URL)
    http_response_code(200);
    echo "<pre style='padding:16px;color:#b00;background:#fee;border:1px solid #f99'>";
    echo "ERROR: ".$e->getMessage()."\n\nTrace:\n".$e->getTraceAsString();
    echo "</pre>";
    exit;
  } else {
    // modo normal: página amable sin 500
    $rows = [];
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Transferencias | <?=h($app['APP_NAME'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/app/deposito/assets/skin.css" rel="stylesheet">
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
  <h1 class="h5 mb-3">Transferencias</h1>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input class="form-control" type="date" name="desde" value="<?=h($desde)?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input class="form-control" type="date" name="hasta" value="<?=h($hasta)?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Depósito (origen o destino)</label>
      <select name="dep" class="form-select">
        <option value="0">(Todos)</option>
        <?php foreach($depos as $d): $id=(int)$d['id']; ?>
          <option value="<?=$id?>" <?=$dep===$id?'selected':''?>><?=h($d['nombre'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-outline-primary w-100">Filtrar</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Origen → Destino</th>
          <th>Ítems</th>
          <th>Usuario</th>
          <th>Obs.</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>#<?=h($r['id'])?></td>
            <td><?=h(substr($r['fecha'],0,16))?></td>
            <td><?=h($r['dep_origen'])?> → <?=h($r['dep_destino'])?></td>
            <td><?=h($r['items'])?></td>
            <td><?=h($r['user_name'] ?: $r['username'] ?: '-')?></td>
            <td class="text-truncate" style="max-width:260px;" title="<?=h($r['observacion'])?>"><?=h($r['observacion'])?></td>
            <td><span class="badge bg-success"><?=h($r['status'])?></span></td>
            <td><a class="btn btn-sm btn-outline-secondary" href="/app/deposito/public/transfers_view.php?id=<?=h($r['id'])?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!count($rows)): ?>
          <tr><td colspan="8" class="text-muted text-center py-4">Sin resultados</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>