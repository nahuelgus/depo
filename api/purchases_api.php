<?php
// /app/deposito/api/purchases_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');

// ---------- Compatibilidad DB (evita usar DB::q / DB::all) ----------
function db_pdo(): PDO {
    if (method_exists('DB','pdo')) return DB::pdo();
    if (method_exists('DB','get')) return DB::get();
    if (property_exists('DB','pdo')) return DB::$pdo;
    throw new RuntimeException('No se encontró conexión PDO en DB');
}
function db_all(string $sql, array $params = []): array {
    $st = db_pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function db_exec(string $sql, array $params = []): int {
    $st = db_pdo()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
function jerr(string $msg, int $code=400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action !== 'create') {
        jerr('invalid_action', 405);
    }

    // ----------- Inputs -----------
    $deposit_id = (int)($_POST['deposit_id'] ?? 0);
    $proveedor  = trim((string)($_POST['proveedor'] ?? ''));
    $documento  = trim((string)($_POST['documento'] ?? ''));
    $fecha      = trim((string)($_POST['fecha'] ?? date('Y-m-d')));
    $obs        = trim((string)($_POST['obs'] ?? ''));

    if ($deposit_id <= 0) jerr('deposit_id requerido');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) jerr('fecha inválida (Y-m-d)');

    $items_json = $_POST['items_json'] ?? '[]';
    $items = is_array($items_json) ? $items_json : json_decode((string)$items_json, true);
    if (!is_array($items) || !count($items)) jerr('items vacíos');

    // Mapa de productos -> requiere_lote
    $pids = array_values(array_unique(array_map(fn($it)=>(int)($it['product_id'] ?? 0), $items)));
    if (!$pids) jerr('items sin product_id');
    $marks = implode(',', array_fill(0, count($pids), '?'));
    $prods = db_all("SELECT id, requiere_lote FROM depo_products WHERE id IN ($marks)", $pids);
    $reqMap = [];
    foreach ($prods as $p) { $reqMap[(int)$p['id']] = ((int)$p['requiere_lote'] === 1); }

    // Normalización / validación
    $clean = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (float)($it['cantidad'] ?? 0);
        $cst = (float)($it['costo_unit'] ?? 0);
        $lot = $it['lote'] ?? null; // {nro_lote, vto} | null

        if ($pid <= 0) jerr('product_id inválido');
        if ($qty <= 0) jerr("cantidad inválida para producto $pid");
        if ($cst < 0)  jerr("costo_unit inválido para producto $pid");

        $requiere = $reqMap[$pid] ?? false;
        $nro_lote = null; $vto = null;

        if ($requiere) {
            if (!is_array($lot)) jerr("El producto $pid requiere lote");
            $nro_lote = trim((string)($lot['nro_lote'] ?? ''));
            if ($nro_lote === '') jerr("El producto $pid requiere N° de lote");
            $vto = trim((string)($lot['vto'] ?? '')) ?: null;
            if ($vto !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vto)) {
                jerr("Fecha de vto inválida en producto $pid");
            }
        } else {
            if (is_array($lot)) {
                $nro_lote = trim((string)($lot['nro_lote'] ?? '')) ?: null;
                $vto      = trim((string)($lot['vto'] ?? '')) ?: null;
            }
        }

        $clean[] = [
            'product_id' => $pid,
            'cantidad'   => $qty,
            'costo_unit' => $cst,
            'nro_lote'   => $nro_lote,
            'vto'        => $vto
        ];
    }

    $pdo = db_pdo();
    $pdo->beginTransaction();

    // Cabecera de movimiento
    $notas = trim("Proveedor: $proveedor | Doc: $documento | Fecha: $fecha | Obs: $obs");
    db_exec("
        INSERT INTO depo_movements (tipo, deposit_from, deposit_to, ref_table, ref_id, notas, created_at)
        VALUES ('ingreso_compra', NULL, :dep, 'purchase', NULL, :notas, NOW())
    ", [':dep'=>$deposit_id, ':notas'=>$notas]);

    $mov_id = (int)db_all("SELECT LAST_INSERT_ID() AS id")[0]['id'];

    if ($mov_id <= 0) throw new RuntimeException('No se pudo crear el movimiento');

    foreach ($clean as $it) {
        $pid = $it['product_id'];
        $qty = $it['cantidad'];
        $cst = $it['costo_unit'];
        $nro = $it['nro_lote'];
        $vto = $it['vto'];

        // Crear/obtener lote (si corresponde)
        $lot_id = null;
        if ($nro !== null || $vto !== null) {
            $lot = db_all("
                SELECT id FROM depo_lots
                WHERE product_id = :pid
                  AND IFNULL(nro_lote,'') = IFNULL(:nro,'')
                  AND IFNULL(vto,'0000-00-00') = IFNULL(:vto,'0000-00-00')
                LIMIT 1
            ", [':pid'=>$pid, ':nro'=>$nro, ':vto'=>$vto]);

            if ($lot) {
                $lot_id = (int)$lot[0]['id'];
            } else {
                db_exec("INSERT INTO depo_lots (product_id, nro_lote, vto, created_at) VALUES (:pid, :nro, :vto, NOW())",
                        [':pid'=>$pid, ':nro'=>$nro, ':vto'=>$vto]);
                $lot_id = (int)db_all("SELECT LAST_INSERT_ID() AS id")[0]['id'];
                if ($lot_id <= 0) throw new RuntimeException('No se pudo crear el lote');
            }
        }

        // Upsert stock: sumar cantidad
        if ($lot_id === null) {
            $rc = db_exec("
                UPDATE depo_stock
                   SET cantidad = cantidad + :cant
                 WHERE deposit_id = :dep AND product_id = :pid AND lot_id IS NULL
                 LIMIT 1
            ", [':cant'=>$qty, ':dep'=>$deposit_id, ':pid'=>$pid]);

            if ($rc === 0) {
                db_exec("
                    INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad)
                    VALUES (:pid, :dep, NULL, :cant)
                ", [':pid'=>$pid, ':dep'=>$deposit_id, ':cant'=>$qty]);
            }
        } else {
            $rc = db_exec("
                UPDATE depo_stock
                   SET cantidad = cantidad + :cant
                 WHERE deposit_id = :dep AND product_id = :pid AND lot_id = :lot
                 LIMIT 1
            ", [':cant'=>$qty, ':dep'=>$deposit_id, ':pid'=>$pid, ':lot'=>$lot_id]);

            if ($rc === 0) {
                db_exec("
                    INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad)
                    VALUES (:pid, :dep, :lot, :cant)
                ", [':pid'=>$pid, ':dep'=>$deposit_id, ':lot'=>$lot_id, ':cant'=>$qty]);
            }
        }

        // Detalle del movimiento
        db_exec("
            INSERT INTO depo_movement_items (movement_id, product_id, lot_id, cantidad, costo_unit, precio_unit)
            VALUES (:mov, :pid, :lot, :cant, :costo, NULL)
        ", [
            ':mov'=>$mov_id,
            ':pid'=>$pid,
            ':lot'=>$lot_id,
            ':cant'=>$qty,
            ':costo'=>$cst > 0 ? $cst : null
        ]);
    }

    $pdo->commit();

    echo json_encode(['ok'=>true, 'movement_id'=>$mov_id], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    try { db_pdo()->rollBack(); } catch (Throwable $ee) {}
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}