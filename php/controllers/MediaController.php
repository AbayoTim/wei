<?php
declare(strict_types=1);

class MediaController
{
    private static array $IMAGE_MIME = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    ];
    private static array $VIDEO_MIME = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo',
    ];
    private static array $DOC_MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public static function upload(Request $req): void
    {
        Auth::required($req);

        if (!$req->hasFile('file')) {
            Response::error('No file uploaded.');
        }

        $file     = $req->files['file'];
        $mime     = mime_content_type($file['tmp_name']);
        $origName = $file['name'];
        $size     = $file['size'];

        if (in_array($mime, self::$IMAGE_MIME, true)) {
            $type    = 'image';
            $subdir  = 'images';
            $maxSize = MAX_UPLOAD_SIZE;
        } elseif (in_array($mime, self::$VIDEO_MIME, true)) {
            $type    = 'video';
            $subdir  = 'videos';
            $maxSize = MAX_VIDEO_SIZE;
        } elseif (in_array($mime, self::$DOC_MIME, true)) {
            $type    = 'document';
            $subdir  = 'docs';
            $maxSize = MAX_UPLOAD_SIZE;
        } else {
            Response::error('Unsupported file type.');
        }

        if ($size > $maxSize) {
            $mb = $maxSize / 1024 / 1024;
            Response::error("File exceeds the {$mb} MB limit.");
        }

        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $filename = Database::uuid() . ($ext ? ".$ext" : '');
        $dest     = UPLOAD_DIR . $subdir . '/';
        if (!is_dir($dest)) mkdir($dest, 0755, true);

        if (!move_uploaded_file($file['tmp_name'], $dest . $filename)) {
            Response::error('Failed to save file.', 500);
        }

        // Build a public URL relative to the app root
        $url = '/uploads/' . $subdir . '/' . $filename;

        $media = Media::create([
            'filename'     => $filename,
            'originalName' => $origName,
            'mimetype'     => $mime,
            'size'         => $size,
            'type'         => $type,
            'url'          => $url,
            'uploadedBy'   => $req->userId,
        ]);

        Response::success(Media::toPublic($media), 'File uploaded.', 201);
    }

    public static function index(Request $req): void
    {
        Auth::required($req);

        $page  = max(1, (int)($req->query['page']  ?? 1));
        $limit = min(100, max(1, (int)($req->query['limit'] ?? 20)));
        $type  = $req->query['type'] ?? null;

        $where  = []; $params = [];
        if ($type) { $where[] = "type = ?"; $params[] = $type; }

        $whereStr = $where ? implode(' AND ', $where) : '';
        $pg    = Helpers::paginate($page, $limit);
        $total = Media::count($whereStr, $params);
        $rows  = Media::findAll($whereStr, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        Response::paginated(Media::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);

        $id    = $req->param('id');
        $media = Media::find($id);
        if (!$media) Response::error('Media not found.', 404);

        // Determine subfolder from type
        $subdirs = ['image' => 'images', 'video' => 'videos', 'document' => 'docs'];
        $subdir  = $subdirs[$media['type']] ?? 'images';
        $path    = UPLOAD_DIR . $subdir . '/' . $media['filename'];
        if (file_exists($path)) @unlink($path);

        Media::delete($id);
        Response::success(null, 'Media deleted.');
    }
}
