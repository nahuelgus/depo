<?php
// /app/deposito/api/session_api.php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? 'set_deposit';

try{
  if ($action==='set_deposit'){
    $depId = (int)($_POST['deposit_id'] ?? 0);
    if($depId<=0) { echo json_encode(['ok'=>false,'error'=>'deposit_id requerido']); exit; }
    // validar que existe y está activo
    $r = DB::all("SELECT id FROM depo_deposits WHERE id=? AND is_activo=1 LIMIT 1",[$depId]);
    if(!$r){ echo json_encode(['ok'=>false,'error'=>'deposito inválido']); exit; }
    $_SESSION['deposit_id'] = $depId;
    echo json_encode(['ok'=>true,'deposit_id'=>$depId]); exit;
  }

  if ($action==='get_deposit'){
    $depId = (int)($_SESSION['deposit_id'] ?? 0);
    echo json_encode(['ok'=>true,'deposit_id'=>$depId]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'invalid_action']); exit;
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}
