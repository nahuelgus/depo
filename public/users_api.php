<?php
// /app/deposito/api/users_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']); // ver puede supervisor, muta solo admin

header('Content-Type: application/json; charset=utf-8');

function json_ok($data = []){ echo json_encode(['ok'=>true]+$data, JSON_UNESCAPED_UNICODE); exit; }
function json_fail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {

  /* ===== LIST ===== */
  if ($action === 'list') {
    $q = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $sql = "SELECT id, nombre, email, role, is_active, created_at FROM depo_users";
    if ($q !== '') {
      $like = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q).'%';
      $sql .= " WHERE (nombre LIKE ? OR email LIKE ?)";
      $params = [$like,$like];
    }
    $sql .= " ORDER BY role ASC, nombre ASC LIMIT 500";
    $rows = DB::all($sql, $params);
    json_ok(['items'=>$rows]);
  }

  /* ===== GET ===== */
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_fail('id requerido');
    $u = DB::all("SELECT id, nombre, email, role, is_active FROM depo_users WHERE id=? LIMIT 1", [$id])[0] ?? null;
    if (!$u) json_fail('not_found',404);
    json_ok(['data'=>$u]);
  }

  // A partir de acá solo admin
  require_role(['admin']);

  /* ===== CREATE ===== */
  if ($action === 'create') {
    if ($method !== 'POST') json_fail('invalid_method',405);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $role   = trim((string)($_POST['role'] ?? 'deposito'));
    $pass   = (string)($_POST['password'] ?? '');

    if ($nombre==='') json_fail('nombre requerido');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_fail('email inválido');
    if (!in_array($role, ['admin','supervisor','deposito'], true)) json_fail('role inválido');
    if (strlen($pass) < 6) json_fail('password muy corta (min 6)');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    DB::exec("INSERT INTO depo_users (nombre,email,role,is_active,password_hash,created_at) VALUES (?,?,?,?,?,NOW())",
      [$nombre,$email,$role,1,$hash]);
    $id = (int)(DB::all("SELECT LAST_INSERT_ID() AS id")[0]['id'] ?? 0);
    json_ok(['id'=>$id]);
  }

  /* ===== UPDATE ===== */
  if ($action === 'update') {
    if ($method !== 'POST') json_fail('invalid_method',405);
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $role   = trim((string)($_POST['role'] ?? 'deposito'));

    if ($id<=0) json_fail('id requerido');
    if ($nombre==='') json_fail('nombre requerido');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_fail('email inválido');
    if (!in_array($role, ['admin','supervisor','deposito'], true)) json_fail('role inválido');

    DB::exec("UPDATE depo_users SET nombre=?, email=?, role=?, updated_at=NOW() WHERE id=? LIMIT 1",
      [$nombre,$email,$role,$id]);
    json_ok(['id'=>$id]);
  }

  /* ===== TOGGLE ACTIVE ===== */
  if ($action === 'toggle') {
    if ($method !== 'POST') json_fail('invalid_method',405);
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) json_fail('id requerido');
    DB::exec("UPDATE depo_users SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE id=? LIMIT 1", [$id]);
    $u = DB::all("SELECT is_active FROM depo_users WHERE id=? LIMIT 1", [$id])[0] ?? ['is_active'=>0];
    json_ok(['id'=>$id,'is_active'=>(int)$u['is_active']]);
  }

  /* ===== RESET PASSWORD ===== */
  if ($action === 'reset_password') {
    if ($method !== 'POST') json_fail('invalid_method',405);
    $id   = (int)($_POST['id'] ?? 0);
    $pass = (string)($_POST['password'] ?? '');
    if ($id<=0) json_fail('id requerido');
    if (strlen($pass) < 6) json_fail('password muy corta (min 6)');
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    DB::exec("UPDATE depo_users SET password_hash=?, updated_at=NOW() WHERE id=? LIMIT 1", [$hash,$id]);
    json_ok(['id'=>$id]);
  }

  json_fail('invalid_action',405);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
