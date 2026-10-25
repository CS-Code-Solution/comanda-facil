<?php

namespace App\Http\Controllers;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffCredentials;
use App\Http\Requests\Staff\ChangeOwnPinRequest;
use App\Http\Support\RefreshStaffSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OwnPinController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/pin', ['pinLength' => StaffCredentials::PIN_LENGTH]);
    }

    public function update(ChangeOwnPinRequest $request, ManageStaffAccount $accounts): RedirectResponse
    {
        $account = $accounts->changeOwnPin($request->string('pin')->toString(), $request->string('pin_confirmation')->toString(), $request->accessContext());
        RefreshStaffSession::forOwnAccount($request, $account);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'PIN alterado. Outras sessões foram encerradas.']);

        return to_route('pin.edit');
    }
}
