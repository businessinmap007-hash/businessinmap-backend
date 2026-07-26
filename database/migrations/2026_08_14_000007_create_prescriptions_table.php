<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A medical prescription (روشتة): a doctor (a clinic business) writes it for a
 * patient (a client). The patient may then send it to a pharmacy (a business)
 * to dispense the medicine and deliver it or hand it over for pickup.
 *
 * It is medical PII — readable only by the three parties (doctor, patient,
 * pharmacy). The item lines live in prescription_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id')->index();   // the clinic (business)
            $table->unsignedBigInteger('patient_id')->index();  // the client
            $table->unsignedBigInteger('pharmacy_id')->nullable()->index(); // chosen on send

            // issued | sent_to_pharmacy | preparing | ready | dispensed | cancelled
            $table->string('status', 20)->default('issued')->index();

            // delivery | pickup — chosen by the patient when sending.
            $table->string('fulfillment_type', 12)->nullable();

            $table->string('diagnosis')->nullable();
            $table->text('notes')->nullable();

            // A one-off delivery address the patient gives at send time.
            $table->string('delivery_address')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('dispensed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
