<?php

namespace App\Services\Posts;

use App\Models\BookableItem;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that knows what a post may be linked to.
 *
 * Three jobs, kept together on purpose because getting any of them wrong
 * separately is what makes this kind of feature leak: what a given business is
 * allowed to offer, whether a chosen subject is really theirs, and where the
 * app should go when the reader taps it.
 *
 * Short keys, not class names: a global morph map would break the existing
 * morph columns, which store fully-qualified names (see the migration).
 */
final class PostSubjectService
{
    public const TYPE_MENU_ITEM = 'menu_item';
    public const TYPE_BOOKABLE_ITEM = 'bookable_item';

    /** Short key => model class. */
    private const TYPES = [
        self::TYPE_MENU_ITEM => MenuItem::class,
        self::TYPE_BOOKABLE_ITEM => BookableItem::class,
    ];

    /** Resolved subjects for this request, keyed "type:id". */
    private array $cache = [];

    public function types(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * Load every subject a page of posts points at, one query per type.
     *
     * Without this the feed pays a query per linked post — the exact N+1 this
     * controller was rebuilt to get rid of.
     *
     * @param iterable<Post> $posts
     */
    public function preload(iterable $posts): void
    {
        $wanted = [];

        foreach ($posts as $post) {
            $type = (string) ($post->subject_type ?? '');
            $id = (int) ($post->subject_id ?? 0);

            if ($type !== '' && $id > 0 && isset(self::TYPES[$type]) && ! isset($this->cache[$type . ':' . $id])) {
                $wanted[$type][] = $id;
            }
        }

        foreach ($wanted as $type => $ids) {
            $models = self::TYPES[$type]::query()->findMany(array_unique($ids))->keyBy('id');

            foreach (array_unique($ids) as $id) {
                // Misses are cached too: a deleted item must not be re-queried
                // once per post that still points at it.
                $this->cache[$type . ':' . $id] = $models->get($id);
            }
        }
    }

    private function find(string $type, int $id): ?Model
    {
        $key = $type . ':' . $id;

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = self::TYPES[$type]::query()->find($id);
        }

        return $this->cache[$key];
    }

    /**
     * What this business can advertise, derived from what it actually owns.
     *
     * NOT from `user_platform_service`: that table holds 3 rows platform-wide
     * and no menu subscription at all, so gating on it would show every business
     * an empty picker. Owning a menu item is the honest signal that a business
     * runs a menu.
     *
     * @return array<int, array{type: string, label: string, groups: array}>
     */
    public function optionsFor(User $business): array
    {
        $businessId = (int) $business->id;
        $out = [];

        $menu = $this->menuOptions($businessId);
        if ($menu !== []) {
            $out[] = ['type' => self::TYPE_MENU_ITEM, 'label' => __('المنيو'), 'groups' => $menu];
        }

        $bookables = $this->bookableOptions($businessId);
        if ($bookables !== []) {
            $out[] = ['type' => self::TYPE_BOOKABLE_ITEM, 'label' => __('العناصر القابلة للحجز'), 'groups' => $bookables];
        }

        return $out;
    }

    /** Menu items grouped by section — the picker the business expects. */
    private function menuOptions(int $businessId): array
    {
        $items = MenuItem::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'menu_section_id', 'name_ar', 'name_en', 'base_price']);

        if ($items->isEmpty()) {
            return [];
        }

        $sections = MenuSection::query()
            ->where('business_id', $businessId)
            ->get(['id', 'name_ar', 'name_en'])
            ->keyBy('id');

        return $items
            ->groupBy(fn (MenuItem $i) => (int) ($i->menu_section_id ?? 0))
            ->map(fn ($group, $sectionId) => [
                'id' => $sectionId ?: null,
                'label' => $sectionId && $sections->has($sectionId)
                    ? (string) $sections[$sectionId]->loc('name')
                    : __('أخرى'),
                'items' => $group->map(fn (MenuItem $i) => [
                    'id' => (int) $i->id,
                    'name' => (string) $i->loc('name'),
                    'price' => (float) $i->base_price,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function bookableOptions(int $businessId): array
    {
        $items = BookableItem::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get(['id', 'title']);

        if ($items->isEmpty()) {
            return [];
        }

        return [[
            'id' => null,
            'label' => __('العناصر القابلة للحجز'),
            'items' => $items->map(fn (BookableItem $i) => [
                'id' => (int) $i->id,
                'name' => (string) $i->title,
                'price' => null,
            ])->values()->all(),
        ]];
    }

    /**
     * The subject, only if this business owns it.
     *
     * Ownership is the whole point of this method: without it a business could
     * publish a post pointing at a competitor's item and route the orders there.
     */
    public function resolveOwned(string $type, int $id, int $businessId): ?Model
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null || $id <= 0) {
            return null;
        }

        return $class::query()
            ->where('business_id', $businessId)
            ->find($id);
    }

    /**
     * What the reader sees and where tapping it goes, or null when unlinked.
     *
     * @return array{type: string, id: int, name: string, action_type: string, action_url: string}|null
     */
    public function present(Post $post): ?array
    {
        $type = (string) ($post->subject_type ?? '');
        $id = (int) ($post->subject_id ?? 0);

        if ($type === '' || $id <= 0 || ! isset(self::TYPES[$type])) {
            return null;
        }

        $model = $this->find($type, $id);

        if (! $model) {
            // The item was deleted after the post was written. Say nothing
            // rather than render a tap target that leads nowhere.
            return null;
        }

        return [
            'type' => $type,
            'id' => $id,
            'name' => $this->nameOf($type, $model),
            'action_type' => $type === self::TYPE_MENU_ITEM ? 'open_menu_item' : 'open_bookable_item',
            'action_url' => $this->urlOf($type, $model),
        ];
    }

    /** The subject's name in the reader's language, for a post with no title. */
    public function nameFor(Post $post): ?string
    {
        return $this->present($post)['name'] ?? null;
    }

    private function nameOf(string $type, Model $model): string
    {
        return $type === self::TYPE_MENU_ITEM
            ? (string) $model->loc('name')
            : (string) $model->title;
    }

    /** Path-only deep links, matching the app's existing `/jobs/12` shape. */
    private function urlOf(string $type, Model $model): string
    {
        return $type === self::TYPE_MENU_ITEM
            ? '/menu/' . (int) $model->business_id . '/items/' . (int) $model->id
            : '/bookable-items/' . (int) $model->id;
    }
}
