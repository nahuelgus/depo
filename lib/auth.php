<?php
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void {
  if (!current_user()) { header('Location: /app/deposito/public/login.php'); exit; }
}
function require_role(array $roles): void {
  require_login();
  $u = current_user();
  if (!in_array($u['role'] ?? '', $roles, true)) { http_response_code(403); exit('Acceso denegado'); }
}
function login(string $username, string $password): bool {
  $u = DB::one("SELECT * FROM depo_users WHERE username=? AND is_activo=1", [$username]);
  if (!$u) return false;
  if (!password_verify($password, $u['password_hash'])) return false;
  $_SESSION['user'] = ['id'=>$u['id'],'username'=>$u['username'],'nombre'=>$u['nombre'],'role'=>$u['role']];
  return true;
}
function logout(): void { $_SESSION = []; session_destroy(); }
