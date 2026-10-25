<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('game.'.$this->game->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'game' => [
                'uuid' => $this->game->uuid,
                'board' => $this->game->board,
                'turn' => $this->game->turn,
                'status' => $this->game->status,
                'winner' => $this->game->winner,
                'players' => (int) (bool) $this->game->player_x_id + (int) (bool) $this->game->player_o_id,
                'player_x_assigned' => (bool) $this->game->player_x_id,
                'player_o_assigned' => (bool) $this->game->player_o_id,
            ],
        ];
    }
}
