<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function edit()
    {
        return view('videos.upload');
    }

    public function uploadToS3(Request $request) {

        // Code here
    }
}
