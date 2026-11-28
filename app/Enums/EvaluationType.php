<?php

namespace App\Enums;

enum EvaluationType: string
{
    case Auto = 'auto';
    case Human = 'human';
    case Judge = 'judge';
}
