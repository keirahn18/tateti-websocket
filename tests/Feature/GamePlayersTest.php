<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Events\GameUpdatedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GamePlayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_are_matched_in_pairs_and_additional_players_get_a_new_waiting_game(): void
    {
        $this->withSession(['tateti_player_id' => 'player-one'])
            ->get(route('game.create'))
            ->assertRedirect();
        $firstGame = Game::firstOrFail();

        $this->withSession(['tateti_player_id' => 'player-one'])
            ->get(route('game.show', $firstGame->uuid))
            ->assertOk()
            ->assertSee('Esperando al segundo jugador (1/2)...')
            ->assertSee('Jugador X')
            ->assertSee('En la partida')
            ->assertSee('Jugador O')
            ->assertSee('Esperando jugador');

        $this->withSession(['tateti_player_id' => 'player-two'])
            ->get(route('game.create'))
            ->assertRedirect(route('game.show', $firstGame->uuid));

        $this->withSession(['tateti_player_id' => 'player-one'])
            ->getJson(route('game.state', $firstGame->uuid))
            ->assertOk()
            ->assertJsonPath('role', 'X')
            ->assertJsonPath('game.player_x_assigned', true)
            ->assertJsonPath('game.player_o_assigned', true);

        $this->withSession(['tateti_player_id' => 'player-three'])
            ->postJson(route('game.join', $firstGame->uuid))
            ->assertOk()
            ->assertJsonPath('role', null)
            ->assertJsonPath('game.players', 2);

        $this->withSession(['tateti_player_id' => 'player-three'])
            ->get(route('game.create'))
            ->assertRedirect();
        $secondGame = Game::where('uuid', '!=', $firstGame->uuid)->firstOrFail();

        $this->withSession(['tateti_player_id' => 'player-four'])
            ->get(route('game.create'))
            ->assertRedirect(route('game.show', $secondGame->uuid));

        $this->assertSame('player-one', $firstGame->fresh()->player_x_id);
        $this->assertSame('player-two', $firstGame->fresh()->player_o_id);
        $this->assertSame('playing', $firstGame->fresh()->status);
        $this->assertSame('player-three', $secondGame->fresh()->player_x_id);
        $this->assertSame('player-four', $secondGame->fresh()->player_o_id);
    }

    public function test_a_player_with_an_old_waiting_room_is_matched_into_another_players_room(): void
    {
        $waitingGame = $this->createGame(['player_x_id' => 'waiting-player']);
        $oldGame = $this->createGame(['player_x_id' => 'joining-player']);

        $this->withSession(['tateti_player_id' => 'joining-player'])
            ->get(route('game.create'))
            ->assertRedirect(route('game.show', $waitingGame->uuid));

        $this->assertSame('joining-player', $waitingGame->fresh()->player_o_id);
        $this->assertSame('playing', $waitingGame->fresh()->status);
        $this->assertNull($oldGame->fresh()->player_x_id);
    }

    public function test_joining_a_shared_waiting_room_releases_the_players_old_waiting_room(): void
    {
        $sharedGame = $this->createGame(['player_x_id' => 'waiting-player']);
        $oldGame = $this->createGame(['player_x_id' => 'joining-player']);

        $this->withSession(['tateti_player_id' => 'joining-player'])
            ->postJson(route('game.join', $sharedGame->uuid))
            ->assertOk()
            ->assertJsonPath('role', 'O')
            ->assertJsonPath('game.players', 2);

        $this->assertSame('joining-player', $sharedGame->fresh()->player_o_id);
        $this->assertSame('playing', $sharedGame->fresh()->status);
        $this->assertNull($oldGame->fresh()->player_x_id);
    }

    public function test_moves_use_the_session_role_instead_of_a_client_supplied_role(): void
    {
        $game = $this->createGame([
            'player_x_id' => 'player-one',
            'player_o_id' => 'player-two',
        ]);

        Event::fake([GameUpdatedEvent::class]);

        $this->withSession(['tateti_player_id' => 'player-two'])
            ->postJson(route('game.move', $game->uuid), ['index' => 0, 'player' => 'X'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'No es tu turno.');

        $response = $this->withSession(['tateti_player_id' => 'player-one'])
            ->postJson(route('game.move', $game->uuid), ['index' => 0, 'player' => 'O'])
            ->assertOk()
            ->assertJsonPath('game.board.0', 'X')
            ->assertJsonPath('game.turn', 'O');

        $this->assertArrayNotHasKey('player_x_id', $response->json('game'));
        Event::assertDispatched(
            GameUpdatedEvent::class,
            fn (GameUpdatedEvent $event) => $event->action === 'move'
                && $event->game->board[0] === 'X',
        );
    }

    public function test_a_completed_game_releases_both_roles_and_can_be_restarted(): void
    {
        $game = $this->createGame([
            'player_x_id' => 'player-one',
            'player_o_id' => 'player-two',
        ]);

        foreach ([
            ['player-one', 0],
            ['player-two', 3],
            ['player-one', 1],
            ['player-two', 4],
            ['player-one', 2],
        ] as [$playerId, $index]) {
            $this->withSession(['tateti_player_id' => $playerId])
                ->postJson(route('game.move', $game->uuid), ['index' => $index])
                ->assertOk();
        }

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertSame('X', $game->winner);
        $this->assertNull($game->player_x_id);
        $this->assertNull($game->player_o_id);

        $this->postJson(route('game.reset', $game->uuid))
            ->assertOk()
            ->assertJsonPath('game.status', 'waiting')
            ->assertJsonPath('game.board.0', '');

        $this->withSession(['tateti_player_id' => 'player-three'])
            ->postJson(route('game.join', $game->uuid))
            ->assertOk()
            ->assertJsonPath('role', 'X');
    }

    private function createGame(array $attributes = []): Game
    {
        return Game::create(array_merge([
            'uuid' => fake()->uuid(),
            'board' => array_fill(0, 9, ''),
            'turn' => 'X',
            'status' => 'waiting',
        ], $attributes));
    }
}
