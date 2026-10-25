<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'board',
        'turn',
        'status',
        'winner',
        'player_x_id',
        'player_o_id',
    ];

    protected $casts = [
        'board' => 'array',
    ];
}
