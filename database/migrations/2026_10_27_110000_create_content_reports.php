<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE report (owner, 2026-10-27): any account can report any post or any owner listing, and any moderator account in the
 * app gets a tab to see what was reported and decide.
 *
 * One table for every kind of content — `reportable_type` is a short key ('post', 'owner_listing'), not a class name, so a
 * new kind of content is one entry in ContentReportService::TYPES. A person counts once per item (the unique key); the
 * reports an owner listing already collected in `owner_listing_reports` are carried over so none are lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_reports')) {
            Schema::create('content_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reporter_id');
                $table->string('reportable_type', 30);
                $table->unsignedBigInteger('reportable_id');
                $table->string('reason', 30);
                $table->string('details', 500)->nullable();
                $table->string('status', 12)->default('open');   // open | dismissed | actioned
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->string('action', 20)->nullable();         // removed
                $table->timestamps();

                $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'cr_one_per_person');
                $table->index(['status', 'reportable_type', 'reportable_id'], 'cr_queue');
            });
        }

        if (Schema::hasTable('owner_listing_reports')) {
            $now = now();

            DB::table('owner_listing_reports')->orderBy('id')->each(function ($r) use ($now) {
                DB::table('content_reports')->insertOrIgnore([
                    'reporter_id' => $r->reporter_id,
                    'reportable_type' => 'owner_listing',
                    'reportable_id' => $r->owner_listing_id,
                    'reason' => 'other',
                    'details' => $r->reason,
                    'status' => 'open',
                    'created_at' => $r->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
