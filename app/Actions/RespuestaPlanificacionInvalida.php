<?php

namespace App\Actions;

use RuntimeException;

class RespuestaPlanificacionInvalida extends RuntimeException
{
    public function __construct(string $message, public readonly string $respuesta)
    {
        parent::__construct($message);
    }
}
