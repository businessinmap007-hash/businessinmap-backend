<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Requests\Comments\CommentsFormRequest;
use App\Http\Requests\Comments\CommentsRepliesFormRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;


class CommentController extends Controller
{


    public function index(Request $request, Post $post)
    {



        $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
        $user = User::whereApiToken($token)->first();




        if ($user) {
            if ($user->id == $post->user_id) {
                return CommentResource::collection($post->comments)->additional(['status' => 200, 'message' => "List Comments"]);
            } else {
                $privatePosts = $post->comments()->where('status', 'private')
                    ->where(['parent_id' => 0, 'user_id' => $user->id])->get();

                $comments = $privatePosts->merge($post->comments()->where('status', 'public')->get());

                $comments = collect($comments)->sortByDesc('id')->flatten();

                return CommentResource::collection($comments)->additional(['status' => 200, 'message' => "List Comments"]);
            }


        } else {
            return CommentResource::collection($post->comments()->where('status', 'public')->get())->additional(['status' => 200, 'message' => "List Comments"]);
        }


    }


    public function store(CommentsFormRequest $request)
    {

        $user = $request->user();
        $inputs = $request->validated();
        $comment = $user->comments()->create($inputs);

        $post = $comment->post;


        $notifyData = array(
            'body' => "comment",
            'user_id' => $post->user->id,
            'created_by' => $user->id
        );

        if ($post->user->id != $request->user()->id)
            $post->notifications()->create($notifyData);

        return CommentResource::make($comment)->additional(['status' => 200, 'message' => "Comment has been added successfully."]);

    }


    public function commentReplies(CommentsRepliesFormRequest $request, Comment $comment)
    {

        $user = $request->user();
        $inputs = $request->validated();
        $commentReply = $comment->children()->create(array_merge($inputs,
            array(
                'user_id' => $user->id,
                'post_id' => $comment->post_id,
                'status' => $request->status
            )));

        $notifyData = array(
            'body' => "reply",
            'user_id' => $comment->user_id,
            'created_by' => $request->user()->id
        );

        if ($comment->user_id != $request->user()->id)
            $comment->post->notifications()->create($notifyData);

        return CommentResource::make($commentReply)->additional(['status' => 200, 'message' => "Comment has been added successfully."]);

    }

    public function commentList(Request $request)
    {
        /**
         * Set Default Value For Skip Count To Avoid Error In Service.
         * @ Default Value 15...
         */
        if (isset($request->pageSize)):
            $pageSize = $request->pageSize;
        else:
            $pageSize = 15;
        endif;
        /**
         * SkipCount is Number will Skip From Array
         */
        $skipCount = $request->skipCount;
        $itemId = $request->itemId;

        $currentPage = $request->get('page', 1); // Default to 1

        $query = Comment::with('user')
            ->where(['commentable_id' => $request->companyId, 'is_agree' => 1])
            ->orderBy('created_at', 'desc')
            ->select();

        /**
         * @ If item Id Exists skipping by it.
         */
        if ($itemId) {
            $query->where('id', '<=', $itemId);
        }

        if (isset($request->filterby) && $request->filterby == 'date') {
            $query->orderBy('created_at', 'desc');
        }
        /**
         * @@ Skip Result Based on SkipCount Number And Pagesize.
         */
        $query->skip($skipCount + (($currentPage - 1) * $pageSize));
        $query->take($pageSize);

        /**
         * @ Get All Data Array
         */

        $comments = $query->get();

        /**
         * Return Data Array
         */

        return response()->json([
            'status' => true,
            'data' => $comments
        ]);

    }


    public function commentRepliesList(Request $request, Comment $comment)
    {

        $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
        $user = User::whereApiToken($token)->first();

        if ($user) {
            if ($user->id == $comment->user_id) {
                return CommentResource::collection($comment->children()->get())->additional(['status' => 200, 'message' => "Replies List Comments"]);
            } else {
                $privatePosts = $comment->children()->where('status', 'private')
                    ->where(['parent_id' => $comment->id, 'user_id' => $user->id])->get();

                $comments = $privatePosts->merge($comment->children()->where('status', 'public')->get());

                $comments = collect($comments)->sortByDesc('id')->flatten();

                // Create Custom Pagination For Collection Array
//                $currentPage = Paginator::resolveCurrentPage();
//                $col = collect($comments);
//                $perPage = 12;
//                $currentPageItems = $col->slice(($currentPage - 1) * $perPage, $perPage)->all();
//                $items = new Paginator($currentPageItems, count($col), $perPage);
//                $items->setPath($request->url());
//                $items->appends($request->all());

                return CommentResource::collection($comments)->additional(['status' => 200, 'message' => " Replies List Comments"]);
            }

        } else {
            return CommentResource::collection($comment->children()->where('status', 'public')->get())->additional(['status' => 200, 'message' => "List Comments"]);
        }




//        $replies = $comment->children()->paginate(10);
//
//        return CommentResource::collection($replies)->additional(['status' => 200, 'message' => "List Comments"]);
    }

}
