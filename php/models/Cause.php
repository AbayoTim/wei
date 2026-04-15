<?php
declare(strict_types=1);

class Cause extends Model
{
    protected static string $table  = 'causes';
    protected static array  $hidden = [];

    public static function create(array $data): array
    {
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = self::uniqueSlug(Helpers::generateSlug($data['title']));
        }
        if (!isset($data['gallery']))     $data['gallery']     = '[]';
        if (!isset($data['raisedAmount'])) $data['raisedAmount'] = 0;
        return parent::create($data);
    }

    public static function update(string $id, array $data): ?array
    {
        if (isset($data['title']) && !isset($data['slug'])) {
            $data['slug'] = self::uniqueSlug(Helpers::generateSlug($data['title']), $id);
        }
        return parent::update($id, $data);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::findBy('slug', $slug);
    }

    public static function incrementRaised(string $category, string $currency, float $amount): void
    {
        self::db()->prepare(
            "UPDATE causes SET raisedAmount = raisedAmount + ? WHERE category = ? AND currency = ? AND status = 'active'"
        )->execute([$amount, $category, $currency]);
    }

    public static function toPublic(array $row): array
    {
        $row['isFeatured']  = (bool)(int)($row['isFeatured']  ?? 0);
        $row['isPublished'] = (bool)(int)($row['isPublished'] ?? 0);
        $row['goalAmount']  = $row['goalAmount']  !== null ? (float)$row['goalAmount']  : null;
        $row['raisedAmount'] = (float)($row['raisedAmount'] ?? 0);
        $row['gallery']     = Helpers::jsonDecode($row['gallery'] ?? null, []);
        return $row;
    }

    private static function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base; $i = 1;
        do {
            $sql = "SELECT COUNT(*) FROM causes WHERE slug = ?";
            $p   = [$slug];
            if ($excludeId) { $sql .= " AND id != ?"; $p[] = $excludeId; }
            $stmt = self::db()->prepare($sql); $stmt->execute($p);
            if ((int)$stmt->fetchColumn() === 0) break;
            $slug = "$base-" . (++$i);
        } while (true);
        return $slug;
    }
}
