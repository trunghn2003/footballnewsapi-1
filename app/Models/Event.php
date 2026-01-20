<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixture_id',
        'team_id',
        'player_id',
        'assist_id',
        'type',
        'detail',
        'comments',
        'time_elapsed',
        'time_extra',
        'player_name',
        'assist_name',
    ];

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function player()
    {
        return $this->belongsTo(Person::class, 'player_id');
    }

    public function assist()
    {
        return $this->belongsTo(Person::class, 'assist_id');
    }
}
