<?php
// /app/deposito/lib/stock.php
class StockService {
  /** Crea (si hace falta) un lote y devuelve su id (o NULL si no aplica) */
  public static function ensureLot(?int $productId, ?string $nro_lote, ?string $vto): ?int {
    if (!$productId) return null;
    if ($nro_lote === '' && $vto === '') return null; // lote no aplicaba
    // Busco si ya existe lote igual (mismo producto + nro_lote + vto)
    $lot = DB::one("SELECT id FROM depo_lots WHERE product_id=? AND
                    ( (nro_lote IS NULL AND ? IS NULL) OR (nro_lote = ?) ) AND
                    ( (vto IS NULL AND ? IS NULL) OR (vto = ?) ) LIMIT 1",
                    [$productId, $nro_lote, $nro_lote, $vto, $vto]);
    if ($lot) return (int)$lot['id'];
    DB::exec("INSERT INTO depo_lots (product_id, nro_lote, vto) VALUES (?,?,?)",
             [$productId, $nro_lote ?: null, $vto ?: null]);
    return (int)DB::lastId();
  }

  /**
   * Aplica un movimiento y actualiza depo_stock.
   * $tipo: ingreso_compra | ingreso_ajuste | egreso_venta | egreso_ajuste | transfer_salida | transfer_ingreso
   * $header: [deposit_from?, deposit_to?, user_id?, ref_table?, ref_id?, notas?]
   * $items: array de [product_id, lot_id|null, cantidad(+/-), costo_unit?, precio_unit?]
   *   - Para Ingresos: cantidad > 0
   *   - Para Egresos:  cantidad > 0 (se resta internamente)
   */
  public static function applyMovement(string $tipo, array $header, array $items): int {
    DB::exec("INSERT INTO depo_movements (tipo, deposit_from, deposit_to, user_id, ref_table, ref_id, notas)
              VALUES (?,?,?,?,?, ?,?)", [
                $tipo,
                $header['deposit_from'] ?? null,
                $header['deposit_to']   ?? null,
                $header['user_id']      ?? null,
                $header['ref_table']    ?? null,
                $header['ref_id']       ?? null,
                $header['notas']        ?? null,
              ]);
    $movId = (int)DB::lastId();

    foreach ($items as $it) {
      $pid = (int)$it['product_id'];
      $lot = $it['lot_id'] !== null ? (int)$it['lot_id'] : null;
      $qty = (float)$it['cantidad'];        // positiva
      $cu  = isset($it['costo_unit'])  ? (float)$it['costo_unit']  : null;
      $pu  = isset($it['precio_unit']) ? (float)$it['precio_unit'] : null;

      DB::exec("INSERT INTO depo_movement_items (movement_id, product_id, lot_id, cantidad, costo_unit, precio_unit)
                VALUES (?,?,?,?,?,?)", [$movId, $pid, $lot, $qty, $cu, $pu]);

      // Determinar depósito que impacta según tipo (ingreso/egreso/transferencia)
      $depImpact = null;
      $delta     = 0.0;
      switch ($tipo) {
        case 'ingreso_compra':
        case 'ingreso_ajuste':
        case 'transfer_ingreso':
          $depImpact = (int)($header['deposit_to'] ?? $header['deposit_id'] ?? $header['deposit_from'] ?? 1);
          $delta = +$qty;
          break;
        case 'egreso_venta':
        case 'egreso_ajuste':
        case 'transfer_salida':
          $depImpact = (int)($header['deposit_from'] ?? $header['deposit_id'] ?? 1);
          $delta = -$qty;
          break;
      }

      // UPSERT en depo_stock (lot_id puede ser NULL; la unique usa lot_key generado)
      DB::exec("
        INSERT INTO depo_stock (product_id, deposit_id, lot_id, cantidad)
        VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)
      ", [$pid, $depImpact, $lot, $delta]);
    }

    return $movId;
  }
}
