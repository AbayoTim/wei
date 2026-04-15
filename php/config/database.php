<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = DB_DRIVER === 'mysql'
                ? self::connectMysql()
                : self::connectSqlite();
        }
        return self::$instance;
    }

    // ── Connections ───────────────────────────────────────────────────────────

    private static function connectSqlite(): PDO
    {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        self::migrateSqlite($pdo);
        self::seed($pdo);
        return $pdo;
    }

    private static function connectMysql(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        self::migrateMysql($pdo);
        self::seed($pdo);
        return $pdo;
    }

    // ── SQLite migration ──────────────────────────────────────────────────────

    private static function migrateSqlite(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id          TEXT PRIMARY KEY,
                email       TEXT UNIQUE NOT NULL,
                password    TEXT NOT NULL,
                name        TEXT NOT NULL,
                role        TEXT NOT NULL DEFAULT 'editor' CHECK(role IN ('admin','editor')),
                isActive    INTEGER NOT NULL DEFAULT 1,
                lastLogin   TEXT,
                createdAt   TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt   TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS blogs (
                id            TEXT PRIMARY KEY,
                title         TEXT NOT NULL,
                slug          TEXT UNIQUE NOT NULL,
                content       TEXT NOT NULL,
                excerpt       TEXT,
                featuredImage TEXT,
                category      TEXT,
                tags          TEXT NOT NULL DEFAULT '[]',
                status        TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published')),
                publishedAt   TEXT,
                views         INTEGER NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                authorId      TEXT REFERENCES users(id) ON DELETE SET NULL,
                createdAt     TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt     TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS subscribers (
                id                TEXT PRIMARY KEY,
                email             TEXT UNIQUE NOT NULL,
                name              TEXT,
                status            TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','confirmed','unsubscribed')),
                confirmationToken TEXT UNIQUE,
                confirmedAt       TEXT,
                unsubscribedAt    TEXT,
                source            TEXT,
                createdAt         TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt         TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS contacts (
                id           TEXT PRIMARY KEY,
                firstName    TEXT NOT NULL,
                lastName     TEXT NOT NULL,
                email        TEXT NOT NULL,
                phone        TEXT,
                subject      TEXT NOT NULL,
                message      TEXT NOT NULL,
                status       TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','read','replied','archived')),
                repliedAt    TEXT,
                replyMessage TEXT,
                ipAddress    TEXT,
                createdAt    TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS donations (
                id                   TEXT PRIMARY KEY,
                referenceNumber      TEXT UNIQUE NOT NULL,
                donorName            TEXT NOT NULL,
                donorEmail           TEXT NOT NULL,
                donorPhone           TEXT,
                amount               REAL NOT NULL,
                currency             TEXT NOT NULL DEFAULT 'TZS',
                paymentMethod        TEXT,
                transactionReference TEXT,
                receiptFile          TEXT,
                message              TEXT,
                cause                TEXT,
                status               TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
                approvedAt           TEXT,
                approvedBy           TEXT REFERENCES users(id) ON DELETE SET NULL,
                rejectionReason      TEXT,
                isAnonymous          INTEGER NOT NULL DEFAULT 0,
                createdAt            TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt            TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS partners (
                id           TEXT PRIMARY KEY,
                name         TEXT NOT NULL,
                logo         TEXT,
                website      TEXT,
                description  TEXT,
                partnerType  TEXT NOT NULL DEFAULT 'other' CHECK(partnerType IN ('funding','implementing','government','other')),
                isActive     INTEGER NOT NULL DEFAULT 1,
                displayOrder INTEGER NOT NULL DEFAULT 0,
                createdAt    TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS site_contents (
                id            TEXT PRIMARY KEY,
                key           TEXT UNIQUE NOT NULL,
                value         TEXT,
                type          TEXT NOT NULL DEFAULT 'text' CHECK(type IN ('text','html','json','image')),
                description   TEXT,
                lastUpdatedBy TEXT REFERENCES users(id) ON DELETE SET NULL,
                createdAt     TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt     TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS events (
                id            TEXT PRIMARY KEY,
                title         TEXT NOT NULL,
                slug          TEXT UNIQUE NOT NULL,
                description   TEXT,
                content       TEXT,
                featuredImage TEXT,
                eventDate     TEXT,
                endDate       TEXT,
                startTime     TEXT,
                endTime       TEXT,
                location      TEXT,
                venue         TEXT,
                status        TEXT NOT NULL DEFAULT 'upcoming' CHECK(status IN ('upcoming','ongoing','completed','cancelled')),
                isPublished   INTEGER NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                createdAt     TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt     TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS causes (
                id            TEXT PRIMARY KEY,
                title         TEXT NOT NULL,
                slug          TEXT UNIQUE NOT NULL,
                description   TEXT,
                content       TEXT,
                featuredImage TEXT,
                category      TEXT NOT NULL DEFAULT 'other' CHECK(category IN ('education','health','livelihood','advocacy','other')),
                goalAmount    REAL,
                raisedAmount  REAL NOT NULL DEFAULT 0,
                currency      TEXT NOT NULL DEFAULT 'TZS',
                startDate     TEXT,
                endDate       TEXT,
                status        TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','completed','paused')),
                isFeatured    INTEGER NOT NULL DEFAULT 0,
                isPublished   INTEGER NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                createdAt     TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt     TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS team_members (
                id           TEXT PRIMARY KEY,
                name         TEXT NOT NULL,
                position     TEXT NOT NULL,
                bio          TEXT,
                photo        TEXT,
                email        TEXT,
                phone        TEXT,
                linkedIn     TEXT,
                twitter      TEXT,
                displayOrder INTEGER NOT NULL DEFAULT 0,
                isActive     INTEGER NOT NULL DEFAULT 1,
                createdAt    TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS media (
                id           TEXT PRIMARY KEY,
                filename     TEXT NOT NULL,
                originalName TEXT NOT NULL,
                mimetype     TEXT NOT NULL,
                size         INTEGER NOT NULL,
                type         TEXT NOT NULL DEFAULT 'image' CHECK(type IN ('image','video','document')),
                url          TEXT NOT NULL,
                uploadedBy   TEXT REFERENCES users(id) ON DELETE SET NULL,
                createdAt    TEXT NOT NULL DEFAULT (datetime('now')),
                updatedAt    TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS rate_limits (
                id      TEXT PRIMARY KEY,
                rkey    TEXT NOT NULL,
                count   INTEGER NOT NULL DEFAULT 0,
                resetAt INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_rl_key ON rate_limits(rkey);
        ");
    }

    // ── MySQL migration ───────────────────────────────────────────────────────

    private static function migrateMysql(PDO $db): void
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id          VARCHAR(36)  PRIMARY KEY,
                email       VARCHAR(255) UNIQUE NOT NULL,
                password    VARCHAR(255) NOT NULL,
                name        VARCHAR(255) NOT NULL,
                role        ENUM('admin','editor') NOT NULL DEFAULT 'editor',
                isActive    TINYINT(1) NOT NULL DEFAULT 1,
                lastLogin   DATETIME,
                createdAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS blogs (
                id            VARCHAR(36)  PRIMARY KEY,
                title         VARCHAR(500) NOT NULL,
                slug          VARCHAR(500) UNIQUE NOT NULL,
                content       LONGTEXT NOT NULL,
                excerpt       TEXT,
                featuredImage VARCHAR(500),
                category      VARCHAR(255),
                tags          TEXT NOT NULL DEFAULT '[]',
                status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
                publishedAt   DATETIME,
                views         INT NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                authorId      VARCHAR(36),
                createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (authorId) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS subscribers (
                id                VARCHAR(36)  PRIMARY KEY,
                email             VARCHAR(255) UNIQUE NOT NULL,
                name              VARCHAR(255),
                status            ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
                confirmationToken VARCHAR(255) UNIQUE,
                confirmedAt       DATETIME,
                unsubscribedAt    DATETIME,
                source            VARCHAR(255),
                createdAt         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS contacts (
                id           VARCHAR(36)  PRIMARY KEY,
                firstName    VARCHAR(255) NOT NULL,
                lastName     VARCHAR(255) NOT NULL,
                email        VARCHAR(255) NOT NULL,
                phone        VARCHAR(50),
                subject      VARCHAR(500) NOT NULL,
                message      TEXT NOT NULL,
                status       ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
                repliedAt    DATETIME,
                replyMessage TEXT,
                ipAddress    VARCHAR(45),
                createdAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS donations (
                id                   VARCHAR(36)  PRIMARY KEY,
                referenceNumber      VARCHAR(100) UNIQUE NOT NULL,
                donorName            VARCHAR(255) NOT NULL,
                donorEmail           VARCHAR(255) NOT NULL,
                donorPhone           VARCHAR(50),
                amount               DECIMAL(14,2) NOT NULL,
                currency             VARCHAR(10) NOT NULL DEFAULT 'TZS',
                paymentMethod        VARCHAR(100),
                transactionReference VARCHAR(255),
                receiptFile          VARCHAR(500),
                message              TEXT,
                cause                VARCHAR(255),
                status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                approvedAt           DATETIME,
                approvedBy           VARCHAR(36),
                rejectionReason      TEXT,
                isAnonymous          TINYINT(1) NOT NULL DEFAULT 0,
                createdAt            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (approvedBy) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS partners (
                id           VARCHAR(36)  PRIMARY KEY,
                name         VARCHAR(255) NOT NULL,
                logo         VARCHAR(500),
                website      VARCHAR(500),
                description  TEXT,
                partnerType  ENUM('funding','implementing','government','other') NOT NULL DEFAULT 'other',
                isActive     TINYINT(1) NOT NULL DEFAULT 1,
                displayOrder INT NOT NULL DEFAULT 0,
                createdAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS site_contents (
                id            VARCHAR(36)  PRIMARY KEY,
                `key`         VARCHAR(255) UNIQUE NOT NULL,
                value         LONGTEXT,
                type          ENUM('text','html','json','image') NOT NULL DEFAULT 'text',
                description   TEXT,
                lastUpdatedBy VARCHAR(36),
                createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (lastUpdatedBy) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS events (
                id            VARCHAR(36)  PRIMARY KEY,
                title         VARCHAR(500) NOT NULL,
                slug          VARCHAR(500) UNIQUE NOT NULL,
                description   TEXT,
                content       LONGTEXT,
                featuredImage VARCHAR(500),
                eventDate     DATE,
                endDate       DATE,
                startTime     VARCHAR(10),
                endTime       VARCHAR(10),
                location      VARCHAR(255),
                venue         VARCHAR(255),
                status        ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
                isPublished   TINYINT(1) NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS causes (
                id            VARCHAR(36)  PRIMARY KEY,
                title         VARCHAR(500) NOT NULL,
                slug          VARCHAR(500) UNIQUE NOT NULL,
                description   TEXT,
                content       LONGTEXT,
                featuredImage VARCHAR(500),
                category      ENUM('education','health','livelihood','advocacy','other') NOT NULL DEFAULT 'other',
                goalAmount    DECIMAL(14,2),
                raisedAmount  DECIMAL(14,2) NOT NULL DEFAULT 0,
                currency      VARCHAR(10) NOT NULL DEFAULT 'TZS',
                startDate     DATE,
                endDate       DATE,
                status        ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
                isFeatured    TINYINT(1) NOT NULL DEFAULT 0,
                isPublished   TINYINT(1) NOT NULL DEFAULT 0,
                gallery       TEXT NOT NULL DEFAULT '[]',
                createdAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS team_members (
                id           VARCHAR(36)  PRIMARY KEY,
                name         VARCHAR(255) NOT NULL,
                position     VARCHAR(255) NOT NULL,
                bio          TEXT,
                photo        VARCHAR(500),
                email        VARCHAR(255),
                phone        VARCHAR(50),
                linkedIn     VARCHAR(500),
                twitter      VARCHAR(255),
                displayOrder INT NOT NULL DEFAULT 0,
                isActive     TINYINT(1) NOT NULL DEFAULT 1,
                createdAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS media (
                id           VARCHAR(36)  PRIMARY KEY,
                filename     VARCHAR(500) NOT NULL,
                originalName VARCHAR(500) NOT NULL,
                mimetype     VARCHAR(100) NOT NULL,
                size         INT NOT NULL,
                type         ENUM('image','video','document') NOT NULL DEFAULT 'image',
                url          VARCHAR(500) NOT NULL,
                uploadedBy   VARCHAR(36),
                createdAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (uploadedBy) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS rate_limits (
                id      VARCHAR(36) PRIMARY KEY,
                rkey    VARCHAR(255) NOT NULL,
                count   INT NOT NULL DEFAULT 0,
                resetAt INT NOT NULL,
                INDEX idx_rl_key (rkey)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $sql) {
            $db->exec($sql);
        }
    }

    // ── Seed ──────────────────────────────────────────────────────────────────

    private static function seed(PDO $db): void
    {
        $count = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) return;

        $adminEmail    = env('ADMIN_EMAIL',    'admin@wei.or.tz');
        $adminPassword = env('ADMIN_PASSWORD', 'WeiAdmin2024!');
        $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $adminId = self::uuid();

        $stmt = $db->prepare(
            "INSERT INTO users (id, email, password, name, role, isActive) VALUES (?, ?, ?, 'WEI Administrator', 'admin', 1)"
        );
        $stmt->execute([$adminId, $adminEmail, $hashedPassword]);

        $contents = [
            ['hero_title',        'Empowering Women,<br>Transforming Tanzania',       'html',  'Main hero title'],
            ['hero_subtitle',     'Working together to advance women\'s rights and create sustainable communities across Tanzania.', 'text', 'Hero subtitle'],
            ['about_title',       'About Women Empowerment Initiatives',               'text',  'About section title'],
            ['about_description', 'WEI is a non-governmental organization dedicated to empowering women through education, economic empowerment, health programs, and advocacy.', 'text', 'About section description'],
            ['contact_phone',     '+255 743 111 867',                                 'text',  'Contact phone number'],
            ['contact_email',     'info@wei.or.tz',                                  'text',  'Contact email'],
            ['contact_address',   'Dodoma - Makulu, Tanzania',                        'text',  'Office address'],
            ['facebook_url',      '#',                                                 'text',  'Facebook page URL'],
            ['twitter_url',       '#',                                                 'text',  'Twitter/X profile URL'],
            ['instagram_url',     '#',                                                 'text',  'Instagram profile URL'],
        ];

        // Use INSERT IGNORE for MySQL, INSERT OR IGNORE for SQLite
        $ignore = DB_DRIVER === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $stmt = $db->prepare(
            "$ignore INTO site_contents (id, `key`, value, type, description, lastUpdatedBy) VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($contents as [$key, $value, $type, $description]) {
            $stmt->execute([self::uuid(), $key, $value, $type, $description, $adminId]);
        }

        error_log(
            "\n╔════════════════════════════════════════════════╗\n" .
            "║  WEI PHP API — database initialised            ║\n" .
            "║  Driver:         " . DB_DRIVER . "\n" .
            "║  Admin email:    $adminEmail\n" .
            "║  Admin password: $adminPassword\n" .
            "╚════════════════════════════════════════════════╝\n"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
