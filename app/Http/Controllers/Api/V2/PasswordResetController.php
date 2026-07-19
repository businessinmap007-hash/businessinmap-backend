<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * v2 password reset — email + short code flow (replaces the insecure v1
 * Forgot/ResetPasswordController). Improvements over v1:
 *   - the code is HASHED at rest (dedicated password_reset_codes table), never
 *     stored plaintext on users.action_code and never returned in the response;
 *   - reset actually verifies the code (v1 reset ignored it entirely);
 *   - codes expire and lock after too many wrong attempts;
 *   - "forgot" gives one generic answer whether or not the email exists
 *     (no account enumeration).
 * Request throttling is applied at the route (throttle middleware).
 */
final class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    /** POST /api/v2/auth/password/forgot — request a reset code by email. */
    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()->where('email', $data['email'])->first();
        if ($user) {
            $code = $this->issueCode($data['email']);
            $this->sendCode($user, $code);
        }

        // Same answer either way — do not reveal whether the account exists.
        return response()->json([
            'success' => true,
            'message' => __('إن كان هناك حساب مرتبط بهذا البريد فقد أُرسل إليه رمز الاستعادة.'),
        ]);
    }

    /** POST /api/v2/auth/password/resend — alias of forgot. */
    public function resend(Request $request)
    {
        return $this->forgot($request);
    }

    /** POST /api/v2/auth/password/verify — check a code without consuming it. */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ]);

        $this->assertValidCode($data['email'], $data['code']); // throws 422 on failure

        return response()->json(['success' => true, 'data' => ['valid' => true]]);
    }

    /** POST /api/v2/auth/password/reset — set a new password with a valid code. */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $this->assertValidCode($data['email'], $data['code']);

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            // Code matched but user vanished — treat as invalid.
            throw ValidationException::withMessages(['code' => [__('رمز الاستعادة غير صالح.')]]);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        // Consume the code and drop every existing session token.
        DB::table('password_reset_codes')->where('email', $data['email'])->delete();
        $user->tokens()->delete();

        return response()->json(['success' => true, 'message' => __('تم تغيير كلمة المرور بنجاح.')]);
    }

    // ─────────────────────────── internals ───────────────────────────

    /** Generate, store (hashed), and return a fresh 6-digit code. */
    private function issueCode(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $email],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => Carbon::now()->addMinutes(self::CODE_TTL_MINUTES),
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]
        );

        return $code;
    }

    /**
     * Verify email+code against the stored hash. Throws a 422 ValidationException
     * on any failure (missing / expired / locked / wrong), counting attempts.
     */
    private function assertValidCode(string $email, string $code): void
    {
        $row = DB::table('password_reset_codes')->where('email', $email)->first();

        if (! $row) {
            throw ValidationException::withMessages(['code' => [__('رمز الاستعادة غير صالح أو منتهي.')]]);
        }

        if (Carbon::parse($row->expires_at)->isPast()) {
            DB::table('password_reset_codes')->where('email', $email)->delete();
            throw ValidationException::withMessages(['code' => [__('انتهت صلاحية الرمز. اطلب رمزاً جديداً.')]]);
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => [__('تم تجاوز عدد المحاولات. اطلب رمزاً جديداً.')]]);
        }

        if (! Hash::check($code, $row->code_hash)) {
            DB::table('password_reset_codes')->where('email', $email)->increment('attempts');
            throw ValidationException::withMessages(['code' => [__('رمز الاستعادة غير صحيح.')]]);
        }
    }

    /**
     * Email the code. Best-effort — a mail-transport failure must NOT 500 the
     * forgot request (nor reveal that the account exists), but it IS logged as a
     * warning with context so a broken production mailer is observable.
     */
    private function sendCode(User $user, string $code): void
    {
        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($code));
        } catch (\Throwable $e) {
            Log::warning('Password reset email failed to send.', [
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
