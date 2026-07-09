<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the notification-delivery infrastructure that the code already
 * expects (NotificationChannelRule + NotificationDeliveryLog + the dispatcher)
 * but whose tables were never migrated. Guarded with hasTable so it is safe to
 * run on any environment where these were created by hand.
 *
 * user_push_tokens already exists (registration: POST api/v2/push-tokens) and is
 * the live device-token store; app_notifications already exists too — so neither
 * is (re)created here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_channel_rules')) {
            Schema::create('notification_channel_rules', function (Blueprint $table) {
                $table->id();
                $table->string('event_key')->unique();
                $table->string('name_ar')->nullable();
                $table->string('name_en')->nullable();
                $table->string('type')->default('system');
                $table->string('priority')->default('normal');
                $table->boolean('is_active')->default(true);
                $table->boolean('in_app_enabled')->default(true);
                $table->boolean('realtime_enabled')->default(false);
                $table->boolean('firebase_enabled')->default(false);
                $table->boolean('fallback_to_firebase')->default(false);
                $table->boolean('requires_operator_session')->default(false);
                $table->boolean('critical')->default(false);
                $table->integer('escalation_minutes')->default(0);
                $table->string('sound_key')->nullable();
                $table->json('rules')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('notification_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event_key')->nullable()->index();
                $table->string('channel');
                $table->string('status');
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('failed_reason')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['channel', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('notification_channel_rules');
    }
};
