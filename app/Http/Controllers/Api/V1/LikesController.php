<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use App\Like;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use PhpParser\Node\Expr\Cast\Object_;

class LikesController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function likeCompany(Request $request)
    {
        $user = auth()->user();
        $company = Company::whereId($request->companyId)->first();

        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Company Not Found in System']);
        }

        try {
            $isLiked = $company->likes()->where('user_id', $user->id)->first();
            if (is_object($isLiked)) {
                $like = Like::find($isLiked->id);
                $like->like = $request->type;
                $like->user_id = auth()->id();
                if ($company->likes()->save($like)) {
                    $message = ($like->like == 1) ? 'Like' : 'Dislike';
                    return response()->json([
                        'status' => true,
                        'message' => $message,
                        'data' => [
                            'likesCount' => $company->likes()->whereLike(1)->count(),
                            'disLikesCount' => $company->likes()->whereLike(0)->count(),

                        ]
                    ]);
                }
            } else {
                $like = new Like;
                $like->like = $request->type;
                $like->user_id = auth()->id();
                if ($company->likes()->save($like)) {
                    return response()->json([
                        'status' => true,
                        'message' => 'لقد تم الاعجاب',
                        'data' => [
                            'likesCount' => $company->likes()->whereLike(1)->count(),
                            'disLikesCount' => $company->likes()->whereLike(0)->count(),

                        ]
                    ]);
                }
            }

        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'message' => 'erroraddtofavorite',
                'data' => []
            ]);

        }
    }

}
