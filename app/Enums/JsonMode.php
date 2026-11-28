<?php

namespace App\Enums;

enum JsonMode: string
{
    case Native = 'native';
    case Fallback = 'fallback';
    case None = 'none';
}
