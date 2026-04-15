<?php
declare(strict_types=1);

class ContactController
{
    public static function store(Request $req): void
    {
        RateLimit::form($req->ip(), (bool)$req->userId);
        Honeypot::check($req, [
            'success' => true,
            'message' => 'Message sent successfully. We\'ll get back to you soon.',
        ]);

        $firstName = Helpers::sanitize(trim($req->body['firstName'] ?? ''));
        $lastName  = Helpers::sanitize(trim($req->body['lastName']  ?? ''));
        $email     = trim($req->body['email']   ?? '');
        $phone     = Helpers::sanitize(trim($req->body['phone']     ?? ''));
        $subject   = Helpers::sanitize(trim($req->body['subject']   ?? ''));
        $message   = Helpers::sanitize(trim($req->body['message']   ?? ''));

        if (!$firstName || !$lastName || !$email || !$subject || !$message) {
            Response::error('firstName, lastName, email, subject, and message are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.');
        }

        $contact = Contact::create([
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'email'     => $email,
            'phone'     => $phone ?: null,
            'subject'   => $subject,
            'message'   => $message,
            'ipAddress' => $req->ip(),
        ]);

        Email::send($email, 'contactReceived', [$firstName]);
        Email::notifyAdmin('Contact Message', [
            'From'    => "$firstName $lastName",
            'Email'   => $email,
            'Phone'   => $phone ?: 'Not provided',
            'Subject' => $subject,
            'Message' => mb_substr($message, 0, 200) . (mb_strlen($message) > 200 ? '...' : ''),
        ]);

        Response::success(
            ['id' => $contact['id']],
            'Message sent successfully. We\'ll get back to you soon.',
            201
        );
    }

    public static function index(Request $req): void
    {
        Auth::required($req);

        $page   = max(1, (int)($req->query['page']   ?? 1));
        $limit  = min(100, max(1, (int)($req->query['limit'] ?? 20)));
        $status = $req->query['status'] ?? null;

        $where  = []; $params = [];
        if ($status) { $where[] = "status = ?"; $params[] = $status; }

        $whereStr = $where ? implode(' AND ', $where) : '';
        $pg    = Helpers::paginate($page, $limit);
        $total = Contact::count($whereStr, $params);
        $rows  = Contact::findAll($whereStr, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        Response::paginated($rows, $total, $pg['page'], $pg['limit']);
    }

    public static function stats(Request $req): void
    {
        Auth::required($req);

        Response::success([
            'total'    => Contact::count(),
            'new'      => Contact::count("status = 'new'"),
            'read'     => Contact::count("status = 'read'"),
            'replied'  => Contact::count("status = 'replied'"),
            'archived' => Contact::count("status = 'archived'"),
        ]);
    }

    public static function show(Request $req): void
    {
        Auth::required($req);

        $id      = $req->param('id');
        $contact = Contact::find($id);
        if (!$contact) Response::error('Contact message not found.', 404);

        // Auto-mark as read
        if ($contact['status'] === 'new') {
            $contact = Contact::update($id, ['status' => 'read']);
        }

        Response::success($contact);
    }

    public static function reply(Request $req): void
    {
        Auth::required($req);

        $id      = $req->param('id');
        $contact = Contact::find($id);
        if (!$contact) Response::error('Contact message not found.', 404);

        $replyMessage = trim($req->body['replyMessage'] ?? '');
        $newStatus    = in_array($req->body['status'] ?? '', ['new','read','replied','archived'])
                        ? $req->body['status']
                        : null;

        $updates = [];
        if ($replyMessage) {
            $updates['replyMessage'] = $replyMessage;
            $updates['status']       = 'replied';
            $updates['repliedAt']    = gmdate('Y-m-d H:i:s');
            Email::send($contact['email'], 'contactReplied', [$contact['firstName'], $replyMessage]);
        }
        if ($newStatus) $updates['status'] = $newStatus;

        $updated = Contact::update($id, $updates);
        Response::success($updated, 'Contact updated.');
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);

        $id      = $req->param('id');
        $contact = Contact::find($id);
        if (!$contact) Response::error('Contact message not found.', 404);

        Contact::delete($id);
        Response::success(null, 'Contact message deleted.');
    }
}
