<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
 * Se lanza cuando el recurso solicitado no existe.
 *
 * @api respuesta HTTP 404
 */
final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Recurso no encontrado.')
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
