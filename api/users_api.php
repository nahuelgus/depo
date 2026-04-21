<?php
// /app/deposito/api/users_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor']); // ver: admin/supervisor — mutar: sólo admin

header('Content-Type: application/json; charset=utf-8');

function ok($data = []){ echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg, int $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Detectamos si el usuario autenticado ES supervisor (para filtrar admins en list/get).
$is_supervisor = function_exists('user_has_role') ? user_has_role('supervisor') : false;
// Si tenés otra forma, podés reemplazar esa línea por lectura de la sesión.

try {

  /* ================= LIST ================= */
  if ($action === 'list') {
    $q = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $sql = "SELECT id, username, nombre, email, telefono, role, is_activo, created_at FROM depo_users";

    $where = [];
    if ($q !== '') {
      $like = '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q).'%';
      $where[] = "(username LIKE ? OR nombre LIKE ? OR email LIKE ? OR telefono LIKE ?)";
      array_push($params,$like,$like,$like,$like);
    }
    // Supervisores NO ven admins
    if ($is_supervisor) {
      $where[] = "role <> 'admin'";
    }
    if ($where) $sql .= " WHERE ".implode(' AND ',$where);

    $sql .= " ORDER BY role ASC, nombre ASC LIMIT 500";
    $rows = DB::all($sql, $params);
    ok(['items'=>$rows]);
  }

  /* ================= GET ================= */
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) err('id requerido');
    $u = DB::all("SELECT id, username, nombre, email, telefono, role, is_activo FROM depo_users WHERE id=? LIMIT 1", [$id])[0] ?? null;
    if (!$u) err('not_found',404);
    // Supervisor no puede ver admins
    if ($is_supervisor && strtolower((string)$u['role']) === 'admin') {
      err('forbidden', 403);
    }
    ok(['data'=>$u]);
  }

  /* ===== A partir de acá SOLO ADMIN en forma estricta ===== */
  require_role(['admin']);

  /* ================= CREATE ================= */
  if ($action === 'create') {
    if ($method !== 'POST') err('invalid_method',405);
    $username = trim((string)($_POST['username'] ?? ''));
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $role     = trim((string)($_POST['role'] ?? 'deposito'));
    $pass     = (string)($_POST['password'] ?? '');

    if ($username==='') err('username requerido');
    if ($nombre==='')   err('nombre requerido');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('email inválido');
    if (!in_array($role, ['admin','supervisor','deposito'], true)) err('role inválido');
    if (strlen($pass) < 6) err('password muy corta (min 6)');

    $dup = DB::all("SELECT id FROM depo_users WHERE username = ? OR email = ? LIMIT 1", [$username,$email]);
    if ($dup) err('username o email ya existe');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    DB::exec("INSERT INTO depo_users (username,nombre,email,telefono,role,password_hash,is_activo,created_at)
              VALUES (?,?,?,?,?, ?,1,NOW())",
              [$username,$nombre,$email,$telefono,$role,$hash]);
    $id = (int)(DB::all("SELECT LAST_INSERT_ID() AS id")[0]['id'] ?? 0);
    ok(['id'=>$id]);
  }

  /* ================= UPDATE ================= */
  if ($action === 'update') {
    if ($method !== 'POST') err('invalid_method',405);
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $role     = trim((string)($_POST['role'] ?? 'deposito'));

    if ($id<=0) err('id requerido');
    if ($username==='') err('username requerido');
    if ($nombre==='')   err('nombre requerido');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('email inválido');
    if (!in_array($role, ['admin','supervisor','deposito'], true)) err('role inválido');

    $dup = DB::all("SELECT id FROM depo_users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1",
                   [$username,$email,$id]);
    if ($dup) err('username o email ya existe');

    DB::exec("UPDATE depo_users
              SET username=?, nombre=?, email=?, telefono=?, role=?
              WHERE id=? LIMIT 1",
              [$username,$nombre,$email,$telefono,$role,$id]);
    ok(['id'=>$id]);
  }

  /* ================= TOGGLE ACTIVO ================= */
  if ($action === 'toggle') {
    if ($method !== 'POST') err('invalid_method',405);
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) err('id requerido');
    DB::exec("UPDATE depo_users SET is_activo = IF(is_activo=1,0,1) WHERE id=? LIMIT 1", [$id]);
    $u = DB::all("SELECT is_activo FROM depo_users WHERE id=? LIMIT 1", [$id])[0] ?? ['is_activo'=>0];
    ok(['id'=>$id,'is_activo'=>(int)$u['is_activo']]);
  }

  /* ================= RESET PASSWORD ================= */
  if ($action === 'reset_password') {
    if ($method !== 'POST') err('invalid_method',405);
    $id   = (int)($_POST['id'] ?? 0);
    $pass = (string)($_POST['password'] ?? '');
    if ($id<=0) err('id requerido');
    if (strlen($pass) < 6) err('password muy corta (min 6)');
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    DB::exec("UPDATE depo_users SET password_hash=? WHERE id=? LIMIT 1", [$hash,$id]);
    ok(['id'=>$id]);
  }

  err('invalid_action',405);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}