<?php
declare(strict_types=1);

class BlogController
{
    public static function index(Request $req): void
    {
        $page     = max(1, (int)($req->query['page']  ?? 1));
        $limit    = min(50, max(1, (int)($req->query['limit'] ?? 10)));
        $status   = $req->query['status']   ?? null;
        $category = $req->query['category'] ?? null;
        $search   = $req->query['search']   ?? null;

        $where  = [];
        $params = [];

        // Public endpoint shows only published; admin can see all
        if ($status) {
            $where[]  = "status = ?"; $params[] = $status;
        } else {
            $where[]  = "status = 'published'";
        }
        if ($category) { $where[] = "category = ?"; $params[] = $category; }
        if ($search) {
            $where[]  = "(title LIKE ? OR excerpt LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        $whereStr = $where ? implode(' AND ', $where) : '';
        $pg   = Helpers::paginate($page, $limit);
        $total = Blog::count($whereStr, $params);
        $rows  = Blog::findAll($whereStr, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        // Attach author name
        $db = Database::getInstance();
        $rows = array_map(function ($row) use ($db) {
            $row = Blog::toPublic($row);
            if ($row['authorId']) {
                $u = $db->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
                $u->execute([$row['authorId']]);
                $row['author'] = $u->fetch() ?: null;
            } else {
                $row['author'] = null;
            }
            return $row;
        }, $rows);

        Response::paginated($rows, $total, $pg['page'], $pg['limit']);
    }

    public static function adminIndex(Request $req): void
    {
        Auth::required($req);

        $page   = max(1, (int)($req->query['page']  ?? 1));
        $limit  = min(50, max(1, (int)($req->query['limit'] ?? 20)));
        $status = $req->query['status'] ?? null;

        $where  = []; $params = [];
        if ($status) { $where[] = "status = ?"; $params[] = $status; }

        $whereStr = $where ? implode(' AND ', $where) : '';
        $pg    = Helpers::paginate($page, $limit);
        $total = Blog::count($whereStr, $params);
        $rows  = Blog::findAll($whereStr, $params, 'createdAt DESC', $pg['limit'], $pg['offset']);

        Response::paginated(Blog::publicAll($rows), $total, $pg['page'], $pg['limit']);
    }

    public static function categories(Request $req): void
    {
        Response::success(Blog::getCategories());
    }

    public static function show(Request $req): void
    {
        $slug = $req->param('slug');
        $blog = Blog::findBySlug($slug);
        if (!$blog || $blog['status'] !== 'published') {
            Response::error('Blog post not found.', 404);
        }
        Blog::incrementViews($blog['id']);
        Response::success(Blog::toPublic($blog));
    }

    public static function store(Request $req): void
    {
        Auth::required($req);
        self::validateBlogInput($req);

        $data = [
            'title'         => Helpers::sanitize($req->body['title']),
            'content'       => $req->body['content'] ?? '',
            'excerpt'       => Helpers::sanitize($req->body['excerpt']  ?? ''),
            'category'      => Helpers::sanitize($req->body['category'] ?? ''),
            'status'        => in_array($req->body['status'] ?? '', ['draft','published']) ? $req->body['status'] : 'draft',
            'featuredImage' => $req->body['featuredImage'] ?? null,
            'tags'          => is_array($req->body['tags'] ?? null) ? json_encode($req->body['tags']) : ($req->body['tags'] ?? '[]'),
            'gallery'       => is_array($req->body['gallery'] ?? null) ? json_encode($req->body['gallery']) : ($req->body['gallery'] ?? '[]'),
            'authorId'      => $req->userId,
        ];
        if (!empty($req->body['slug'])) {
            $data['slug'] = Helpers::generateSlug($req->body['slug']);
        }

        $blog = Blog::create($data);
        Response::success(Blog::toPublic($blog), 'Blog post created.', 201);
    }

    public static function update(Request $req): void
    {
        Auth::required($req);

        $id   = $req->param('id');
        $blog = Blog::find($id);
        if (!$blog) Response::error('Blog post not found.', 404);

        $data = [];
        $fields = ['title','content','excerpt','category','status','featuredImage'];
        foreach ($fields as $f) {
            if (array_key_exists($f, $req->body)) $data[$f] = $req->body[$f];
        }
        if (isset($data['title']))    $data['title']   = Helpers::sanitize($data['title']);
        if (isset($data['excerpt']))  $data['excerpt']  = Helpers::sanitize($data['excerpt']);
        if (isset($data['category'])) $data['category'] = Helpers::sanitize($data['category']);
        if (array_key_exists('tags', $req->body)) {
            $data['tags'] = is_array($req->body['tags']) ? json_encode($req->body['tags']) : $req->body['tags'];
        }
        if (array_key_exists('gallery', $req->body)) {
            $data['gallery'] = is_array($req->body['gallery']) ? json_encode($req->body['gallery']) : $req->body['gallery'];
        }
        if (!empty($req->body['slug'])) {
            $data['slug'] = Helpers::generateSlug($req->body['slug']);
        }

        $updated = Blog::update($id, $data);
        Response::success(Blog::toPublic($updated), 'Blog post updated.');
    }

    public static function destroy(Request $req): void
    {
        Auth::required($req);

        $id   = $req->param('id');
        $blog = Blog::find($id);
        if (!$blog) Response::error('Blog post not found.', 404);

        Blog::delete($id);
        Response::success(null, 'Blog post deleted.');
    }

    private static function validateBlogInput(Request $req): void
    {
        if (empty($req->body['title'])) {
            Response::error('Title is required.');
        }
        if (empty($req->body['content'])) {
            Response::error('Content is required.');
        }
    }
}
