<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LikeController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $inputs = $request->only(['post_id', 'like']);

        $post = Post::whereId($inputs['post_id'])->first();

        $isExistLikeOrDislike = $user->likes()->where('post_id', $inputs['post_id'])->first();

        if ($isExistLikeOrDislike)
            $isExistLikeOrDislike->delete();


        $like = $user->likes()->create($inputs);


        $notifyData = array(
            'body' => $request->like == -1 ? 'dislike' : 'like',
            'user_id' => $post->user->id,
            'created_by' => $request->user()->id
        );

        if ($request->user()->id != $post->user->id)
            $post->notifications()->create($notifyData);
        return response()->json([
            'status' => 200,
            'data' => $like,
            'message' => "Like Success"
        ]);
    }


}
