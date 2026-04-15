<?php
declare(strict_types=1);

class User extends Model
{
    protected static string $table  = 'users';
    protected static array  $hidden = ['password'];

    public static function create(array $data): array
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return parent::create($data);
    }

    public static function update(string $id, array $data): ?array
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        return parent::update($id, $data);
    }

    public static function findByEmail(string $email): ?array
    {
        return static::findBy('email', $email);
    }

    public static function verifyPassword(array $user, string $plain): bool
    {
        return password_verify($plain, $user['password']);
    }

    public static function toPublic(array $row): array
    {
        unset($row['password']);
        $row['isActive'] = (bool)(int)$row['isActive'];
        return $row;
    }
}
