<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        DB::transaction(function () {

            // 1️⃣ جلب كل notes الفريدة
            $notes = DB::table('wallet_transactions')
                ->whereNotNull('note')
                ->where('note', '<>', '')
                ->select('note')
                ->distinct()
                ->pluck('note');

            foreach ($notes as $noteText) {

                // 2️⃣ إنشاء template لو غير موجود
                $templateId = DB::table('wallet_note_templates')
                    ->where('title', $noteText)
                    ->value('id');

                if (!$templateId) {
                    $templateId = DB::table('wallet_note_templates')->insertGetId([
                        'title'      => $noteText,
                        'text'       => $noteText,
                        'is_active'  => 1,
                        'sort'       => 0,
                        'created_at'=> now(),
                        'updated_at'=> now(),
                    ]);
                }

                // 3️⃣ ربط كل المعاملات
                DB::table('wallet_transactions')
                    ->where('note', $noteText)
                    ->whereNull('note_id')
                    ->update([
                        'note_id' => $templateId,
                    ]);
            }
        });
    }

    public function down(): void
    {
        // لا rollback — تحويل بيانات مقصود
    }
};