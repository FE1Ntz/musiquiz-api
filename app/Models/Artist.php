<?php

namespace App\Models;

use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['deezer_id', 'name', 'cover', 'albums_count', 'fans'])]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;
}
