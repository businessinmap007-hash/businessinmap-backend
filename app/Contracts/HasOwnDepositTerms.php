<?php

namespace App\Contracts;

/**
 * An operation whose deposit is NOT «a percent of its value under شروط الحجز» but its own rule — a menu/retail order
 * asks the customer's cover for the WHOLE order once it passes the merchant's threshold. It hands the deposit core the
 * policy to judge it by (the same shape the deposit calculator returns), or null when nothing is asked.
 */
interface HasOwnDepositTerms
{
    /** @return array<string,mixed>|null */
    public function depositTerms(): ?array;
}
