<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
 * Se lanza cuando faltan credenciales, son inválidas o el JWT no es válido.
 *
 * @api respuesta HTTP 401
 */
final class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'No autorizado.')
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
