<?php
// /app/deposito/api/clientes_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? '';
    $limit  = max(1, min((int)($_GET['limit'] ?? 25), 100));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $escLike = static function (string $s): string {
        return str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $s);
    };

    switch ($action) {
        case 'search': {
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q === '') { echo json_encode(['ok'=>true,'items'=>[],'next'=>null]); exit; }

            $like = '%' . $escLike($q) . '%';
            $sql = "
                SELECT id, tipo_id, razon_social, doc, domicilio, ciudad,
                       tel, email, referente, nota, created_at
                FROM depo_clients
                WHERE (razon_social LIKE ? OR doc LIKE ? OR email LIKE ? OR tel LIKE ? OR referente LIKE ?)
                ORDER BY razon_social ASC
                LIMIT $limit OFFSET $offset
            ";
            $items = DB::all($sql, [$like,$like,$like,$like,$like]);
            $next  = (count($items)===$limit) ? ($page+1) : null;
            echo json_encode(['ok'=>true,'items'=>$items,'next'=>$next]); exit;
        }

        case 'list': {
            $sql = "
                SELECT id, tipo_id, razon_social, doc, domicilio, ciudad,
                       tel, email, referente, nota, created_at
                FROM depo_clients
                ORDER BY razon_social ASC
                LIMIT $limit OFFSET $offset
            ";
            $items = DB::all($sql);
            $next  = (count($items)===$limit) ? ($page+1) : null;
            echo json_encode(['ok'=>true,'items'=>$items,'next'=>$next]); exit;
        }

        case 'get': {
            $id = (int)($_GET['id'] ?? 0);
            if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
            $row = DB::all("SELECT * FROM depo_clients WHERE id=? LIMIT 1", [$id])[0] ?? null;
            echo json_encode(['ok'=>true,'data'=>$row]); exit;
        }

        case 'save': {
            $id           = (int)($_POST['id'] ?? 0);
            $tipo_id      = (int)($_POST['tipo_id'] ?? 0);
            $razon_social = trim((string)($_POST['razon_social'] ?? ''));
            if ($razon_social==='') { echo json_encode(['ok'=>false,'error'=>'razon_social_required']); exit; }

            $doc       = trim((string)($_POST['doc'] ?? ''));
            $domicilio = trim((string)($_POST['domicilio'] ?? ''));
            $ciudad    = trim((string)($_POST['ciudad'] ?? ''));
            $tel       = trim((string)($_POST['tel'] ?? ''));
            $email     = trim((string)($_POST['email'] ?? ''));
            $referente = trim((string)($_POST['referente'] ?? ''));
            $nota      = trim((string)($_POST['nota'] ?? ''));

            if ($id > 0) {
                DB::exec("UPDATE depo_clients
                          SET tipo_id=?, razon_social=?, doc=?, domicilio=?, ciudad=?, tel=?, email=?, referente=?, nota=?
                          WHERE id=?",
                          [$tipo_id,$razon_social,$doc,$domicilio,$ciudad,$tel,$email,$referente,$nota,$id]);
            } else {
                DB::exec("INSERT INTO depo_clients
                          (tipo_id, razon_social, doc, domicilio, ciudad, tel, email, referente, nota)
                          VALUES (?,?,?,?,?,?,?,?,?)",
                          [$tipo_id,$razon_social,$doc,$domicilio,$ciudad,$tel,$email,$referente,$nota]);
                $id = (int)DB::all("SELECT LAST_INSERT_ID() AS id")[0]['id'];
            }
            echo json_encode(['ok'=>true,'id'=>$id]); exit;
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }
            DB::exec("DELETE FROM depo_clients WHERE id=?", [$id]);
            echo json_encode(['ok'=>true]); exit;
        }

        default:
            echo json_encode(['ok'=>false,'error'=>'unknown_action']); exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}