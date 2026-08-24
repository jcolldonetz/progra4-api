<?php

declare(strict_types=1);

namespace App\Http;

/*
 * Envoltorio mínimo de respuesta HTTP que el controlador devuelve al
 * front controller. Mantiene el código de estado junto a los datos.
 */
final class JsonResponse
{
    public function __construct(
        public readonly int $status,
        public readonly mixed $data,
    ) {
    }
}
