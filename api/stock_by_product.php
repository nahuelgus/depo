<?php
// /app/deposito/api/stock_by_product.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
header('Content-Type: application/json; charset=utf-8');

function ok($d=[]){ echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function err($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE); exit; }

/** Modo SIMPLE: solo depo_stock, agrupa por lot_id */
function query_simple(int $pid, ?int $dep): array {
  $where  = "s.product_id = ?";
  $params = [$pid];
  if ($dep !== null) { $where .= " AND s.deposit_id = ?"; $params[] = $dep; }

  $sql = "
    SELECT s.lot_id, SUM(s.cantidad) AS cantidad
    FROM depo_stock s
    WHERE $where
    GROUP BY s.lot_id
    ORDER BY (s.lot_id IS NULL) DESC
  ";
  $rows = DB::all($sql, $params);
  $out = [];
  foreach($rows as $r){
    $out[] = [
      'lot_id'   => $r['lot_id'] !== null ? (int)$r['lot_id'] : null,
      'cantidad' => (float)$r['cantidad'],
      'nro_lote' => null,
      'vto'      => null,
    ];
  }
  return $out;
}

/** Modo ENRIQUECIDO: LEFT JOIN con depo_lots, autodetecta columnas numero/nro/codigo y vto/vencimiento */
function query_enriched(int $pid, ?int $dep): array {
  // ¿Existe la tabla?
  $hasLots = (bool) DB::all("SHOW TABLES LIKE 'depo_lots'");
  if (!$hasLots) return query_simple($pid, $dep);

  // Detectar columnas reales
  $colsRes = DB::all("SHOW COLUMNS FROM depo_lots");
  $cols = array_map(fn($r)=>$r['Field']??$r['COLUMN_NAME']??'', $colsRes);

  $numCol = in_array('numero',$cols) ? 'numero'
         : (in_array('nro',$cols)    ? 'nro'
         : (in_array('codigo',$cols) ? 'codigo' : null));
  $vtoCol = in_array('vto',$cols) ? 'vto'
         : (in_array('vencimiento',$cols) ? 'vencimiento' : null);

  $selectNum = $numCol ? "l.`$numCol` AS nro_lote" : "NULL AS nro_lote";
  $selectVto = $vtoCol ? "l.`$vtoCol` AS vto"     : "NULL AS vto";

  // Para GROUP BY usamos las expresiones SIN alias
  $groupNum = $numCol ? "l.`$numCol`" : "NULL";
  $groupVto = $vtoCol ? "l.`$vtoCol`" : "NULL";

  $where  = "s.product_id = ?";
  $params = [$pid];
  if ($dep !== null) { $where .= " AND s.deposit_id = ?"; $params[] = $dep; }

  $sql = "
    SELECT
      s.lot_id,
      $selectNum,
      $selectVto,
      SUM(s.cantidad) AS cantidad
    FROM depo_stock s
    LEFT JOIN depo_lots l ON l.id = s.lot_id
    WHERE $where
    GROUP BY s.lot_id, $groupNum, $groupVto
    ORDER BY (s.lot_id IS NULL) DESC, vto IS NULL DESC, vto ASC
  ";
  $rows = DB::all($sql, $params);

  $out  = [];
  foreach($rows as $r){
    $out[] = [
      'lot_id'   => $r['lot_id'] !== null ? (int)$r['lot_id'] : null,
      'cantidad' => (float)$r['cantidad'],
      'nro_lote' => $r['nro_lote'] ?? null,
      'vto'      => $r['vto'] ?? null,
    ];
  }
  return $out;
}

try{
  $pid = (int)($_GET['product_id'] ?? 0);
  if ($pid <= 0) err('product_id requerido');

  $dep   = isset($_GET['deposit_id']) && $_GET['deposit_id']!=='' ? (int)$_GET['deposit_id'] : null;
  $debug = isset($_GET['debug']) ? 1 : 0;

  $mode = 'enriched';
  try {
    $items = query_enriched($pid, $dep);
  } catch (Throwable $e) {
    $mode = 'simple_fallback';
    $errDetail = $e->getMessage();
    $items = query_simple($pid, $dep);
  }

  $resp = ['ok'=>true,'items'=>$items];
  if ($debug) $resp['_meta'] = ['mode'=>$mode] + (isset($errDetail)?['error'=>$errDetail]:[]);
  echo json_encode($resp, JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}