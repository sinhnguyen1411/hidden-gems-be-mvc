<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\JsonResponse;

class PoliciesController extends Controller
{
    private function readFile(string $name): ?string
    {
        $root = dirname(__DIR__, 3);
        $paths = [
            $root . '/storage/policies/' . $name . '.md',
            $root . '/public/policies/' . $name . '.md',
        ];
        foreach ($paths as $p) {
            if (is_file($p)) return file_get_contents($p);
        }
        return null;
    }

    public function terms(Request $req): Response
    {
        $txt = $this->readFile('terms') ?? "Terms not available.";
        return JsonResponse::ok(['name'=>'Terms of Service','content'=>$txt]);
    }

    public function privacy(Request $req): Response
    {
        $txt = $this->readFile('privacy') ?? "Privacy policy not available.";
        return JsonResponse::ok(['name'=>'Privacy Policy','content'=>$txt]);
    }
}

