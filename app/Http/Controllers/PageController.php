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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @@ Show Privacy And Policy Page Content.
     */
    public function privacy(){
        return view('pages.privacy');
    }


}
