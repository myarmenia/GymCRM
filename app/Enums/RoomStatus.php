<?php

namespace App\Enums;


enum RoomStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case OCCUPIED = 'occupied';
    case CHECKOUT = 'checkout';
    case CLEANING = 'cleaning';
}
