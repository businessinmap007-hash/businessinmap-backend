<?php

namespace App\Services\Requests;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPhoto;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;

/**
 * The photos of the problem on a «أحتاج فنيًا» request. Stored privately; a viewer is only ever handed signed links that run out,
 * and only the customer, the craftsmen who may answer while the request is open and the one he chose get them. Nothing of them
 * outlives the need: a cancelled or expired request, or a visit that is over, takes its photos with it.
 */
final class RequestPhotos
{
    public const MAX = 4;

    /** A visit's photos stay at most this long after it was arranged, whatever became of it. */
    private const KEEP_AFTER_ACCEPT_DAYS = 30;

    public function __construct(private readonly ImageUploadService $images) {}

    /** @param list<UploadedFile> $files */
    public function attach(ServiceRequest $request, array $files): void
    {
        foreach (array_values(array_slice($files, 0, self::MAX)) as $i => $file) {
            ServiceRequestPhoto::create([
                'service_request_id' => (int) $request->id,
                'path' => $this->images->storePrivateShrunk($file),
                'sort' => $i,
            ]);
        }
    }

    /** Signed links to the request's photos, in the order they were sent. @return list<string> */
    public function urls(int $requestId): array
    {
        return ServiceRequestPhoto::query()->where('service_request_id', $requestId)->orderBy('sort')->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => URL::temporarySignedRoute('service-request-photos.show', now()->addHours(6), ['photo' => (int) $id]))
            ->values()->all();
    }

    /** Rows and files both. */
    public function purge(int $requestId): int
    {
        $photos = ServiceRequestPhoto::query()->where('service_request_id', $requestId)->get();

        foreach ($photos as $photo) {
            $this->images->delete($photo->path);
            $photo->delete();
        }

        return $photos->count();
    }

    /**
     * Daily: the photos of every request that no longer needs them — cancelled, expired unanswered, or accepted and its visit
     * finished, cancelled or arranged more than thirty days ago.
     */
    public function sweep(): int
    {
        $ids = ServiceRequestPhoto::query()->distinct()->pluck('service_request_id');
        $deleted = 0;

        ServiceRequest::query()->whereIn('id', $ids)->get()->each(function (ServiceRequest $r) use (&$deleted) {
            if ($this->stillNeeded($r)) {
                return;
            }
            $deleted += $this->purge((int) $r->id);
        });

        // photos whose request is gone altogether
        ServiceRequestPhoto::query()->whereNotIn('service_request_id', ServiceRequest::query()->select('id'))
            ->pluck('service_request_id')->unique()
            ->each(function ($id) use (&$deleted) {
                $deleted += $this->purge((int) $id);
            });

        return $deleted;
    }

    private function stillNeeded(ServiceRequest $r): bool
    {
        if ($r->isOpen()) {
            return true;
        }

        if ($r->status !== ServiceRequest::STATUS_ACCEPTED || $r->operation_id === null) {
            return false;
        }

        if ($r->updated_at !== null && $r->updated_at->lt(now()->subDays(self::KEEP_AFTER_ACCEPT_DAYS))) {
            return false;
        }

        $status = Booking::withTrashed()->whereKey((int) $r->operation_id)->value('status');

        return in_array($status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS], true);
    }
}
