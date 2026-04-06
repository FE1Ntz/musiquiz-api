<?php

namespace App\Enums;

enum GameStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Abandoned = 'abandoned';
}
