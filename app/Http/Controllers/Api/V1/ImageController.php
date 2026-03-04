<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;

class ImageController extends Controller
{


    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/uploads/';
    }

    public function store(Request $request)
    {
        if ($request->hasFile('images')):
            $uploadedImages = [];
            foreach ($request->images as $image):
                if (!$image)
                    continue;
                $attachment = $this->public_path . Images::imageUploader($image, $this->public_path);
                $uploadedImages[] = $attachment;
            endforeach;
        endif;
        return response()->json(['status' => 200, 'message' => 'Images has been uploaded successfully.', 'data' => $uploadedImages]);
    }


    public function fileUploader(Request $request)
    {
        if ($request->hasFile('file')):
            $attachment = $this->public_path . Images::imageUploader($request->file, $this->public_path);
            return response()->json(['status' => 200, 'message' => 'Images has been uploaded successfully.', 'data' => $attachment]);
        endif;
        return response()->json(['status' => 400, 'message' => 'Something went wrong.']);
    }
}
