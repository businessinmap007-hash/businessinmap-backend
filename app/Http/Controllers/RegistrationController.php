<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreUsersRequest;
use App\Libraries\PushNotification;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{


    public $public_path;
    public $main;
    public $push;

    public function __construct(Request $request, \App\Libraries\Main $main, PushNotification $push)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);

        $this->main = $main;
        $this->push = $push;

    }


    public function showRegister()
    {
        // Sectors (parent categories) each with their business types (children),
        // to drive the cascading select on the business path of the two-path
        // form. Clients don't need these.
        $sectors = \App\Models\Category::query()
            ->whereHas('children')
            ->with(['children' => fn ($q) => $q->orderBy('category_children_master.reorder')->orderBy('category_children_master.id')])
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en']);

        return view('auth.register', compact('sectors'));
    }


    public function signup(StoreUsersRequest $request)
    {
        // The form's path choice. Accept the modern 'business' and the legacy
        // 'vendor' value, but always STORE User::TYPE_BUSINESS — isBusiness()
        // checks for exactly 'business', so a stored 'vendor' would never count
        // as a business (that was a latent bug).
        // No consent, no account: registration is cancelled if the terms are not
        // accepted. Validated inline (not in the shared StoreUsersRequest, which
        // the admin user form — with no consent checkbox — also uses).
        $request->validate(['terms_accepted' => ['accepted']], [
            'terms_accepted.accepted' => __('يجب الموافقة على الشروط والأحكام لإنشاء الحساب.'),
        ]);

        $isBusiness = in_array($request->get('auth'), ['business', 'vendor'], true);
        $type = $isBusiness ? User::TYPE_BUSINESS : User::TYPE_CLIENT;

        // A ban lives on the identity, not the row: mirror the API register
        // guard (Api\V2\AuthController@register) so a banned user cannot
        // re-register through the legacy web form after a delete/re-signup.
        if (\App\Models\BlockedIdentity::isBlocked($request->input('email'), $request->input('phone'))) {
            return returnedResponse(400, __('لا يمكن إنشاء حساب بهذه البيانات.'), null, null);
        }

        // A business is defined by its category_child (its service catalog key),
        // so the business path must pick one. Validated inline — not in the
        // shared StoreUsersRequest, which the admin user form also uses.
        if ($isBusiness) {
            $request->validate([
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'category_child_id' => ['required', 'integer', 'exists:category_children_master,id'],
            ]);
        }

        // Whitelist EXPLICITLY. Never mass-assign $request->all() here: User's
        // $fillable still carries some privileged columns (pin_code, activated_at,
        // paid_at, api_token …). The worst offenders (balance + the consent/trust
        // flags) are no longer fillable, but explicit whitelisting is the
        // defence that does not depend on remembering to keep $fillable clean.
        // The password is hashed by User::setPasswordAttribute.
        $name = $request->input('name')
            ?: trim($request->input('first_name') . ' ' . $request->input('last_name'));

        $attributes = [
            'name' => $name,
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'password' => $request->input('password'),
            'type' => $type,
            'api_token' => Str::random(120),
        ];

        if ($isBusiness) {
            $attributes['category_id'] = $request->input('category_id') ?: null;
            $attributes['category_child_id'] = (int) $request->input('category_child_id');
        }

        $user = User::create($attributes);

        if ($user) {
            if ($isBusiness) {
                $user->assign(2);
            }
            // Consent was validated as accepted above → record it against the
            // current terms + privacy versions (audit trail).
            app(\App\Services\LegalConsentService::class)->recordSignupConsent($user, $request->ip());
            auth()->loginUsingId($user->id);
            session()->flash('success', 'لقد تم تسجيل المستخدم بنجاح');
            return returnedResponse(200, 'لقد تم تسجيل المستخدم بنجاح', null, route('profile'));
        }

        return returnedResponse(400, 'تعذّر إنشاء الحساب، حاول مرة أخرى.', null, null);
    }


    private function notificationsSender($userId)
    {
        $data = [];
        $staticData = [
            'title' => "تسجيل حساب",
            'body' => "تم تسجيل فرد جديد",
            'item_id' => $userId,
            'type' => "signup",
            "url" => generateUrl($userId),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
        foreach (getNotificationManagers() as $userData) {
            $collection = collect($staticData);
            $collection->put('user_id', $userData);
            $data[] = $collection->all();
        }


        if (count($data) > 0) {
            $this->main->insertData(Notification::class, $data);
            $this->push->sendPushNotification([], getAdminDevices(), $staticData['title'], $staticData['body'], $staticData);
        }

    }


    public function sendSMSWK($userAccount, $passAccount, $numbers, $sender, $msg, $viewResult = 1)
    {
        global $arraySendMsgWK;
        $url = "www.mobily.ws/api/msgSend.php";
        $applicationType = "68";
        $msg = $msg;
        $sender = urlencode($sender);
        $stringToPost = "mobile=" . $userAccount . "&password=" . $passAccount . "&numbers=" . $numbers . "&sender=" . $sender . "&msg=" . $msg . "&applicationType=" . $applicationType . "&lang=3";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $stringToPost);
        $result = curl_exec($ch);

        if ($viewResult)

            $result = trim($result);
        // echo $result;
        return $result;

    }

    public function checkIsColExist(Request $request)
    {
        $user = User::where($request->col, $request->email)->first();
        if ($user) {
            if ($request->col == 'email') {
                $message = "عفواً, هذا البريد مستخدم من قبل مستخدم آخر.";
            } elseif ($request->col == 'phone') {
                $message = "عفواً, هذا الجوال مستخدم من قبل مستخدم آخر.";
            } else if ($request->col == 'username') {
                $message = "عفواً, هذا الاسم مستخدم من قبل مستخدم آخر.";

            }
            return response()->json([
                'status' => true,
                'message' => $message,
                'type' => $request->col
            ]);
        }
    }
}



