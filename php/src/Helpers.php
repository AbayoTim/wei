<?php
declare(strict_types=1);

class Helpers
{
    public static function generateToken(array $user): string
    {
        return JWT::encode([
            'id'   => $user['id'],
            'role' => $user['role'],
        ], JWT_SECRET);
    }

    /**
     * XSS-safe HTML entity encoding (same as sanitizeHtml in Node.js helpers)
     */
    public static function sanitize(?string $value): string
    {
        if ($value === null) return '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function generateSlug(string $title): string
    {
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Make a slug unique in a given table/column.
     * Appends -2, -3, … until unique.
     */
    public static function uniqueSlug(PDO $db, string $table, string $base, ?string $excludeId = null): string
    {
        $slug  = $base;
        $index = 1;
        do {
            $sql    = "SELECT COUNT(*) FROM $table WHERE slug = ?";
            $params = [$slug];
            if ($excludeId) { $sql .= " AND id != ?"; $params[] = $excludeId; }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() === 0) break;
            $index++;
            $slug = "$base-$index";
        } while (true);

        return $slug;
    }

    /**
     * Generate a donation reference number like WEI-AB3F12-C7D
     */
    public static function generateReference(): string
    {
        $ts  = strtoupper(base_convert((string) time(), 10, 36));
        $rnd = strtoupper(bin2hex(random_bytes(2)));
        return "WEI-$ts-$rnd";
    }

    /**
     * Generate a secure random token (for subscription links, etc.)
     */
    public static function randomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function paginate(int $page, int $limit): array
    {
        $page   = max(1, $page);
        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;
        return ['limit' => $limit, 'offset' => $offset, 'page' => $page];
    }

    /**
     * Cast boolean-ish values from form/JSON input.
     */
    public static function boolVal(mixed $v): bool
    {
        if (is_bool($v)) return $v;
        if (is_string($v)) return in_array(strtolower($v), ['1', 'true', 'yes'], true);
        return (bool) $v;
    }

    /**
     * Strip keys with null values from an array (for partial updates).
     */
    public static function compact(array $data): array
    {
        return array_filter($data, fn($v) => $v !== null);
    }

    /**
     * Decode a JSON column or return the fallback.
     */
    public static function jsonDecode(?string $value, mixed $default = []): mixed
    {
        if ($value === null) return $default;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }
}
