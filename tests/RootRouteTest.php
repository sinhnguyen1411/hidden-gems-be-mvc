<?php
use PHPUnit\Framework\TestCase;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

final class RootRouteTest extends TestCase
{
    public function testRootReturnsWelcome(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $router = new Router();
        $app = ['router' => $router];
        require __DIR__ . '/../routes/api.php';

        $request = Request::capture();
        $response = $router->dispatch($request);

        ob_start();
        $response->send();
        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertSame('Hidden Gems API', $data['message']);
    }
}

