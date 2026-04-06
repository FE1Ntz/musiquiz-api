<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('game-session.{gameSessionId}', function (User $user, int $gameSessionId) {
    $gameSession = GameSession::find($gameSessionId);

    if (! $gameSession) {
        return false;
    }

    return $gameSession->user_id === $user->id;
});
