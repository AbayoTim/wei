<?php
declare(strict_types=1);

class Partner extends Model
{
    protected static string $table  = 'partners';
    protected static array  $hidden = [];

    public static function toPublic(array $row): array
    {
        $row['isActive']     = (bool)(int)($row['isActive']     ?? 1);
        $row['displayOrder'] = (int)$row['displayOrder'];
        return $row;
    }
}
