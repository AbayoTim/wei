<?php
declare(strict_types=1);

class GalleryController
{
    public static function index(Request $req): void
    {
        $group = $req->query['group'] ?? null;
        $where  = ['isPublished = 1'];
        $params = [];
        if ($group !== null) { $where[] = 'groupName = ?'; $params[] = $group; }
        $rows = GalleryItem::findAll(implode(' AND ', $where), $params, 'groupName ASC, sortOrder ASC, createdAt DESC');
        Response::success(GalleryItem::publicAll($rows));
    }

    public static function adminIndex(Request $req): void
    {
        Auth::required($req);
        $rows = GalleryItem::findAll('', [], 'groupName ASC, sortOrder ASC, createdAt DESC');
        Response::success(GalleryItem::publicAll($rows));
    }

    public static function groups(Request $req): void
    {
        $rows   = GalleryItem::findAll('isPublished = 1', [], 'groupName ASC');
        $groups = array_values(array_unique(array_filter(array_column($rows, 'groupName'))));
        Response::success($groups);
    }

    public static function show(Request $req): void
    {
        Auth::required($req);
        $id   = $req->param('id');
        $item = GalleryItem::find($id);
        if (!$item) Response::error('Gallery item not found.', 404);
        Response::success(GalleryItem::toPublic($item));
    }

    public static function store(Request $req): void
    {
        Auth::required($req);
        $imageUrl = trim($req->body['imageUrl'] ?? '');
        if (!$imageUrl) Response::error('imageUrl is required.');

        $item = GalleryItem::create([
            'imageUrl'    => $imageUrl,
            'description' => Helpers::sanitize($req->body['description'] ?? ''),
            'groupName'   => Helpers::sanitize($req->body['groupName']   ?? ''),
            'sortOrder'   => (int)($req->body['sortOrder'] ?? 0),
            'isPublished' => isset($req->body['isPublished']) ? ((int)(bool)$req->body['isPublished']) : 1,
        ]);
        Response::success(GalleryItem::toPublic($item), 'Photo added.', 201);
    }

    public static function update(Request $req): void
    {
        Auth::required($req);
        $id   = $req->param('id');
        $item = GalleryItem::find($id);
        if (!$item) Response::error('Gallery item not found.', 404);

        $data = [];
        if (array_key_exists('imageUrl',    $req->body)) $data['imageUrl']    = trim($req->body['imageUrl']);
        if (array_key_exists('description', $req->body)) $data['description'] = Helpers::sanitize($req->body['description']);
        if (array_key_exists('groupName',   $req->body)) $data['groupName']   = Helpers::sanitize($req->body['groupName']);
        if (array_key_exists('sortOrder',   $req->body)) $data['sortOrder']   = (int)$req->body['sortOrder'];
        if (array_key_exists('isPublished', $req->body)) $data['isPublished'] = (int)(bool)$req->body['isPublished'];

        $updated = GalleryItem::update($id, $data);
        Response::success(GalleryItem::toPublic($updated), 'Photo updated.');
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);
        $id = $req->param('id');
        if (!GalleryItem::find($id)) Response::error('Gallery item not found.', 404);
        GalleryItem::delete($id);
        Response::success(null, 'Photo deleted.');
    }
}
