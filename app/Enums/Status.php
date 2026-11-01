<?php

namespace App\Enums;

enum Status: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
}
