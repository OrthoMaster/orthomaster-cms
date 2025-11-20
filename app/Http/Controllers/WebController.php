<?php

namespace App\Http\Controllers;

class WebController extends Controller
{
    public function legalNotice() {
        return view('landingpage.legal-notice');
    }
}
