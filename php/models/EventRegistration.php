<?php
declare(strict_types=1);

class EventRegistration extends Model
{
    protected static string $table  = 'event_registrations';
    protected static array  $hidden = [];

    public static function generate6DigitCode(): string
    {
        $db = self::db();
        do {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $db->prepare("SELECT COUNT(*) FROM event_registrations WHERE authCode = ?");
            $stmt->execute([$code]);
        } while ((int)$stmt->fetchColumn() > 0);
        return $code;
    }

    public static function findByAuthCode(string $code): ?array
    {
        return static::findBy('authCode', $code);
    }

    public static function listByEvent(string $eventId, int $limit = 200, int $offset = 0): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM event_registrations WHERE eventId = ? ORDER BY createdAt DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$eventId, $limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countByEvent(string $eventId): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM event_registrations WHERE eventId = ?");
        $stmt->execute([$eventId]);
        return (int)$stmt->fetchColumn();
    }

    public static function toPublic(array $row): array
    {
        $row['checkedIn'] = (bool)(int)($row['checkedIn'] ?? 0);
        $row['amount']    = (float)($row['amount'] ?? 0);
        return $row;
    }
}
