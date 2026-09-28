<?php

declare(strict_types=1);

namespace App\Realtime;

/*
 * Publisher "no operation": descarta los eventos en silencio.
 *
 * Se usa cuando REDIS_URL no está configurado (o falla la conexión), para que
 * la API siga funcionando de forma 100% normal sin realtime. El caso de uso
 * del pedido NUNCA depende de que se publique el evento.
 */
final class NullEventPublisher implements EventPublisherInterface
{
    public function publish(string $channel, array $payload): void
    {
        // Intencionalmente vacío: sin broker no hay nada que notificar.
    }
}