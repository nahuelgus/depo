<?php
class DB {
  private static ?PDO $pdo = null;

  public static function init(string $dsn, string $user, string $pass): void {
    if (self::$pdo) return;
    $opts = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    self::$pdo = new PDO($dsn, $user, $pass, $opts);
    self::$pdo->exec("SET NAMES utf8mb4; SET time_zone='-03:00'");
  }

  public static function pdo(): PDO { return self::$pdo; }

  public static function one(string $sql, array $p = []) {
    $st = self::$pdo->prepare($sql); $st->execute($p); return $st->fetch();
  }
  public static function all(string $sql, array $p = []): array {
    $st = self::$pdo->prepare($sql); $st->execute($p); return $st->fetchAll();
  }
  public static function exec(string $sql, array $p = []): int {
    $st = self::$pdo->prepare($sql); $st->execute($p); return $st->rowCount();
  }
  public static function lastId(): string { return self::$pdo->lastInsertId(); }
}
