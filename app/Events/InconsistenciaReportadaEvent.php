<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InconsistenciaReportadaEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $inconsistencia;

    public function __construct(array $inconsistencia)
    {
        $this->inconsistencia = $inconsistencia;
    }

    public function broadcastOn(): array
    {
        return [new Channel('autorizaciones-captura')];
    }

    public function broadcastAs(): string
    {
        return 'inconsistencia.reportada';
    }
}
