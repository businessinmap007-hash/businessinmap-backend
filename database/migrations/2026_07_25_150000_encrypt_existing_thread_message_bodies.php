<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypt any thread_message bodies already stored in plaintext, so that once
 * the model's `encrypted` cast is in force EVERY row is ciphertext and reads
 * decrypt cleanly. Idempotent: a body that already decrypts is left alone, so
 * re-running never double-encrypts.
 *
 * No column change — an encrypted 5,000-char message fits comfortably in TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('thread_messages')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                if ($row->body === null || $row->body === '') {
                    continue;
                }

                // Already ciphertext? Then decrypt succeeds — skip it.
                try {
                    Crypt::decryptString($row->body);
                    continue;
                } catch (\Throwable $e) {
                    // Not encrypted yet — encrypt it below.
                }

                DB::table('thread_messages')
                    ->where('id', $row->id)
                    ->update(['body' => Crypt::encryptString($row->body)]);
            }
        });
    }

    public function down(): void
    {
        // One-way: we do not return conversation text to plaintext at rest.
    }
};
