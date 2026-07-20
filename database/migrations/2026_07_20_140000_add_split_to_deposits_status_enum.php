<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A split ruling is a third outcome, not a released or a refunded deposit: part
 * of the escrow goes to the client and part to the business. Recording it as
 * either of the existing two would misreport where the money went, so give it
 * its own final status.
 */
return new class extends Migration
{
    private const WITH_SPLIT = "ENUM('frozen','released','refunded','dispute','split')";

    private const WITHOUT_SPLIT = "ENUM('frozen','released','refunded','dispute')";

    public function up(): void
    {
        if (Schema::hasColumn('deposits', 'status')) {
            DB::statement('ALTER TABLE `deposits` MODIFY `status` ' . self::WITH_SPLIT . " NOT NULL DEFAULT 'frozen'");
        }
    }

    public function down(): void
    {
        // Only revert when no deposit was actually split, to avoid data loss.
        if (Schema::hasColumn('deposits', 'status')
            && DB::table('deposits')->where('status', 'split')->doesntExist()) {
            DB::statement('ALTER TABLE `deposits` MODIFY `status` ' . self::WITHOUT_SPLIT . " NOT NULL DEFAULT 'frozen'");
        }
    }
};
