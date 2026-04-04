<?php

namespace App\Models;

use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['deezer_id', 'title', 'cover', 'record_type', 'release_date', 'explicit_lyrics'])]
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'explicit_lyrics' => 'boolean',
        ];
    }

    /**
     * Get the artists associated with the album.
     */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class);
    }

    /**
     * Get the tracks belonging to the album.
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
