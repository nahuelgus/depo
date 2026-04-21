<?php
// /app/deposito/api/deposits_api.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']); // ver/ABM: admin y supervisor

header('Content-Type: application/json; charset=utf-8');
function ok($d=[]){ echo json_encode(['ok'=>true]+$d,JSON_UNESCAPED_UNICODE); exit; }
function err($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m],JSON_UNESCAPED_UNICODE); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  if ($action==='list'){
    $q=trim((string)($_GET['q']??'')); $params=[];
    $sql="SELECT id,nombre,direccion,ciudad,telefono,is_activo FROM depo_deposits";
    if($q!==''){ $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';
      $sql.=" WHERE (nombre LIKE ? OR ciudad LIKE ? OR direccion LIKE ?)"; $params=[$like,$like,$like];
    }
    $sql.=" ORDER BY is_activo DESC, nombre ASC LIMIT 500";
    ok(['items'=>DB::all($sql,$params)]);
  }

  if ($action==='get'){
    $id=(int)($_GET['id']??0); if($id<=0) err('id requerido');
    $r=DB::all("SELECT id,nombre,direccion,ciudad,telefono,is_activo FROM depo_deposits WHERE id=? LIMIT 1",[$id])[0]??null;
    if(!$r) err('not_found',404); ok(['data'=>$r]);
  }

  // Mutaciones solo admin
  require_role(['admin']);

  if ($action==='create'){
    if($method!=='POST') err('invalid_method',405);
    $nombre=trim((string)($_POST['nombre']??'')); if($nombre==='') err('nombre requerido');
    $dir=trim((string)($_POST['direccion']??'')); $ciu=trim((string)($_POST['ciudad']??'')); $tel=trim((string)($_POST['telefono']??''));
    DB::exec("INSERT INTO depo_deposits (nombre,direccion,ciudad,telefono,is_activo,created_at) VALUES (?,?,?,?,1,NOW())",
      [$nombre,$dir,$ciu,$tel]);
    $id=(int)(DB::all("SELECT LAST_INSERT_ID() id")[0]['id']??0);
    ok(['id'=>$id]);
  }

  if ($action==='update'){
    if($method!=='POST') err('invalid_method',405);
    $id=(int)($_POST['id']??0); if($id<=0) err('id requerido');
    $nombre=trim((string)($_POST['nombre']??'')); if($nombre==='') err('nombre requerido');
    $dir=trim((string)($_POST['direccion']??'')); $ciu=trim((string)($_POST['ciudad']??'')); $tel=trim((string)($_POST['telefono']??''));
    DB::exec("UPDATE depo_deposits SET nombre=?,direccion=?,ciudad=?,telefono=? WHERE id=? LIMIT 1",
      [$nombre,$dir,$ciu,$tel,$id]);
    ok(['id'=>$id]);
  }

  if ($action==='toggle'){
    if($method!=='POST') err('invalid_method',405);
    $id=(int)($_POST['id']??0); if($id<=0) err('id requerido');
    DB::exec("UPDATE depo_deposits SET is_activo=IF(is_activo=1,0,1) WHERE id=? LIMIT 1",[$id]);
    $r=DB::all("SELECT is_activo FROM depo_deposits WHERE id=? LIMIT 1",[$id])[0]??['is_activo'=>0];
    ok(['id'=>$id,'is_activo'=>(int)$r['is_activo']]);
  }

  err('invalid_action',405);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
