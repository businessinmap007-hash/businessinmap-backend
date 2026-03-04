<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sponsors\PostSponsorFormRequest;
use App\Http\Resources\Sponsors\SponsorsIndexResource;
use App\Libraries\Main;
use App\Models\Setting;
use App\Models\Sponsor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SponsorController extends Controller
{
    public string $public_path;
    public Main $config;

    public function __construct(Main $config)
    {
        $this->public_path = 'files/uploads/';
        $this->config = $config;
    }

    private function t(string $key): string
    {
        // You can move these to resources/lang later (recommended)
        $messages = [
            'sponsors.list' => [
                'ar' => 'قائمة الإعلانات.',
                'en' => 'Sponsors list.',
            ],
            'sponsors.balance_low' => [
                'ar' => 'عذرًا، رصيدك أقل من تكلفة الإعلان.',
                'en' => 'Sorry, your balance is less than the advertisement cost.',
            ],
            'sponsors.created' => [
                'ar' => 'تم إضافة الإعلان بنجاح.',
                'en' => 'Sponsor has been added successfully.',
            ],
            'sponsors.updated' => [
                'ar' => 'تم تحديث الإعلان بنجاح.',
                'en' => 'Sponsor has been updated successfully.',
            ],
            'sponsors.deleted' => [
                'ar' => 'تم حذف الإعلان بنجاح.',
                'en' => 'Sponsor has been deleted successfully.',
            ],
            'sponsors.stopped' => [
                'ar' => 'تم تنفيذ العملية بنجاح.',
                'en' => 'Operation has been successful.',
            ],
            'sponsors.error' => [
                'ar' => 'حدث خطأ ما.',
                'en' => 'Something went wrong.',
            ],
            'sponsors.free_list' => [
                'ar' => 'قائمة الإعلانات المجانية.',
                'en' => 'List of free ads.',
            ],
            'sponsors.free_ads' => [
                'ar' => 'إعلانات مجانية.',
                'en' => 'Free ads.',
            ],
        ];

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        return $messages[$key][$locale] ?? $key;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $paid = $user->sponsors()
            ->whereDate('expire_at', '>', Carbon::now())
            ->where('type', 'paid')
            ->get();

        $free = $user->sponsors()
            ->where('type', 'free')
            ->get();

        $ads = [
            'paid' => SponsorsIndexResource::collection($paid),
            'free' => SponsorsIndexResource::collection($free),
        ];

        return response()->json([
            'status'  => 200,
            'data'    => $ads,
            'message' => $this->t('sponsors.list'),
        ]);
    }

    public function store(PostSponsorFormRequest $request)
    {
        $user = $request->user();

        $setting = new Setting();
        $date = Carbon::parse($request->expire_at);

        $diff = (int) $date->diffInDays(Carbon::now());
        $costPerDay = (float) $setting->getBody('ad_cost');
        $adCost = $diff * $costPerDay;

        if ($this->config->calculateUserBalance($user) < $adCost) {
            return response()->json([
                'status'  => 400,
                'message' => $this->t('sponsors.balance_low'),
            ]);
        }

        $inputs = $request->validated();
        $inputs['activated_at'] = now();
        $inputs['expire_at'] = $date;

        if ($user->sponsors()->create($inputs)) {
            $user->transactions()->create([
                'status'    => 'withdrawal',
                'price'     => $adCost,
                'operation' => 'advertisement',
                'notes'     => 'Create a paid advertise',
                'target_id' => null,
            ]);

            return response()->json([
                'status'  => 200,
                'message' => $this->t('sponsors.created'),
            ]);
        }

        return response()->json([
            'status'  => 400,
            'message' => $this->t('sponsors.error'),
        ]);
    }

    public function update(PostSponsorFormRequest $request, Sponsor $sponsor)
    {
        $data = $request->validated();

        $updated = $sponsor->update($data);

        return response()->json([
            'status'  => $updated ? 200 : 400,
            'message' => $updated ? $this->t('sponsors.updated') : $this->t('sponsors.error'),
        ]);
    }

    public function delete(Sponsor $sponsor)
    {
        $deleted = (bool) $sponsor->delete();

        return response()->json([
            'status'  => $deleted ? 200 : 400,
            'message' => $deleted ? $this->t('sponsors.deleted') : $this->t('sponsors.error'),
        ]);
    }

    public function stop(Sponsor $sponsor)
    {
        $toggled = $sponsor->update([
            'activated_at' => $sponsor->activated_at ? null : now(),
        ]);

        return response()->json([
            'status'  => $toggled ? 200 : 400,
            'message' => $toggled ? $this->t('sponsors.stopped') : $this->t('sponsors.error'),
        ]);
    }

    public function paidSponsorList(Request $request)
    {
        $pageSize = (int) ($request->pageSize ?? 10);
        if ($pageSize <= 0) $pageSize = 10;

        $sponsors = Sponsor::whereNotNull('activated_at')
            ->where('type', 'paid')
            ->whereDate('expire_at', '>', Carbon::now())
            ->inRandomOrder()
            ->paginate($pageSize);

        // Keep resource response, but add localized message
        return SponsorsIndexResource::collection($sponsors)
            ->additional(['status' => 200, 'message' => $this->t('sponsors.list')]);
    }

    public function getFreeAds(Request $request)
    {
        $token = ltrim((string) $request->headers->get('Authorization'), 'Bearer ');

        if ($token !== '') {
            $collectionsIds = getTargetsAndFollowersBusiness($token);

            $sponsors = Sponsor::whereNotNull('activated_at')
                ->whereIn('user_id', $collectionsIds)
                ->where('type', 'free')
                ->get();

            return SponsorsIndexResource::collection($sponsors)
                ->additional(['status' => 200, 'message' => $this->t('sponsors.free_list')]);
        }

        $sponsors = Sponsor::whereNotNull('activated_at')
            ->where('type', 'free')
            ->paginate(15);

        return SponsorsIndexResource::collection($sponsors)
            ->additional(['status' => 200, 'message' => $this->t('sponsors.free_ads')]);
    }
}
