<?php

namespace App\Domain\Resources\Constants;

enum TrafficLight: string {
    case GREEN = 'green';
    case RED = 'red';
    CASE YELLOW = 'yellow';
    CASE YELLOW_RED = 'yellow_red';
}
