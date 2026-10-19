<?php

namespace App\Contracts;

/**
 * A chat subject whose conversation may not carry phone numbers (the platform's way of keeping a deal on the platform
 * until the parties pay to swap numbers). See {@see \App\Services\ThreadService::post()}.
 */
interface BlocksPhoneNumbers
{
    /** Is the chat closed to phone numbers right now? (An inquiry whose numbers were already swapped is not.) */
    public function blocksPhoneNumbers(): bool;
}
