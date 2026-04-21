<?php
// /app/deposito/api/products_category_api.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']); // asignar categoría: admin/supervisor

header('Content-Type: application/json; charset=utf-8');
function ok($d=[]){ echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function err($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE); exit; }

try{
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') err('invalid_method',405);
  $pid = (int)($_POST['product_id'] ?? 0);
  $cid = $_POST['category_id'] ?? null;
  if ($pid<=0) err('product_id requerido');
  if ($cid==='' || $cid==='0') $cid = null; else $cid = (int)$cid;

  // validar existencia (si se envía)
  if ($cid!==null){
    $ex = DB::all("SELECT id FROM depo_categories WHERE id=? AND is_activo=1 LIMIT 1",[$cid]);
    if (!$ex) err('categoría inválida');
  }

  DB::exec("UPDATE depo_products SET category_id = :cid WHERE id=:pid LIMIT 1",[":cid"=>$cid,":pid"=>$pid]);
  ok(['product_id'=>$pid,'category_id'=>$cid]);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
