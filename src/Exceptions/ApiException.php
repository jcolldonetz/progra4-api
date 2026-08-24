<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/*
 * Base para las excepciones de negocio que se traducen a respuestas HTTP.
 * Cada subclase declara el código HTTP que le corresponde.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function httpStatus(): int;
}
