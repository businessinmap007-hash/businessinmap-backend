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
use App\Services\UserPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private UserPurgeService $purger) {}

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
                ->whereIn('id', function ($sub) use ($categoryChildId) {
                    $sub->select('option_id')
                        ->from('category_child_option')
                        ->where('child_id', $categoryChildId);
                })
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

        $optionCatalog = CategoryChild::query()
            ->get(['id'])
            ->mapWithKeys(function ($child) {
                $childId = (int) $child->id;

                $options = Option::query()
                    ->when($this->hasOptionIsActiveColumn(), fn ($q) => $q->where('is_active', 1))
                    ->whereIn('id', function ($sub) use ($childId) {
                        $sub->select('option_id')
                            ->from('category_child_option')
                            ->where('child_id', $childId);
                    })
                    ->orderBy('name_ar')
                    ->orderBy('id')
                    ->get(['id', 'name_ar', 'name_en'])
                    ->map(fn ($opt) => [
                        'id' => (int) $opt->id,
                        'name_ar' => (string) ($opt->name_ar ?? ''),
                        'name_en' => (string) ($opt->name_en ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    $childId => $options,
                ];
            })
            ->all();

        $serviceCatalog = CategoryChild::query()
            ->get(['id'])
            ->mapWithKeys(function ($child) {
                $childId = (int) $child->id;

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
                    ->get(['id', 'name_ar', 'name_en'])
                    ->map(fn ($srv) => [
                        'id' => (int) $srv->id,
                        'name_ar' => (string) ($srv->name_ar ?? ''),
                        'name_en' => (string) ($srv->name_en ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    $childId => $services,
                ];
            })
            ->all();

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
            'optionCatalog' => $optionCatalog,
            'serviceCatalog' => $serviceCatalog,
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

            $groups = OptionGroup::query()
                ->where('is_active', 1)
                ->with([
                    'options' => function ($query) use ($q, $childId) {
                        $query
                            ->when($this->hasOptionIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                            ->whereIn('id', function ($sub) use ($childId) {
                                $sub->select('option_id')
                                    ->from('category_child_option')
                                    ->where('child_id', $childId);
                            })
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
                ->whereIn('id', function ($sub) use ($childId) {
                    $sub->select('option_id')
                        ->from('category_child_option')
                        ->where('child_id', $childId);
                })
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

        $optionCatalog = CategoryChild::query()
            ->get(['id'])
            ->mapWithKeys(function ($child) {
                $childId = (int) $child->id;

                $groups = OptionGroup::query()
                    ->where('is_active', 1)
                    ->with([
                        'options' => function ($query) use ($childId) {
                            $query
                                ->when($this->hasOptionIsActiveColumn(), fn ($sub) => $sub->where('is_active', 1))
                                ->whereIn('id', function ($sub) use ($childId) {
                                    $sub->select('option_id')
                                        ->from('category_child_option')
                                        ->where('child_id', $childId);
                                })
                                ->orderBy('id', 'asc');
                        }
                    ])
                    ->orderBy('reorder')
                    ->orderBy('id')
                    ->get(['id', 'name_ar', 'name_en', 'reorder'])
                    ->map(function ($group) {
                        return [
                            'id' => (int) $group->id,
                            'name_ar' => (string) ($group->name_ar ?? ''),
                            'name_en' => (string) ($group->name_en ?? ''),
                            'options' => collect($group->options)
                                ->map(fn ($opt) => [
                                    'id' => (int) $opt->id,
                                    'name_ar' => (string) ($opt->name_ar ?? ''),
                                    'name_en' => (string) ($opt->name_en ?? ''),
                                ])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->filter(fn ($group) => !empty($group['options']))
                    ->values()
                    ->all();

                $ungrouped = Option::query()
                    ->when($this->hasOptionIsActiveColumn(), fn ($query) => $query->where('is_active', 1))
                    ->whereNull('group_id')
                    ->whereIn('id', function ($sub) use ($childId) {
                        $sub->select('option_id')
                            ->from('category_child_option')
                            ->where('child_id', $childId);
                    })
                    ->orderBy('id', 'asc')
                    ->get(['id', 'name_ar', 'name_en'])
                    ->map(fn ($opt) => [
                        'id' => (int) $opt->id,
                        'name_ar' => (string) ($opt->name_ar ?? ''),
                        'name_en' => (string) ($opt->name_en ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    $childId => [
                        'groups' => $groups,
                        'ungrouped' => $ungrouped,
                    ],
                ];
            })
            ->all();

        $serviceCatalog = CategoryChild::query()
            ->get(['id'])
            ->mapWithKeys(function ($child) {
                $childId = (int) $child->id;

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
                    ->get(['id', 'name_ar', 'name_en'])
                    ->map(fn ($srv) => [
                        'id' => (int) $srv->id,
                        'name_ar' => (string) ($srv->name_ar ?? ''),
                        'name_en' => (string) ($srv->name_en ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    $childId => $services,
                ];
            })
            ->all();

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
            'optionCatalog' => $optionCatalog,
            'serviceCatalog' => $serviceCatalog,
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
                ->get(['id', 'name_ar', 'name_en']);
        }

        return view('admin-v2.users.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'groupedOptions' => $groupedOptions,
            'childServices' => $childServices,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:191',
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

        if (($data['type'] ?? '') === 'business') {
            $this->validateBusinessClassification(
                (int) ($data['category_id'] ?? 0),
                (int) ($data['category_child_id'] ?? 0),
                $optionIds,
                $serviceIds
            );
        } else {
            $data['category_id'] = null;
            $data['category_child_id'] = null;
            $optionIds = [];
            $serviceIds = [];
        }

        unset($data['service_ids']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        DB::transaction(function () use ($user, $data, $optionIds, $serviceIds) {
            $user->fill($data);
            $user->save();

            if (method_exists($user, 'options')) {
                $user->options()->sync($optionIds);
            }
        });
        if (method_exists($user, 'platformServices')) {
            $user->platformServices()->sync(
                collect($serviceIds)->mapWithKeys(fn($id) => [
                    $id => ['is_active' => 1]
                ])->all()
            );
        }
        return redirect()
            ->route('admin.users.edit', $user->id)
            ->with('success', 'تم تحديث بيانات المستخدم بنجاح');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي.']);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return back()->withErrors(['error' => 'لا يمكن حذف حساب Admin.']);
        }

        DB::transaction(function () use ($user) {
            $user->subscriptions()->delete();
            $user->delete();
        });

        return back()->with('success', 'تم حذف المستخدم (Soft) بنجاح');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));

        if (empty($ids)) {
            return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);
        }

        $ids = array_values(array_diff($ids, [auth()->id()]));
        if (empty($ids)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي.']);
        }

        $adminIds = User::whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')
            ->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابات Admin ضمن الاختيار.']);
        }

        DB::transaction(function () use ($ids) {
            Subscription::whereIn('user_id', $ids)->delete();
            User::whereIn('id', $ids)->delete();
        });

        return back()->with('success', 'تم حذف المستخدمين المحددين (Soft) بنجاح');
    }

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

    public function bulkRestore(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));
        if (empty($ids)) {
            return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);
        }

        $ids = array_values(array_diff($ids, [auth()->id()]));

        $adminIds = User::withTrashed()->whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')
            ->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن استرجاع حسابات Admin ضمن الاختيار.']);
        }

        User::onlyTrashed()->whereIn('id', $ids)->restore();

        return back()->with('success', 'تم استرجاع المستخدمين المحددين بنجاح');
    }

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
            $this->purger->purge($user->id);
            $user->forceDelete();
        });

        return back()->with('success', 'تم حذف المستخدم نهائيًا مع كل علاقاته.');
    }

    public function bulkForceDelete(Request $request)
    {
        $ids = $this->cleanIds($request->input('ids', []));
        if (empty($ids)) {
            return back()->withErrors(['error' => 'اختر مستخدمين أولاً.']);
        }

        $ids = array_values(array_diff($ids, [auth()->id()]));
        if (empty($ids)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابك الحالي نهائيًا ضمن الاختيار.']);
        }

        $adminIds = User::withTrashed()->whereIn('id', $ids)->get()
            ->filter(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin())
            ->pluck('id')
            ->all();

        if (!empty($adminIds)) {
            return back()->withErrors(['error' => 'لا يمكن حذف حسابات Admin نهائيًا ضمن الاختيار.']);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->purger->purge((int) $id);
            }

            User::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        return back()->with('success', 'تم حذف المستخدمين نهائيًا مع كل علاقاتهم.');
    }

    protected function validateBusinessClassification(int $categoryId, int $childId, array $optionIds, array $serviceIds = []): void
    {
        $errors = [];

        if ($categoryId <= 0) {
            $errors['category_id'] = 'التصنيف الرئيسي مطلوب للحساب التجاري.';
        }

        if ($childId <= 0) {
            $errors['category_child_id'] = 'القسم الفرعي مطلوب للحساب التجاري.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $validChild = CategoryChild::query()
            ->where('id', $childId)
            ->whereHas('parents', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            })
            ->exists();

        if (!$validChild) {
            throw ValidationException::withMessages([
                'category_child_id' => 'القسم الفرعي لا يتبع التصنيف الرئيسي المختار.',
            ]);
        }

        if (!empty($optionIds)) {
            $validOptionIds = CategoryChildOption::query()
                ->where('child_id', $childId)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $invalidOptionIds = array_values(array_diff($optionIds, $validOptionIds));

            if (!empty($invalidOptionIds)) {
                throw ValidationException::withMessages([
                    'options' => 'بعض الخيارات المختارة لا تتبع القسم الفرعي المختار.',
                ]);
            }
        }

        if (!empty($serviceIds)) {
            $validServiceIds = DB::table('category_platform_services')
                ->where('child_id', $childId)
                ->where('is_active', 1)
                ->pluck('platform_service_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $invalidServiceIds = array_values(array_diff($serviceIds, $validServiceIds));

            if (!empty($invalidServiceIds)) {
                throw ValidationException::withMessages([
                    'service_ids' => 'بعض الخدمات المختارة لا تتبع القسم الفرعي المختار.',
                ]);
            }
        }
    }

    protected function hasOptionIsActiveColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('options', 'is_active');
        }

        return $hasColumn;
    }

    private function cleanIds($ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn ($v) => $v > 0);

        return array_values(array_unique($ids));
    }
}