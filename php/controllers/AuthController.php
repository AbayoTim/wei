<?php
declare(strict_types=1);

class AuthController
{
    public static function login(Request $req): void
    {
        RateLimit::auth($req->ip());

        $email    = trim((string)($req->body['email']    ?? ''));
        $password = trim((string)($req->body['password'] ?? ''));

        if (!$email || !$password) {
            Response::error('Email and password are required.');
        }

        $user = User::findByEmail($email);
        if (!$user || !User::verifyPassword($user, $password)) {
            Response::error('Invalid email or password.', 401);
        }
        if (!$user['isActive']) {
            Response::error('Account is inactive. Please contact administrator.', 401);
        }

        User::update($user['id'], ['lastLogin' => gmdate('Y-m-d H:i:s')]);

        $token = Helpers::generateToken($user);
        Response::json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'  => User::toPublic($user),
                'token' => $token,
            ],
        ]);
    }

    public static function getMe(Request $req): void
    {
        Auth::required($req);
        Response::success(User::toPublic($req->userRow));
    }

    public static function updatePassword(Request $req): void
    {
        Auth::required($req);

        $current = trim((string)($req->body['currentPassword'] ?? ''));
        $new     = trim((string)($req->body['newPassword']     ?? ''));

        if (!$current || !$new) {
            Response::error('currentPassword and newPassword are required.');
        }
        if (strlen($new) < 8) {
            Response::error('New password must be at least 8 characters.');
        }

        $user = User::find($req->userId);
        if (!User::verifyPassword($user, $current)) {
            Response::error('Current password is incorrect.');
        }

        User::update($req->userId, ['password' => $new]);
        Response::success(null, 'Password updated successfully.');
    }

    public static function createUser(Request $req): void
    {
        Auth::required($req);
        Auth::adminOnly($req);

        $email    = trim((string)($req->body['email']    ?? ''));
        $password = trim((string)($req->body['password'] ?? ''));
        $name     = trim((string)($req->body['name']     ?? ''));
        $role     = in_array($req->body['role'] ?? '', ['admin', 'editor']) ? $req->body['role'] : 'editor';

        if (!$email || !$password || !$name) {
            Response::error('email, password, and name are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address.');
        }
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.');
        }
        if (User::findByEmail($email)) {
            Response::error('Email already registered.');
        }

        $user = User::create(['email' => $email, 'password' => $password, 'name' => $name, 'role' => $role]);
        Response::success(User::toPublic($user), 'User created successfully.', 201);
    }

    public static function getUsers(Request $req): void
    {
        Auth::required($req);
        Auth::adminOnly($req);

        $users = User::findAll('', [], 'createdAt DESC');
        Response::success(User::publicAll($users));
    }

    public static function updateUser(Request $req): void
    {
        Auth::required($req);
        Auth::adminOnly($req);

        $id   = $req->param('id');
        $user = User::find($id);
        if (!$user) Response::error('User not found.', 404);

        $updates = [];
        foreach (['name', 'email', 'role', 'isActive'] as $f) {
            if (array_key_exists($f, $req->body)) {
                $updates[$f] = $req->body[$f];
            }
        }
        if (!empty($req->body['newPassword'])) {
            $updates['password'] = $req->body['newPassword'];
        }
        if (isset($updates['isActive'])) {
            $updates['isActive'] = Helpers::boolVal($updates['isActive']) ? 1 : 0;
        }

        $updated = User::update($id, $updates);
        Response::success(User::toPublic($updated), 'User updated successfully.');
    }

    public static function deleteUser(Request $req): void
    {
        Auth::required($req);
        Auth::adminOnly($req);

        $id = $req->param('id');
        if ($id === $req->userId) {
            Response::error('Cannot delete your own account.');
        }

        $user = User::find($id);
        if (!$user) Response::error('User not found.', 404);

        User::delete($id);
        Response::success(null, 'User deleted successfully.');
    }
}
