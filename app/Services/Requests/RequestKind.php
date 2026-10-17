<?php

namespace App\Services\Requests;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of request on the requests-and-offers core. The core knows how to post, answer, choose and cancel; a kind
 * knows the three things that differ — what its input means, who may answer, and what an accepted offer becomes.
 */
interface RequestKind
{
    public function key(): string;

    /**
     * Validate the customer's input and turn it into the request's own fields (`category_child_id`, `governorate_id`,
     * `title`, `params`, `starts_at`, `budget`, `suggested_price`, `expires_at`…).
     *
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public function build(User $requester, array $input): array;

    /** The businesses that may answer this request, nearest first. @return \Illuminate\Support\Collection<int,User> */
    public function eligible(ServiceRequest $request, int $limit = 500);

    /** May this business answer it? */
    public function mayAnswer(ServiceRequest $request, User $business): bool;

    /** What the accepted offer becomes: the platform's ordinary operation (a booking). */
    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer): Model;
}
