<?php

use App\Services\Catalog\MenuShapeCurator;
use Illuminate\Database\Migrations\Migration;

/**
 * «نظام التصنيع» (إنتاج جاهز من المخزون، تصنيع حسب الطلب…) is a factory's word. A cosmetics shop and
 * a ready-to-wear shop (#73, #60, #168) kept it as chips on every item even after it stopped being
 * their price axis — so the trade stops offering it at all, and the decision is recorded so no
 * seeder grants it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new MenuShapeCurator)->unlink(MenuShapeCurator::MANUFACTURING, [73, 60, 168]);
    }

    public function down(): void
    {
        // Recorded decision; reversible from «مكونات الخدمة» / the child's options.
    }
};
