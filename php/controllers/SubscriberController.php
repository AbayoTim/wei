<?php
declare(strict_types=1);

class SubscriberController
{
    public static function subscribe(Request $req): void
    {
        RateLimit::subscribe($req->ip());
        Honeypot::check($req, ['success' => true, 'message' => 'You have been subscribed successfully!']);

        $email = strtolower(trim((string)($req->body['email'] ?? '')));
        $name  = trim((string)($req->body['name']  ?? ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.');
        }

        $existing = Subscriber::findByEmail($email);

        if ($existing) {
            if ($existing['status'] === 'confirmed') {
                Response::error('Email already subscribed.');
            }
            // Re-activate
            $updated = Subscriber::update($existing['id'], [
                'status'         => 'confirmed',
                'confirmedAt'    => gmdate('Y-m-d H:i:s'),
                'unsubscribedAt' => null,
                'name'           => $name ?: $existing['name'],
            ]);
            $unsubUrl = FRONTEND_URL . '/api/subscribers/unsubscribe/' . $updated['confirmationToken'];
            Email::send($updated['email'], 'subscriptionConfirmed', [$updated['name'] ?? '', $unsubUrl]);
            Response::success(null, 'You have been subscribed successfully!');
        }

        $sub = Subscriber::create([
            'email'      => $email,
            'name'       => $name ?: null,
            'status'     => 'confirmed',
            'confirmedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $unsubUrl = FRONTEND_URL . '/api/subscribers/unsubscribe/' . $sub['confirmationToken'];
        Email::send($email, 'subscriptionConfirmed', [$name, $unsubUrl]);
        Email::notifyAdmin('Newsletter Subscription', [
            'Email'  => $email,
            'Name'   => $name ?: 'Not provided',
            'Status' => 'Subscribed',
        ]);

        Response::success(null, 'You have been subscribed successfully!', 201);
    }

    public static function confirm(Request $req): void
    {
        $token = $req->param('token');
        $sub   = Subscriber::findByToken($token);

        if (!$sub) {
            Response::redirect(FRONTEND_URL . '/subscription-error');
        }
        if ($sub['status'] === 'confirmed') {
            Response::redirect(FRONTEND_URL . '/subscription-confirmed');
        }

        $updated  = Subscriber::update($sub['id'], ['status' => 'confirmed', 'confirmedAt' => gmdate('Y-m-d H:i:s')]);
        $unsubUrl = FRONTEND_URL . '/api/subscribers/unsubscribe/' . $updated['confirmationToken'];
        Email::send($updated['email'], 'subscriptionConfirmed', [$updated['name'] ?? '', $unsubUrl]);

        Response::redirect(FRONTEND_URL . '/subscription-confirmed');
    }

    public static function unsubscribe(Request $req): void
    {
        $token = $req->param('token');
        $sub   = Subscriber::findByToken($token);

        if (!$sub) {
            Response::redirect(FRONTEND_URL . '/subscription-error');
        }

        Subscriber::update($sub['id'], ['status' => 'unsubscribed', 'unsubscribedAt' => gmdate('Y-m-d H:i:s')]);
        Response::redirect(FRONTEND_URL . '/unsubscribed');
    }

    public static function index(Request $req): void
    {
        Auth::required($req);

        $page   = max(1, (int)($req->query['page']  ?? 1));
        $limit  = min(100, max(1, (int)($req->query['limit'] ?? 20)));
        $status = $req->query['status'] ?? null;

        $where  = $status ? "status = ?" : "status = 'confirmed'";
        $params = $status ? [$status] : [];

        $pg    = Helpers::paginate($page, $limit);
        $total = Subscriber::count($where, $params);
        $rows  = Subscriber::findAll($where, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        Response::paginated($rows, $total, $pg['page'], $pg['limit']);
    }

    public static function stats(Request $req): void
    {
        Auth::required($req);

        $confirmed   = Subscriber::count("status = 'confirmed'");
        $unsubscribed = Subscriber::count("status = 'unsubscribed'");

        Response::success([
            'confirmed'    => $confirmed,
            'unsubscribed' => $unsubscribed,
            'total'        => $confirmed + $unsubscribed,
        ]);
    }

    public static function export(Request $req): void
    {
        Auth::required($req);

        $rows = Subscriber::findAll("status = 'confirmed'", [], 'confirmedAt DESC');

        $escape = fn($v) => '"' . str_replace('"', '""', (string)($v ?? '')) . '"';
        $lines  = ['"Email","Name","Confirmed At"'];
        foreach ($rows as $r) {
            $lines[] = implode(',', [$escape($r['email']), $escape($r['name']), $escape($r['confirmedAt'])]);
        }

        Response::csv(implode("\n", $lines), 'subscribers.csv');
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);

        $id  = $req->param('id');
        $sub = Subscriber::find($id);
        if (!$sub) Response::error('Subscriber not found.', 404);

        Subscriber::delete($id);
        Response::success(null, 'Subscriber deleted successfully.');
    }
}
