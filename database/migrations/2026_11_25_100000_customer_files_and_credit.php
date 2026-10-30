<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «ملفات العملاء ودفتر الآجل» (desktop suite phase 3, owner approved 2026-10-30).
 *
 * - `crm_customers`: one file per (business, customer). An app customer's file opens by itself from his first order
 *   (`user_id`); one outside the app is typed in by the business (name + phone). `balance` is the running sum of the
 *   file's moves, kept beside them so a list sorts by it — the moves stay the truth.
 * - `crm_account_moves`: the credit book, append-only like the stock ledger. + raises what the customer owes (a sale on
 *   account, an app order charged to the account, an opening balance), − lowers it (a payment once BOTH sides confirmed
 *   it, a return). A mistake is corrected by an opposite move with its reason, never by an edit.
 * - `crm_payments`: a credit payment counts only when both sides confirm it (owner: «تؤكد من الطرفين»). For a transfer
 *   (a wallet outside the app, InstaPay, a bank) each side types the transaction reference from his own app, blind, and
 *   the two must match. A customer outside the app cannot confirm: the business's word + a signed printed receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('name', 120);
            $t->string('phone', 30)->nullable();
            $t->string('address', 255)->nullable();
            $t->json('tags')->nullable();
            $t->text('notes')->nullable(); // the business's own, never shown to the customer
            $t->decimal('credit_limit', 14, 2)->nullable(); // null = no limit set
            $t->unsignedSmallInteger('payment_terms_days')->nullable();
            $t->decimal('balance', 14, 2)->default(0); // + the customer owes, − the business owes him
            $t->timestamp('last_reminded_at')->nullable();
            $t->timestamp('anonymized_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'user_id']);
            $t->index(['business_id', 'phone']);
        });

        Schema::create('crm_payments', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('customer_id')->index();
            $t->unsignedInteger('number')->nullable(); // the receipt number, given once confirmed
            $t->decimal('amount', 14, 2);
            $t->string('method', 16); // cash | wallet | instapay | bank
            $t->string('started_by', 10); // business | customer
            $t->string('status', 20)->default('pending'); // pending | confirmed | mismatch | rejected | cancelled
            $t->string('business_ref', 64)->nullable(); // normalized; never shown to the customer before the match
            $t->string('customer_ref', 64)->nullable(); // normalized; never shown to the business before the match
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('business_confirmed_at')->nullable();
            $t->unsignedBigInteger('business_confirmed_by')->nullable();
            $t->timestamp('customer_confirmed_at')->nullable();
            $t->boolean('business_only')->default(false); // a customer outside the app: «مؤكدة من المحل فقط»
            $t->string('rejected_by', 10)->nullable();
            $t->string('reason', 255)->nullable();
            $t->string('note', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->uuid('device_uuid')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('crm_account_moves', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('business_id')->index();
            $t->unsignedBigInteger('customer_id')->index();
            $t->string('kind', 16); // opening | sale | order | payment | return | adjustment
            $t->decimal('amount', 14, 2); // signed
            $t->string('source_type', 20)->nullable(); // pos_sale | order | crm_payment | crm_move
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('note', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->uuid('device_uuid')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['source_type', 'source_id']);
            $t->index(['customer_id', 'occurred_at']);
        });

        Schema::table('inv_pos_sales', function (Blueprint $t) {
            $t->unsignedBigInteger('customer_id')->nullable()->after('warehouse_id')->index();
            $t->decimal('on_account', 14, 2)->default(0)->after('paid');
            $t->unsignedBigInteger('credit_override_by')->nullable()->after('on_account');
        });

        // Every app customer who already ordered from a business gets his file there.
        $now = now();
        DB::table('orders')
            ->whereNotNull('user_id')
            ->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->select('business_id', 'user_id', DB::raw('MIN(created_at) as first_at'))
            ->groupBy('business_id', 'user_id')
            ->orderBy('business_id')
            ->chunk(500, function ($rows) use ($now) {
                $names = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');
                $insert = [];
                foreach ($rows as $r) {
                    if (! isset($names[$r->user_id])) {
                        continue;
                    }
                    $insert[] = [
                        'business_id' => $r->business_id,
                        'user_id' => $r->user_id,
                        'name' => mb_substr((string) ($names[$r->user_id] ?: '—'), 0, 120),
                        'created_at' => $r->first_at ?? $now,
                        'updated_at' => $now,
                    ];
                }
                if ($insert !== []) {
                    DB::table('crm_customers')->insertOrIgnore($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inv_pos_sales', function (Blueprint $t) {
            $t->dropColumn(['customer_id', 'on_account', 'credit_override_by']);
        });
        Schema::dropIfExists('crm_account_moves');
        Schema::dropIfExists('crm_payments');
        Schema::dropIfExists('crm_customers');
    }
};
