<?php
declare(strict_types=1);

class SiteContent extends Model
{
    protected static string $table  = 'site_contents';
    protected static array  $hidden = [];

    public static function findByKey(string $key): ?array
    {
        return static::findBy('key', $key);
    }

    /** Return all content as a key => {value, type} map */
    public static function getMap(): array
    {
        $rows = static::findAll();
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['key']] = ['value' => $row['value'], 'type' => $row['type'] ?? 'text'];
        }
        return $map;
    }
}
