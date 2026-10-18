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

    /** The most a price on this kind of request may be (a ride: hundreds; a car: millions). */
    public function maxPrice(): float;

    /** The trades (category_children_master ids) whose businesses may see and answer this kind of request. @return list<int> */
    public function tradeIds(): array;

    /**
     * The trades whose requests of this kind THIS business answers: all of the kind's trades for a ride or a car, only its
     * own for a home service (a plumber never sees an electrician's job). Empty when it answers none.
     *
     * @return list<int>
     */
    public function answerTradesFor(User $business): array;

    /** The businesses that may answer this request, nearest first. @return \Illuminate\Support\Collection<int,User> */
    public function eligible(ServiceRequest $request, int $limit = 500);

    /** May this business answer it? */
    public function mayAnswer(ServiceRequest $request, User $business): bool;

    /** What the accepted offer becomes: the platform's ordinary operation (a booking). */
    public function fulfil(ServiceRequest $request, ServiceRequestOffer $offer): Model;
}
