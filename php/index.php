<?php
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/src/JWT.php';
require_once __DIR__ . '/src/Response.php';
require_once __DIR__ . '/src/Request.php';
require_once __DIR__ . '/src/Helpers.php';
require_once __DIR__ . '/src/Email.php';
require_once __DIR__ . '/src/RateLimit.php';
require_once __DIR__ . '/src/Router.php';

require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/Honeypot.php';

require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Blog.php';
require_once __DIR__ . '/models/Subscriber.php';
require_once __DIR__ . '/models/Contact.php';
require_once __DIR__ . '/models/Donation.php';
require_once __DIR__ . '/models/Partner.php';
require_once __DIR__ . '/models/SiteContent.php';
require_once __DIR__ . '/models/Event.php';
require_once __DIR__ . '/models/Cause.php';
require_once __DIR__ . '/models/TeamMember.php';
require_once __DIR__ . '/models/Media.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/BlogController.php';
require_once __DIR__ . '/controllers/SubscriberController.php';
require_once __DIR__ . '/controllers/ContactController.php';
require_once __DIR__ . '/controllers/DonationController.php';
require_once __DIR__ . '/controllers/ContentController.php';
require_once __DIR__ . '/controllers/PartnerController.php';
require_once __DIR__ . '/controllers/MediaController.php';

// ── Security headers (mirrors Helmet.js in server.js) ────────────────────────
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net https://esm.sh; "
    . "script-src-attr 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; "
    . "img-src 'self' data: blob: https: http:; "
    . "font-src 'self' cdn.jsdelivr.net; "
    . "connect-src 'self' https://esm.sh cdn.jsdelivr.net; "
    . "object-src 'none'; "
    . "frame-src 'self' https://www.youtube.com https://player.vimeo.com https://www.openstreetmap.org; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'"
);

// ── CORS ──────────────────────────────────────────────────────────────────────
$allowedOrigins = array_filter(array_map(
    'trim',
    explode(',', env('FRONTEND_URL', ''))
));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (empty($allowedOrigins) || in_array($origin, $allowedOrigins, true)) {
    if ($origin) {
        header("Access-Control-Allow-Origin: $origin");
        header("Vary: Origin");
    }
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$req = new Request();

// ── MIME type map for static files ────────────────────────────────────────────
function mimeFor(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return [
        'html'  => 'text/html; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'map'   => 'application/json',
    ][$ext] ?? (mime_content_type($path) ?: 'application/octet-stream');
}

function serveStatic(string $file): never
{
    $mime = mimeFor($file);
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($file));
    header("Cache-Control: public, max-age=3600");
    if ($mime === 'application/pdf') {
        header("Content-Security-Policy: frame-ancestors 'self'");
    }
    readfile($file);
    exit;
}

// ── Uploaded files (php/uploads/…) ───────────────────────────────────────────
if (str_starts_with($req->path, '/uploads/')) {
    $file = __DIR__ . '/' . ltrim($req->path, '/');
    if (file_exists($file) && is_file($file)) {
        header("Cross-Origin-Resource-Policy: cross-origin");
        serveStatic($file);
    }
    http_response_code(404); exit;
}

// ── Frontend static files (served from parent wei/ directory) ─────────────────
// API requests go straight to the router; everything else tries the filesystem.
if (!str_starts_with($req->path, '/api/')) {
    $root      = dirname(__DIR__);               // /…/wei
    $rootReal  = realpath($root);
    $rel       = ltrim($req->path, '/');

    // 301 redirect: /page.html → /page  (keeps SEO clean)
    if (str_ends_with($rel, '.html') && $rel !== 'index.html') {
        $clean = '/' . substr($rel, 0, -5);
        header("Location: $clean", true, 301);
        exit;
    }
    if ($rel === 'index.html') {
        header("Location: /", true, 301);
        exit;
    }

    $candidate = $root . '/' . ($rel ?: 'index.html');

    // Security: prevent path traversal out of $root
    $real = realpath($candidate);
    if ($real && str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)) {
        if (is_file($real)) {
            serveStatic($real);
        }
        // Directory request — try index.html inside it (e.g. /admin → admin/index.html)
        if (is_dir($real)) {
            $dirIndex = $real . DIRECTORY_SEPARATOR . 'index.html';
            if (is_file($dirIndex)) {
                serveStatic($dirIndex);
            }
        }
    }

    // Clean URL: /about → about.html, /contact → contact.html, etc.
    if ($rel && !str_contains($rel, '.')) {
        $htmlCandidate = $root . '/' . $rel . '.html';
        $htmlReal = realpath($htmlCandidate);
        if ($htmlReal && str_starts_with($htmlReal, $rootReal . DIRECTORY_SEPARATOR) && is_file($htmlReal)) {
            serveStatic($htmlReal);
        }
        // Blog post clean URL: /blog/:slug → serve single.html
        if (str_starts_with($rel, 'blog/')) {
            $singleFile = $root . '/single.html';
            if (is_file($singleFile)) {
                serveStatic($singleFile);
            }
        }
    }

    // SPA / HTML fallback — serve index.html for unknown paths
    $indexFile = $root . '/index.html';
    if (file_exists($indexFile)) {
        serveStatic($indexFile);
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h2>404 Not Found</h2></body></html>';
    exit;
}

// ── General API rate limit ────────────────────────────────────────────────────
RateLimit::api($req->ip());

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();

// Health
$router->get('/api/health', function (Request $req) {
    Response::json([
        'success'   => true,
        'message'   => 'WEI PHP API is running',
        'timestamp' => gmdate('c'),
    ]);
});

// ── Auth ──────────────────────────────────────────────────────────────────────
$router->post('/api/auth/login',          [AuthController::class, 'login']);
$router->get ('/api/auth/me',             [AuthController::class, 'getMe']);
$router->put ('/api/auth/password',       [AuthController::class, 'updatePassword']);
$router->post('/api/auth/users',          [AuthController::class, 'createUser']);
$router->get ('/api/auth/users',          [AuthController::class, 'getUsers']);
$router->put ('/api/auth/users/:id',      [AuthController::class, 'updateUser']);
$router->delete('/api/auth/users/:id',    [AuthController::class, 'deleteUser']);

// ── Blogs ─────────────────────────────────────────────────────────────────────
$router->get ('/api/blogs',               [BlogController::class, 'index']);
$router->get ('/api/blogs/admin',         [BlogController::class, 'adminIndex']);
$router->get ('/api/blogs/categories',    [BlogController::class, 'categories']);
$router->get ('/api/blogs/:slug',         [BlogController::class, 'show']);
$router->post('/api/blogs',               [BlogController::class, 'store']);
$router->put ('/api/blogs/:id',           [BlogController::class, 'update']);
$router->delete('/api/blogs/:id',         [BlogController::class, 'destroy']);

// ── Subscribers ───────────────────────────────────────────────────────────────
$router->post('/api/subscribers/subscribe',              [SubscriberController::class, 'subscribe']);
$router->get ('/api/subscribers/confirm/:token',         [SubscriberController::class, 'confirm']);
$router->get ('/api/subscribers/unsubscribe/:token',     [SubscriberController::class, 'unsubscribe']);
$router->get ('/api/subscribers',                        [SubscriberController::class, 'index']);
$router->get ('/api/subscribers/stats',                  [SubscriberController::class, 'stats']);
$router->get ('/api/subscribers/export',                 [SubscriberController::class, 'export']);
$router->delete('/api/subscribers/:id',                  [SubscriberController::class, 'destroy']);

// ── Contact ───────────────────────────────────────────────────────────────────
$router->post('/api/contact',             [ContactController::class, 'store']);
$router->get ('/api/contact',             [ContactController::class, 'index']);
$router->get ('/api/contact/stats',       [ContactController::class, 'stats']);
$router->get ('/api/contact/:id',         [ContactController::class, 'show']);
$router->put ('/api/contact/:id',         [ContactController::class, 'reply']);
$router->delete('/api/contact/:id',       [ContactController::class, 'destroy']);

// ── Donations ─────────────────────────────────────────────────────────────────
// NOTE: specific paths must be registered before wildcard /:id
$router->post('/api/donations',                      [DonationController::class, 'store']);
$router->get ('/api/donations/approved',             [DonationController::class, 'approved']);
$router->get ('/api/donations/stats',                [DonationController::class, 'stats']);
$router->get ('/api/donations/receipt/:filename',    [DonationController::class, 'receipt']);
$router->get ('/api/donations',                      [DonationController::class, 'index']);
$router->get ('/api/donations/:id',                  [DonationController::class, 'show']);
$router->post('/api/donations/:id/approve',          [DonationController::class, 'approve']);
$router->post('/api/donations/:id/reject',           [DonationController::class, 'reject']);

// ── Partners ──────────────────────────────────────────────────────────────────
$router->get   ('/api/partners',            [PartnerController::class, 'index']);
$router->get   ('/api/partners/:id',        [PartnerController::class, 'show']);
$router->post  ('/api/partners',            [PartnerController::class, 'store']);
$router->put   ('/api/partners/:id',        [PartnerController::class, 'update']);
$router->delete('/api/partners/:id',        [PartnerController::class, 'destroy']);
$router->post  ('/api/partners/reorder',    [PartnerController::class, 'reorder']);

// ── Content ───────────────────────────────────────────────────────────────────
$router->get   ('/api/content/site',              [ContentController::class, 'getSiteContent']);
$router->put   ('/api/content/site',              [ContentController::class, 'updateSiteContent']);   // key in body
$router->put   ('/api/content/site/bulk',         [ContentController::class, 'bulkUpdateSiteContent']);
$router->get   ('/api/content/site/:key',         [ContentController::class, 'getSiteContentByKey']);
$router->put   ('/api/content/site/:key',         [ContentController::class, 'updateSiteContent']);   // key in URL
$router->get   ('/api/content/team',              [ContentController::class, 'getTeam']);
$router->post  ('/api/content/team',              [ContentController::class, 'createTeamMember']);
$router->put   ('/api/content/team/:id',          [ContentController::class, 'updateTeamMember']);
$router->delete('/api/content/team/:id',          [ContentController::class, 'deleteTeamMember']);
$router->get   ('/api/content/events',            [ContentController::class, 'getEvents']);
$router->get   ('/api/content/events/admin',      [ContentController::class, 'getAdminEvents']);
$router->get   ('/api/content/events/id/:id',     [ContentController::class, 'getEventById']);
$router->get   ('/api/content/events/:slug',      [ContentController::class, 'getEvent']);
$router->post  ('/api/content/events',            [ContentController::class, 'createEvent']);
$router->put   ('/api/content/events/:id',        [ContentController::class, 'updateEvent']);
$router->delete('/api/content/events/:id',        [ContentController::class, 'deleteEvent']);
$router->get   ('/api/content/causes',            [ContentController::class, 'getCauses']);
$router->get   ('/api/content/causes/admin',      [ContentController::class, 'getAdminCauses']);
$router->get   ('/api/content/causes/id/:id',     [ContentController::class, 'getCauseById']);
$router->get   ('/api/content/causes/:slug',      [ContentController::class, 'getCause']);
$router->post  ('/api/content/causes',            [ContentController::class, 'createCause']);
$router->put   ('/api/content/causes/:id',        [ContentController::class, 'updateCause']);
$router->delete('/api/content/causes/:id',        [ContentController::class, 'deleteCause']);

// ── Media ─────────────────────────────────────────────────────────────────────
$router->post  ('/api/media',     [MediaController::class, 'upload']);
$router->get   ('/api/media',     [MediaController::class, 'index']);
$router->delete('/api/media/:id', [MediaController::class, 'destroy']);

// ── Dispatch ──────────────────────────────────────────────────────────────────
$router->dispatch($req);
