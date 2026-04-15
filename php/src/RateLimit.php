<?php
declare(strict_types=1);

/**
 * SQLite-backed rate limiter.
 * Mirrors the Node.js express-rate-limit behaviour used in server.js.
 */
class RateLimit
{
    /**
     * @param string $key       Unique bucket key (e.g. "ip:1.2.3.4:login")
     * @param int    $maxHits   Maximum allowed hits in the window
     * @param int    $windowSec Window length in seconds
     * @param bool   $skip      If true the check is bypassed (mimics the skip callback)
     */
    public static function check(string $key, int $maxHits, int $windowSec, bool $skip = false): void
    {
        if ($skip) return;

        $db  = Database::getInstance();
        $now = time();

        // Clean expired entries for this key
        $db->prepare("DELETE FROM rate_limits WHERE rkey = ? AND resetAt < ?")
           ->execute([$key, $now]);

        $stmt = $db->prepare("SELECT id, count, resetAt FROM rate_limits WHERE rkey = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            // First hit — create entry
            $db->prepare("INSERT INTO rate_limits (id, rkey, count, resetAt) VALUES (?, ?, 1, ?)")
               ->execute([Database::uuid(), $key, $now + $windowSec]);
            return;
        }

        $newCount = $row['count'] + 1;
        if ($newCount > $maxHits) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            $reset = $row['resetAt'];
            header("X-RateLimit-Limit: $maxHits");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: $reset");
            echo json_encode(['success' => false, 'message' => 'Too many requests, please try again later.']);
            exit;
        }

        $db->prepare("UPDATE rate_limits SET count = ? WHERE id = ?")
           ->execute([$newCount, $row['id']]);
    }

    // Predefined limiters matching server.js --------------------------------

    /** General API: 100 req / 15 min */
    public static function api(string $ip): void
    {
        self::check("api:$ip", 100, 15 * 60);
    }

    /** Auth (login): 5 attempts / 1 hour */
    public static function auth(string $ip): void
    {
        self::check("auth:$ip", 5, 3600);
    }

    /** Public forms (donations, contact): 10 / 1 hour; skipped when authenticated */
    public static function form(string $ip, bool $isAuthenticated = false): void
    {
        self::check("form:$ip", 10, 3600, $isAuthenticated);
    }

    /** Subscribe: 5 / 1 hour */
    public static function subscribe(string $ip): void
    {
        self::check("sub:$ip", 5, 3600);
    }
}
