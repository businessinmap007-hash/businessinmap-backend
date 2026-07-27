<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AgendaItem;
use App\Services\Agenda\AgendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A user's personal agenda: their unified day of appointments, bookings,
 * medication doses and personal tasks. They read their day, add their own tasks
 * (which block time like any commitment), and cancel the ones they added.
 */
class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda)
    {
    }

    /** GET /api/v2/agenda?date=YYYY-MM-DD — my items for a day (default today). */
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->get('date'))
            : Carbon::today();

        $items = $this->agenda->forDay((int) $request->user()->id, $date)
            ->map(fn (AgendaItem $i) => $this->serialize($i))
            ->all();

        return response()->json([
            'success' => true,
            'data' => ['date' => $date->toDateString(), 'items' => $items],
        ]);
    }

    /**
     * GET /api/v2/agenda/week?date=YYYY-MM-DD — my week (7 days from the Saturday
     * of the week containing `date`, default this week). Every day is returned,
     * empty ones included, so the client can render a full grid.
     */
    public function week(Request $request)
    {
        $anchor = $request->filled('date') ? Carbon::parse($request->get('date')) : Carbon::today();
        $start = $anchor->copy()->startOfWeek(Carbon::SATURDAY);
        $end = $start->copy()->addDays(6)->endOfDay();

        $byDay = $this->agenda->forRange((int) $request->user()->id, $start, $end)
            ->groupBy(fn (AgendaItem $i) => $i->starts_at->toDateString());

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $date = $start->copy()->addDays($d)->toDateString();
            $days[] = [
                'date' => $date,
                'items' => ($byDay->get($date) ?? collect())->map(fn (AgendaItem $i) => $this->serialize($i))->values()->all(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => ['from' => $start->toDateString(), 'to' => $start->copy()->addDays(6)->toDateString(), 'days' => $days],
        ]);
    }

    /** POST /api/v2/agenda — add my own timed task (blocks time, may remind). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'remind' => ['nullable', 'boolean'],
        ]);

        $item = $this->agenda->addPersonalTask(
            (int) $request->user()->id,
            $data['title'],
            Carbon::parse($data['starts_at']),
            isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
            $data['notes'] ?? null,
            (bool) ($data['remind'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => __('تمت إضافة المهمة.'),
            'data' => ['item' => $this->serialize($item)],
        ], 201);
    }

    /**
     * POST /api/v2/agenda/recurring — add a repeating personal task. `frequency`
     * daily or weekly; `weekdays` (0=Sun..6=Sat) applies to weekly. Days that
     * clash with an existing commitment are skipped. Returns created/skipped.
     */
    public function storeRecurring(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'frequency' => ['required', 'in:daily,weekly'],
            'weekdays' => ['required_if:frequency,weekly', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'remind' => ['nullable', 'boolean'],
        ]);

        [$h, $m] = array_map('intval', explode(':', $data['start_time']));
        $weekdays = $data['frequency'] === 'weekly'
            ? array_values(array_unique(array_map('intval', $data['weekdays'])))
            : [];

        $result = $this->agenda->addRecurringTasks(
            (int) $request->user()->id,
            $data['title'],
            $h,
            $m,
            (int) ($data['duration_minutes'] ?? 30),
            $weekdays,
            (int) ($data['weeks'] ?? 4) * 7,
            $data['notes'] ?? null,
            (bool) ($data['remind'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => __('تمت إضافة المهام المتكررة.'),
            'data' => $result,
        ], 201);
    }

    /** DELETE /api/v2/agenda/{item} — cancel a personal task I added. */
    public function destroy(Request $request, int $item)
    {
        $row = AgendaItem::query()
            ->where('id', $item)
            ->where('user_id', (int) $request->user()->id)
            ->firstOrFail();

        // Only self-added tasks can be removed here; mirrored commitments are
        // cancelled from their own screen (the appointment, the booking…).
        abort_unless($row->kind === AgendaItem::KIND_PERSONAL, 422, __('لا يمكن حذف هذا العنصر من هنا.'));

        $row->update(['status' => AgendaItem::STATUS_CANCELLED]);

        return response()->json(['success' => true, 'message' => __('تم حذف المهمة.')]);
    }

    private function serialize(AgendaItem $i): array
    {
        return [
            'id' => (int) $i->id,
            'kind' => (string) $i->kind,
            'title' => $i->title,
            'notes' => $i->notes,
            'starts_at' => optional($i->starts_at)->toIso8601String(),
            'ends_at' => optional($i->ends_at)->toIso8601String(),
            'blocking' => (bool) $i->blocking,
            'source' => $i->source_type ? ['type' => class_basename($i->source_type), 'id' => (int) $i->source_id] : null,
        ];
    }
}
