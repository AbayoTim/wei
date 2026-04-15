<?php
declare(strict_types=1);

class Auth
{
    /**
     * Require a valid JWT. Aborts with 401 if missing or invalid.
     */
    public static function required(Request $req): void
    {
        $token = $req->bearerToken();
        if (!$token) {
            Response::error('No token provided. Please log in.', 401);
        }

        $payload = JWT::decode($token, JWT_SECRET);
        if (!$payload) {
            Response::error('Invalid or expired token. Please log in again.', 401);
        }

        // Verify user still exists and is active
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id, email, name, role, isActive FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$payload['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('User account not found.', 401);
        }
        if (!$user['isActive']) {
            Response::error('Account is inactive. Please contact administrator.', 401);
        }

        $req->userId   = $user['id'];
        $req->userRow  = $user;
        $req->userRole = $user['role'];
    }

    /**
     * Same as required() but does not abort — sets req props if valid.
     */
    public static function optional(Request $req): void
    {
        $token = $req->bearerToken();
        if (!$token) return;

        $payload = JWT::decode($token, JWT_SECRET);
        if (!$payload) return;

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id, email, name, role, isActive FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$payload['id']]);
        $user = $stmt->fetch();

        if ($user && $user['isActive']) {
            $req->userId   = $user['id'];
            $req->userRow  = $user;
            $req->userRole = $user['role'];
        }
    }

    /**
     * Require admin role (must call required() first).
     */
    public static function adminOnly(Request $req): void
    {
        if ($req->userRole !== 'admin') {
            Response::error('Admin access required.', 403);
        }
    }
}
