<?php
namespace App\Core;

use Exception;

class HttpException extends Exception
{
    protected int $status;

    public function __construct(string $message, int $status = 500)
    {
        parent::__construct($message, $status);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
