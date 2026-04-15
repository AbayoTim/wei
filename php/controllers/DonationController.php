<?php
declare(strict_types=1);

class DonationController
{
    private static array $ALLOWED_RECEIPT_MIME = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf',
    ];

    public static function store(Request $req): void
    {
        RateLimit::form($req->ip(), (bool)$req->userId);
        Honeypot::check($req, [
            'success' => true,
            'message' => 'Donation submitted successfully.',
            'data'    => ['referenceNumber' => 'WEI-' . strtoupper(bin2hex(random_bytes(4)))],
        ]);

        $donorName  = Helpers::sanitize(trim($req->body['donorName']  ?? ''));
        $donorEmail = trim($req->body['donorEmail'] ?? '');
        $donorPhone = Helpers::sanitize(trim($req->body['donorPhone'] ?? ''));
        $amount     = $req->body['amount']     ?? '';
        $currency   = strtoupper(trim($req->body['currency']  ?? 'TZS'));
        $payMethod  = Helpers::sanitize(trim($req->body['paymentMethod'] ?? ''));
        $txRef      = Helpers::sanitize(trim($req->body['transactionReference'] ?? ''));
        $message    = Helpers::sanitize(trim($req->body['message'] ?? ''));
        $cause      = Helpers::sanitize(trim($req->body['cause']   ?? ''));
        $isAnon     = Helpers::boolVal($req->body['isAnonymous'] ?? false);

        if (!$donorName || !$donorEmail || !$amount) {
            Response::error('donorName, donorEmail, and amount are required.');
        }
        if (!filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.');
        }
        if (!is_numeric($amount) || (float)$amount <= 0) {
            Response::error('Amount must be a positive number.');
        }
        if (!in_array($currency, ['TZS','USD','EUR','GBP'], true)) {
            $currency = 'TZS';
        }

        // Handle receipt upload
        $receiptFile = null;
        if ($req->hasFile('receiptFile')) {
            $file = $req->files['receiptFile'];

            if ($file['size'] > MAX_UPLOAD_SIZE) {
                Response::error('Receipt file must be under 5 MB.');
            }
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, self::$ALLOWED_RECEIPT_MIME, true)) {
                Response::error('Receipt must be a JPEG, PNG, GIF, or PDF.');
            }

            $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename    = Database::uuid() . '.' . strtolower($ext);
            $destDir     = UPLOAD_DIR . 'receipts/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                Response::error('Failed to save receipt file.', 500);
            }
            $receiptFile = $filename;
        }

        $donation = Donation::create([
            'donorName'            => $donorName,
            'donorEmail'           => $donorEmail,
            'donorPhone'           => $donorPhone ?: null,
            'amount'               => (float)$amount,
            'currency'             => $currency,
            'paymentMethod'        => $payMethod ?: null,
            'transactionReference' => $txRef ?: null,
            'receiptFile'          => $receiptFile,
            'message'              => $message ?: null,
            'cause'                => $cause ?: null,
            'isAnonymous'          => $isAnon ? 1 : 0,
        ]);

        Email::send($donorEmail, 'donationReceived', [
            $donorName, (float)$amount, $currency, $donation['referenceNumber'],
        ]);
        Email::notifyAdmin('Donation Submission', [
            'Reference Number' => $donation['referenceNumber'],
            'Donor'            => $isAnon ? 'Anonymous' : $donorName,
            'Email'            => $donorEmail,
            'Amount'           => "$currency $amount",
            'Payment Method'   => $payMethod ?: 'Not specified',
            'Tx Reference'     => $txRef ?: 'Not provided',
            'Status'           => 'Pending Verification',
        ]);

        Response::json([
            'success' => true,
            'message' => 'Donation submitted successfully. We will verify your payment and send a confirmation.',
            'data'    => ['referenceNumber' => $donation['referenceNumber']],
        ], 201);
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
        $total = Donation::count($whereStr, $params);
        $rows  = Donation::findAll($whereStr, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        // Attach approver name
        $db   = Database::getInstance();
        $rows = array_map(function ($row) use ($db) {
            $row = Donation::toPublic($row);
            if ($row['approvedBy']) {
                $u = $db->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
                $u->execute([$row['approvedBy']]);
                $row['approver'] = $u->fetch() ?: null;
            } else {
                $row['approver'] = null;
            }
            return $row;
        }, $rows);

        Response::paginated($rows, $total, $pg['page'], $pg['limit']);
    }

    public static function show(Request $req): void
    {
        Auth::required($req);

        $id       = $req->param('id');
        $donation = Donation::find($id);
        if (!$donation) Response::error('Donation not found.', 404);

        Response::success(Donation::toPublic($donation));
    }

    public static function approve(Request $req): void
    {
        Auth::required($req);

        $id       = $req->param('id');
        $donation = Donation::find($id);
        if (!$donation) Response::error('Donation not found.', 404);
        if ($donation['status'] !== 'pending') {
            Response::error('Only pending donations can be approved.');
        }

        $updated = Donation::update($id, [
            'status'     => 'approved',
            'approvedAt' => gmdate('Y-m-d H:i:s'),
            'approvedBy' => $req->userId,
        ]);

        // Increment raisedAmount on matching active causes
        if ($donation['cause']) {
            Cause::incrementRaised($donation['cause'], $donation['currency'], (float)$donation['amount']);
        }

        Email::send($donation['donorEmail'], 'donationApproved', [
            $donation['donorName'],
            (float)$donation['amount'],
            $donation['currency'],
            $donation['referenceNumber'],
        ]);

        Response::success(Donation::toPublic($updated), 'Donation approved successfully.');
    }

    public static function reject(Request $req): void
    {
        Auth::required($req);

        $id       = $req->param('id');
        $donation = Donation::find($id);
        if (!$donation) Response::error('Donation not found.', 404);
        if ($donation['status'] !== 'pending') {
            Response::error('Only pending donations can be rejected.');
        }

        $reason  = Helpers::sanitize(trim($req->body['reason'] ?? ''));
        $updated = Donation::update($id, [
            'status'          => 'rejected',
            'rejectionReason' => $reason ?: null,
            'approvedBy'      => $req->userId,
        ]);

        Email::send($donation['donorEmail'], 'donationRejected', [
            $donation['donorName'],
            $donation['referenceNumber'],
            $reason ?: null,
        ]);

        Response::success(Donation::toPublic($updated), 'Donation rejected.');
    }

    public static function stats(Request $req): void
    {
        Auth::required($req);

        $db = Database::getInstance();

        $total    = Donation::count();
        $pending  = Donation::count("status = 'pending'");
        $approved = Donation::count("status = 'approved'");
        $rejected = Donation::count("status = 'rejected'");

        $stmt = $db->query(
            "SELECT currency, SUM(amount) as total FROM donations WHERE status='approved' GROUP BY currency"
        );
        $totalsByCurrency = [];
        foreach ($stmt->fetchAll() as $row) {
            $totalsByCurrency[$row['currency']] = (float)$row['total'];
        }

        Response::success([
            'total'            => $total,
            'pending'          => $pending,
            'approved'         => $approved,
            'rejected'         => $rejected,
            'totalsByCurrency' => $totalsByCurrency,
        ]);
    }

    public static function approved(Request $req): void
    {
        $limit = min(100, max(1, (int)($req->query['limit'] ?? 10)));
        $rows  = Donation::findAll(
            "status = 'approved' AND isAnonymous = 0",
            [],
            'approvedAt DESC',
            $limit
        );
        $public = array_map(fn($r) => [
            'donorName'  => $r['donorName'],
            'amount'     => (float)$r['amount'],
            'currency'   => $r['currency'],
            'cause'      => $r['cause'],
            'approvedAt' => $r['approvedAt'],
        ], $rows);

        Response::success($public);
    }

    public static function receipt(Request $req): void
    {
        Auth::required($req);

        $filename = $req->param('filename');

        // Validate: UUID + allowed extension, no path traversal
        if (!preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(jpg|jpeg|png|gif|pdf)$/i',
            $filename
        )) {
            Response::error('Invalid filename.', 400);
        }

        $path = UPLOAD_DIR . 'receipts/' . $filename;
        if (!file_exists($path)) Response::error('Receipt not found.', 404);

        Response::file($path);
    }
}
