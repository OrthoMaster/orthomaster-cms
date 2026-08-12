<?php

namespace App\Http\Controllers;

class GuideController extends Controller
{
    /**
     * Guide pas à pas : créer et mettre en ligne une vidéo.
     * GET /admin/guides/create-media
     */
    public function createMedia()
    {
        return view('guides.create-media');
    }
}