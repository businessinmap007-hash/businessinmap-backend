<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The password-reset code email. A real Mailable (the app had none — everything
 * used raw Mail::send) so the flow is testable and the sender/subject are in one
 * place. Sender comes from the global mail `from` config (MAIL_FROM_*), so
 * production controls it via env instead of a hard-coded address.
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('إستعادة كلمة المرور - BIM')
            ->view('emails.email', ['content' => ['code' => $this->code]]);
    }
}
