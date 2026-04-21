<?php
// /app/deposito/api/stock_list_api.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
header('Content-Type: application/json; charset=utf-8');

function ok($d=[]){ echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function err($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE); exit; }

try{
  $q    = trim((string)($_GET['q'] ?? ''));
  $cat  = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
  $dep  = isset($_GET['deposit_id'])  && $_GET['deposit_id']  !== '' ? (int)$_GET['deposit_id']  : null;
  $only = (int)($_GET['only_positive'] ?? 1);

  // WHERE de productos (¡ojo! usamos COALESCE para soportar category_id o categoria_id)
  $where = [];
  $params = [];

  if ($q !== '') {
    $like = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q).'%';
    $where[] = '(p.nombre LIKE ? OR p.barcode LIKE ?)';
    array_push($params, $like, $like);
  }

  if ($cat !== null) {
    $where[] = 'COALESCE(p.category_id, p.categoria_id) = ?';
    $params[] = $cat;
  }

  // Suma robusta por producto (CASE para no romper la LEFT JOIN al filtrar depósito)
  $sql = "
    SELECT
      p.id AS product_id,
      p.nombre AS producto,
      c.nombre AS categoria,
      COALESCE(SUM(CASE WHEN ".($dep!==null ? "s.deposit_id = ?" : "1=1")." THEN s.cantidad ELSE 0 END),0) AS stock
    FROM depo_products p
    LEFT JOIN depo_categories c
           ON c.id = COALESCE(p.category_id, p.categoria_id)
    LEFT JOIN depo_stock s
           ON s.product_id = p.id
  ";

  if ($where) {
    $sql .= " WHERE ".implode(' AND ', $where);
  }

  // el param del CASE (depósito) va primero si existe
  if ($dep !== null) array_unshift($params, $dep);

  $sql .= " GROUP BY p.id, p.nombre, c.nombre";
  if ($only === 1) $sql .= " HAVING stock > 0";
  $sql .= " ORDER BY producto ASC LIMIT 2000";

  $rows = DB::all($sql, $params);

  // Detalle por depósito para los productos listados
  $items = [];
  if ($rows) {
    $ids = array_column($rows, 'product_id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    $detParams = $ids;
    $detSql = "
      SELECT s.product_id, s.deposit_id, d.nombre deposito, SUM(s.cantidad) cantidad
      FROM depo_stock s
      JOIN depo_deposits d ON d.id = s.deposit_id
      WHERE s.product_id IN ($ph)
    ";
    if ($dep !== null) { $detSql .= " AND s.deposit_id = ?"; $detParams[] = $dep; }
    $detSql .= " GROUP BY s.product_id, s.deposit_id, d.nombre";

    $det = DB::all($detSql, $detParams);
    $byProd = [];
    foreach ($det as $r){
      $pid = (int)$r['product_id'];
      $byProd[$pid] = $byProd[$pid] ?? [];
      $byProd[$pid][] = ['deposito'=>$r['deposito'], 'cantidad'=>(float)$r['cantidad']];
    }

    foreach ($rows as $r){
      $pid = (int)$r['product_id'];
      $items[] = [
        'product_id' => $pid,
        'producto'   => $r['producto'],
        'categoria'  => $r['categoria'],
        'stock'      => (float)$r['stock'],
        'detalle'    => $byProd[$pid] ?? [],
      ];
    }
  }

  ok(['items'=>$items]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}