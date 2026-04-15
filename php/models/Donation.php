<?php
declare(strict_types=1);

class Donation extends Model
{
    protected static string $table  = 'donations';
    protected static array  $hidden = [];

    public static function create(array $data): array
    {
        if (!isset($data['referenceNumber'])) {
            $data['referenceNumber'] = Helpers::generateReference();
        }
        return parent::create($data);
    }

    public static function toPublic(array $row): array
    {
        $row['isAnonymous'] = (bool)(int)($row['isAnonymous'] ?? 0);
        $row['amount']      = (float)$row['amount'];
        return $row;
    }
}
