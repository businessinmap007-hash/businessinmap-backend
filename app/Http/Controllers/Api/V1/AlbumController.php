<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Albums\AlbumsRequestForm;
use App\Http\Resources\Albums\AlbumResource;
use App\Models\Album;
use App\Models\Image;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Images;

class AlbumController extends Controller
{

    public $public_path;

    public function __construct(Request $request)
    {
        $language = $request->headers->get('lang') ? $request->headers->get('lang') : 'ar';
        app()->setLocale($language);
        $this->public_path = 'files/uploads/';
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(AlbumsRequestForm $request)
    {
        $user = $request->user();

        $inputs = $request->validated();

        $album = $user->albums()->create($inputs);

        foreach ($request->images as $key => $image):
            if (!$image)
                continue;
            $attachment = new Image();
            $attachment->image = $image;
            $album->images()->save($attachment);
        endforeach;

        return AlbumResource::make($album)->additional(['message' => "Message", 'status' => 200]);

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(AlbumsRequestForm $request, Album $album)
    {

        $album->fill($request->validated())->update($request->validated());

        if (count($request->images) > 0):
            $album->images->each->delete();
            foreach ($request->images as $key => $image):
                if (!$image)
                    continue;
                $attachment = new Image();
                $attachment->image = $image;
                $album->images()->save($attachment);
            endforeach;
        endif;
        return AlbumResource::make($album)->additional(['message' => "Message", 'status' => 200]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Album $album)
    {
        if ($album->delete())
            return response()->json(['status' => 200, 'message' => "Album has been deleted successfully"]);
    }
}
