<?php
declare(strict_types=1);

class JWT
{
    public static function encode(array $payload, string $secret, int $ttl = JWT_TTL): string
    {
        $header  = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;
        $body    = self::b64u(json_encode($payload));
        $sig     = self::b64u(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;

        $expected = self::b64u(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expected, $sig)) return null;

        $data = json_decode(self::b64d($body), true);
        if (!is_array($data)) return null;
        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    private static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64d(string $data): string
    {
        $pad    = strlen($data) % 4;
        $padded = $pad ? $data . str_repeat('=', 4 - $pad) : $data;
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
