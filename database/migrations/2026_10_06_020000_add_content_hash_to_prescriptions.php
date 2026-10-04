<?php

use App\Models\Prescription;
use App\Services\Prescriptions\PrescriptionContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fingerprint of what a doctor wrote, so a copy of the prescription kept on the patient's phone can be
 * proved authentic at the pharmacy counter (see PrescriptionContent). Existing doctor prescriptions get theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('notes');
        });

        $content = new PrescriptionContent;
        Prescription::query()->whereNull('content_hash')->where('origin', Prescription::ORIGIN_DOCTOR)->with('items')->chunkById(200, function ($rows) use ($content) {
            foreach ($rows as $p) {
                $content->stamp($p);
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
