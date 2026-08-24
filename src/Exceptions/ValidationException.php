<?php

declare(strict_types=1);

namespace App\Exceptions;

/*
 * Se lanza desde el servicio cuando los datos no cumplen las reglas de negocio.
 * Agrupa TODOS los errores encontrados, campo por campo.
 *
 * @api respuesta HTTP 422
 */
final class ValidationException extends ApiException
{
    /**
     * @param array<string, string[]> $errors mapa campo => lista de mensajes
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'La solicitud contiene datos inválidos.',
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 422;
    }

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
