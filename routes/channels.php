<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Company channel - only company members can listen
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

// Shipment channel - company members of involved companies
Broadcast::channel('shipment.{shipmentId}', function ($user, $shipmentId) {
    $shipment = \App\Models\Shipment::find($shipmentId);
    if (!$shipment) return false;

    return $user->company_id === $shipment->company_id
        || $user->company_id === $shipment->logistics_company_id;
});

// Government channel - only government users
Broadcast::channel('government', function ($user) {
    return $user->isGovernment() || $user->isAdmin();
});

// User private channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
