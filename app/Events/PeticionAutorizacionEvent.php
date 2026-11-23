<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PeticionAutorizacionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $evento;
    public array $peticion;
    public string $timestamp;

    public function __construct(string $evento, array $peticion)
    {
        $this->evento = $evento;
        $this->peticion = $peticion;
        $this->timestamp = now()->format('Y-m-d H:i:s');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('autorizaciones-captura'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'peticion.actualizada';
    }
}
