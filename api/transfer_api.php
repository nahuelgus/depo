<?php
// /app/deposito/api/transfers_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
header('Content-Type: application/json; charset=utf-8');

function fail(string $m,int $c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m],JSON_UNESCAPED_UNICODE); exit; }
function ok(array $d=[]){ echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }

try{
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = $_GET['action'] ?? $_POST['action'] ?? 'create';
  if($method!=='POST' || $action!=='create') fail('invalid_action',405);

  $dep_o = (int)($_POST['deposit_origen_id'] ?? 0);
  $dep_d = (int)($_POST['deposit_destino_id'] ?? 0);
  if($dep_o<=0 || $dep_d<=0) fail('origen/destino requeridos');
  if($dep_o===$dep_d) fail('origen y destino iguales');

  $fecha_in = trim((string)($_POST['fecha'] ?? date('Y-m-d')));
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_in)) fail('fecha inválida (Y-m-d)');
  $fecha = $fecha_in.' 00:00:00';

  $obs = trim((string)($_POST['obs'] ?? ''));

  $items_raw = $_POST['items_json'] ?? '[]';
  $items = is_array($items_raw) ? $items_raw : json_decode((string)$items_raw,true);
  if(!is_array($items) || !count($items)) fail('items vacíos');

  // Normalizamos
  $rows=[];
  foreach($items as $i){
    $pid=(int)($i['product_id'] ?? 0);
    $lot=$i['lot_id'] ?? null;
    $qty=(float)($i['cantidad'] ?? 0);
    if($pid<=0 || $qty<=0) fail('item inválido');
    if($lot==='' || $lot===0 || $lot==='0') $lot=null;
    else $lot = $lot!==null ? (int)$lot : null;
    $rows[]=['pid'=>$pid,'lot_id'=>$lot,'qty'=>$qty];
  }

  DB::pdo()->beginTransaction();

  // Guardamos cabecera si existen las tablas
  $transfer_id = null;
  $has = DB::all("SHOW TABLES LIKE 'depo_transfers'");
  if($has){
    DB::exec("INSERT INTO depo_transfers (deposit_origen_id, deposit_destino_id, fecha, observacion, status, created_at)
              VALUES (:o,:d,:f,:obs,'confirmada',NOW())",
              [':o'=>$dep_o,':d'=>$dep_d,':f'=>$fecha,':obs'=>$obs]);
    $transfer_id = (int)(DB::all("SELECT LAST_INSERT_ID() id")[0]['id'] ?? 0);
  }

  // Por cada ítem: validar stock en origen, descontar, sumar en destino
  foreach($rows as $r){
    $pid=$r['pid']; $lot=$r['lot_id']; $qty=$r['qty'];

    // Stock disponible en origen
    if($lot===null){
      $row = DB::all("SELECT cantidad FROM depo_stock WHERE product_id=? AND deposit_id=? AND lot_id IS NULL LIMIT 1", [$pid,$dep_o]);
    }else{
      $row = DB::all("SELECT cantidad FROM depo_stock WHERE product_id=? AND deposit_id=? AND lot_id=? LIMIT 1", [$pid,$dep_o,$lot]);
    }
    $disp=(float)($row[0]['cantidad'] ?? 0);
    if($disp < $qty){
      throw new Exception("Stock insuficiente (prod=$pid, disp=$disp, req=$qty) en origen");
    }

    // Descontar en origen
    if($lot===null){
      DB::exec("UPDATE depo_stock SET cantidad = cantidad - :q
                WHERE product_id=:p AND deposit_id=:d AND lot_id IS NULL LIMIT 1",
               [':q'=>$qty,':p'=>$pid,':d'=>$dep_o]);
    }else{
      DB::exec("UPDATE depo_stock SET cantidad = cantidad - :q
                WHERE product_id=:p AND deposit_id=:d AND lot_id=:l LIMIT 1",
               [':q'=>$qty,':p'=>$pid,':d'=>$dep_o,':l'=>$lot]);
    }

    // Sumar en destino (upsert)
    if($lot===null){
      $aff = DB::exec("UPDATE depo_stock SET cantidad = cantidad + :q
                       WHERE product_id=:p AND deposit_id=:d AND lot_id IS NULL LIMIT 1",
                      [':q'=>$qty,':p'=>$pid,':d'=>$dep_d]);
      if($aff===0){
        DB::exec("INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad) VALUES (:p,:d,NULL,:q)",
                 [':p'=>$pid,':d'=>$dep_d,':q'=>$qty]);
      }
    }else{
      $aff = DB::exec("UPDATE depo_stock SET cantidad = cantidad + :q
                       WHERE product_id=:p AND deposit_id=:d AND lot_id=:l LIMIT 1",
                      [':q'=>$qty,':p'=>$pid,':d'=>$dep_d,':l'=>$lot]);
      if($aff===0){
        DB::exec("INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad) VALUES (:p,:d,:l,:q)",
                 [':p'=>$pid,':d'=>$dep_d,':l'=>$lot,':q'=>$qty]);
      }
    }

    // Guardar item si hay tabla
    if($transfer_id){
      DB::exec("INSERT INTO depo_transfer_items (transfer_id, product_id, lot_id, cantidad)
                VALUES (?,?,?,?)", [$transfer_id,$pid,$lot,$qty]);
    }
  }

  DB::pdo()->commit();
  ok(['transfer_id'=>$transfer_id, 'status'=>'confirmada']);

}catch(Throwable $e){
  try{ DB::pdo()->rollBack(); }catch(Throwable $ee){}
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}