<?php
declare(strict_types=1);

class ContentController
{
    // ── Site content (key-value CMS) ──────────────────────────────────────

    public static function getSiteContent(Request $req): void
    {
        Response::success(SiteContent::getMap());
    }

    public static function getSiteContentByKey(Request $req): void
    {
        $key  = $req->param('key');
        $item = SiteContent::findByKey($key);
        if (!$item) Response::error('Content key not found.', 404);
        Response::success($item);
    }

    public static function updateSiteContent(Request $req): void
    {
        Auth::required($req);

        // Key may come from the URL param (:key) or from the request body
        $key   = $req->param('key') ?: ($req->body['key'] ?? null);
        $value = $req->body['value'] ?? null;
        $type  = $req->body['type']  ?? null;

        if (!$key) Response::error('key is required.');

        $item = SiteContent::findByKey($key);
        if (!$item) {
            // Create new key
            $item = SiteContent::create([
                'key'           => $key,
                'value'         => $value,
                'type'          => in_array($type, ['text','html','json','image']) ? $type : 'text',
                'lastUpdatedBy' => $req->userId,
            ]);
        } else {
            $updates = ['lastUpdatedBy' => $req->userId];
            if ($value !== null) $updates['value'] = $value;
            if ($type  !== null && in_array($type, ['text','html','json','image'])) $updates['type'] = $type;
            $item = SiteContent::update($item['id'], $updates);
        }
        Response::success($item, 'Content updated.');
    }

    public static function bulkUpdateSiteContent(Request $req): void
    {
        Auth::required($req);

        $items = $req->body['items'] ?? [];
        if (!is_array($items)) Response::error('items must be an array.');

        $db      = Database::getInstance();
        $updated = [];
        foreach ($items as $entry) {
            if (empty($entry['key'])) continue;
            $existing = SiteContent::findByKey($entry['key']);
            if ($existing) {
                $u = SiteContent::update($existing['id'], [
                    'value'         => $entry['value'] ?? $existing['value'],
                    'lastUpdatedBy' => $req->userId,
                ]);
            } else {
                $u = SiteContent::create([
                    'key'           => $entry['key'],
                    'value'         => $entry['value'] ?? '',
                    'type'          => 'text',
                    'lastUpdatedBy' => $req->userId,
                ]);
            }
            $updated[] = $u;
        }
        Response::success($updated, 'Content updated.');
    }

    // ── Team members ──────────────────────────────────────────────────────

    public static function getTeam(Request $req): void
    {
        $rows = TeamMember::findAll("isActive = 1", [], 'displayOrder ASC');
        Response::success(TeamMember::publicAll($rows));
    }

    public static function createTeamMember(Request $req): void
    {
        Auth::required($req);

        $name     = Helpers::sanitize(trim($req->body['name']     ?? ''));
        $position = Helpers::sanitize(trim($req->body['position'] ?? ''));
        if (!$name || !$position) Response::error('name and position are required.');

        $data = [
            'name'         => $name,
            'position'     => $position,
            'bio'          => Helpers::sanitize($req->body['bio']     ?? ''),
            'email'        => $req->body['email']    ?? null,
            'phone'        => $req->body['phone']    ?? null,
            'linkedIn'     => $req->body['linkedIn'] ?? null,
            'twitter'      => $req->body['twitter']  ?? null,
            'displayOrder' => (int)($req->body['displayOrder'] ?? 0),
            'isActive'     => 1,
        ];

        // Handle photo upload
        if ($req->hasFile('photo')) {
            $data['photo'] = self::saveImage($req->files['photo'], 'images');
        } elseif (!empty($req->body['photo'])) {
            $data['photo'] = $req->body['photo'];
        }

        $member = TeamMember::create($data);
        Response::success(TeamMember::toPublic($member), 'Team member created.', 201);
    }

    public static function updateTeamMember(Request $req): void
    {
        Auth::required($req);

        $id     = $req->param('id');
        $member = TeamMember::find($id);
        if (!$member) Response::error('Team member not found.', 404);

        $updates = [];
        foreach (['name','position','bio','email','phone','linkedIn','twitter','displayOrder','isActive'] as $f) {
            if (array_key_exists($f, $req->body)) $updates[$f] = $req->body[$f];
        }
        if (isset($updates['name']))     $updates['name']     = Helpers::sanitize($updates['name']);
        if (isset($updates['position'])) $updates['position'] = Helpers::sanitize($updates['position']);
        if (isset($updates['bio']))      $updates['bio']      = Helpers::sanitize($updates['bio']);
        if (isset($updates['isActive'])) $updates['isActive'] = Helpers::boolVal($updates['isActive']) ? 1 : 0;

        if ($req->hasFile('photo')) {
            $updates['photo'] = self::saveImage($req->files['photo'], 'images');
        }

        $updated = TeamMember::update($id, $updates);
        Response::success(TeamMember::toPublic($updated), 'Team member updated.');
    }

    public static function deleteTeamMember(Request $req): void
    {
        Auth::required($req);

        $id     = $req->param('id');
        $member = TeamMember::find($id);
        if (!$member) Response::error('Team member not found.', 404);

        TeamMember::delete($id);
        Response::success(null, 'Team member deleted.');
    }

    // ── Events ────────────────────────────────────────────────────────────

    public static function getEvents(Request $req): void
    {
        $page   = max(1, (int)($req->query['page']   ?? 1));
        $limit  = min(50, max(1, (int)($req->query['limit'] ?? 10)));
        $status = $req->query['status'] ?? null;

        $where  = ["isPublished = 1"]; $params = [];
        if ($status) { $where[] = "status = ?"; $params[] = $status; }

        $pg    = Helpers::paginate($page, $limit);
        $total = Event::count(implode(' AND ', $where), $params);
        $rows  = Event::findAll(implode(' AND ', $where), $params, 'eventDate ASC', $pg['limit'], $pg['offset']);

        Response::paginated(Event::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function getAdminEvents(Request $req): void
    {
        Auth::required($req);
        $page  = max(1, (int)($req->query['page']  ?? 1));
        $limit = min(50, max(1, (int)($req->query['limit'] ?? 20)));
        $pg    = Helpers::paginate($page, $limit);
        $total = Event::count();
        $rows  = Event::findAll('', [], 'createdAt DESC', $pg['limit'], $pg['offset']);
        Response::paginated(Event::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function getEvent(Request $req): void
    {
        $slug  = $req->param('slug');
        $event = Event::findBySlug($slug);
        if (!$event || !$event['isPublished']) Response::error('Event not found.', 404);
        Response::success(Event::toPublic($event));
    }

    public static function getEventById(Request $req): void
    {
        Auth::required($req);
        $id    = $req->param('id');
        $event = Event::find($id);
        if (!$event) Response::error('Event not found.', 404);
        Response::success(Event::toPublic($event));
    }

    public static function createEvent(Request $req): void
    {
        Auth::required($req);

        if (empty($req->body['title'])) Response::error('title is required.');

        $data = array_filter(self::eventData($req), fn($v) => $v !== null);
        if ($req->hasFile('featuredImage')) {
            $data['featuredImage'] = self::saveImage($req->files['featuredImage'], 'images');
        }

        $event = Event::create($data);
        Response::success(Event::toPublic($event), 'Event created.', 201);
    }

    public static function updateEvent(Request $req): void
    {
        Auth::required($req);

        $id    = $req->param('id');
        $event = Event::find($id);
        if (!$event) Response::error('Event not found.', 404);

        $data = array_filter(self::eventData($req), fn($v) => $v !== null);
        if ($req->hasFile('featuredImage')) {
            $data['featuredImage'] = self::saveImage($req->files['featuredImage'], 'images');
        }

        $updated = Event::update($id, $data);
        Response::success(Event::toPublic($updated), 'Event updated.');
    }

    public static function deleteEvent(Request $req): void
    {
        Auth::required($req);
        $id = $req->param('id');
        if (!Event::find($id)) Response::error('Event not found.', 404);
        Event::delete($id);
        Response::success(null, 'Event deleted.');
    }

    // ── Causes ────────────────────────────────────────────────────────────

    public static function getCauses(Request $req): void
    {
        $page     = max(1, (int)($req->query['page']     ?? 1));
        $limit    = min(50, max(1, (int)($req->query['limit'] ?? 10)));
        $category = $req->query['category'] ?? null;
        $featured = $req->query['featured'] ?? null;

        $where  = ["isPublished = 1"]; $params = [];
        if ($category) { $where[] = "category = ?"; $params[] = $category; }
        if ($featured !== null) { $where[] = "isFeatured = ?"; $params[] = ($featured === 'true' || $featured === '1') ? 1 : 0; }

        $pg    = Helpers::paginate($page, $limit);
        $total = Cause::count(implode(' AND ', $where), $params);
        $rows  = Cause::findAll(implode(' AND ', $where), $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        Response::paginated(Cause::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function getAdminCauses(Request $req): void
    {
        Auth::required($req);
        $page  = max(1, (int)($req->query['page']  ?? 1));
        $limit = min(50, max(1, (int)($req->query['limit'] ?? 20)));
        $pg    = Helpers::paginate($page, $limit);
        $total = Cause::count();
        $rows  = Cause::findAll('', [], 'createdAt DESC', $pg['limit'], $pg['offset']);
        Response::paginated(Cause::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function getCause(Request $req): void
    {
        $slug  = $req->param('slug');
        $cause = Cause::findBySlug($slug);
        if (!$cause || !$cause['isPublished']) Response::error('Cause not found.', 404);
        Response::success(Cause::toPublic($cause));
    }

    public static function getCauseById(Request $req): void
    {
        Auth::required($req);
        $id    = $req->param('id');
        $cause = Cause::find($id);
        if (!$cause) Response::error('Cause not found.', 404);
        Response::success(Cause::toPublic($cause));
    }

    public static function createCause(Request $req): void
    {
        Auth::required($req);
        if (empty($req->body['title'])) Response::error('title is required.');

        $data = array_filter(self::causeData($req), fn($v) => $v !== null);
        if ($req->hasFile('featuredImage')) {
            $data['featuredImage'] = self::saveImage($req->files['featuredImage'], 'images');
        }

        $cause = Cause::create($data);
        Response::success(Cause::toPublic($cause), 'Cause created.', 201);
    }

    public static function updateCause(Request $req): void
    {
        Auth::required($req);
        $id    = $req->param('id');
        $cause = Cause::find($id);
        if (!$cause) Response::error('Cause not found.', 404);

        $data = array_filter(self::causeData($req), fn($v) => $v !== null);
        if ($req->hasFile('featuredImage')) {
            $data['featuredImage'] = self::saveImage($req->files['featuredImage'], 'images');
        }

        $updated = Cause::update($id, $data);
        Response::success(Cause::toPublic($updated), 'Cause updated.');
    }

    public static function deleteCause(Request $req): void
    {
        Auth::required($req);
        $id = $req->param('id');
        if (!Cause::find($id)) Response::error('Cause not found.', 404);
        Cause::delete($id);
        Response::success(null, 'Cause deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function eventData(Request $req): array
    {
        return [
            'title'         => isset($req->body['title']) ? Helpers::sanitize($req->body['title']) : null,
            'description'   => $req->body['description'] ?? null,
            'content'       => $req->body['content']     ?? null,
            'eventDate'     => $req->body['eventDate']   ?? null,
            'endDate'       => $req->body['endDate']     ?? null,
            'startTime'     => $req->body['startTime']   ?? null,
            'endTime'       => $req->body['endTime']     ?? null,
            'location'      => $req->body['location']    ?? null,
            'venue'         => $req->body['venue']       ?? null,
            'status'        => in_array($req->body['status'] ?? '', ['upcoming','ongoing','completed','cancelled'])
                               ? $req->body['status'] : null,
            'isPublished'   => isset($req->body['isPublished']) ? (Helpers::boolVal($req->body['isPublished']) ? 1 : 0) : null,
            'gallery'       => isset($req->body['gallery'])
                               ? (is_array($req->body['gallery']) ? json_encode($req->body['gallery']) : $req->body['gallery'])
                               : null,
        ];
    }

    private static function causeData(Request $req): array
    {
        $categories = ['education','health','livelihood','advocacy','other'];
        return [
            'title'       => isset($req->body['title']) ? Helpers::sanitize($req->body['title']) : null,
            'description' => $req->body['description'] ?? null,
            'content'     => $req->body['content']     ?? null,
            'category'    => in_array($req->body['category'] ?? '', $categories) ? $req->body['category'] : null,
            'goalAmount'  => isset($req->body['goalAmount'])  ? (float)$req->body['goalAmount']  : null,
            'raisedAmount'=> isset($req->body['raisedAmount'])? (float)$req->body['raisedAmount'] : null,
            'currency'    => $req->body['currency']    ?? null,
            'startDate'   => $req->body['startDate']   ?? null,
            'endDate'     => $req->body['endDate']     ?? null,
            'status'      => in_array($req->body['status'] ?? '', ['active','completed','paused']) ? $req->body['status'] : null,
            'isFeatured'    => isset($req->body['isFeatured'])  ? (Helpers::boolVal($req->body['isFeatured'])  ? 1 : 0) : null,
            'isPublished'   => isset($req->body['isPublished']) ? (Helpers::boolVal($req->body['isPublished']) ? 1 : 0) : null,
            'featuredImage' => array_key_exists('featuredImage', $req->body) ? ($req->body['featuredImage'] ?: null) : null,
            'gallery'       => isset($req->body['gallery'])
                               ? (is_array($req->body['gallery']) ? json_encode($req->body['gallery']) : $req->body['gallery'])
                               : null,
        ];
    }

    private static function saveImage(array $file, string $subdir): string
    {
        $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            Response::error('Image must be JPEG, PNG, GIF, or WebP.');
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            Response::error('Image must be under 5 MB.');
        }
        $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = Database::uuid() . '.' . strtolower($ext);
        $dest = UPLOAD_DIR . $subdir . '/';
        if (!is_dir($dest)) mkdir($dest, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $dest . $name)) {
            Response::error('Failed to save image.', 500);
        }
        return $name;
    }
}
