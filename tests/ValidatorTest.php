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
}
