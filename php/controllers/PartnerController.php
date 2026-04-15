<?php
declare(strict_types=1);

class PartnerController
{
    public static function index(Request $req): void
    {
        $rows = Partner::findAll("isActive = 1", [], 'displayOrder ASC');
        Response::success(Partner::publicAll($rows));
    }

    public static function show(Request $req): void
    {
        $id      = $req->param('id');
        $partner = Partner::find($id);
        if (!$partner) Response::error('Partner not found.', 404);
        Response::success(Partner::toPublic($partner));
    }

    public static function store(Request $req): void
    {
        Auth::required($req);

        $name = Helpers::sanitize(trim($req->body['name'] ?? ''));
        if (!$name) Response::error('name is required.');

        $partnerTypes = ['funding','implementing','government','other'];
        $data = [
            'name'         => $name,
            'website'      => $req->body['website']     ?? null,
            'description'  => $req->body['description'] ?? null,
            'partnerType'  => in_array($req->body['partnerType'] ?? '', $partnerTypes)
                              ? $req->body['partnerType'] : 'other',
            'isActive'     => 1,
            'displayOrder' => (int)($req->body['displayOrder'] ?? 0),
        ];

        if ($req->hasFile('logo')) {
            $data['logo'] = self::saveLogo($req->files['logo']);
        } elseif (!empty($req->body['logo'])) {
            $data['logo'] = $req->body['logo'];
        }

        $partner = Partner::create($data);
        Response::success(Partner::toPublic($partner), 'Partner created.', 201);
    }

    public static function update(Request $req): void
    {
        Auth::required($req);

        $id      = $req->param('id');
        $partner = Partner::find($id);
        if (!$partner) Response::error('Partner not found.', 404);

        $partnerTypes = ['funding','implementing','government','other'];
        $updates = [];
        foreach (['name','website','description','partnerType','isActive','displayOrder'] as $f) {
            if (array_key_exists($f, $req->body)) $updates[$f] = $req->body[$f];
        }
        if (isset($updates['name']))        $updates['name']        = Helpers::sanitize($updates['name']);
        if (isset($updates['isActive']))    $updates['isActive']    = Helpers::boolVal($updates['isActive']) ? 1 : 0;
        if (isset($updates['displayOrder'])) $updates['displayOrder'] = (int)$updates['displayOrder'];
        if (isset($updates['partnerType']) && !in_array($updates['partnerType'], $partnerTypes, true)) {
            unset($updates['partnerType']);
        }

        if ($req->hasFile('logo')) {
            $updates['logo'] = self::saveLogo($req->files['logo']);
        }

        $updated = Partner::update($id, $updates);
        Response::success(Partner::toPublic($updated), 'Partner updated.');
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);

        $id      = $req->param('id');
        $partner = Partner::find($id);
        if (!$partner) Response::error('Partner not found.', 404);

        Partner::delete($id);
        Response::success(null, 'Partner deleted.');
    }

    public static function reorder(Request $req): void
    {
        Auth::required($req);

        $items = $req->body['items'] ?? [];
        if (!is_array($items)) Response::error('items must be an array.');

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE partners SET displayOrder = ?, updatedAt = ? WHERE id = ?");
        $now  = gmdate('Y-m-d H:i:s');
        foreach ($items as $item) {
            if (isset($item['id'], $item['order'])) {
                $stmt->execute([(int)$item['order'], $now, $item['id']]);
            }
        }

        Response::success(null, 'Partners reordered.');
    }

    private static function saveLogo(array $file): string
    {
        $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp','image/svg+xml'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            Response::error('Logo must be an image file.');
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            Response::error('Logo must be under 5 MB.');
        }
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = Database::uuid() . '.' . strtolower($ext);
        $dest = UPLOAD_DIR . 'images/';
        if (!is_dir($dest)) mkdir($dest, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dest . $name)) {
            Response::error('Failed to save logo.', 500);
        }
        return $name;
    }
}
