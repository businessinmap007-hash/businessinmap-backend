<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\TrainingPlan;
use App\Services\Training\TrainingPlanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight of every trainer's plans. An admin can browse all training
 * plans (filter by status, search a trainer/client/title) and open one to see
 * its workout, nutrition, the client's progress, and this week's adherence.
 * Nothing here edits a plan, and the trainer↔trainee chat is deliberately NOT
 * surfaced (it is encrypted and read only through the judge-gated chats hub).
 */
class TrainingAdminController extends Controller
{
    public function __construct(private readonly TrainingPlanService $plans)
    {
    }

    /** GET admin/training-plans — all plans across trainers. */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $q = trim((string) $request->get('q', ''));

        $rows = TrainingPlan::query()
            ->with(['trainer:id,name', 'client:id,name'])
            ->withCount(['exercises', 'meals', 'progressLogs'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhereHas('trainer', fn ($b) => $b->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('client', fn ($b) => $b->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin-v2.training.index', [
            'rows' => $rows,
            'statuses' => TrainingPlan::STATUSES,
            'filters' => ['status' => $status, 'q' => $q],
        ]);
    }

    /** GET admin/training-plans/{plan} — one plan in full + this week's adherence. */
    public function show(TrainingPlan $plan): View
    {
        $plan->load([
            'trainer:id,name',
            'client:id,name',
            'exercises',
            'meals',
            'progressLogs',
        ]);

        return view('admin-v2.training.show', [
            'plan' => $plan,
            'summary' => $this->plans->weeklySummary($plan),
        ]);
    }
}
