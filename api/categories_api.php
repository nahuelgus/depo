<?php
// /app/deposito/api/categories_api.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']); // listar para todos

header('Content-Type: application/json; charset=utf-8');
function ok($d=[]){ echo json_encode(['ok'=>true]+$d, JSON_UNESCAPED_UNICODE); exit; }
function err($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m], JSON_UNESCAPED_UNICODE); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try{
  if ($action==='list'){
    $q = trim((string)($_GET['q'] ?? ''));
    $sql = "SELECT id, nombre FROM depo_categories";
    $p=[];
    if ($q!==''){ $sql.=" WHERE nombre LIKE ?"; $p[]='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%'; }
    $sql.=" ORDER BY nombre ASC";
    ok(['items'=>DB::all($sql,$p)]);
  }

  // ABM solo admin/supervisor
  require_role(['admin','supervisor']);

  if ($action==='create'){
    if ($method!=='POST') err('invalid_method',405);
    $nombre=trim((string)($_POST['nombre'] ?? '')); if($nombre==='') err('nombre requerido');
    DB::exec("INSERT INTO depo_categories (nombre) VALUES (?)",[$nombre]);
    $id=(int)(DB::all("SELECT LAST_INSERT_ID() id")[0]['id'] ?? 0);
    ok(['id'=>$id]);
  }

  if ($action==='update'){
    if ($method!=='POST') err('invalid_method',405);
    $id=(int)($_POST['id'] ?? 0); if($id<=0) err('id requerido');
    $nombre=trim((string)($_POST['nombre'] ?? '')); if($nombre==='') err('nombre requerido');
    DB::exec("UPDATE depo_categories SET nombre=? WHERE id=? LIMIT 1",[$nombre,$id]);
    ok(['id'=>$id]);
  }

  if ($action==='delete'){
    if ($method!=='POST') err('invalid_method',405);
    $id=(int)($_POST['id'] ?? 0); if($id<=0) err('id requerido');
    // evitar borrar si está en uso
    $cnt=DB::all("SELECT COUNT(*) c FROM depo_products WHERE category_id=?",[$id])[0]['c'] ?? 0;
    if ($cnt>0) err('categoria_en_uso',409);
    DB::exec("DELETE FROM depo_categories WHERE id=? LIMIT 1",[$id]);
    ok(['deleted'=>1]);
  }

  err('invalid_action',405);

}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}