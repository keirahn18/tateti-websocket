<?php

namespace App\Http\Controllers;

use App\Events\GameUpdatedEvent;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function create(Request $request)
    {
        $playerId = $this->playerId($request);

        $result = DB::transaction(function () use ($playerId) {
            $activeGame = Game::where('status', 'playing')
                ->where(function ($query) use ($playerId) {
                    $query->where('player_x_id', $playerId)
                        ->orWhere('player_o_id', $playerId);
                })
                ->lockForUpdate()
                ->first();

            if ($activeGame) {
                return $activeGame;
            }

            $ownWaitingGame = Game::where('status', 'waiting')
                ->where(function ($query) use ($playerId) {
                    $query->where('player_x_id', $playerId)
                        ->orWhere('player_o_id', $playerId);
                })
                ->lockForUpdate()
                ->first();

            $game = Game::where('status', 'waiting')
                ->where(function ($query) {
                    $query->whereNull('player_x_id')
                        ->orWhereNull('player_o_id');
                })
                ->where(function ($query) use ($playerId) {
                    $query->where(function ($query) use ($playerId) {
                        $query->whereNull('player_x_id')
                            ->orWhere('player_x_id', '!=', $playerId);
                    })->where(function ($query) use ($playerId) {
                        $query->whereNull('player_o_id')
                            ->orWhere('player_o_id', '!=', $playerId);
                    });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $game) {
                if ($ownWaitingGame) {
                    return ['game' => $ownWaitingGame, 'released_game' => null];
                }

                $game = Game::create([
                    'uuid' => Str::uuid(),
                    'board' => array_fill(0, 9, ''),
                    'turn' => 'X',
                    'status' => 'waiting',
                    'player_x_id' => $playerId,
                ]);
            } elseif (! $game->player_x_id) {
                if ($ownWaitingGame) {
                    $ownWaitingGame->player_x_id = null;
                    $ownWaitingGame->save();
                }

                $game->player_x_id = $playerId;
                $game->save();
            } else {
                if ($ownWaitingGame) {
                    if ($ownWaitingGame->player_x_id === $playerId) {
                        $ownWaitingGame->player_x_id = null;
                    } else {
                        $ownWaitingGame->player_o_id = null;
                    }
                    $ownWaitingGame->save();
                }

                $game->player_o_id = $playerId;
                $game->save();
            }

            if ($game->player_x_id && $game->player_o_id && $game->status === 'waiting') {
                $game->status = 'playing';
                $game->save();
            }

            return ['game' => $game, 'released_game' => $ownWaitingGame];
        });

        if ($result instanceof Game) {
            $game = $result;
            $releasedGame = null;
        } else {
            $game = $result['game'];
            $releasedGame = $result['released_game'];
        }

        if ($releasedGame) {
            broadcast(new GameUpdatedEvent($releasedGame, 'left'))->toOthers();
        }

        broadcast(new GameUpdatedEvent($game, 'joined'))->toOthers();

        return redirect()->route('game.show', $game->uuid);
    }

    public function show(Request $request, $uuid)
    {
        $game = Game::where('uuid', $uuid)->firstOrFail();

        return view('game', [
            'game' => $game,
            'role' => $this->playerRole($game, $request->session()->get('tateti_player_id')),
            'gameState' => $this->gameState($game),
        ]);
    }

    public function state(Request $request, $uuid)
    {
        $game = Game::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'role' => $this->playerRole($game, $request->session()->get('tateti_player_id')),
            'game' => $this->gameState($game),
        ]);
    }

    public function makeMove(Request $request, $uuid)
    {
        $validated = $request->validate([
            'index' => ['required', 'integer', 'between:0,8'],
        ]);
        $playerId = $request->session()->get('tateti_player_id');

        if (! $playerId) {
            return response()->json(['error' => 'Únete a la partida antes de jugar.'], 403);
        }

        $result = DB::transaction(function () use ($uuid, $validated, $playerId) {
            $game = Game::where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            if ($game->status === 'finished') {
                return response()->json(['error' => 'La partida ya ha terminado.'], 400);
            }

            $player = $this->playerRole($game, $playerId);

            if (! $player) {
                return response()->json(['error' => 'No tienes una ficha asignada en esta partida.'], 403);
            }

            if (! $game->player_x_id || ! $game->player_o_id) {
                return response()->json(['error' => 'Esperando a que se una el segundo jugador.'], 409);
            }

            if ($game->turn !== $player) {
                return response()->json(['error' => 'No es tu turno.'], 400);
            }

            $board = $game->board;
            $index = $validated['index'];

            if ($board[$index] !== '') {
                return response()->json(['error' => 'Casilla ocupada o inválida.'], 400);
            }

            $board[$index] = $player;
            $game->board = $board;

            $winner = $this->checkWinner($board);

            if ($winner || ! in_array('', $board, true)) {
                $game->status = 'finished';
                $game->winner = $winner ?? 'Empate';
                $game->player_x_id = null;
                $game->player_o_id = null;
            } else {
                $game->turn = $player === 'X' ? 'O' : 'X';
                $game->status = 'playing';
            }

            $game->save();

            return $game;
        });

        if ($result instanceof Game) {
            broadcast(new GameUpdatedEvent($result, 'move'))->toOthers();

            return response()->json(['success' => true, 'game' => $this->gameState($result)]);
        }

        return $result;
    }

    public function join(Request $request, $uuid)
    {
        $playerId = $this->playerId($request);

        $result = DB::transaction(function () use ($uuid, $playerId) {
            $game = Game::where('uuid', $uuid)->lockForUpdate()->firstOrFail();

            if ($game->status === 'finished') {
                return ['redirect' => route('game.create')];
            }

            $role = $this->playerRole($game, $playerId);

            if (! $role) {
                if (! $game->player_x_id) {
                    $game->player_x_id = $playerId;
                    $role = 'X';
                } elseif (! $game->player_o_id) {
                    $game->player_o_id = $playerId;
                    $role = 'O';
                }

                if ($role) {
                    if ($game->player_x_id && $game->player_o_id) {
                        $game->status = 'playing';
                    }

                    $game->save();
                }
            }

            $previousGame = null;

            if ($role) {
                $previousGame = Game::where('status', 'waiting')
                    ->where('id', '!=', $game->id)
                    ->where(function ($query) use ($playerId) {
                        $query->where('player_x_id', $playerId)
                            ->orWhere('player_o_id', $playerId);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($previousGame) {
                    if ($previousGame->player_x_id === $playerId) {
                        $previousGame->player_x_id = null;
                    } else {
                        $previousGame->player_o_id = null;
                    }

                    $previousGame->save();
                }
            }

            return ['game' => $game, 'role' => $role, 'previous_game' => $previousGame];
        });

        if (isset($result['redirect'])) {
            return response()->json($result);
        }

        if ($result['previous_game']) {
            broadcast(new GameUpdatedEvent($result['previous_game'], 'left'))->toOthers();
        }

        if ($result['role']) {
            broadcast(new GameUpdatedEvent($result['game'], 'joined'))->toOthers();
        }

        return response()->json([
            'role' => $result['role'],
            'game' => $this->gameState($result['game']),
        ]);
    }

    public function reset($uuid)
    {
        $game = DB::transaction(function () use ($uuid) {
            $game = Game::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            $game->board = array_fill(0, 9, '');
            $game->turn = 'X';
            $game->status = 'waiting';
            $game->winner = null;
            $game->player_x_id = null;
            $game->player_o_id = null;
            $game->save();

            return $game;
        });

        broadcast(new GameUpdatedEvent($game, 'reset'))->toOthers();

        return response()->json(['success' => true, 'game' => $this->gameState($game)]);
    }

    private function playerId(Request $request): string
    {
        $playerId = $request->session()->get('tateti_player_id');

        if (! $playerId) {
            $playerId = (string) Str::uuid();
            $request->session()->put('tateti_player_id', $playerId);
        }

        return $playerId;
    }

    private function playerRole(Game $game, ?string $playerId): ?string
    {
        if (! $playerId) {
            return null;
        }

        return match ($playerId) {
            $game->player_x_id => 'X',
            $game->player_o_id => 'O',
            default => null,
        };
    }

    private function gameState(Game $game): array
    {
        return [
            'uuid' => $game->uuid,
            'board' => $game->board,
            'turn' => $game->turn,
            'status' => $game->status,
            'winner' => $game->winner,
            'players' => (int) (bool) $game->player_x_id + (int) (bool) $game->player_o_id,
            'player_x_assigned' => (bool) $game->player_x_id,
            'player_o_assigned' => (bool) $game->player_o_id,
        ];
    }

    private function checkWinner($board)
    {
        $lines = [
            [0,1,2], [3,4,5], [6,7,8],
            [0,3,6], [1,4,7], [2,5,8],
            [0,4,8], [2,4,6],
        ];

        foreach ($lines as $line) {
            [$a, $b, $c] = $line;

            if ($board[$a] !== '' && $board[$a] === $board[$b] && $board[$a] === $board[$c]) {
                return $board[$a];
            }
        }

        return null;
    }
}
