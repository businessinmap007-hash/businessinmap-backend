<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripProgram;
use App\Models\TripProgramItem;
use App\Services\Media\ImageUploadService;
use App\Services\TripPrograms\TripProgramService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «برنامج الرحلة»: a tour company writes a trip (days → items: text, photo, the card of a room a hotel gave it) and publishes it on its
 * page. The author's routes are business/trip-programs; a customer reads the published ones under businesses/{id}/trip-programs.
 */
class TripProgramController extends Controller
{
    public function __construct(private readonly TripProgramService $programs)
    {
    }

    // ---- the author -----------------------------------------------------------------------------------------------------

    /** GET business/trip-programs */
    public function index(Request $request)
    {
        $rows = $this->programs->mine(BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => ['programs' => $rows->map(fn (TripProgram $p) => [
            'id' => (int) $p->id, 'title' => (string) $p->title, 'summary' => $p->summary, 'status' => (string) $p->status, 'days_count' => (int) $p->days_count,
        ])->values()]]);
    }

    /** GET business/trip-programs/room-cards — the rooms it may drop into a programme, at the price it set. */
    public function roomCards(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['rooms' => $this->programs->roomCards(BusinessContext::id($request))]]);
    }

    /** POST business/trip-programs {title, summary?} */
    public function store(Request $request)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:140'], 'summary' => ['nullable', 'string', 'max:2000']]);
        $program = $this->programs->create(BusinessContext::id($request), $data);

        return response()->json(['success' => true, 'data' => ['program' => $this->programs->present($program, true)]], 201);
    }

    /** GET business/trip-programs/{id} */
    public function show(Request $request, int $program)
    {
        return $this->reply($this->own($request, $program));
    }

    /** PUT business/trip-programs/{id} {title?, summary?} */
    public function update(Request $request, int $program)
    {
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:140'], 'summary' => ['nullable', 'string', 'max:2000']]);

        return $this->reply($this->programs->update($this->own($request, $program), $data));
    }

    /** DELETE business/trip-programs/{id} */
    public function destroy(Request $request, int $program)
    {
        $this->programs->delete($this->own($request, $program));

        return response()->json(['success' => true, 'message' => __('حُذف البرنامج.')]);
    }

    /** POST business/trip-programs/{id}/publish|unpublish */
    public function publish(Request $request, int $program, string $action)
    {
        abort_unless(in_array($action, ['publish', 'unpublish'], true), 404);

        return $this->reply($this->programs->setPublished($this->own($request, $program), $action === 'publish'));
    }

    /** POST business/trip-programs/{id}/days {title?} */
    public function addDay(Request $request, int $program)
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:140']]);
        $p = $this->own($request, $program);
        $this->programs->addDay($p, $data['title'] ?? null);

        return $this->reply($p, 201);
    }

    /** PUT business/trip-programs/{id}/days/{day} {title} */
    public function renameDay(Request $request, int $program, int $day)
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:140']]);
        $p = $this->own($request, $program);
        $this->programs->dayOrFail($p, $day)->update(['title' => $data['title'] ?? null]);

        return $this->reply($p);
    }

    /** DELETE business/trip-programs/{id}/days/{day} */
    public function removeDay(Request $request, int $program, int $day)
    {
        $p = $this->own($request, $program);
        $this->programs->removeDay($this->programs->dayOrFail($p, $day));

        return $this->reply($p);
    }

    /** POST business/trip-programs/{id}/days/{day}/move {direction: up|down} */
    public function moveDay(Request $request, int $program, int $day)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $p = $this->own($request, $program);
        $this->programs->moveDay($this->programs->dayOrFail($p, $day), $data['direction'] === 'up' ? -1 : 1);

        return $this->reply($p);
    }

    /** POST business/trip-programs/{id}/days/{day}/items {kind: text|room|image, body?, allocation_id?} */
    public function addItem(Request $request, int $program, int $day)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(TripProgramItem::KINDS)],
            'body' => ['nullable', 'string', 'max:2000'],
            'allocation_id' => ['nullable', 'integer'],
        ]);
        $p = $this->own($request, $program);
        $item = $this->programs->addItem($p, $this->programs->dayOrFail($p, $day), $data);

        return response()->json(['success' => true, 'data' => ['item_id' => (int) $item->id, 'program' => $this->programs->present($p->refresh(), true)]], 201);
    }

    /** PUT business/trip-programs/{id}/items/{item} {body} */
    public function updateItem(Request $request, int $program, int $item)
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:2000']]);
        $p = $this->own($request, $program);
        $this->programs->updateItem($this->programs->itemOrFail($p, $item), $data['body'] ?? null);

        return $this->reply($p);
    }

    /** DELETE business/trip-programs/{id}/items/{item} */
    public function removeItem(Request $request, int $program, int $item)
    {
        $p = $this->own($request, $program);
        $this->programs->removeItem($this->programs->itemOrFail($p, $item));

        return $this->reply($p);
    }

    /** POST business/trip-programs/{id}/items/{item}/move {direction: up|down} */
    public function moveItem(Request $request, int $program, int $item)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $p = $this->own($request, $program);
        $this->programs->moveItem($this->programs->itemOrFail($p, $item), $data['direction'] === 'up' ? -1 : 1);

        return $this->reply($p);
    }

    /** POST business/trip-programs/{id}/items/{item}/image — multipart `image`, for an item of kind image. */
    public function setImage(Request $request, int $program, int $item)
    {
        $request->validate(['image' => ImageUploadService::validationRules()]);
        $p = $this->own($request, $program);
        $this->programs->setImage($this->programs->itemOrFail($p, $item), $request->file('image'));

        return $this->reply($p);
    }

    // ---- the reader -----------------------------------------------------------------------------------------------------

    /** GET businesses/{business}/trip-programs — what it has published. */
    public function published(Request $request, int $business)
    {
        $rows = TripProgram::query()->published()->where('business_id', $business)->withCount('days')->orderByDesc('published_at')->get();

        return response()->json(['success' => true, 'data' => ['programs' => $rows->map(fn (TripProgram $p) => [
            'id' => (int) $p->id, 'title' => (string) $p->title, 'summary' => $p->summary, 'days_count' => (int) $p->days_count,
        ])->values()]]);
    }

    /** GET businesses/{business}/trip-programs/{program} */
    public function showPublished(Request $request, int $business, int $program)
    {
        return response()->json(['success' => true, 'data' => ['program' => $this->programs->present($this->programs->publishedOrFail($business, $program))]]);
    }

    // ---- helpers --------------------------------------------------------------------------------------------------------

    private function own(Request $request, int $id): TripProgram
    {
        return $this->programs->ownedOrFail(BusinessContext::id($request), $id);
    }

    private function reply(TripProgram $program, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => ['program' => $this->programs->present($program->refresh(), true)]], $status);
    }
}
