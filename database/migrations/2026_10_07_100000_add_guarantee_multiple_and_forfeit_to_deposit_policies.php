<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_deposit_policies', function (Blueprint $table) {
            // «يجمّد الضمان حتى لو أضعاف قيمة حجز اليوم»: how many times the first day's value the customer's
            // guarantee coverage must reach for the guarantee to stand in for the cash deposit. 0 = just the deposit itself (no multiple asked).
            $table->decimal('guarantee_multiple', 5, 2)->default(0)->after('guarantee_hybrid_extra_percent');

            // «يخصم لصالح التاجر بالكامل»: the term the business declares up front — if the customer breaks the booking
            // the whole deposit goes to the business. Shown to the customer at booking and read by the arbitrator.
            $table->boolean('forfeit_to_business')->default(false)->after('guarantee_multiple');
        });
    }

    public function down(): void
    {
        Schema::table('business_deposit_policies', function (Blueprint $table) {
            $table->dropColumn(['guarantee_multiple', 'forfeit_to_business']);
        });
    }
};
