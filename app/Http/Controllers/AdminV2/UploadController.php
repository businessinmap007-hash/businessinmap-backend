<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $file = $request->file('file');

        // ✅ متوافق مع Image component: public/files/uploads
        $dir = public_path('files/uploads');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $ext  = strtolower($file->getClientOriginalExtension());
        $name = date('Ymd_His') . '_' . Str::random(12) . '.' . $ext;

        $file->move($dir, $name);

        $path = 'files/uploads/' . $name;

        return response()->json([
            'path' => $path,
            'url'  => asset($path),
        ]);
    }
}
