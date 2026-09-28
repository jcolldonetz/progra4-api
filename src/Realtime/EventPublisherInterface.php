<?php

declare(strict_types=1);

namespace App\Realtime;

/*
 * CONTRATO para notificar eventos del dominio a otras capas (WebSockets).
 *
 * La API no conoce WebSockets: solo publica un payload JSON en un canal de
 * un broker (Redis). Un server de notificaciones suscrito al canal reenvía el
 * mensaje a los clientes conectados.
 *
 * Implementaciones:
 *   - RedisEventPublisher: publica en Redis vía Predis (canal configurable,
 *     por defecto "items.stock").
 *   - NullEventPublisher: no hace nada. Útil cuando no hay Redis disponible o
 *     en pruebas.
 */
interface EventPublisherInterface
{
    /** Publica un evento JSON en el canal indicado. */
    public function publish(string $channel, array $payload): void;
}