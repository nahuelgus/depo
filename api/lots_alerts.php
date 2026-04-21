<?php
// /app/deposito/api/lots_alerts.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);
header('Content-Type: application/json; charset=utf-8');

try {
    $days       = max(1, min((int)($_GET['days'] ?? 30), 365));
    $limit      = max(1, min((int)($_GET['limit'] ?? 12), 200));
    $deposit_id = isset($_GET['deposit_id']) ? (int)$_GET['deposit_id'] : null;

    // NOTA: Si más adelante querés filtrar por depósito, asegurate de que depo_lots tenga ese vínculo.
    // Por ahora, devolvemos próximos a vencer globalmente.
    $params = [ $days, $limit ];

    $sql = "
      SELECT
        l.id        AS lot_id,
        l.product_id,
        l.nro_lote,
        l.vto,
        p.nombre    AS producto,
        p.presentacion,
        p.barcode
      FROM depo_lots l
      JOIN depo_products p ON p.id = l.product_id
      WHERE l.vto IS NOT NULL
        AND l.vto <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
      ORDER BY l.vto ASC
      LIMIT ?
    ";

    $items = DB::all($sql, $params);

    echo json_encode([
        'ok'    => true,
        'items' => $items,
        'days'  => $days
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()]);
}
