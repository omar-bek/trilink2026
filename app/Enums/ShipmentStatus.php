<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case IN_PRODUCTION = 'in_production';
    case READY_FOR_PICKUP = 'ready_for_pickup';
    case IN_TRANSIT = 'in_transit';
    case IN_CLEARANCE = 'in_clearance';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
