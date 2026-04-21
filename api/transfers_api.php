<?php
// /app/deposito/api/transfers_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
header('Content-Type: application/json; charset=utf-8');

function JOK(array $d=[]){ echo json_encode(['ok'=>true] + $d, JSON_UNESCAPED_UNICODE); exit; }
function JERR(string $m,int $code=400,array $extra=[]){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$m]+$extra, JSON_UNESCAPED_UNICODE); exit; }

function read_qty(int $product_id, int $deposit_id, ?int $lot_id): float {
  if ($lot_id===null){
    $r = DB::all("SELECT SUM(cantidad) qty
                  FROM depo_stock
                  WHERE product_id=? AND deposit_id=? AND lot_id IS NULL",
                  [$product_id,$deposit_id]);
  } else {
    $r = DB::all("SELECT SUM(cantidad) qty
                  FROM depo_stock
                  WHERE product_id=? AND deposit_id=? AND lot_id=?",
                  [$product_id,$deposit_id,$lot_id]);
  }
  return (float)($r[0]['qty'] ?? 0);
}

function upsert_add(int $product_id,int $deposit_id,?int $lot_id,float $qty): void {
  if ($lot_id===null) {
    $aff = DB::exec("UPDATE depo_stock
                     SET cantidad = cantidad + :q
                     WHERE product_id=:p AND deposit_id=:d AND lot_id IS NULL",
                    [':q'=>$qty,':p'=>$product_id,':d'=>$deposit_id]);
    if ($aff===0){
      DB::exec("INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad)
                VALUES (:p,:d,NULL,:q)",
               [':p'=>$product_id,':d'=>$deposit_id,':q'=>$qty]);
    }
  } else {
    $aff = DB::exec("UPDATE depo_stock
                     SET cantidad = cantidad + :q
                     WHERE product_id=:p AND deposit_id=:d AND lot_id=:l",
                    [':q'=>$qty,':p'=>$product_id,':d'=>$deposit_id,':l'=>$lot_id]);
    if ($aff===0){
      DB::exec("INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad)
                VALUES (:p,:d,:l,:q)",
               [':p'=>$product_id,':d'=>$deposit_id,':l'=>$lot_id,':q'=>$qty]);
    }
  }
}

function subtract_origin(int $product_id,int $deposit_id,?int $lot_id,float $qty): void {
  $disp = read_qty($product_id,$deposit_id,$lot_id);
  if ($disp + 1e-9 < $qty) {
    throw new Exception("Stock insuficiente en origen (prod=$product_id, lote=".($lot_id??'NULL').", disp=$disp, req=$qty)");
  }
  if ($lot_id===null){
    $rows = DB::all("SELECT id, cantidad FROM depo_stock
                     WHERE product_id=? AND deposit_id=? AND lot_id IS NULL AND cantidad>0
                     ORDER BY id ASC", [$product_id,$deposit_id]);
  } else {
    $rows = DB::all("SELECT id, cantidad FROM depo_stock
                     WHERE product_id=? AND deposit_id=? AND lot_id=? AND cantidad>0
                     ORDER BY id ASC", [$product_id,$deposit_id,$lot_id]);
  }
  $rest = $qty;
  foreach($rows as $r){
    if ($rest<=0) break;
    $id=(int)$r['id']; $t=(float)$r['cantidad'];
    if ($t > $rest){
      DB::exec("UPDATE depo_stock SET cantidad=cantidad-:q WHERE id=:id LIMIT 1",
               [':q'=>$rest,':id'=>$id]);
      $rest = 0;
    }else{
      DB::exec("UPDATE depo_stock SET cantidad=0 WHERE id=:id LIMIT 1", [':id'=>$id]);
      $rest -= $t;
    }
  }
  if ($rest>1e-9) throw new Exception("No se pudo descontar todo; faltante=$rest");
}

try{
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = $_GET['action'] ?? $_POST['action'] ?? 'create';
  if ($method!=='POST' || $action!=='create') JERR('invalid_action',405);

  // admite ambos nombres
  $dep_o = (int)($_POST['deposit_origen_id'] ?? $_POST['from_deposit_id'] ?? 0);
  $dep_d = (int)($_POST['deposit_destino_id'] ?? $_POST['to_deposit_id'] ?? 0);
  if ($dep_o<=0 || $dep_d<=0) JERR('origen/destino requeridos');
  if ($dep_o===$dep_d) JERR('origen y destino iguales');

  $fecha_in = trim((string)($_POST['fecha'] ?? date('Y-m-d')));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_in)) JERR('fecha inválida (Y-m-d)');
  $fecha = $fecha_in.' 00:00:00';
  $obs = trim((string)($_POST['obs'] ?? ''));

  $items_raw = $_POST['items_json'] ?? '[]';
  $items = is_array($items_raw) ? $items_raw : json_decode((string)$items_raw,true);
  if (!is_array($items) || !count($items)) JERR('items vacíos');

  $rows = [];
  foreach($items as $i){
    $pid=(int)($i['product_id'] ?? 0);
    $lot=$i['lot_id'] ?? null;
    $qty=(float)($i['cantidad'] ?? 0);
    if ($pid<=0 || $qty<=0) JERR('item inválido');
    if ($lot==='' || $lot===0 || $lot==='0') $lot=null;
    else $lot = $lot!==null ? (int)$lot : null;
    $rows[]=['pid'=>$pid,'lot_id'=>$lot,'qty'=>$qty];
  }

  // usuario actual
  $user_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);

  DB::pdo()->beginTransaction();

  // Cabecera de transferencia
  $transfer_id = null;
  $hasTransfers = (bool) DB::all("SHOW TABLES LIKE 'depo_transfers'");
  if($hasTransfers){
    DB::exec("INSERT INTO depo_transfers (from_deposit_id,to_deposit_id,fecha,status,observacion,user_id,created_at)
              VALUES (:o,:d,:f,'confirmada',:obs,:uid,NOW())",
              [':o'=>$dep_o,':d'=>$dep_d,':f'=>$fecha,':obs'=>$obs,':uid'=>$user_id]);
    $transfer_id = (int)(DB::all("SELECT LAST_INSERT_ID() id")[0]['id'] ?? 0);
  }

  $debug_items = [];

  foreach($rows as $r){
    $pid=$r['pid']; $lot=$r['lot_id']; $qty=$r['qty'];

    $before_o = read_qty($pid,$dep_o,$lot);
    $before_d = read_qty($pid,$dep_d,$lot);

    subtract_origin($pid,$dep_o,$lot,$qty);   // resta origen
    upsert_add($pid,$dep_d,$lot,$qty);        // suma destino (sin lot_key!)

    $after_o  = read_qty($pid,$dep_o,$lot);
    $after_d  = read_qty($pid,$dep_d,$lot);

    if ($transfer_id){
      DB::exec("INSERT INTO depo_transfer_items (transfer_id, product_id, lot_id, cantidad)
                VALUES (?,?,?,?)", [$transfer_id,$pid,$lot,$qty]);
    }

    $debug_items[]=[
      'product_id'=>$pid,'lot_id'=>$lot,'moved'=>$qty,
      'origin_before'=>$before_o,'origin_after'=>$after_o,
      'dest_before'=>$before_d,'dest_after'=>$after_d
    ];
  }

  DB::pdo()->commit();
  JOK(['transfer_id'=>$transfer_id,'status'=>'confirmada','debug'=>$debug_items]);

}catch(Throwable $e){
  try{ DB::pdo()->rollBack(); }catch(Throwable $ee){}
  JERR('server_error',500,['detail'=>$e->getMessage()]);
}