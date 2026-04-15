<?php
declare(strict_types=1);

class Media extends Model
{
    protected static string $table  = 'media';
    protected static array  $hidden = [];

    public static function toPublic(array $row): array
    {
        $row['size'] = (int)$row['size'];
        return $row;
    }
}
