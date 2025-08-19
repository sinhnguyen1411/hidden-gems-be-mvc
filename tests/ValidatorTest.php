<?php
use PHPUnit\Framework\TestCase;
use App\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testInvalidEmail(): void
    {
        $errors = Validator::validate(['email'=>'bad'],['email'=>'required|email']);
        $this->assertArrayHasKey('email',$errors);
    }

    public function testInRule(): void
    {
        $errors = Validator::validate(['role'=>'guest'],['role'=>'in:admin,shop,customer']);
        $this->assertArrayHasKey('role',$errors);
    }
}
