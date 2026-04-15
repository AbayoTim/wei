<?php
declare(strict_types=1);

class Subscriber extends Model
{
    protected static string $table  = 'subscribers';
    protected static array  $hidden = [];

    public static function create(array $data): array
    {
        if (!isset($data['confirmationToken'])) {
            $data['confirmationToken'] = Helpers::randomToken();
        }
        return parent::create($data);
    }

    public static function findByEmail(string $email): ?array
    {
        return static::findBy('email', $email);
    }

    public static function findByToken(string $token): ?array
    {
        return static::findBy('confirmationToken', $token);
    }
}
