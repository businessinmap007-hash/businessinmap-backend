<?php

namespace App\Http\Controllers\Api\V1;

use App\Company;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Image;
use App\Http\Helpers\Images;

class ImagesController extends Controller
{

    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/companies/';
    }

    public function postImage(Request $request)
    {
        $index = $request->index;
        //$company = Company::byId($request->companyId);
        $image = new Image;

        $image->imageable_id = 0;
        $image->imageable_type = "Default";
        $image->image = $request->root() . '/' . $this->public_path . UploadImage::uploadSubImages($request, 'image', $this->public_path);
        if ($image->save()) {
            return response()->json([
                'status' => true,
                'message' => 'uploadsuccess',
                'data' => $image
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'uploadfail',
                'data' => []
            ]);
        }
    }

}

