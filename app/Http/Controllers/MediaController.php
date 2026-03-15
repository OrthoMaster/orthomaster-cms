<?php

namespace App\Http\Controllers;

use App\Models\OldContent;
use App\Models\Video;
use Illuminate\Http\Request;
use Aws\CloudFront\CloudFrontClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = OldContent::where('type', 'flash');

        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }
        if ($request->filled('author')) {
            $query->where('author', 'LIKE', '%' . $request->author . '%');
        }
        
        $query->orderBy('views', 'desc')->orderBy('id_content', 'desc');

        $medias = $query->paginate(10);
        $medias->appends($request->query());

        return view('medias.index', compact('medias'));
    }
}
