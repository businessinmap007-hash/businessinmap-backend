<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    private function normalizePerPage($perPage): int
    {
        $perPage = (int) $perPage;
        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 50;
    }

    private function keepQs(Request $request): array
    {
        return $request->only([
            'q','user_id','category_id','is_active',
            'per_page','sort','dir'
        ]);
    }

    private function sortWhitelist(): array
    {
        return ['id','created_at','updated_at','is_active','user_id','category_id'];
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, $this->sortWhitelist(), true) ? $sort : 'id';
    }

    private function normalizeDir(string $dir): string
    {
        return $dir === 'asc' ? 'asc' : 'desc';
    }

    public function index(Request $request)
    {
        $q          = trim((string) $request->get('q', ''));
        $userId     = (string) $request->get('user_id', '');
        $categoryId = (string) $request->get('category_id', '');
        $isActive   = (string) $request->get('is_active', ''); // '' | 0 | 1

        $perPage = $this->normalizePerPage($request->get('per_page', 50));
        $sort    = $this->normalizeSort((string) $request->get('sort', 'id'));
        $dir     = $this->normalizeDir((string) $request->get('dir', 'desc'));

        $query = Subscription::query()
            ->with([
                'user:id,name,email,phone,type',
                'category:id,parent_id,name_ar',
            ]);

        // ===== Search =====
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)
                      ->orWhere('user_id', (int) $q)
                      ->orWhere('category_id', (int) $q);
                }

                $w->orWhereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
                });

                $w->orWhereHas('category', function ($c) use ($q) {
                    $c->where('name_ar', 'like', "%{$q}%");
                });
            });
        }

        // ===== Filters =====
        if ($userId !== '' && ctype_digit($userId)) {
            $query->where('user_id', (int) $userId);
        }

        if ($categoryId !== '' && ctype_digit($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($isActive === '0' || $isActive === '1') {
            $query->where('is_active', (int) $isActive);
        }

        // ===== Sort =====
        $query->orderBy($sort, $dir);

        $items = $query->paginate($perPage)->appends($this->keepQs($request));

        $usersForFilter = User::query()
            ->select('id','name','email','type')
            ->orderBy('id','desc')
            ->limit(60)
            ->get();

        $categoriesForFilter = Category::query()
            ->select('id','parent_id','name_ar')
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->orderBy('id','desc')
            ->limit(200)
            ->get();

        return view('admin-v2.subscriptions.index', [
            'items' => $items,

            'q' => $q,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'is_active' => $isActive,

            'perPage' => $perPage,
            'sort' => $sort,
            'dir' => $dir,

            'usersForFilter' => $usersForFilter,
            'categoriesForFilter' => $categoriesForFilter,
        ]);
    }

    public function show(Subscription $subscription)
    {
        $subscription->load([
            'user:id,name,email,phone,type',
            'category:id,parent_id,name_ar',
        ]);

        return view('admin-v2.subscriptions.show', [
            'subscription' => $subscription,
        ]);
    }

    public function edit(Subscription $subscription)
    {
        $subscription->load([
            'user:id,name,email,phone,type',
            'category:id,parent_id,name_ar',
        ]);

        $users = User::query()
            ->select('id','name','email','type')
            ->orderBy('id','desc')
            ->limit(200)
            ->get();

       $categories = Category::query()
            ->select('id','parent_id','name_ar')
            ->where(function ($q) {$q->whereNull('parent_id')->orWhere('parent_id', 0);})
            ->orderBy('id','desc')
            ->limit(500)
            ->get();

        return view('admin-v2.subscriptions.edit', [
            'subscription' => $subscription,
            'users' => $users,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'user_id'     => ['required','integer','exists:users,id'],
            'category_id' => ['nullable','integer','exists:categories,id'],
            'is_active'   => ['nullable','boolean'],
        ]);

        $data['is_active'] = (int)($data['is_active'] ?? 1);

        // ===== Rule 1: Business user => ONLY ONE subscription total =====
        $targetUser = User::query()->select('id','type')->find((int)$data['user_id']);

        if ($targetUser && (string)$targetUser->type === 'business') {
            $hasAnother = Subscription::query()
                ->where('user_id', $targetUser->id)
                ->where('id', '<>', $subscription->id)
                ->exists();

            if ($hasAnother) {
                return back()
                    ->withInput()
                    ->withErrors(['user_id' => 'هذا المستخدم (Business) لا يمكنه الاشتراك في أكثر من قسم.']);
            }
        }

        // ===== Rule 2 (optional hard): prevent duplicate (user_id + category_id) =====
        if (!empty($data['category_id'])) {
            $dup = Subscription::query()
                ->where('user_id', (int)$data['user_id'])
                ->where('category_id', (int)$data['category_id'])
                ->where('id', '<>', $subscription->id)
                ->exists();

            if ($dup) {
                return back()
                    ->withInput()
                    ->withErrors(['category_id' => 'هذا القسم مُسجل بالفعل لنفس المستخدم.']);
            }
        }

        $subscription->update($data);

        return redirect()
            ->route('admin.subscriptions.show', $subscription->id)
            ->with('success', 'تم تحديث السجل');
    }

    public function toggleActive(Subscription $subscription)
    {
        $subscription->is_active = (int)!((int)$subscription->is_active);
        $subscription->save();

        return back()->with('success', 'تم تحديث الحالة');
    }
}