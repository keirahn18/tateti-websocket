<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            // Guardamos el tablero como JSON (ej: ["", "", "X", "", "O", "", "", "", ""])
            $table->json('board')->nullable();
            // Control de turnos ('X' o 'O')
            $table->string('turn')->default('X');
            // Estado de la partida: waiting, playing, finished
            $table->string('status')->default('waiting');
            // Ganador ('X', 'O', o 'Empate')
            $table->string('winner')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
