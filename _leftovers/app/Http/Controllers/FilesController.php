<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;

class FilesController extends Controller
{
    /**
     * @var Category
     */

    public $public_path;

    public function __construct()
    {
        $this->public_path = 'files/services/';
    }


    public function uploadFile(Request $request)
    {


//        return $request->file('file');


        if ($request->file('file')):


            $file = new Image();
            $filename = time() . '-' . $requestFile->getClientOriginalName();
            $requestFile->move(public_path($this->public_path), $filename);
            $file->url = request()->root() . '/' . $this->public_path . $filename;
            if ($file->save()) {
                return response()->json([
                    'status' => 200,
                ]);

            } else {
                return response()->json([
                    'status' => 400,
                    'message' => "error"
                ]);
            }
        endif;


    }
}
