<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use App\Support\InvestigationFileUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A doctor's list of lab tests and radiology exams for a patient (or a patient's own request to a centre) — see the
 * create_investigation_orders migration. Its `images` are of two kinds, told apart by `purpose`: the patient's photo of
 * a paper request, and the results the centre attaches. All of them private.
 */
class InvestigationOrder extends Model
{
    use HasOwnedImages;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_READY = 'ready';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    public const PURPOSE_REQUEST = 'investigation_request';
    public const PURPOSE_RESULT = 'investigation_result';

    /** A result photo stays this many days after the patient AND the doctor have it… */
    public const FILE_GRACE_DAYS = 3;

    /** …or, when nobody kept it, this many days after the results came in (with a warning before). */
    public const FILE_RETENTION_DAYS = 180;
    public const FILE_WARN_DAYS = 14;

    /** Child trades that take these orders: lab, radiology centre, hospital, medical centre. */
    public const CENTER_CHILDREN = [163, 252, 513, 515];

    /** Child trades that only receive (a lab or a radiology centre issues nothing). */
    public const RECEIVE_ONLY_CHILDREN = [163, 252];

    protected $fillable = [
        'doctor_id', 'patient_id', 'center_id', 'status', 'notes', 'center_note', 'appointment_at', 'total',
        'issued_at', 'sent_at', 'accepted_at', 'ready_at', 'patient_saved_at', 'doctor_seen_at', 'expiry_warned_at', 'files_purged_at',
    ];

    protected $casts = [
        'appointment_at' => 'datetime',
        'issued_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'patient_saved_at' => 'datetime',
        'doctor_seen_at' => 'datetime',
        'expiry_warned_at' => 'datetime',
        'files_purged_at' => 'datetime',
        'total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvestigationOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(User::class, 'center_id');
    }

    /** The only people who may read it. */
    public function isParty(int $userId): bool
    {
        return in_array($userId, array_filter([(int) $this->doctor_id, (int) $this->patient_id, (int) $this->center_id]), true);
    }

    public static function isCenter(?User $user): bool
    {
        return $user && $user->isBusiness() && in_array((int) ($user->category_child_id ?? 0), self::CENTER_CHILDREN, true);
    }

    /** Files are private: signed links, handed out only inside a payload a party is already entitled to read. */
    protected function imageAddress(Image $image): string
    {
        return InvestigationFileUrl::for($image);
    }

    /** @return list<array{id:int,image:string,type:string}> `type` is `pdf` for a printed report, `image` otherwise */
    public function filesOf(string $purpose): array
    {
        return $this->images->where('purpose', $purpose)->map(fn (Image $i) => [
            'id' => (int) $i->id,
            'image' => $this->imageAddress($i),
            'type' => str_ends_with(strtolower((string) $i->image), '.pdf') ? 'pdf' : 'image',
        ])->values()->all();
    }
}
