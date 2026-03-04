<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Posts\PostFormRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\UpdatePostFormRequesr;
use App\Http\Requests\Posts\UpdatePostFormRequest;
use App\Http\Resources\Jobs\JobsIndexResource;
use App\Http\Resources\Posts\PostResource;
use App\Models\Apply;
use App\Models\Image;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    // Post New Articles Form Users -- or -- business


    public function index(Request $request, $id)
    {
        $posts = Post::whereUserId($id)->paginate(15);
        return PostResource::collection($posts);
    }

    public function store(PostFormRequest $request)
    {
        $user = $request->user();
        $inputs = $request->validated();
        $inputs['expire_at'] = Carbon::parse($request->expire_at);
        $post = $user->posts()->create($inputs);
        if (isset($request->images) && count($request->images) > 0):
            foreach ($request->images as $image):
                if (!$image)
                    continue;
                $attachment = new Image();
                $attachment->image = $image;
                $post->images()->save($attachment);
            endforeach;
        endif;

        return PostResource::make($post)->additional(['message' => "Message", 'status' => 200]);
    }


    public function getPosts(Request $request)
    {
        $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));

        if (!$token) {

            $posts = Post::get();

            $posts->map(function ($post) use ($request) {
                return $post->distance = getDistanceBetweenPointsNew($request->latitude, $request->longitude, $post->user->latitude, $post->user->longitude, "Km");
            });

            return PostResource::collection($this->paginate($posts->sortBy('distance', false), 15, request('page'), ['path' => request()->url()]))->additional(['message' => "Posts List.", 'status' => 200]);
        } else {

            $user = User::byToken($token);

            if ($request->has('authPosts') && $request->get('authPosts') == 'owner') {
                $posts = Post::orderBy('created_at', 'desc')->where('user_id', $user->id)->paginate(10);
                return PostResource::collection($posts)->additional(['message' => "Posts List.", 'status' => 200]);
            }

            $query = Post::orderBy('created_at', 'desc');

            if ($token != "") :
                $collectionsIds = getTargetsAndFollowersBusiness($token);
                $query->whereIn('user_id', $collectionsIds);
            endif;
            $posts = $query->where('user_id', '!=', $user->id)->paginate(10);

            return PostResource::collection($posts)->additional(['message' => "Posts List.", 'status' => 200]);
        }
    }

    public function paginate($items, $perPage = 15, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);

        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage)->values()->all(), $items->count(), $perPage, $page, $options);
    }


    public function sharePost(Post $post)
    {
        if (!$post)
            return response()->json(['status' => 400, 'message' => "Post Not Found."]);
        $post->share_count = $post->share_count + 1;
        if ($post->save()) {
            return response()->json(['status' => 200, 'message' => "Post has been shared successfully."]);
        }
    }


    public function delete(Request $request, Post $post)
    {
        if (!$post)
            return response()->json(['status' => 400, 'message' => "Post Not Found."]);

        $targetPost = $request->user()->posts()->where('id', $post->id)->first();

        if ($targetPost->delete())
            return response()->json(['status' => 200, 'message' => "post has been deleted successfully."]);
    }


    public function update(UpdatePostFormRequest $request, Post $post)
    {
        $user = $request->user();

        $inputs = $request->validated();
        $inputs['expire_at'] = Carbon::parse($request->expire_at);
        $isUpdated = $post->fill($inputs)->update($inputs);
        if ($isUpdated) {
            if (isset($request->images) && count($request->images) > 0):
                $post->images->each->delete();
                foreach ($request->images as $image):
                    if (!$image)
                        continue;
                    $attachment = new Image();
                    $attachment->image = $image;
                    $post->images()->save($attachment);
                endforeach;
            endif;
        }

        return PostResource::make($post)->additional(['message' => "Message Updated", 'status' => 200]);
    }


    public function getJobs(Request $request)
    {
        // get token from header if user logged in else return an empty array.
        $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));


        $query = Post::orderBy('created_at', 'desc')->whereDate('expire_at', '>', Carbon::now())->whereType('job');


        if ($token != "") :
            $collectionsIds = getTargetsAndFollowersBusiness($token);
            $query->whereIn('user_id', $collectionsIds);
        endif;

        $applicationOnThisJobs = Apply::whereIn('post_id', $query->pluck('id'))->count();


        if ($request->has('keyword') && $request->get('keyword')) {
            $keyword = $request->get('keyword');
            $query->whereHas('translations', function ($obj) use ($keyword) {
                $obj->where('title', 'LIKE', "%$keyword%")
                    ->orWhere('body', 'LIKE', "%$keyword%");
            });
        }


        $count = $query->count();


        $jobs = $query->paginate(10);


        return PostResource::collection($jobs)->additional([
            'status' => 200,
            'message' => "jobs list.",
            'applicationCount' => $applicationOnThisJobs,
            "total" => $count]);


    }
}
