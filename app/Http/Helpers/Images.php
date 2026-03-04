<?php

namespace App\Http\Helpers;

/**
 * Created by PhpStorm.
 * User: hassan saeed
 * Date: 7/25/2017
 * Time: 4:24 PM
 */
use Image;

class Images
{


    public static function imageUploader($image, $path = null)
    {

        if ($image):
            // Get File name from POST Form
            // Custom file name with adding Timestamp
            $filename = time() . '.' . $image->getClientOriginalName();

            // Directory Path Save Images
            $path = public_path($path . $filename);

            // Upload images to Target folder By INTERVENTION/IMAGE
            $img = Image::make($image);

            $img->save($path);

            // RETURN path to save in images tables DATABASE
            return $filename;

        endif;
    }

    /**
     * RETURN path to save in images tables DATABASE
     * @RETURN IMAGE PATH
     *
     * SAVE MAIN IMAGE
     */
    public static function uploadMainImage($request, $name, $path = null)
    {

        if ($request->hasFile($name)):
            // Get File name from POST Form
            $image = $request->file($name);

            // Custom file name with adding Timestamp
            $filename = time() . '.' . $image->getClientOriginalName();

            // Directory Path Save Images
            $path = public_path($path . $filename);

            // Upload images to Target folder By INTERVENTION/IMAGE
            $img = Image::make($image);

            $img->save($path);

            // RETURN path to save in images tables DATABASE
            return $filename;
        endif;
    }

    /**
     * RETURN path to save in images tables DATABASE
     * @RETURN IMAGE PATH
     *
     * SAVE MAIN IMAGE
     */
    public static function uploadSubImages($request, $name, $path = null)
    {

        if ($request->hasFile($name)):
            // Get File name from POST Form
            $image = $request->file($name);

            // Custom file name with adding Timestamp
            $filename = time() . '.' . str_random(20) . $image->getClientOriginalName();

            // Directory Path Save Images
            $path = public_path($path . $filename);

            // Upload images to Target folder By INTERVENTION/IMAGE
            $img = Image::make($image);

            $img->save($path);

            // RETURN path to save in images tables DATABASE
            return $filename;
        endif;
    }

    /**
     * RETURN path to save in images tables DATABASE
     * @RETURN IMAGE PATH
     *
     * SAVE THUMBNAILS IMAGES
     */
    public static function uploadThumbImage($request, $name, $path = null, $width = null, $height = null)
    {
        if ($request->hasFile($name)):
            // Get File name from POST Form
            $image = $request->file($name);

            // Custom file name with adding Timestamp
            $filename = time() . '.' . $image->getClientOriginalName();

            // Directory Path Save Images
            $path = public_path($path . $filename);

            // Upload images to Target folder By INTERVENTION/IMAGE
            $img = Image::make($image);

            // RESIZE IMAGE TO CREATE THUMBNAILS
            $img->resize($width, $height, function ($ratio) {
                $ratio->aspectRatio();
            });
            $img->save($path);

            // RETURN path to save in images tables DATABASE
            return $filename;
        endif;
    }


    /**
     * RETURN path to save in images tables DATABASE
     * @RETURN IMAGE PATH
     *
     * SAVE THUMBNAILS IMAGES
     */
    public static function uploadImage($request, $name, $path = null, $width = null, $height = null)
    {
        if ($request->hasFile($name)):
            // Get File name from POST Form
            $image = $request->file($name);

            // Custom file name with adding Timestamp
            $filename = time() . '.' . str_random(20) . $image->getClientOriginalName();


            // Directory Path Save Images
            $path = public_path($path . $filename);

            // Upload images to Target folder By INTERVENTION/IMAGE
            $img = Image::make($image);

            // RESIZE IMAGE TO CREATE THUMBNAILS
            if (isset($width) || isset($height))
                $img->resize($width, $height, function ($ratio) {
                    $ratio->aspectRatio();
                });
            $img->save($path);

            // RETURN path to save in images tables DATABASE
            return $filename;
        endif;
    }


    public function getDefaultImage($image, $defaultImagePath)
    {
        return ($image != null) ? $image : $defaultImagePath;
    }



//    public static function list_categories(Array $categories)
//    {
//        $data = [];
//
//        foreach($categories as $category)
//        {
//            $data[] = [
//                'comment' => $category->comment,
//                'children' => list_categories($category->children),
//            ];
//        }
//
//        return $data;
//    }


    /**
     * Converts numbers in string from western to eastern Arabic numerals.
     *
     * @param  string $str Arbitrary text
     * @return string Text with western Arabic numerals converted into eastern Arabic numerals.
     */
//    public static function arabic_w2e($str)
//    {
//        if(config('app.locale') == 'ar'):
//            $arabic_eastern = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
//            $arabic_western = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
//            return str_replace($arabic_western, $arabic_eastern, $str);
//    else:
//    $arabic_eastern = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
//    $arabic_western = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
//    return str_replace($arabic_eastern, $arabic_western, $str);
//            endif;
//
//    }

    /**
     * Converts numbers from eastern to western Arabic numerals.
     *
     * @param  string $str Arbitrary text
     * @return string Text with eastern Arabic numerals converted into western Arabic numerals.
     */
//    function arabic_e2w($str)
//    {
//        $arabic_eastern = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
//        $arabic_western = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
//        return str_replace($arabic_eastern, $arabic_western, $str);
//    }

    public static function translate($from_lan, $to_lan, $text)
    {
        $json = json_decode(file_get_contents('https://ajax.googleapis.com/ajax/services/language/translate?v=1.0&q=' . urlencode($text) . '&langpair=' . $from_lan . '|' . $to_lan));
        $translated_text = $json->responseData->translatedText;

        return $translated_text;
    }


}
