<?php
// /app/deposito/api/sales_api.php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin','supervisor','deposito']);

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? $_POST['action'] ?? 'create';

    if ($method !== 'POST') {
        fail('invalid_method', 405);
    }

    switch ($action) {

        /* ============================================================
           CREAR VENTA (tu implementación original)
        ============================================================ */
        case 'create': {
            // -------- INPUTS principales ----------
            $deposit_id = (int)($_POST['deposit_id'] ?? 0);
            if ($deposit_id <= 0) fail('deposit_id requerido');

            $fecha_in = trim((string)($_POST['fecha'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_in)) fail('fecha inválida (Y-m-d)');
            $fecha = $fecha_in . ' 00:00:00';

            $observacion   = trim((string)($_POST['obs'] ?? ''));
            $client_id      = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;
            $client_type_id = isset($_POST['client_type_id']) ? (int)$_POST['client_type_id'] : null;

            $items_raw = $_POST['items_json'] ?? '[]';
            if (!is_array($items_raw)) {
                $items = json_decode((string)$items_raw, true);
            } else {
                $items = $items_raw;
            }
            if (!is_array($items) || !count($items)) {
                fail('items vacíos');
            }

            // -------- Normalización items ----------
            $clean = [];
            foreach ($items as $i) {
                $pid     = (int)($i['product_id'] ?? 0);
                $lot     = $i['lote_id'] ?? ($i['lot_id'] ?? null);
                $cant    = (float)($i['cantidad'] ?? 0);
                $precio  = (float)($i['precio'] ?? 0);
                $descPct = (float)($i['desc_pct'] ?? 0);

                if ($pid <= 0) fail('product_id inválido');
                if ($cant <= 0) fail('cantidad inválida');
                if ($precio < 0) fail('precio inválido');
                if ($descPct < 0) $descPct = 0;

                if ($lot === '' || $lot === 0 || $lot === '0') {
                    $lot = null;
                } else {
                    $lot = $lot !== null ? (int)$lot : null;
                    if ($lot !== null && $lot <= 0) $lot = null;
                }

                $clean[] = [
                    'product_id' => $pid,
                    'lot_id'     => $lot,
                    'cantidad'   => $cant,
                    'precio'     => $precio,
                    'desc_pct'   => $descPct,
                ];
            }

            // -------- Datos productos ----------
            $pids = array_values(array_unique(array_column($clean, 'product_id')));
            $ph   = implode(',', array_fill(0, count($pids), '?'));
            $prodRows = DB::all("
                SELECT id, iva_pct, requiere_lote
                FROM depo_products
                WHERE id IN ($ph)
            ", $pids);

            $prodMap = [];
            foreach ($prodRows as $r) {
                $prodMap[(int)$r['id']] = [
                    'iva_pct'       => $r['iva_pct'] !== null ? (float)$r['iva_pct'] : null,
                    'requiere_lote' => (int)$r['requiere_lote'] === 1
                ];
            }
            if (count($prodMap) !== count($pids)) {
                fail('producto inexistente');
            }

            // -------- Transacción ----------
            DB::pdo()->beginTransaction();

            $aplica_iva = 0;
            foreach ($clean as $it) {
                $iva_pct = $prodMap[$it['product_id']]['iva_pct'];
                if ($iva_pct !== null) { $aplica_iva = 1; break; }
            }

            DB::exec("
                INSERT INTO depo_sales
                (deposit_id, fecha, client_id, client_type_id, observacion,
                 status, aplica_iva, descuento_pct, descuento_mto, recargo_pct, recargo_mto,
                 subtotal, iva_total, total, user_id, created_at)
                VALUES
                (:dep, :fec, :cid, :ctid, :obs,
                 'confirmada', :apiva, 0.000, 0.0000, 0.000, 0.0000,
                 0.0000, 0.0000, 0.0000, NULL, NOW())
            ", [
                ':dep'   => $deposit_id,
                ':fec'   => $fecha,
                ':cid'   => $client_id ?: null,
                ':ctid'  => $client_type_id ?: null,
                ':obs'   => $observacion,
                ':apiva' => $aplica_iva,
            ]);

            $sale_id = (int)(DB::all("SELECT LAST_INSERT_ID() AS id")[0]['id'] ?? 0);
            if ($sale_id <= 0) { throw new Exception('no se pudo crear cabecera'); }

            $subtotal = 0.0;
            $iva_total = 0.0;

            // -------- Items + stock ----------
            foreach ($clean as $it) {
                $pid     = $it['product_id'];
                $lot_id  = $it['lot_id'];
                $cant    = $it['cantidad'];
                $precio  = $it['precio'];
                $descPct = $it['desc_pct'];

                $pinfo   = $prodMap[$pid];
                $iva_pct = $pinfo['iva_pct'];
                $reqLot  = $pinfo['requiere_lote'];

                if ($reqLot && $lot_id === null) {
                    throw new Exception("El producto $pid requiere lote");
                }

                // Stock disponible
                if ($lot_id === null) {
                    $row = DB::all("
                        SELECT cantidad FROM depo_stock
                        WHERE product_id = :pid AND deposit_id = :dep AND lot_id IS NULL
                        LIMIT 1
                    ", [':pid'=>$pid, ':dep'=>$deposit_id]);
                } else {
                    $row = DB::all("
                        SELECT cantidad FROM depo_stock
                        WHERE product_id = :pid AND deposit_id = :dep AND lot_id = :lot
                        LIMIT 1
                    ", [':pid'=>$pid, ':dep'=>$deposit_id, ':lot'=>$lot_id]);
                }
                $disp = (float)($row[0]['cantidad'] ?? 0);
                if ($disp < $cant) {
                    throw new Exception("Stock insuficiente product_id=$pid (disp=$disp, req=$cant)");
                }

                // Cálculo
                $base = $cant * $precio;
                if ($descPct > 0) $base = $base * (1 - ($descPct/100));
                $base = round($base, 4);

                $iva_linea = 0.0;
                if ($iva_pct !== null) {
                    $iva_linea = round($base * ($iva_pct/100), 4);
                }

                $subtotal  += $base;
                $iva_total += $iva_linea;

                // Ítem
                DB::exec("
                    INSERT INTO depo_sale_items
                    (sale_id, product_id, lot_id, cantidad, precio_unit, desc_pct, desc_mto, iva_pct)
                    VALUES
                    (:sid, :pid, :lot, :cant, :puni, :dpct, 0.0000, :ivap)
                ", [
                    ':sid'  => $sale_id,
                    ':pid'  => $pid,
                    ':lot'  => $lot_id,
                    ':cant' => $cant,
                    ':puni' => $precio,
                    ':dpct' => $descPct,
                    ':ivap' => $iva_pct,
                ]);

                // Descuento de stock
                if ($lot_id === null) {
                    DB::exec("
                        UPDATE depo_stock
                        SET cantidad = cantidad - :cant
                        WHERE product_id = :pid AND deposit_id = :dep AND lot_id IS NULL
                        LIMIT 1
                    ", [':cant'=>$cant, ':pid'=>$pid, ':dep'=>$deposit_id]);
                } else {
                    DB::exec("
                        UPDATE depo_stock
                        SET cantidad = cantidad - :cant
                        WHERE product_id = :pid AND deposit_id = :dep AND lot_id = :lot
                        LIMIT 1
                    ", [':cant'=>$cant, ':pid'=>$pid, ':dep'=>$deposit_id, ':lot'=>$lot_id]);
                }
            }

            $subtotal  = round($subtotal, 4);
            $iva_total = round($iva_total, 4);
            $total     = round($subtotal + $iva_total, 4);

            DB::exec("
                UPDATE depo_sales
                SET subtotal = :st, iva_total = :iva, total = :tot, observacion = :obs
                WHERE id = :sid
                LIMIT 1
            ", [
                ':st'  => $subtotal,
                ':iva' => $iva_total,
                ':tot' => $total,
                ':obs' => $observacion,
                ':sid' => $sale_id
            ]);

            DB::pdo()->commit();

            echo json_encode([
                'ok'         => true,
                'sale_id'    => $sale_id,
                'subtotal'   => (float)number_format($subtotal, 2, '.', ''),
                'iva_total'  => (float)number_format($iva_total, 2, '.', ''),
                'total'      => (float)number_format($total, 2, '.', ''),
                'status'     => 'confirmada'
            ], JSON_UNESCAPED_UNICODE);

            break;
        }

        /* ============================================================
           ANULAR VENTA (sin reponer stock)
           POST: sale_id (req), reason (opt)
        ============================================================ */
        case 'cancel': {
            $sale_id = (int)($_POST['sale_id'] ?? 0);
            if ($sale_id <= 0) fail('sale_id requerido');

            $reason = trim((string)($_POST['reason'] ?? ''));

            // Traer cabecera
            $sale = DB::all("
                SELECT id, status
                FROM depo_sales
                WHERE id = ?
                LIMIT 1
            ", [$sale_id])[0] ?? null;

            if (!$sale) fail('not_found', 404);
            if (strtolower((string)$sale['status']) === 'anulada') {
                echo json_encode(['ok'=>true,'sale_id'=>$sale_id,'status'=>'anulada']); exit;
            }

            DB::pdo()->beginTransaction();

            // Motivo (deja traza en observación)
            if ($reason !== '') {
                DB::exec("
                    UPDATE depo_sales
                    SET observacion = CONCAT(COALESCE(observacion,''), '\n[ANULADA] ', :r)
                    WHERE id = :sid
                    LIMIT 1
                ", [':r'=>$reason, ':sid'=>$sale_id]);
            }

            // Solo marcar como anulada (NO repone stock)
            DB::exec("UPDATE depo_sales SET status='anulada' WHERE id = ? LIMIT 1", [$sale_id]);

            DB::pdo()->commit();

            echo json_encode(['ok'=>true,'sale_id'=>$sale_id,'status'=>'anulada'], JSON_UNESCAPED_UNICODE);
            break;
        }

        default:
            fail('invalid_action', 405);
    }

} catch (Throwable $e) {
    try { DB::pdo()->rollBack(); } catch(Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'server_error', 'detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}