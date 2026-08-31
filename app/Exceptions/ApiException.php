<?php

namespace App\Exceptions;

use Exception;

/**
 * Error terkontrol yang harus dibalas sebagai JSON {"error": message} dengan
 * status HTTP tertentu -- dilempar dari controller/service, ditangani
 * seragam lewat bootstrap/app.php (withExceptions).
 */
class ApiException extends Exception
{
    public function __construct(string $message, protected int $status = 400)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
