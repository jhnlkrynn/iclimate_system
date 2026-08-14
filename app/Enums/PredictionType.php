<?php

namespace App\Enums;

enum PredictionType: string
{
    case WEATHER = 'weather';
    case RICE_YIELD = 'rice_yield';
}
