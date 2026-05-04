<?php
declare(strict_types=1);

class GalleryItem extends Model
{
    protected static string $table  = 'gallery_items';
    protected static array  $hidden = [];

    public static function toPublic(array $row): array
    {
        $row['isPublished'] = (bool)(int)($row['isPublished'] ?? 1);
        $row['sortOrder']   = (int)($row['sortOrder'] ?? 0);
        return $row;
    }
}
