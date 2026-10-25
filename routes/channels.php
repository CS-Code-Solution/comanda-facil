<?php

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->isActiveStaff() && (int) $user->id === (int) $id;
});

foreach (['kitchen' => [StaffPermission::Kitchen], 'dining' => [StaffPermission::DiningRoom], 'cashier' => [StaffPermission::Cashier],
    'printing' => [StaffPermission::PrintKitchen, StaffPermission::PrintCashier]] as $scope => $permissions) {
    Broadcast::channel('operations.'.$scope, function (User $user) use ($permissions): bool {
        $current = $user->fresh();

        return $current && $current->auth_version === $user->auth_version
            && collect($permissions)->contains(fn ($permission) => $current->hasPermission($permission));
    });
}
