<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ta-Te-Ti en Tiempo Real</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-white flex flex-col items-center justify-center min-h-screen">

    <div class="max-w-md w-full bg-slate-800 p-6 rounded-xl shadow-lg text-center">
        <h1 class="text-3xl font-bold mb-4">Ta-Te-Ti WebSockets</h1>

        <div class="mb-4 text-lg">
            <p>Partida ID: <span class="font-mono text-cyan-400">{{ $game->uuid }}</span></p>
            <p class="mt-2">Juegas como: <span id="player-role" class="font-bold text-amber-400">{{ $role ?? 'Espectador' }}</span></p>
            <p class="mt-1">Turno actual: <span id="current-turn" class="font-bold text-emerald-400">{{ $game->turn }}</span></p>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 text-sm" aria-label="Jugadores de la partida">
            <div id="player-x-status" class="rounded bg-slate-700 px-3 py-2">
                <span class="font-bold text-blue-300">Jugador X</span>
                <span class="block" data-player-state>
                    {{ $gameState['player_x_assigned'] ? 'En la partida' : 'Esperando jugador' }}
                </span>
            </div>
            <div id="player-o-status" class="rounded bg-slate-700 px-3 py-2">
                <span class="font-bold text-purple-300">Jugador O</span>
                <span class="block" data-player-state>
                    {{ $gameState['player_o_assigned'] ? 'En la partida' : 'Esperando jugador' }}
                </span>
            </div>
        </div>

        <div class="mb-4 text-xs text-slate-400" role="status">
            WebSocket: <span id="connection-status" class="font-semibold text-amber-300">Conectando...</span>
        </div>

        <div id="status-message" class="mb-4 text-sm bg-slate-700 py-2 rounded" role="status">
            @if ($game->status === 'finished')
                {{ $game->winner === 'Empate' ? '¡Empate!' : '¡Ganó el jugador '.$game->winner.'!' }}
            @elseif ($gameState['players'] < 2)
                Esperando al segundo jugador ({{ $gameState['players'] }}/2)...
            @else
                Esperando el primer movimiento...
            @endif
        </div>

        <!-- Tablero de 3x3 -->
        <div class="grid grid-cols-3 gap-3 bg-slate-700 p-3 rounded-lg mb-6">
            @php $board =$game->board ?? array_fill(0, 9, ''); @endphp
            @foreach($board as $index =>$cell)
                <button
                    id="cell-{{ $index }}"
                    data-cell-index="{{ $index }}"
                    class="h-24 bg-slate-800 hover:bg-slate-600 text-4xl font-extrabold flex items-center justify-center rounded transition disabled:cursor-not-allowed">
                    {{ $cell }}
                </button>
            @endforeach
        </div>

        <button id="join-button" class="bg-cyan-600 hover:bg-cyan-500 px-4 py-2 rounded font-semibold mr-2">Intentar unirme</button>
        <button id="reset-button" class="bg-slate-600 hover:bg-slate-500 px-4 py-2 rounded font-semibold">Reiniciar partida</button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        let myRole = @json($role);
        let currentGame = @json($gameState);
        const gameUuid = @json($game->uuid);
        const csrfToken = @json(csrf_token());

        async function sendGameRequest(action, body = {}) {
            const response = await fetch(`/game/${gameUuid}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();

            if (!response.ok) {
                const validationMessage = Object.values(data.errors ?? {}).flat().join(' ');
                throw new Error(data.error || validationMessage || 'No se pudo actualizar la partida.');
            }

            return data;
        }

        async function joinGame() {
            try {
                const data = await sendGameRequest('join');
                if (data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                myRole = data.role;
                currentGame = data.game;
                if (!myRole) {
                    window.location.assign(@json(route('game.create')));
                    return;
                }
                updateBoardUI();
            } catch (error) {
                document.getElementById('status-message').innerText = error.message;
            }
        }

        async function makeMove(index) {
            if (!myRole) {
                document.getElementById('status-message').innerText = 'No tienes una ficha asignada. Si hay un lugar disponible, puedes unirte.';
                return;
            }

            try {
                const data = await sendGameRequest('move', { index });
                currentGame = data.game;
                updateBoardUI();
            } catch (error) {
                document.getElementById('status-message').innerText = error.message;
            }
        }

        async function resetGame() {
            try {
                const data = await sendGameRequest('reset');
                myRole = null;
                currentGame = data.game;
                updateBoardUI();
                await joinGame();
            } catch (error) {
                document.getElementById('status-message').innerText = error.message;
            }
        }

        document.querySelectorAll('[data-cell-index]').forEach((cell) => {
            cell.addEventListener('click', () => makeMove(Number(cell.dataset.cellIndex)));
        });
        document.getElementById('join-button').addEventListener('click', joinGame);
        document.getElementById('reset-button').addEventListener('click', resetGame);

        function updateBoardUI() {
            currentGame.board.forEach((value, index) => {
                const cell = document.getElementById(`cell-${index}`);
                cell.innerText = value;
                cell.disabled = !myRole || currentGame.players < 2 || currentGame.status === 'finished' || currentGame.turn !== myRole || value !== '';
            });
            document.getElementById('current-turn').innerText = currentGame.turn;
            document.getElementById('player-role').innerText = myRole ?? 'Espectador';
            document.getElementById('join-button').hidden = Boolean(myRole) || currentGame.status === 'finished';
            document.querySelector('#player-x-status [data-player-state]').innerText =
                currentGame.player_x_assigned ? 'En la partida' : 'Esperando jugador';
            document.querySelector('#player-o-status [data-player-state]').innerText =
                currentGame.player_o_assigned ? 'En la partida' : 'Esperando jugador';

            let message = currentGame.players < 2
                ? `Esperando al segundo jugador (${currentGame.players}/2)...`
                : 'Esperando el primer movimiento...';
            if (currentGame.status === 'playing') {
                message = myRole ? `Turno de ${currentGame.turn}` : `Partida en curso. Turno de ${currentGame.turn}`;
            } else if (currentGame.status === 'finished') {
                message = currentGame.winner === 'Empate' ? '¡Empate!' : `¡Ganó el jugador ${currentGame.winner}!`;
            }

            document.getElementById('status-message').innerText = message;
        }

        function listenForGameUpdates(channel) {
            channel.listen('.game.updated', (event) => {
                currentGame = event.game;

                if (event.action === 'reset' || currentGame.status === 'finished') {
                    myRole = null;
                }

                updateBoardUI();

                if (event.action === 'reset') {
                    joinGame();
                }
            });

            channel.subscribed(async () => {
                connectionStatus.innerText = 'Conectado: escuchando esta partida';
                connectionStatus.className = 'font-semibold text-emerald-400';
                await refreshGameState();
            });

            channel.error(() => {
                connectionStatus.innerText = 'No se pudo suscribir a la partida. Revisa Reverb y su túnel.';
                connectionStatus.className = 'font-semibold text-red-400';
            });
        }

        async function refreshGameState() {
            try {
                const response = await fetch(`/game/${gameUuid}/state`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'No se pudo sincronizar el estado de la partida.');
                }

                currentGame = data.game;
                myRole = data.role;
                updateBoardUI();
            } catch (error) {
                connectionStatus.innerText = error.message;
                connectionStatus.className = 'font-semibold text-red-400';
            }
        }

        updateBoardUI();
        if (!myRole && currentGame.status !== 'finished') {
            joinGame();
        }

        const connectionStatus = document.getElementById('connection-status');
        if (!window.Echo) {
            connectionStatus.innerText = 'No se pudo iniciar Echo. Verifica que los assets estén compilados.';
            connectionStatus.className = 'font-semibold text-red-400';

            return;
        }

        const channel = window.Echo.channel(`game.${gameUuid}`);
        listenForGameUpdates(channel);

        window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => {
            const isConnected = current === 'connected';
            connectionStatus.innerText = isConnected
                ? 'Conectado: esperando la partida'
                : current === 'connecting'
                    ? 'Conectando...'
                    : 'Desconectado: revisa el túnel de Reverb';
            connectionStatus.className = isConnected
                ? 'font-semibold text-emerald-400'
                : 'font-semibold text-amber-300';
        });

        if (window.Echo.connector.pusher.connection.state === 'connected') {
            connectionStatus.innerText = 'Conectado: escuchando esta partida';
            connectionStatus.className = 'font-semibold text-emerald-400';
        }
    });
    </script>
</body>
</html>
