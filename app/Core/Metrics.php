<?php
namespace App\Core;

class Metrics
{
    private static ?string $file = null;

    private static function path(): string
    {
        if (self::$file !== null) return self::$file;
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        self::$file = $dir . DIRECTORY_SEPARATOR . 'metrics.json';
        return self::$file;
    }

    private static function load(): array
    {
        $p = self::path();
        if (!file_exists($p)) return ['requests'=>[], 'duration'=>[], 'errors_total'=>0, 'updated_at'=>time()];
        $json = file_get_contents($p);
        $data = json_decode($json, true);
        return is_array($data) ? $data : ['requests'=>[], 'duration'=>[], 'errors_total'=>0, 'updated_at'=>time()];
    }

    private static function save(array $data): void
    {
        $p = self::path();
        $data['updated_at'] = time();
        file_put_contents($p, json_encode($data), LOCK_EX);
    }

    public static function recordRequest(string $method, string $path, int $status, float $durationMs): void
    {
        // Reduce label cardinality: replace numeric ids with {id}
        $labelPath = preg_replace('#/\d+#', '/{id}', $path);
        $key = strtolower($method) . ' ' . $labelPath . ' ' . $status;
        $data = self::load();
        if (!isset($data['requests'][$key])) $data['requests'][$key] = 0;
        $data['requests'][$key]++;
        if ($status >= 500) $data['errors_total'] = ($data['errors_total'] ?? 0) + 1;

        // Duration histogram buckets (ms)
        $buckets = [5,10,25,50,100,250,500,1000,2500,5000];
        $hkey = strtolower($method) . ' ' . $labelPath;
        if (!isset($data['duration'][$hkey])) $data['duration'][$hkey] = array_fill_keys(array_map('strval',$buckets), 0);
        foreach ($buckets as $b) {
            if ($durationMs <= $b) { $data['duration'][$hkey][(string)$b]++; break; }
        }
        self::save($data);
    }

    public static function renderPrometheus(): string
    {
        $data = self::load();
        $lines = [];
        $lines[] = '# HELP app_info Application info';
        $lines[] = '# TYPE app_info gauge';
        $env = $_ENV['APP_ENV'] ?? 'local';
        $lines[] = 'app_info{env="'.$env.'"} 1';

        $lines[] = '# HELP app_requests_total Total HTTP requests';
        $lines[] = '# TYPE app_requests_total counter';
        foreach ($data['requests'] as $k => $v) {
            // key format: method path status
            [$m,$p,$s] = explode(' ', $k, 3);
            $lines[] = sprintf('app_requests_total{method="%s",path="%s",status="%s"} %d', $m, $p, $s, $v);
        }

        $lines[] = '# HELP app_request_duration_ms Request duration histogram (ms)';
        $lines[] = '# TYPE app_request_duration_ms histogram';
        foreach ($data['duration'] as $hkey => $buckets) {
            [$m,$p] = explode(' ', $hkey, 2);
            $cumulative = 0;
            foreach ($buckets as $le => $count) {
                $cumulative += $count;
                $lines[] = sprintf('app_request_duration_ms_bucket{method="%s",path="%s",le="%s"} %d', $m, $p, $le, $cumulative);
            }
            $lines[] = sprintf('app_request_duration_ms_count{method="%s",path="%s"} %d', $m, $p, array_sum($buckets));
        }

        $lines[] = '# HELP app_errors_total Total 5xx errors';
        $lines[] = '# TYPE app_errors_total counter';
        $lines[] = 'app_errors_total ' . (int)($data['errors_total'] ?? 0);

        // DB up gauge
        $dbUp = 0;
        try {
            DB::pdo()->query('SELECT 1');
            $dbUp = 1;
        } catch (\Throwable $e) {
            $dbUp = 0;
        }
        $lines[] = '# HELP db_up Database connectivity';
        $lines[] = '# TYPE db_up gauge';
        $lines[] = 'db_up ' . $dbUp;

        return implode("\n", $lines) . "\n";
    }
}

