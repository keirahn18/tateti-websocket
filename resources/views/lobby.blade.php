<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ta-Te-Ti en Tiempo Real</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4 text-white">
    <main class="w-full max-w-lg rounded-2xl bg-slate-800 p-8 text-center shadow-xl">
        <p class="mb-2 text-sm font-semibold uppercase tracking-widest text-cyan-400">Ta-Te-Ti en tiempo real</p>
        <h1 class="mb-4 text-4xl font-bold">¿Jugamos?</h1>
        <p class="mb-8 text-slate-300">
            Busca una partida disponible. Cada tablero admite solo dos jugadores; si no hay rival, espera y alguien se unirá.
        </p>
        <a href="{{ route('game.create') }}" class="inline-flex rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white transition hover:bg-cyan-500">
            Buscar partida
        </a>
    </main>
</body>
</html>
