<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper — base for all model classes.
 */
abstract class Model
{
    protected static string $table  = '';
    protected static array  $hidden = ['password'];   // Fields stripped by toPublic()

    protected static function db(): PDO
    {
        return Database::getInstance();
    }

    // ── Finders ────────────────────────────────────────────────────────────

    public static function find(string $id): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM " . static::$table . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM " . static::$table . " WHERE $column = ? LIMIT 1");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public static function findAll(string $where = '', array $params = [], string $orderBy = 'createdAt DESC', int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT * FROM " . static::$table;
        if ($where) $sql .= " WHERE $where";
        $sql .= " ORDER BY $orderBy";
        if ($limit > 0) $sql .= " LIMIT $limit OFFSET $offset";
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM " . static::$table;
        if ($where) $sql .= " WHERE $where";
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── Mutations ──────────────────────────────────────────────────────────

    public static function create(array $data): array
    {
        $data['id']        = $data['id']        ?? Database::uuid();
        $data['createdAt'] = $data['createdAt'] ?? self::now();
        $data['updatedAt'] = $data['updatedAt'] ?? self::now();

        $cols   = array_keys($data);
        $places = array_fill(0, count($cols), '?');
        $sql    = "INSERT INTO " . static::$table
                . " (" . implode(', ', $cols) . ")"
                . " VALUES (" . implode(', ', $places) . ")";

        self::db()->prepare($sql)->execute(array_values($data));
        return static::find($data['id']);
    }

    public static function update(string $id, array $data): ?array
    {
        $data['updatedAt'] = self::now();
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $sql  = "UPDATE " . static::$table . " SET $sets WHERE id = ?";
        self::db()->prepare($sql)->execute([...array_values($data), $id]);
        return static::find($id);
    }

    public static function delete(string $id): bool
    {
        $stmt = self::db()->prepare("DELETE FROM " . static::$table . " WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    protected static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Strip hidden fields and cast integers/booleans.
     */
    public static function toPublic(array $row): array
    {
        foreach (static::$hidden as $h) unset($row[$h]);
        // Cast SQLite integers stored as '0'/'1' for isActive, isAnonymous, etc.
        foreach ($row as $k => $v) {
            if (is_string($v) && ($v === '0' || $v === '1') && str_starts_with(strtolower($k), 'is')) {
                $row[$k] = (bool)(int)$v;
            }
        }
        return $row;
    }

    public static function publicAll(array $rows): array
    {
        return array_map([static::class, 'toPublic'], $rows);
    }
}
