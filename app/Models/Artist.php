<?php

namespace App\Models;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['deezer_id', 'name', 'cover', 'albums_count', 'fans'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    /**
     * Get the tracks associated with the artist.
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class);
    }

    /**
     * Get the albums associated with the artist.
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class);
    }

    /**
     * Get the game sessions for this artist.
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }
}
