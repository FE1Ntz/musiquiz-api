<?php

namespace App\Enums;

enum RoomStatus: string
{
    case WaitingForPlayers = 'waiting_for_players';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';
}
