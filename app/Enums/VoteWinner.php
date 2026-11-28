<?php

namespace App\Enums;

enum VoteWinner: string
{
    case A = 'a';
    case B = 'b';
    case Tie = 'tie';
    case BothBad = 'both_bad';
}
