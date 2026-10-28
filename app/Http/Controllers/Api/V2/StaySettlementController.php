<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\StaySettlementPayment;
use App\Services\Hotels\StaySettlement;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\Request;

/**
 * «التسوية» of a non-refundable stay — the two parties' own screen (StaySettlement). A stranger learns nothing: 404.
 */
final class StaySettlementController extends Controller
{
    public function __construct(private readonly StaySettlement $settlement)
    {
    }

    /** GET /api/v2/bookings/{booking}/stay-settlement */
    public function show(Request $request, Booking $booking)
    {
        $this->party($request, $booking);

        return $this->answer($request, $booking);
    }

    /** POST /api/v2/bookings/{booking}/stay-settlement/confirm — «تمت التسوية» */
    public function confirm(Request $request, Booking $booking)
    {
        $this->settlement->confirm($booking, $this->party($request, $booking));

        return $this->answer($request, $booking->fresh());
    }

    /** DELETE /api/v2/bookings/{booking}/stay-settlement/confirm — withdraw it while the other side has not confirmed */
    public function withdraw(Request $request, Booking $booking)
    {
        $this->settlement->withdraw($booking, $this->party($request, $booking));

        return $this->answer($request, $booking->fresh());
    }

    /** POST /api/v2/bookings/{booking}/stay-settlement/payments — multipart: amount, method, reference, proof, acknowledge */
    public function recordPayment(Request $request, Booking $booking)
    {
        $userId = $this->party($request, $booking);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:cash,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['required', ...ImageUploadService::validationRules()],
            // «الدفع خارج التطبيق على مسؤوليتك الشخصية، ويجب أن يكون لديك إثبات الدفع»
            'acknowledge' => ['accepted'],
        ]);

        $this->settlement->recordPayment($booking, $userId, $data, $request->file('proof'));

        return $this->answer($request, $booking->fresh(), 201);
    }

    /** POST /api/v2/bookings/{booking}/stay-settlement/payments/{payment}/answer — `{received: bool, note?}`, the hotel only */
    public function answerPayment(Request $request, Booking $booking, StaySettlementPayment $payment)
    {
        $userId = $this->party($request, $booking);
        $data = $request->validate(['received' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);

        $this->settlement->answerPayment($booking, $payment, $userId, (bool) $data['received'], $data['note'] ?? null);

        return $this->answer($request, $booking->fresh());
    }

    /** POST /api/v2/bookings/{booking}/stay-settlement/collect — `{amount?}` the hotel takes the deposit (or less) from the guest's hold */
    public function collect(Request $request, Booking $booking)
    {
        $userId = $this->party($request, $booking);
        $data = $request->validate(['amount' => ['nullable', 'numeric', 'min:1']]);

        $this->settlement->collect($booking, $userId, isset($data['amount']) ? (float) $data['amount'] : null);

        return $this->answer($request, $booking->fresh());
    }

    /** POST /api/v2/bookings/{booking}/stay-settlement/arbitration — three days after the cancellation, one side is enough */
    public function arbitration(Request $request, Booking $booking)
    {
        $dispute = $this->settlement->requestArbitration($booking, $this->party($request, $booking));

        return $this->answer($request, $booking->fresh(), 200, ['dispute_id' => (int) $dispute->id]);
    }

    /** GET /api/v2/me/stay-settlements — the open settlements of this account (the badge beside the notifications) */
    public function mine(Request $request)
    {
        $userId = (int) $request->user()->id;
        $rows = $this->settlement->pendingFor($userId)->load(['business:id,name', 'user:id,name']);

        return response()->json(['success' => true, 'data' => [
            'count' => $rows->count(),
            'settlements' => $rows->map(fn (Booking $b) => [
                'booking_id' => (int) $b->id,
                'counterpart' => (int) $b->user_id === $userId ? $b->business?->name : $b->user?->name,
                'starts_at' => $b->starts_at?->toIso8601String(),
            ] + (array) $this->settlement->payload($b, $userId))->values()->all(),
        ]]);
    }

    /** GET /api/v2/stay-payment-proofs/{payment} — `signed`: only the booking's two parties are ever handed the link */
    public function proof(StaySettlementPayment $payment)
    {
        abort_unless(ImageUploadService::isPrivate($payment->proof_image), 404);
        $full = ImageUploadService::privatePath($payment->proof_image);
        abort_unless(is_file($full), 404);

        return response()->file($full, ['Cache-Control' => 'private, max-age=21600', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The guest or the hotel of this booking — anyone else is told nothing. */
    private function party(Request $request, Booking $booking): int
    {
        $userId = (int) $request->user()->id;
        abort_unless(in_array($userId, [(int) $booking->user_id, (int) $booking->business_id], true), 404);

        return $userId;
    }

    private function answer(Request $request, Booking $booking, int $status = 200, array $extra = [])
    {
        return response()->json(['success' => true, 'data' => [
            'settlement' => $this->settlement->payload($booking, (int) $request->user()->id),
        ] + $extra], $status);
    }
}
