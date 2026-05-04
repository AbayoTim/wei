<?php
declare(strict_types=1);

class EventRegistrationController
{
    // POST /api/events/:id/register  (public)
    public static function register(Request $req): void
    {
        $eventId = $req->param('id');
        $event   = Event::find($eventId);
        if (!$event) Response::error('Event not found.', 404);
        if (!(bool)(int)($event['isPaid'] ?? 0)) Response::error('This event does not require registration.', 400);

        $name  = trim($req->body['name']  ?? '');
        $email = trim($req->body['email'] ?? '');
        $phone = trim($req->body['phone'] ?? '');

        if (!$name || !$email)                           Response::error('Name and email are required.', 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  Response::error('Invalid email address.', 422);

        // Receipt file upload (optional but encouraged)
        $receiptFile = null;
        if (!empty($_FILES['receipt']['name'])) {
            $upDir  = UPLOAD_DIR . 'receipts/';
            if (!is_dir($upDir)) mkdir($upDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
            if (!in_array($ext, $allowed, true)) Response::error('Invalid receipt format.', 422);
            if ($_FILES['receipt']['size'] > MAX_UPLOAD_SIZE) Response::error('Receipt file too large.', 422);
            $filename    = Helpers::generateId() . '.' . $ext;
            move_uploaded_file($_FILES['receipt']['tmp_name'], $upDir . $filename);
            $receiptFile = $filename;
        }

        $authCode = EventRegistration::generate6DigitCode();

        $reg = EventRegistration::create([
            'id'            => Helpers::generateId(),
            'eventId'       => $eventId,
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone ?: null,
            'amount'        => (float)($event['ticketPrice'] ?? 0),
            'currency'      => $event['ticketCurrency'] ?? 'TZS',
            'paymentMethod' => $req->body['paymentMethod'] ?? null,
            'transactionRef'=> $req->body['transactionRef'] ?? null,
            'receiptFile'   => $receiptFile,
            'authCode'      => $authCode,
            'status'        => 'pending',
        ]);

        // Send ticket email
        $eventPublic = Event::toPublic($event);
        Email::send($email, 'eventTicket', [$name, $authCode, $eventPublic]);

        // Admin notification
        Email::notifyAdmin('Event Registration', [
            'Event'      => $event['title'],
            'Attendee'   => $name,
            'Email'      => $email,
            'Auth Code'  => $authCode,
            'Amount'     => ($event['ticketCurrency'] ?? 'TZS') . ' ' . number_format((float)($event['ticketPrice'] ?? 0), 2),
        ]);

        Response::success(EventRegistration::toPublic($reg), 'Registration successful! Your ticket has been sent to ' . $email);
    }

    // POST /api/events/validate  (admin)
    public static function validate(Request $req): void
    {
        Auth::required($req);
        $code = trim($req->body['code'] ?? '');
        $code = preg_replace('/\D/', '', $code); // keep digits only
        if (strlen($code) !== 6) Response::error('Please enter a valid 6-digit code.', 422);

        $reg = EventRegistration::findByAuthCode($code);
        if (!$reg) Response::error('Invalid entrance code. No registration found.', 404);

        $event = Event::find($reg['eventId']);
        Response::success(array_merge(
            EventRegistration::toPublic($reg),
            ['event' => $event ? Event::toPublic($event) : null]
        ));
    }

    // POST /api/events/checkin  (admin)
    public static function checkin(Request $req): void
    {
        Auth::required($req);
        $code = $req->body['code'] ?? '';
        $code = preg_replace('/\D/', '', (string)$code);
        if (strlen($code) !== 6) Response::error('Please enter a valid 6-digit code.', 422);

        $reg = EventRegistration::findByAuthCode($code);
        if (!$reg) Response::error('Invalid entrance code. No registration found.', 404);

        if ((bool)(int)($reg['checkedIn'] ?? 0)) {
            $event = Event::find($reg['eventId']);
            Response::success(
                array_merge(EventRegistration::toPublic($reg), [
                    'event'           => $event ? Event::toPublic($event) : null,
                    'alreadyCheckedIn'=> true,
                ]),
                'Already checked in at ' . ($reg['checkedInAt'] ?? 'unknown time') . '.'
            );
        }

        EventRegistration::update($reg['id'], [
            'checkedIn'  => 1,
            'checkedInAt'=> date('Y-m-d H:i:s'),
            'status'     => 'confirmed',
        ]);

        $updated = EventRegistration::find($reg['id']);
        $event   = Event::find($reg['eventId']);
        Response::success(
            array_merge(EventRegistration::toPublic($updated), [
                'event' => $event ? Event::toPublic($event) : null,
            ]),
            'Entrance granted!'
        );
    }

    // GET /api/events/:id/registrations  (admin)
    public static function listByEvent(Request $req): void
    {
        Auth::required($req);
        $eventId = $req->param('id');
        if (!Event::find($eventId)) Response::error('Event not found.', 404);
        $rows = EventRegistration::listByEvent($eventId);
        Response::success(array_map([EventRegistration::class, 'toPublic'], $rows));
    }
}
