<?php
use PHPUnit\Framework\TestCase;
use App\Core\Auth;

final class AuthTest extends TestCase
{
    public function testIssueAndVerify(): void
    {
        $_ENV['JWT_SECRET'] = 'test';
        $token = Auth::issue(['uid'=>1], 3600);
        $claims = Auth::verify($token);
        $this->assertSame(1, $claims['uid']);
    }

    public function testExpiredToken(): void
    {
        $_ENV['JWT_SECRET'] = 'test';
        $token = Auth::issue(['uid'=>1], -1);
        $this->expectException(Firebase\JWT\ExpiredException::class);
        Auth::verify($token);
    }
}
