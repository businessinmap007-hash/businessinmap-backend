<?php

namespace App\Http\Controllers\AdminV2\Users;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UserPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(private UserPurgeService $purger) {}

    public function index(Request $request)
    {
        $q         = $request->string('q')->trim()->toString();
        $type      = (string) $request->get('type', '');
        $active    = $request->get('active');      // 1 | 0 | ''
        $subActive = $request->get('sub_active');  // 1 | 0 | ''
        $trashed   = (string) $request->get('trashed', ''); // '' | 'with' | 'only'

        // ✅ pagination
        $perPageAllowed = [10, 20, 50, 100];
        $perPage = (int) $request->get('per_page', 50);
        if (!in_array($perPage, $perPageAllowed, true)) $perPage = 50;

        // ✅ sorting (safe)
        $sortAllowed = ['id', 'name', 'phone', 'email', 'type', 'activated_at'];
        $sort = (string) $request->get('sort', 'id');
        if (!in_array($sort, $sortAllowed, true)) $sort = 'id';

        $dir = strtolower((string) $request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        $usersQuery = User::query()
            // Trashed filter
            ->when($trashed === 'with', fn ($q) => $q->withTrashed())
            ->when($trashed === 'only', fn ($q) => $q->onlyTrashed())

            // Search
            ->search($q)

            // Type filter (هنا بدون roles لتجنب مشاكل Bouncer)
            ->when($type !== '' && $type !== 'admin', fn ($q) => $q->where('type', $type))
            ->when($type === 'admin', fn ($q) => $q->where('type', 'admin'))

            // Activation filter (activated_at)
            ->when($active !== null && $active !== '', function ($q) use ($active) {
                if ((int) $active === 1) $q->whereNotNull('activated_at');
                else $q->whereNull('activated_at');
            })

            // Subscription filter
            ->when($subActive !== null && $subActive !== '', function ($q) use ($subActive) {
                if ((int) $subActive === 1) {
                    $q->whereHas('subscriptions', fn ($s) => $s->where('is_active', 1));
                } else {
                    $q->whereDoesntHave('subscriptions', fn ($s) => $s->where('is_active', 1));
                }
            })

            // badge
            ->with('latestSubscription')

            // ✅ Sorting
            ->when(true, function ($qq) use ($sort, $dir) {
                // ملاحظة: sorting على name/phone/email/type يحتاج الأعمدة تكون موجودة في جدول users
                $qq->orderBy($sort, $dir)->orderBy('id', 'desc');
            });

        $users = $usersQuery->paginate($perPage)->withQueryString();

        // خيارات UI
        $types = [
            '' => 'كل الأنواع',
            'client' => 'Client',
            'business' => 'Business',
            'admin' => 'Admin',
        ];

        $activeOptions = [
            ''  => 'كل حالات التفعيل',
            '1' => 'مفعل',
            '0' => 'غير مفعل',
        ];

        $subscriptionOptions = [
            ''  => 'كل الاشتراكات',
            '1' => 'لديه اشتراك نشط',
            '0' => 'بدون اشتراك نشط',
        ];

        $trashedOptions = [
            ''     => 'غير محذوفين فقط',
            'with' => 'مع المحذوفين',
            'only' => 'المحذوفين فقط',
        ];

        $perPageOptions = $perPageAllowed;

        return view('admin-v2.users.index', compact(
            'users',
            'q',
            'type',
            'active',
            'subActive',
            'trashed',
            'types',
            'activeOptions',
            'subscriptionOptions',
            'trashedOptions',
            'perPage',
            'perPageOptions',
            'sort',
            'dir',
        ));
    }


    public function edit(int $id)
{
    $user = \App\Models\User::query()->findOrFail($id);

    // رجّع لصفحة edit (عدّل المسار حسب مكان ملفك)
    return view('admin-v2.users.edit', [
        'user' => $user,
    ]);
}

    // Soft Delete (single)
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي.']);
        }

        // لو عندك isAdmin() شغالة خليه.. لو لا شيل الشرط
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return back()->withErrors(['error' => 'لا يمكن حذف حساب Admin.']);
        }

        DB::transaction(function () use ($user) {
            // Soft delete children (لو models فيها SoftDeletes)
            $user->subscriptions()->delete();

            // Soft delete user
            $user->delete();
        });

        return back()->with('success', 'تم حذف المستخدم (Soft) بنجاح');
    }

    // Soft Delete (bulk)
    public function bulkDestroy(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));

        if (empty($ids)) return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);

        $ids = array_values(array_diff($ids, [auth()->id()]));
        if (empty($ids)) return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي.']);

        // منع حذف Admins (اختياري)
        $adminIds = User::whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابات Admin ضمن الاختيار.']);
        }

        DB::transaction(function () use ($ids) {
            Subscription::whereIn('user_id', $ids)->delete();
            User::whereIn('id', $ids)->delete(); // soft
        });

        return back()->with('success', 'تم حذف المستخدمين المحددين (Soft) بنجاح');
    }

    // Restore (single)
    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);

        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'لا يمكن تنفيذ العملية على حسابك الحالي.']);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return back()->withErrors(['error' => 'لا يمكن تنفيذ العملية على حساب Admin.']);
        }

        $user->restore();

        return back()->with('success', 'تم استرجاع المستخدم بنجاح');
    }

    // Restore (bulk)
    public function bulkRestore(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));
        if (empty($ids)) return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);

        $ids = array_values(array_diff($ids, [auth()->id()]));

        $adminIds = User::withTrashed()->whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن استرجاع حسابات Admin ضمن الاختيار.']);
        }

        User::onlyTrashed()->whereIn('id', $ids)->restore();

        return back()->with('success', 'تم استرجاع المستخدمين المحددين بنجاح');
    }

    public function show(int $id)
    {
        $user = User::withTrashed()
            ->with(['latestSubscription'])
            ->findOrFail($id);

        $subscriptions = $user->subscriptions()
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin-v2.users.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
        ]);
    }


    // Force Delete (single) - HARD + (A) purge relations
    public function forceDelete($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي نهائيًا.']);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return back()->withErrors(['error' => 'لا يمكن حذف حساب Admin نهائيًا.']);
        }

        DB::transaction(function () use ($user) {
            // (A) حذف نهائي لكل العلاقات (حسب FK + جداول بدون FK)
            $this->purger->purge($user->id);

            // ثم حذف المستخدم نهائيًا
            $user->forceDelete();
        });

        return back()->with('success', 'تم حذف المستخدم نهائيًا مع كل علاقاته.');
    }

    // Force Delete (bulk) - HARD + (A) purge relations
    public function bulkForceDelete(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));
        if (empty($ids)) return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);

        $ids = array_values(array_diff($ids, [auth()->id()]));
        if (empty($ids)) return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي نهائيًا ضمن الاختيار.']);

        $adminIds = User::withTrashed()->whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابات Admin نهائيًا ضمن الاختيار.']);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->purger->purge((int)$id);
            }

            User::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        return back()->with('success', 'تم حذف المستخدمين نهائيًا مع كل علاقاتهم.');
    }

    public function update(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);

       $data = $request->validate([
        'name'  => 'required|string|max:191',
        'email' => 'required|email|max:191|unique:users,email,' . $user->id,
        'phone' => 'required|string|max:15|unique:users,phone,' . $user->id,
        'type'  => 'required|in:admin,client,business',

        'category_id' => 'nullable|integer',
        'about'       => 'nullable|string',

        'image' => 'nullable|string|max:255',
        'logo'  => 'nullable|string|max:255',
        'cover' => 'nullable|string|max:255',

        'action_code' => 'nullable|string|max:191',
        'code'        => 'nullable|string|max:55',

        'latitude'  => 'nullable|numeric',
        'longitude' => 'nullable|numeric',

        'password' => 'nullable|string|min:6|confirmed',

        'image' => 'nullable|string|max:255',
        'logo'  => 'nullable|string|max:255',
        'cover' => 'nullable|string|max:255',

    ]);


        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.edit', $user->id)
            ->with('success', 'تم تحديث بيانات المستخدم بنجاح');
    }

    private function cleanIds($ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn ($v) => $v > 0);
        return array_values(array_unique($ids));
    }
}
