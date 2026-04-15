<?php
declare(strict_types=1);

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = '', int $status = 200): never
    {
        $body = ['success' => true];
        if ($message !== '') $body['message'] = $message;
        if ($data !== null)   $body['data']    = $data;
        self::json($body, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors) $body['errors'] = $errors;
        self::json($body, $status);
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function file(string $filePath, string $disposition = 'inline'): never
    {
        if (!file_exists($filePath)) {
            self::error('File not found', 404);
        }
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filePath) . '"');
        header('Cache-Control: private, max-age=86400');
        if ($mime === 'application/pdf') {
            header("Content-Security-Policy: frame-ancestors 'self'");
        }
        readfile($filePath);
        exit;
    }

    public static function csv(string $csv, string $filename): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }

    public static function paginated(array $rows, int $total, int $page, int $limit): never
    {
        $totalPages = (int) ceil($total / $limit);
        self::json([
            'success'     => true,
            'data'        => $rows,
            'pagination'  => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'totalPages'  => $totalPages,
                'hasMore'     => $page < $totalPages,
            ],
        ]);
    }
}
