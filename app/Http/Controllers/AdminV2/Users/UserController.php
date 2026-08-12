<?php

namespace App\Http\Controllers\AdminV2\Users;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildOption;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Services\CategoryChildOptionScope;
use App\Services\UserPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private UserPurgeService $purger,
        private CategoryChildOptionScope $optionScope = new CategoryChildOptionScope,
    ) {}

    /**
     * The option ids a child carries UNDER ONE ROOT.
     *
     * «الخيارات فى صفحة المستخدم تعرض كل مجموعة الخيارات للابن وليس الخيارات
     * المحددة لهذا الابن» — this screen read `category_child_option` by child
     * alone, so a child sitting under several roots handed back the UNION of
     * all of them. «دعاية وإعلان» granted 19 options under «خدمات» showed 45:
     * the goods vocabulary of the other root it also lives under (نطاق التعامل،
     * حالة المنتج، التسليم والاستلام) rode along, and the picker screen that
     * granted them never showed such a thing.
     *
     * `CategoryChildOptionScope` is where the rule lives — 0 means every root,
     * a real id means that root alone — and it is what the merchant's own
     * screens already obey. With no root in hand the union is still the right
     * answer, which is exactly what root 0 returns.
     */
    private function scopedOptionIds(int $childId, int $rootId): \Illuminate\Support\Collection
    {
        return $this->optionScope->idsFor($childId, max($rootId, 0));
    }

    public function index(Request $request)
    {
        $q         = $request->string('q')->trim()->toString();
        $type      = (string) $request->get('type', '');
        $active    = $request->get('active');
        $subActive = $request->get('sub_active');
        $trashed   = (string) $request->get('trashed', '');

        $categoryId      = (int) $request->get('category_id', 0);
        $categoryChildId = (int) $request->get('category_child_id', 0);

        $optionIds = collect($request->input('option_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $serviceIds = collect($request->input('service_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $perPageAllowed = [10, 20, 50, 100];
        $perPage = (int) $request->get('per_page', 50);
        if (!in_array($perPage, $perPageAllowed, true)) {
            $perPage = 50;
        }

        $sortAllowed = ['id', 'name', 'phone', 'email', 'type', 'activated_at'];
        $sort = (string) $request->get('sort', 'id');
        if (!in_array($sort, $sortAllowed, true)) {
            $sort = 'id';
        }

        $dir = strtolower((string) $request->get('dir', 'desc'));
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $users = User::query()
            ->when($trashed === 'with', fn ($q) => $q->withTrashed())
            ->when($trashed === 'only', fn ($q) => $q->onlyTrashed())
            ->search($q)
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($active !== null && $active !== '', function ($q) use ($active) {
                if ((int) $active === 1) {
                    $q->whereNotNull('activated_at');
                } else {
                    $q->whereNull('activated_at');
                }
            })
            ->when($subActive !== null && $subActive !== '', function ($q) use ($subActive) {
                if ((int) $subActive === 1) {
                    $q->whereHas('subscriptions', fn ($s) => $s->where('is_active', 1));
                } else {
                    $q->whereDoesntHave('subscriptions', fn ($s) => $s->where('is_active', 1));
                }
            })
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when($categoryChildId > 0, fn ($q) => $q->where('category_child_id', $categoryChildId))
            ->when(!empty($optionIds), function ($q) use ($optionIds) {
                $q->whereHas('options', function ($opt) use ($optionIds) {
                    $opt->whereIn('options.id', $optionIds);
                });
            })
            ->when(!empty($serviceIds), function ($q) use ($serviceIds) {
                $q->whereHas('activePlatformServices', function ($s) use ($serviceIds) {
                    $s->whereIn('platform_services.id', $serviceIds);
                });
            })
            ->with([
                'latestSubscription',
                'category:id,name_ar,name_en',
                'categoryChild:id,name_ar,name_en',
            ])
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $types = [
            '' => __('كل الأنواع'),
            'client' => 'Client',
            'business' => 'Business',
            'admin' => 'Admin',
        ];

        $activeOptions = [
            ''  => __('كل حالات التفعيل'),
            '1' => __('مفعل'),
            '0' => __('غير مفعل'),
        ];

        $subscriptionOptions = [
            ''  => __('كل الاشتراكات'),
            '1' => __('لديه اشتراك نشط'),
            '0' => __('بدون اشتراك نشط'),
        ];

        $trashedOptions = [
            ''     => __('غير محذوفين فقط'),
            'with' => __('مع المحذوفين'),
            'only' => __('المحذوفين فقط'),
        ];

        $categories = Category::query()
            ->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->get(['id', 'name_ar', 'name_en']);

        $children = collect();
        if ($categoryId > 0) {
            $children = CategoryChild::query()
                ->whereHas('parents', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                })
                ->orderByRaw('COALESCE(reorder, 999999) ASC')
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en', 'reorder']);
        }

        $options = collect();
        if ($categoryChildId > 0) {
            $options = Option::query()
                ->when($this->hasOptionIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
                ->whereIn('id', $this->scopedOptionIds($categoryChildId, $categoryId))
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en']);
        }

        $services = collect();
        if ($categoryChildId > 0) {
            $services = PlatformService::query()
                ->where('is_active', 1)
                ->whereIn('id', function ($sub) use ($categoryChildId) {
                    $sub->select('platform_service_id')
                        ->from('category_platform_services')
                        ->where('child_id', $categoryChildId)
                        ->where('is_active', 1);
                })
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en']);
        }

        $childCatalog = Category::query()
            ->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->with(['children' => function ($q) {
                $q->orderByRaw('COALESCE(reorder, 999999) ASC')
                    ->orderBy('name_ar')
                    ->orderBy('id');
            }])
            ->get(['id', 'name_ar', 'name_en'])
            ->mapWithKeys(function ($parent) {
                return [
                    (int) $parent->id => $parent->children
                        ->map(fn ($child) => [
                            'id' => (int) $child->id,
                            'name_ar' => (string) ($child->name_ar ?? ''),
                            'name_en' => (string) ($child->name_en ?? ''),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        /*
         * The cascade (pick a child → its options and services appear) used to
         * ship the WHOLE map with every page: 209 children, 5,915 option rows,
         * 380KB of JSON — 41% of the response — so that a dropdown could be
         * repopulated without a round trip. Almost every visit to this screen
         * never touches that filter.
         *
         * It is fetched now when a child is actually picked. The rows for the
         * child already selected still render server-side (above), so the
         * screen arrives complete and only a CHANGE costs a request.
         */

        return view('admin-v2.users.index', [
            'items' => $users,
            'q' => $q,
            'type' => $type,
            'active' => $active,
            'subActive' => $subActive,
            'trashed' => $trashed,
            'categoryId' => $categoryId,
            'categoryChildId' => $categoryChildId,
            'optionIds' => $optionIds,
            'serviceIds' => $serviceIds,
            'types' => $types,
            'activeOptions' => $activeOptions,
            'subscriptionOptions' => $subscriptionOptions,
            'trashedOptions' => $trashedOptions,
            'perPage' => $perPage,
            'perPageOptions' => $perPageAllowed,
            'sort' => $sort,
            'dir' => $dir,
            'categories' => $categories,
            'children' => $children,
            'options' => $options,
            'services' => $services,
            'childCatalog' => $childCatalog,
        ]);
    }

    /**
     * GET /admin/users/catalog?child_id=&category_id= — the options and services
     * one child carries under one root.
     *
     * Shipping the whole map with the page cost 380KB of JSON (209 children,
     * 5,915 option rows) on every visit, for a filter most visits never touch.
     * One request when a child is actually picked replaces it.
     *
     * `category_id` is not decoration: the same child answers a different
     * question under a different root, and without it this hands back the union
     * of every root the child sits under. Both callers send the root they have.
     */
    public function catalog(Request $request)
    {
        $childId = (int) $request->get('child_id', 0);
        $rootId = (int) $request->get('category_id', 0);

        if ($childId <= 0) {
            return response()->json(['options' => [], 'groups' => [], 'ungrouped' => [], 'services' => []]);
        }

        // The two screens want the same facts in two shapes: the FILTER on the
        // index wants one flat list, and the EDIT form wants them under their
        // option groups. Both come back — it is one child, so the difference is
        // a few kilobytes, and one endpoint cannot drift from itself.
        $held = $this->scopedOptionIds($childId, $rootId);

        $options = Option::query()
            ->when($this->hasOptionIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
            ->whereIn('id', $held)
            ->orderBy('name_ar')->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'group_id']);

        $groups = OptionGroup::query()
            ->where('is_active', 1)
            ->whereIn('id', $options->pluck('group_id')->filter()->unique())
            ->orderBy('reorder')->orderBy('id')
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($group) => [
                'id' => (int) $group->id,
                'name_ar' => (string) ($group->name_ar ?? ''),
                'name_en' => (string) ($group->name_en ?? ''),
                'options' => $options->where('group_id', $group->id)
                    ->map(fn ($o) => [
                        'id' => (int) $o->id,
                        'name_ar' => (string) ($o->name_ar ?? ''),
                        'name_en' => (string) ($o->name_en ?? ''),
                    ])->values()->all(),
            ])
            ->filter(fn ($group) => $group['options'] !== [])
            ->values();

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->whereIn('id', fn ($sub) => $sub->select('platform_service_id')
                ->from('category_platform_services')
                ->where('child_id', $childId)->where('is_active', 1))
            ->orderBy('name_ar')->orderBy('id')
            ->get(['id', 'name_ar', 'name_en']);

        return response()->json([
            'options' => $options->map(fn ($o) => [
                'id' => (int) $o->id,
                'name_ar' => (string) ($o->name_ar ?? ''),
                'name_en' => (string) ($o->name_en ?? ''),
            ])->values(),
            'groups' => $groups,
            'ungrouped' => $options->whereNull('group_id')
                ->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'name_ar' => (string) ($o->name_ar ?? ''),
                    'name_en' => (string) ($o->name_en ?? ''),
                ])->values(),
            'services' => $services,
        ]);
    }

    public function edit(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $categories = Category::query()
            ->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id', 'asc')
            ->get(['id', 'name_ar', 'name_en']);

        $children = collect();
        if ((int) $user->category_id > 0) {
            $children = CategoryChild::query()
                ->whereHas('parents', function ($q) use ($user) {
                    $q->where('categories.id', (int) $user->category_id);
                })
                ->orderByRaw('COALESCE(reorder, 999999) ASC')
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en', 'reorder']);
        }

        $selectedOptionIds = method_exists($user, 'options')
            ? $user->options()
                ->pluck('options.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all()
            : [];

        $groups = collect();
        $ungroupedOptions = collect();
        $services = collect();
        $selectedServiceIds = [];

        if ((int) $user->category_child_id > 0) {
            $childId = (int) $user->category_child_id;
            $q = '';

            // Under HIS root, not under every root the child happens to sit in.
            $held = $this->scopedOptionIds($childId, (int) $user->category_id);

            $groups = OptionGroup::query()
                ->where('is_active', 1)
                ->with([
                    'options' => function ($query) use ($q, $held) {
                        $query
                            ->when($this->hasOptionIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                            ->whereIn('id', $held)
                            ->when($q !== '', function ($sub) use ($q) {
                                $sub->where(function ($w) use ($q) {
                                    $w->where('name_ar', 'like', "%{$q}%")
                                        ->orWhere('name_en', 'like', "%{$q}%");
                                });
                            })
                            ->orderBy('id', 'asc');
                    }
                ])
                ->orderBy('reorder')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en', 'reorder'])
                ->map(function ($group) {
                    $group->options = collect($group->options)->values();
                    return $group;
                })
                ->filter(fn ($group) => $group->options->isNotEmpty())
                ->values();

            $ungroupedOptions = Option::query()
                ->when($this->hasOptionIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
                ->whereNull('group_id')
                ->whereIn('id', $held)
                ->orderBy('id', 'asc')
                ->get(['id', 'name_ar', 'name_en', 'group_id']);

            $services = PlatformService::query()
                ->where('is_active', 1)
                ->whereIn('id', function ($sub) use ($childId) {
                    $sub->select('platform_service_id')
                        ->from('category_platform_services')
                        ->where('child_id', $childId)
                        ->where('is_active', 1);
                })
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en']);

            $selectedServiceIds = $services
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $childCatalog = Category::query()
            ->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->with(['children' => function ($q) {
                $q->orderByRaw('COALESCE(reorder, 999999) ASC')
                    ->orderBy('name_ar')
                    ->orderBy('id')
                    ->get(['category_children_master.id', 'name_ar', 'name_en', 'reorder']);
            }])
            ->get(['id', 'name_ar', 'name_en'])
            ->mapWithKeys(function ($parent) {
                return [
                    (int) $parent->id => $parent->children
                        ->map(fn ($child) => [
                            'id' => (int) $child->id,
                            'name_ar' => (string) ($child->name_ar ?? ''),
                            'name_en' => (string) ($child->name_en ?? ''),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        /*
         * The edit form used to receive the option map for ALL 337 children —
         * four queries each (groups, their options, the ungrouped ones,
         * services) for 1,399 queries and 3.1 seconds to open ONE user, and
         * 668KB of it on the wire. The form shows one child at a time.
         *
         * `admin.users.catalog` answers for the child actually chosen, in both
         * the flat and the grouped shape, and the form seeds its cache with the
         * child the user already has — so opening the page costs nothing and
         * only CHANGING the specialty asks.
         */

        return view('admin-v2.users.edit', [
            'user' => $user,
            'categories' => $categories,
            'children' => $children,
            'groups' => $groups,
            'ungroupedOptions' => $ungroupedOptions,
            'services' => $services,
            'selectedServiceIds' => $selectedServiceIds,
            'selectedOptionIds' => $selectedOptionIds,
            'childCatalog' => $childCatalog,
        ]);
    }

    public function show(int $id)
    {
        $user = User::withTrashed()
            ->with([
                'latestSubscription',
                'subscriptions',
                'category:id,name_ar,name_en',
                'categoryChild:id,name_ar,name_en',
                'options:id,name_ar,name_en,group_id',
                'wallet:id,user_id,balance,locked_balance,status,total_in,total_out',
                'serviceFeeConsent',
                'activePlatformServices:id,key,name_ar,name_en',
            ])
            ->findOrFail($id);

        $subscriptions = $user->subscriptions()
            ->latest('id')
            ->limit(20)
            ->get();

        $groupedOptions = collect($user->options ?? [])
            ->groupBy(fn ($opt) => $opt->group_id ?: 'ungrouped');

        $childServices = collect();

        if ((int) ($user->category_child_id ?? 0) > 0) {
            $childId = (int) $user->category_child_id;

            $childServices = PlatformService::query()
                ->where('is_active', 1)
                ->whereIn('id', function ($sub) use ($childId) {
                    $sub->select('platform_service_id')
                        ->from('category_platform_services')
                        ->where('child_id', $childId)
                        ->where('is_active', 1);
                })
                ->orderBy('name_ar')
                ->orderBy('id')
                ->get(['id', 'key', 'name_ar', 'name_en']);
        }

        $activeGuarantee = UserGuarantee::query()
            ->with(['purchasedLevel:id,code,name_ar,name_en', 'effectiveLevel:id,code,name_ar,name_en'])
            ->where('user_id', (int) $user->id)
            ->active()
            ->latest('id')
            ->first();

        $recentGuarantees = UserGuarantee::query()
            ->with(['purchasedLevel:id,code,name_ar,name_en', 'effectiveLevel:id,code,name_ar,name_en'])
            ->where('user_id', (int) $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        return view('admin-v2.users.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'groupedOptions' => $groupedOptions,
            'childServices' => $childServices,
            'activeGuarantee' => $activeGuarantee,
            'recentGuarantees' => $recentGuarantees,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:191',
            // A business is searched by customers and 35% of them carried a
            // Latin-only name, invisible to anyone typing Arabic. A client has
            // one box that takes either script — nobody searches for customers.
            'name_en' => 'nullable|string|max:191',
            'email' => 'required|email|max:191|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:15|unique:users,phone,' . $user->id,
            'type' => 'required|in:admin,client,business',

            'category_id' => 'nullable|integer|exists:categories,id',
            'category_child_id' => 'nullable|integer|exists:category_children_master,id',

            'options' => 'nullable|array',
            'options.*' => 'integer|exists:options,id',

            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:platform_services,id',

            'about' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'logo' => 'nullable|string|max:255',
            'cover' => 'nullable|string|max:255',
            'action_code' => 'nullable|string|max:191',
            'code' => 'nullable|string|max:55',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $optionIds = collect($request->input('options', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $serviceIds = collect($request->input('service_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($optionIds)) {
            // The same test the merchant's own profile endpoint applies: an
            // option must be granted to this child UNDER THIS ROOT. Checking
            // the child alone let the panel save a vocabulary the API would
            // then refuse — two doors into one column disagreeing.
            $allowed = $this->scopedOptionIds(
                (int) ($data['category_child_id'] ?? 0),
                (int) ($data['category_id'] ?? 0)
            );

            if (collect($optionIds)->diff($allowed)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'options' => __('بعض الخيارات لا تنتمي للقسم الفرعي المختار.'),
                ]);
            }
        }

        $user->fill($data);

        if (empty($data['password'])) {
            unset($user->password);
        }

        DB::transaction(function () use ($user, $optionIds, $serviceIds) {
            $user->save();

            if (method_exists($user, 'options')) {
                $user->options()->sync($optionIds);
            }

            if (method_exists($user, 'platformServices')) {
                $sync = [];
                foreach ($serviceIds as $serviceId) {
                    $sync[(int) $serviceId] = ['is_active' => 1];
                }
                $user->platformServices()->sync($sync);
            }
        });

        return redirect()
            ->route('admin.users.show', $user->id)
            ->with('success', __('تم تحديث المستخدم بنجاح.'));
    }

    public function destroy(User $user)
    {
        $this->purger->softDelete($user);
        return back()->with('success', __('تم حذف المستخدم.'));
    }

    public function restore(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->purger->restore($user);
        return back()->with('success', __('تم استرجاع المستخدم.'));
    }

    public function forceDelete(int $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $this->purger->forceDelete($user);
        return back()->with('success', __('تم حذف المستخدم نهائيًا.'));
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(__('اختر مستخدمين أولًا.'));
        }
        User::whereIn('id', $ids)->get()->each(fn ($user) => $this->purger->softDelete($user));
        return back()->with('success', __('تم حذف المستخدمين المحددين.'));
    }

    public function bulkRestore(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        User::withTrashed()->whereIn('id', $ids)->get()->each(fn ($user) => $this->purger->restore($user));
        return back()->with('success', __('تم استرجاع المستخدمين المحددين.'));
    }

    public function bulkForceDelete(Request $request)
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->values();
        User::withTrashed()->whereIn('id', $ids)->get()->each(fn ($user) => $this->purger->forceDelete($user));
        return back()->with('success', __('تم حذف المستخدمين المحددين نهائيًا.'));
    }

    public function toggleSuspend(User $user)
    {
        $user->activated_at = $user->activated_at ? null : now();
        $user->save();

        return back()->with('success', __('تم تحديث حالة التفعيل.'));
    }

    /**
     * Ban a user: mark the account, add its identities to the hashed block list
     * (so a re-register is caught), and kill live tokens. Distinct from
     * toggle-suspend, which is a reversible activation flag with no ban list.
     */
    public function ban(Request $request, User $user, \App\Services\BanService $bans)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', __('لا يمكنك إيقاف حسابك.'));
        }

        $bans->ban($user, $data['reason'] ?? null, (int) $request->user()->id);

        return back()->with('success', __('تم إيقاف المستخدم.'));
    }

    public function unban(Request $request, User $user, \App\Services\BanService $bans)
    {
        $bans->unban($user, (int) $request->user()->id);

        return back()->with('success', __('تم رفع الإيقاف.'));
    }

    /**
     * Whether `options.is_active` exists — asked once per request, not per call.
     *
     * `Schema::hasColumn` is a round trip to information_schema, and this is
     * called from six places, one of which used to sit inside a loop over 337
     * children. That single missing cache was 923ms of a 1,858ms page — half
     * the wait, spent asking the database the same unchanging question 337
     * times.
     */
    private ?bool $optionsHaveIsActive = null;

    private function hasOptionIsActiveColumn(): bool
    {
        return $this->optionsHaveIsActive ??= Schema::hasColumn('options', 'is_active');
    }
}
