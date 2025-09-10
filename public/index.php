<?php
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use App\Http\Middleware\CorsMiddleware;
use App\Core\Logger;
use App\Core\Metrics;
use App\Core\ErrorReporter;
use App\Core\Cache;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
// Ensure CORS headers are present even if dispatch/errors happen later
(new CorsMiddleware())->handle($request);

// Correlation ID (X-Request-Id)
$reqId = $request->getHeaderLine('X-Request-Id');
if ($reqId === '') { $reqId = bin2hex(random_bytes(8)); }
Logger::withBase(['request_id' => $reqId]);

$start = microtime(true);
Cache::resetDebug();
try {
    $response = $app['router']->dispatch($request);
} catch (HttpException $e) {
    $response = (new Response())->json(['error' => $e->getMessage()], $e->getStatus());
} catch (Throwable $e) {
    error_log($e->__toString());
    Logger::error('uncaught_exception', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]);
    try { ErrorReporter::report($e, $request, $reqId); } catch (Throwable $ignore) {}
    $response = (new Response())->json(['error' => 'Server error'], 500);
}

// Add correlation id header
$response->withHeader('X-Request-Id', $reqId);
$status = method_exists($response, 'getStatus') ? $response->getStatus() : 200;
$duration = (microtime(true) - $start) * 1000.0;
Logger::request([
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'status' => $status,
    'duration_ms' => round($duration,2),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
]);
try { Metrics::recordRequest($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', $status, $duration); } catch (Throwable $e) { /* ignore */ }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!empty($_ENV['CACHE_ENABLE_ETAG']) && strtoupper($method) === 'GET' && $status === 200) {
    $body = method_exists($response, 'getBody') ? $response->getBody() : '';
    $etag = 'W/"' . md5($body) . '"';
    $response->withHeader('ETag', $etag);
    $maxAge = (int)($_ENV['CACHE_MAX_AGE'] ?? 60);
    if ($maxAge > 0) { $response->withHeader('Cache-Control', 'public, max-age='.$maxAge); }
    $ifNoneMatch = $request->getHeaderLine('If-None-Match');
    if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
        $response->withStatus(304)->setBody('');
    }
}

// X-Cache debug headers (optional)
if (!empty($_ENV['CACHE_DEBUG_HEADER'])) {
    $dbg = Cache::debugSummary();
    $hit = !empty($dbg['hits']);
    $response->withHeader('X-Cache', $hit ? 'HIT' : 'MISS');
    if (!empty($_ENV['CACHE_DEBUG_HEADER_KEYS'])) {
        $keys = array_slice(array_merge($dbg['hits'], $dbg['misses']), 0, 10);
        if ($keys) {
            // avoid leaking long keys; trim each
            $keys = array_map(fn($k) => substr($k, 0, 64), $keys);
            $response->withHeader('X-Cache-Keys', implode(',', $keys));
        }
    }
}

$response->send();
