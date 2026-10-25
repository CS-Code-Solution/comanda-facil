<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $permission = StaffPermission::from($request->route('permission'));

        return Inertia::render('workspace', ['title' => $permission->label(),
            'printsUrl' => $request->user()->hasPermission(StaffPermission::PrintCashier) ? route('printing.index') : null]);
    }
}
