<?php

namespace App\Http\Presenters;

use App\Enums\StaffPermission;
use App\Models\User;

class StaffNavigation
{
    /** @return list<array{title: string, href: string}> */
    public static function items(User $user): array
    {
        $routes = [
            StaffPermission::Administration->value => 'workspace.administration',
            StaffPermission::Cashier->value => 'workspace.cashier',
            StaffPermission::DiningRoom->value => 'workspace.dining-room',
            StaffPermission::ViewPickup->value => 'pickup.index',
            StaffPermission::Kitchen->value => 'workspace.kitchen',
            StaffPermission::Delivery->value => 'workspace.delivery',
            StaffPermission::ManageStaff->value => 'staff.index',
            StaffPermission::ViewCatalog->value => 'catalog.index',
            StaffPermission::ManageCatalog->value => 'catalog.admin.home',
            StaffPermission::ViewReports->value => 'reports.index',
        ];
        $items = [];

        foreach ($routes as $ability => $routeName) {
            $permission = StaffPermission::from($ability);

            if ($user->hasPermission($permission)) {
                $items[] = ['title' => $permission->label(), 'href' => route($routeName, absolute: false)];
            }
        }

        return $items;
    }
}
