<?php
// /app/deposito/api/products_search.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');

try {
    $q      = trim((string)($_GET['q'] ?? ''));
    $limit  = max(1, min((int)($_GET['limit'] ?? 20), 50));
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Escapar comodines para LIKE
    $esc = static function (string $s): string {
        return str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $s);
    };

    $params = [];
    $sqlWhere = "p.is_activo = 1";

    // Heurística de EAN exacto: todo numérico y largo típico de códigos (8–14)
    $isEan = $q !== '' && ctype_digit($q) && (strlen($q) >= 8 && strlen($q) <= 14);

    if ($isEan) {
        $sqlWhere .= " AND p.barcode = ?";
        $params[] = $q;
    } else {
        // Búsqueda por texto: separar en tokens + LIKE en nombre / barcode
        if ($q !== '') {
            $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $t) {
                $like = '%' . $esc($t) . '%';
                $sqlWhere .= " AND (p.nombre LIKE ? ESCAPE '\\\\' OR p.barcode LIKE ? ESCAPE '\\\\')";
                $params[] = $like;
                $params[] = $like;
            }
        }
    }

    // SELECT incluye precio y presentacion (necesarios para precargar unitario)
    $sql = "
        SELECT
            p.id,
            p.nombre,
            p.barcode,
            p.presentacion,
            p.precio,
            p.requiere_lote,
            p.requiere_vto,
            p.unidad_id,
            p.iva_pct
        FROM depo_products p
        WHERE $sqlWhere
        ORDER BY
            ".($isEan ? "p.id DESC" : "p.nombre ASC")."
        LIMIT $limit OFFSET $offset
    ";

    $items = DB::all($sql, $params);

    // ¿hay siguiente página?
    $next = null;
    if (count($items) === $limit) {
        $next = $page + 1;
    }

    echo json_encode(['ok'=>true, 'items'=>$items, 'next'=>$next], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}