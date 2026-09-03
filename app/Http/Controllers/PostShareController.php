<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use Illuminate\View\View;

/**
 * The web landing page a shared post link opens — what the mobile app's
 * share sheet hands to WhatsApp/Facebook/anywhere else, since only a plain
 * https:// URL auto-links there (a custom app scheme would just sit inert
 * as text). Open Graph tags here are what makes the shared message render
 * as a real card (title/photo/excerpt) instead of a bare link.
 *
 * Public, same as `GET /api/v2/posts/{post}` this borrows its eager-loads
 * from — a link posted into a WhatsApp group has to render for anyone who
 * taps it, not just a signed-in viewer, and the crawler that builds the
 * preview card never carries a session either.
 */
class PostShareController extends Controller
{
    public function show(FeedPost $post): View
    {
        $post->loadMissing(['user:id,name,logo,image', 'images']);

        return view('posts.share', ['post' => $post]);
    }
}
