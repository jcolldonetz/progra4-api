<?php

declare(strict_types=1);

namespace App\Realtime;

use Predis\Client;

/*
 * Publica los eventos del dominio en Redis usando un canal de pub/sub.
 *
 * El server de notificaciones (proyecto progra4-notifications) se suscribe a
 * este canal con clue/redis-react y reenvía cada mensaje a los clientes web
 * conectados por WebSocket.
 */
final class RedisEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $channel = 'items.stock',
    ) {
    }

    public function publish(string $channel, array $payload): void
    {
        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($message === false) {
            throw new \JsonException('No se pudo serializar el evento en JSON.');
        }

        $this->client->publish($channel, $message);
    }
}