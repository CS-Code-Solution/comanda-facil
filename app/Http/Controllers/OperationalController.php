<?php

namespace App\Http\Controllers;

use App\Application\Dining\DiningContext;
use App\Application\Operations\OperationalStatus;
use App\Application\Operations\PilotChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OperationalController extends Controller
{
    public function index(OperationalStatus $status): Response
    {
        $events = DB::table('operations_events')->leftJoin('users', 'users.id', '=', 'operations_events.actor_id')
            ->orderByDesc('operations_events.id')->paginate(15, ['operations_events.action', 'operations_events.details', 'operations_events.created_at', 'users.name as actor']);

        return Inertia::render('operations/health', ['status' => $status->read(),
            'checks' => DB::table('pilot_checks')->orderBy('name')->get()->map(fn ($row) => [
                'name' => $row->name, 'label' => PilotChecklist::LABELS[$row->name], 'passed' => $row->passed, 'version' => $row->version,
                'evidence' => $row->evidence, 'checkedAt' => $row->checked_at, 'url' => route('pilot.check', $row->name)]),
            'events' => $events->getCollection()->map(fn ($row) => ['action' => $row->action, 'details' => json_decode($row->details, true),
                'at' => $row->created_at, 'actor' => $row->actor ?? 'Console']),
            'pagination' => ['previous' => $events->previousPageUrl(), 'next' => $events->nextPageUrl()]]);
    }

    public function record(Request $request, string $name, PilotChecklist $checklist): RedirectResponse
    {
        $checklist->record($name, $request->all(), DiningContext::staff($request->user()));

        return back();
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json(['ready' => true])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['ready' => false], 503)->header('Cache-Control', 'no-store');
        }
    }
}
