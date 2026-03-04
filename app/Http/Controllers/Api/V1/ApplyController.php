<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\JobApplicationResource;
use App\Models\Apply;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApplyController extends Controller
{
    // List jobs.
    public function index(Request $request)
    {
        $jobId = $request->jobId;
        $applies = Apply::where('post_id', $jobId)->get();
        return JobApplicationResource::collection($applies)->additional(['message' => "Message", 'status' => 200, 'total' => $applies->count()]);

    }

    // End User apply to job.
    public function apply(Request $request)
    {

        $user = $request->user();
        $inputs = $request->all();
        $inputs['post_id'] = $request->jobId;
        $job = Post::whereId($request->jobId)->first();
        if ($apply = $user->applies()->create($inputs)):
            $notifyData = array(
                'body' => " بتقديم طلب علي الوظيفة  $job->title $user->name قام ",
                'user_id' => $job->user->id,
                'created_by' => $user->id
            );
            $job->notifications()->create($notifyData);
            return response()->json([
                'status' => 200,

            ]);
        endif;


    }

    // Business Can Approve to applies job.
    public function approve(Request $request)
    {
        // get application.
        $applications = Apply::wherePostId($request->jobId)->whereIn('user_id', $request->usersIds)->get();
//        if (!$apply)
//            return response()->json(['status' => 400, 'message' => "this job not found."]);
        // update applications to approved.

        foreach ($applications as $application):
            $application->update(['approved_at' => Carbon::now()]);
        endforeach;

        $job = Post::whereId($request->jobId)->first();
        $job->update(['is_active' => 0]);
        return response()->json(['status' => 200, 'message' => "Applied This Application"]);
    }


}
