<?php
// /app/deposito/api/lots_by_product.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
// Asegurá que el helper exista en tu auth (o usá require_login si preferís)
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');

try {
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    $deposit_id = isset($_GET['deposit_id']) ? (int)$_GET['deposit_id'] : 0;

    if ($product_id <= 0) {
        throw new Exception('product_id requerido');
    }

    // WHERE dinámico
    $where  = 's.product_id = :pid';
    $params = [':pid' => $product_id];

    if ($deposit_id > 0) {
        $where .= ' AND s.deposit_id = :did';
        $params[':did'] = $deposit_id;
    }

    // Trae una fila por (product_id, lot_id, deposit_id) con cantidad > 0
    // lot_id puede ser NULL (stock sin lote)
    // LEFT JOIN lots solo si hay lot_id
    $sql = "
        SELECT
            s.lot_id,
            l.nro_lote,
            l.vto,
            s.cantidad AS disponible
        FROM depo_stock s
        LEFT JOIN depo_lots l ON l.id = s.lot_id
        WHERE $where
          AND s.cantidad > 0
        ORDER BY
            (l.vto IS NULL), l.vto ASC, s.lot_id ASC
    ";

    $rows = DB::all($sql, $params);

    // Colapsar múltiples filas NULL (si tu stock tiene varias líneas sin lote)
    $seenNull = false;
    $items    = [];

    foreach ($rows as $r) {
        $isNull = ($r['lot_id'] === null);

        if ($isNull) {
            if ($seenNull) {
                // Ignoramos extra NULL para que haya UNA sola opción "(Sin lote)"
                continue;
            }
            $seenNull = true;
            $items[] = [
                'lot_id'     => null,
                'nro_lote'   => null,
                'vto'        => null,
                // Mostrar sin tope (o podés sumar cantidades si preferís)
                'disponible' => null
            ];
            continue;
        }

        $items[] = [
            'lot_id'     => (int)$r['lot_id'],
            'nro_lote'   => $r['nro_lote'],
            'vto'        => $r['vto'],
            'disponible' => ($r['disponible'] !== null ? (float)$r['disponible'] : null),
        ];
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'server_error',
        'detail'=> $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}