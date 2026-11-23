<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoteActualizadoEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $accion;
    public array $lote;
    public string $timestamp;

    public function __construct(string $accion, array $lote = [])
    {
        $this->accion = $accion;
        $this->lote = $lote;
        $this->timestamp = now()->format('Y-m-d H:i:s');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('monitoreo-lotes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'lote.actualizado';
    }
}
