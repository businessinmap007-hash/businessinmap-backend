<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs are `posts` with type='job' (the v1 shape) — this just gives that
 * shape the fields a job posting actually needs, so v2 can build a real
 * feature on it instead of the broken v1 Job/Company/Product classes that
 * never matched this schema. All nullable: ordinary posts (type='post')
 * never set them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')
                ->constrained('categories')->nullOnDelete();
            $table->foreignId('category_child_id')->nullable()->after('category_id')
                ->constrained('category_children_master')->nullOnDelete();
            // Free text on purpose — a job's pay is often "يحدد بعد المقابلة"
            // (decided after the interview), not always a number.
            $table->string('salary', 191)->nullable()->after('body');
            $table->text('requirements')->nullable()->after('salary');
            // When applications/interviews open. expire_at (already on this
            // table) is when the ad itself closes.
            $table->timestamp('interview_starts_at')->nullable()->after('requirements');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_child_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['salary', 'requirements', 'interview_starts_at']);
        });
    }
};
