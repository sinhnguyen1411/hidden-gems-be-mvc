<?php
namespace PHPUnit\Framework;

class TestCase
{
    private ?string $expectedException = null;

    public function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception($message ?: "Failed asserting that two values are the same");
        }
    }

    public function assertArrayHasKey($key, $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Failed asserting that array has the key '$key'");
        }
    }

    public function expectException(string $class): void
    {
        $this->expectedException = $class;
    }

    public function runTest(string $method): void
    {
        $this->expectedException = null;
        try {
            $this->$method();
            if ($this->expectedException !== null) {
                throw new \Exception("Failed asserting that {$this->expectedException} is thrown");
            }
        } catch (\Throwable $e) {
            if ($this->expectedException === null || !is_a($e, $this->expectedException)) {
                throw $e;
            }
        }
    }
}
