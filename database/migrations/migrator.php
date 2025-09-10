<?php
// Versioned migrator: applies *.up.sql in order, tracks in schema_migrations, supports down/baseline.

require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\DB;

$root = dirname(__DIR__, 2);
$dotenv = Dotenv::createImmutable($root);
$dotenv->safeLoad();

// Init DB connection
DB::init([
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'hiddengems',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]);

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(255) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;");

function appliedVersions(PDO $pdo): array {
    $rows = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
}

function allUpMigrations(): array {
    $files = glob(__DIR__ . '/*.up.sql');
    sort($files, SORT_STRING);
    $map = [];
    foreach ($files as $f) {
        $ver = basename($f, '.up.sql');
        $map[$ver] = $f;
    }
    return $map;
}

function runSqlFile(PDO $pdo, string $path): void {
    $sql = file_get_contents($path);
    $pdo->exec($sql);
}

$cmd = $argv[1] ?? 'up';
switch ($cmd) {
    case 'up':
        $applied = array_flip(appliedVersions($pdo));
        $all = allUpMigrations();
        $count = 0;
        foreach ($all as $ver => $file) {
            if (isset($applied[$ver])) continue;
            runSqlFile($pdo, $file);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations(version) VALUES (?)');
            $stmt->execute([$ver]);
            echo "✅ Applied: $ver\n";
            $count++;
        }
        if ($count === 0) echo "✔️  No pending migrations.\n";
        break;

    case 'down':
        $steps = (int)($argv[2] ?? 1);
        if ($steps < 1) { echo "Steps must be >= 1\n"; exit(1); }
        for ($i=0; $i<$steps; $i++) {
            $ver = $pdo->query('SELECT version FROM schema_migrations ORDER BY applied_at DESC LIMIT 1')->fetchColumn();
            if (!$ver) { echo "No more migrations to roll back.\n"; break; }
            $down = __DIR__ . '/' . $ver . '.down.sql';
            if (!is_file($down)) { echo "No down file for $ver\n"; exit(1); }
            runSqlFile($pdo, $down);
            $pdo->prepare('DELETE FROM schema_migrations WHERE version=?')->execute([$ver]);
            echo "↩️  Rolled back: $ver\n";
        }
        break;

    case 'baseline':
        // Marks all existing *.up.sql as applied without executing SQL
        $all = allUpMigrations();
        $applied = array_flip(appliedVersions($pdo));
        $count = 0;
        foreach (array_keys($all) as $ver) {
            if (isset($applied[$ver])) continue;
            $pdo->prepare('INSERT INTO schema_migrations(version) VALUES (?)')->execute([$ver]);
            echo "🧱 Baseline: $ver\n";
            $count++;
        }
        if ($count === 0) echo "✔️  Nothing to baseline.\n";
        break;

    default:
        echo "Usage: php database/migrations/migrator.php [up|down [N]|baseline]\n";
        exit(1);
}

