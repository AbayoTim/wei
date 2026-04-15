<?php
declare(strict_types=1);

class Blog extends Model
{
    protected static string $table  = 'blogs';
    protected static array  $hidden = [];

    public static function create(array $data): array
    {
        if (empty($data['slug']) && !empty($data['title'])) {
            $base = Helpers::generateSlug($data['title']);
            $data['slug'] = self::uniqueSlug($base);
        }
        if (($data['status'] ?? '') === 'published' && empty($data['publishedAt'])) {
            $data['publishedAt'] = self::now();
        }
        if (!isset($data['tags']))    $data['tags']    = '[]';
        if (!isset($data['gallery'])) $data['gallery'] = '[]';
        return parent::create($data);
    }

    public static function update(string $id, array $data): ?array
    {
        if (isset($data['title']) && !isset($data['slug'])) {
            $base = Helpers::generateSlug($data['title']);
            $data['slug'] = self::uniqueSlug($base, $id);
        }
        if (($data['status'] ?? '') === 'published') {
            $existing = static::find($id);
            if ($existing && $existing['status'] !== 'published') {
                $data['publishedAt'] = self::now();
            }
        }
        return parent::update($id, $data);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::findBy('slug', $slug);
    }

    public static function getCategories(): array
    {
        $stmt = self::db()->query(
            "SELECT DISTINCT category FROM blogs WHERE status='published' AND category IS NOT NULL ORDER BY category"
        );
        return array_column($stmt->fetchAll(), 'category');
    }

    public static function incrementViews(string $id): void
    {
        self::db()->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?")->execute([$id]);
    }

    private static function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug  = $base;
        $index = 1;
        do {
            $sql    = "SELECT COUNT(*) FROM blogs WHERE slug = ?";
            $params = [$slug];
            if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            if ((int)$stmt->fetchColumn() === 0) break;
            $index++;
            $slug = "$base-$index";
        } while (true);
        return $slug;
    }

    public static function toPublic(array $row): array
    {
        $row['tags']    = Helpers::jsonDecode($row['tags'] ?? null, []);
        $row['gallery'] = Helpers::jsonDecode($row['gallery'] ?? null, []);
        return $row;
    }
}
