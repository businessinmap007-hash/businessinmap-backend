<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @@ Show Aboutus Page.
     */
    public function aboutUs(){
        return view('pages.about');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @@ Show terms And Conditions Page Content.
     */
    public function termsAndConditions(){
        return view('pages.terms');
    }

    /**
     * @@ Show the app features/services explanation page (data-driven from
     * config/app_features.php), with the terms + consent note.
     */
    public function features(){
        return view('pages.features', [
            'groups' => (array) config('app_features', []),
            'termsVersion' => (string) config('legal.terms_version'),
            'privacyVersion' => (string) config('legal.privacy_version'),
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @@ Show Privacy And Policy Page Content.
     */
    public function privacy(){
        return view('pages.privacy');
    }


}
