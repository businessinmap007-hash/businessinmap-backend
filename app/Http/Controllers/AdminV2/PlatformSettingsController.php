<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payments\PaymentSettingsService;
use App\Support\PlatformSettings;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * «إعدادات المنصة»: the platform's timezone, and a read-only picture of whether the server it is deployed on is set up
 * the way the platform expects (clock, database clock, proxy, uploads…) — what to look at after moving it to
 * a server in another country.
 */
class PlatformSettingsController extends Controller
{
    /** The zone the platform and a business with none of its own are judged in. */
    private const COMMON_ZONES = [
        'Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Amman',
        'Asia/Beirut', 'Asia/Baghdad', 'Africa/Khartoum', 'Africa/Tripoli', 'Africa/Tunis', 'Africa/Algiers',
        'Africa/Casablanca', 'Europe/Istanbul', 'Europe/London', 'UTC',
    ];

    /**
     * GET admin/platform-settings
     *
     * View data key is `platform`, not `setting` — a legacy global composer injects its own `$setting` into every view.
     */
    public function edit(Request $request): View
    {
        $zone = (string) config('app.timezone');
        $now = Carbon::now($zone);

        return view('admin-v2.platform-settings.edit', ['platform' => [
            'zone' => $zone,
            'offset' => $now->format('P'),
            'is_dst' => $now->isDST(),
            'now' => $now->format('Y-m-d H:i:s'),
            'common' => self::COMMON_ZONES,
            'all' => DateTimeZone::listIdentifiers(),
            'from_file' => PlatformSettings::timezone() !== null,
            'checks' => $this->checks($request),
        ]]);
    }

    /** PUT admin/platform-settings */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
            'apply_to_businesses' => ['nullable', 'boolean'],
        ]);

        PlatformSettings::put(['timezone' => $data['timezone']]);
        PlatformSettings::applyTimezone();

        $moved = 0;

        // A business carries its own zone (every one was given the platform's): moving the platform to another country
        // moves them with it unless the admin says otherwise.
        if ($request->boolean('apply_to_businesses')) {
            $moved = User::query()->where('type', User::TYPE_BUSINESS)->update(['timezone' => $data['timezone']]);
        }

        return redirect()
            ->route('admin.platform-settings.edit')
            ->with('success', __('تم حفظ المنطقة الزمنية: :zone — وتُطبَّق على الطلبات القادمة.', ['zone' => $data['timezone']])
                . ($moved ? ' ' . __('طُبّقت على :n نشاطًا.', ['n' => $moved]) : ''));
    }

    /**
     * What the server looks like from here.
     *
     * @return list<array{label:string,value:string,state:string,hint:?string}>  state: ok | warn | bad | info
     */
    private function checks(Request $request): array
    {
        $rows = [];
        $add = function (string $label, string $value, string $state, ?string $hint = null) use (&$rows) {
            $rows[] = ['label' => $label, 'value' => $value, 'state' => $state, 'hint' => $hint];
        };

        $zone = (string) config('app.timezone');

        // 1) the clocks
        $add(__('ساعة المنصة'), Carbon::now($zone)->format('Y-m-d H:i:s') . ' (' . $zone . ')', 'info');
        $add(__('ساعة UTC'), Carbon::now('UTC')->format('Y-m-d H:i:s'), 'info');
        $add(
            __('منطقة PHP الافتراضية'),
            date_default_timezone_get(),
            date_default_timezone_get() === $zone ? 'ok' : 'bad',
            date_default_timezone_get() === $zone ? null : __('تختلف عن منطقة المنصة.')
        );

        try {
            $db = DB::selectOne('SELECT NOW() AS db_now, @@session.time_zone AS zone');
            $skew = abs(Carbon::now()->diffInSeconds(Carbon::parse($db->db_now), false));
            $add(
                __('ساعة قاعدة البيانات'),
                $db->db_now . ' (' . __('جلسة') . ' ' . $db->zone . ')',
                $skew <= 5 ? 'ok' : 'bad',
                $skew <= 5 ? null : __('تنحرف عن ساعة المنصة. اضبط DB_TIMEZONE في ملف البيئة.')
            );
        } catch (\Throwable $e) {
            $add(__('ساعة قاعدة البيانات'), __('تعذّر الاتصال'), 'bad', $e->getMessage());
        }

        // 2) environment
        $env = (string) config('app.env');
        $add(__('البيئة'), $env . ' / debug=' . (config('app.debug') ? 'true' : 'false'),
            $env === 'production' && config('app.debug') ? 'bad' : 'info',
            $env === 'production' && config('app.debug') ? __('أطفئ APP_DEBUG في الإنتاج.') : null);

        $queue = (string) config('queue.default');
        $add(__('الطابور'), $queue, $queue === 'sync' ? 'warn' : 'ok',
            $queue === 'sync' ? __('sync: الإشعارات تُنفَّذ داخل الطلب نفسه.') : null);

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $add(__('عنوان التطبيق'), $url, $https || $env !== 'production' ? 'info' : 'warn',
            $https || $env !== 'production' ? null : __('APP_URL ليس https.'));

        // 3) behind a proxy / CDN: the client IP must not be the proxy's
        $forwarded = $request->headers->has('X-Forwarded-For');
        $proxied = $forwarded && $request->server('REMOTE_ADDR') === $request->ip();
        $add(__('الوكيل العكسي'), $forwarded ? __('الطلب يمرّ عبر وكيل') : __('اتصال مباشر'),
            $proxied ? 'warn' : 'ok',
            $proxied ? __('عنوان العميل المقروء هو عنوان الوكيل: اضبط TrustProxies حتى لا يتساوى كل العملاء.') : null);

        // 4) uploads
        $uploads = public_path('files/uploads');
        $add(__('مجلد الرفع'), is_dir($uploads) ? (is_writable($uploads) ? __('موجود وقابل للكتابة') : __('موجود وغير قابل للكتابة')) : __('غير موجود'),
            is_dir($uploads) && is_writable($uploads) ? 'ok' : 'bad',
            is_dir($uploads) && is_writable($uploads) ? null : __('الصور المرفوعة لن تُحفَظ. أنشئ المجلد وامنح صلاحية الكتابة.'));

        // 5) payment
        $fawry = app(PaymentSettingsService::class)->fawryConfig();
        $hasCode = trim((string) ($fawry['merchant_code'] ?? '')) !== '';
        $add(__('بوابة الدفع'), (string) config('services.payments.default_gateway') . ' — ' . ($hasCode ? __('مضبوطة') : __('بلا بيانات اعتماد')),
            $hasCode ? 'ok' : 'warn', $hasCode ? null : __('أدخل بيانات البوابة من «بوابة الدفع».'));

        return $rows;
    }
}
