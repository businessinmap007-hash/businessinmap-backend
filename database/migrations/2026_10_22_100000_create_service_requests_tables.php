<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The REQUESTS-AND-OFFERS core: a customer posts a need, the businesses of the right trade near him answer with an
 * offer (a price, a note, how soon), and he picks one — which then becomes the platform's ordinary operation.
 * A limousine ride is the first kind; a quote from a craftsman, a workshop or a factory is the next. One table pair
 * serves them all: what differs is the kind's own `params` and who may answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->index();
            $table->unsignedBigInteger('requester_id')->index();
            // who may answer: the trade, and where (a ride: the origin's governorate)
            $table->unsignedBigInteger('category_child_id')->nullable()->index();
            $table->unsignedBigInteger('governorate_id')->nullable()->index();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('title', 190)->nullable();
            $table->text('details')->nullable();
            $table->json('params')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('accepted_offer_id')->nullable();
            // the operation the accepted offer became (a booking)
            $table->string('operation_type', 80)->nullable();
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('service_request_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('business_id')->index();
            $table->decimal('price', 10, 2);
            // «أقبل سعرك»: the business takes the customer's own price as it stands
            $table->boolean('accepts_budget')->default(false);
            $table->string('message', 300)->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();

            $table->unique(['service_request_id', 'business_id']);
            $table->foreign('service_request_id')->references('id')->on('service_requests')->cascadeOnDelete();
            $table->foreign('business_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_offers');
        Schema::dropIfExists('service_requests');
    }
};
