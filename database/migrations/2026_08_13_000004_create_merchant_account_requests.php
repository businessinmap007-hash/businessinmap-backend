<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's application to be granted a Fawry merchant sub-account. The
 * business submits a request; an admin (who holds the master account's ~10,000
 * sub-accounts) reviews it and, on approval, provisions the business's
 * merchant_payment_accounts row with the codes. One open (pending) request per
 * business at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_account_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('note', 1000)->nullable();          // the business's message
            $table->string('decision_note', 1000)->nullable();  // the admin's note
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_account_requests');
    }
};
