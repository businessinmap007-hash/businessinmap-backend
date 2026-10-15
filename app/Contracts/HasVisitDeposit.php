<?php

namespace App\Contracts;

/**
 * A health visit (clinic appointment, lab/radiology order, hospital procedure) that may carry a «عربون الجدية».
 * It names its two parties, what it is worth, and whether it is still ahead of the patient — nothing more.
 * The same two names serve a dispute over that deposit (DisputeService reads the sides from here).
 */
interface HasVisitDeposit
{
    /** The customer (the patient) — the one whose deposit is held. */
    public function visitDepositClientId(): int;

    /** The business the visit is at. */
    public function visitDepositBusinessId(): int;

    /** What the visit costs, as far as it is known (0 = not known yet). */
    public function visitDepositValue(): float;

    /** Still ahead / running — a visit already over has no deposit left to quote. */
    public function visitDepositOpen(): bool;
}
