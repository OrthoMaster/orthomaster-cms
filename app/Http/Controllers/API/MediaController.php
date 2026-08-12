<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TrkContentViews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function getEvents(Request $request)
    {
        try {
            $events = Event::get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media', 'get-events']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    /**
     * Get content of media
     */
    public function getContent(Request $request)
    {
        if (!$request->has('contentId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $content = DB::table('content')
                ->where('id_content', $request->contentId)
                ->where('type', 'flash')
                ->first();

            if ($content->s3_path && $content->version == 'v1') {
                $direction = $content->s3_path . "/" . $content->title . "/content.xml";
                $xmlContent = Storage::disk('s3_root')->get($direction);

                $baseDirectory = $content->s3_path . "/" . $content->title;
                $trainingController = new TrainingController;
                $parsedContent = $trainingController->processXmlImages($xmlContent, $baseDirectory);

                $content->xml_content = $parsedContent;
            } elseif (!!$content->video_id && $content->version == 'v2') {
                $content->video = DB::table('videos')
                    ->where('id', $content->video_id)
                    ->first();
                $content->block = DB::table('blocks')
                    ->where('video_id', $content->video_id)
                    ->first();
                $content->event = DB::table('events')
                    ->where('id', $content->event_id)
                    ->first();
                $content->past_event = DB::table('past_events')
                    ->where('id', $content->id_past_event)
                    ->first();
                $content->experts = DB::table('expert_video as ev')
                    ->join('experts as e', 'e.id', '=', 'ev.expert_id')
                    ->where('ev.content_id', $content->id_content)
                    ->orderBy('e.name')
                    ->get(['e.id', 'e.name']);
            }

            return response()->json([
                'success' => true,
                'data' => $content ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    /**
     * Get content of tool
     */
    public function getToolContent(Request $request)
    {
        if (!$request->has('contentId')) {
            return response()->json([
                'success' => false,
            ]);
        };

        try {
            $content = DB::table('content')
                ->where('id_content', $request->contentId)
                ->where('type', 'tool')
                ->first();

            if ($content->s3_path) {
                $direction = $content->s3_path . "/" . $content->title . "/content.xml";
                $xmlContent = Storage::disk('s3_root')->get($direction);

                $baseDirectory = $content->s3_path . "/" . $content->title;
                $trainingController = new TrainingController;
                $parsedContent = $trainingController->processXmlImages($xmlContent, $baseDirectory);

                $content->xml_content = $parsedContent;
            }

            return response()->json([
                'success' => true,
                'data' => $content ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['tool-content']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    private function baseContentQuery()
    {
        return DB::table('content')
            ->leftJoin('videos', 'videos.id', '=', 'content.video_id')
            ->leftJoin('expert_video', function ($join) {
                $join->on('expert_video.video_id', '=', 'content.video_id')
                    ->orOn('expert_video.content_id', '=', 'content.id_content');
            })
            ->leftJoin('experts', 'experts.id', '=', 'expert_video.expert_id')
            ->where('content.published', true)
            ->where(function ($q) {
                $q->whereNull('videos.id')
                    ->orWhere('videos.published_at', '<=', now());
            })
            ->select([
                'content.id_content',
                'content.video_id',
                'content.creation',
                'content.duration',
                'content.version',
                'content.s3_path',
                'content.aws_object_path',
                'content.title',
                'content.id_past_event',
                'content.id_media_category',
                'videos.published_at',
                'videos.thumbnail as video_thumbnail',
                DB::raw("GROUP_CONCAT(DISTINCT experts.name ORDER BY experts.name SEPARATOR ' - ') as expert_names"),
                DB::raw('GROUP_CONCAT(DISTINCT experts.id) as expert_ids'),
            ])
            ->groupBy(
                'content.id_content',
                'content.video_id',
                'content.creation',
                'content.duration',
                'content.version',
                'content.s3_path',
                'content.aws_object_path',
                'content.title',
                'content.id_past_event',
                'content.id_media_category',
                'videos.published_at',
                'videos.thumbnail'
            )
            ->orderByRaw('COALESCE(videos.published_at, content.creation) DESC')
            ->orderBy('content.creation', 'desc');
    }

    private function attachThumbnails($contents)
    {
        $disk = Storage::disk('s3_root');
        $s3ImagePath = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/';

        foreach ($contents as $content) {
            if ($content->version !== 'v2') {
                $content->thumbnail = $disk->url($content->s3_path . '/' . $content->title . '/logo.png');
            } else {
                $content->thumbnail = $content->video_thumbnail
                    ? $s3ImagePath . ltrim($content->video_thumbnail, '/')
                    : null;
            }
        }

        return $contents;
    }

    public function getNewests(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 6);

            $contents = $this->baseContentQuery()
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $this->attachThumbnails($contents)
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-newest']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getRelateds(Request $request, $id)
    {
        try {
            $limit = (int) $request->input('limit', 6);

            $current = DB::table('content')
                ->select('id_content', 'id_past_event', 'id_media_category')
                ->where('id_content', $id)
                ->first();
            if (! $current) {
                return collect();
            }

            $query = $this->baseContentQuery()
                ->where('content.id_content', '!=', $id);

            if (! empty($current->id_past_event)) {
                $query->where('content.id_past_event', $current->id_past_event);
            } elseif (! empty($current->id_media_category)) {
                $query->where('content.id_media_category', $current->id_media_category);
            } else {
                return collect();
            }

            return response()->json([
                'success' => true,
                'data' => $this->attachThumbnails($query->limit($limit)->get()),
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-related', $id]);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getTrends(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 6);

            $query = $this->baseContentQuery()
                ->where('content.trend_flg', 'true');

            return response()->json([
                'success' => true,
                'data' => $this->attachThumbnails($query->limit($limit)->get()),
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-trends']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getCategories(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 12);

            $categories = DB::table('media_categories')
                ->where('active_flg', true)
                ->orderBy('order_dsp', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            foreach ($categories as $category) {
                $category->medias = $this->attachThumbnails($this->baseContentQuery()
                    ->where('content.id_media_category', '=', $category->id)
                    ->limit($limit)->get());
            }

            return response()->json([
                'success' => true,
                'data' => $categories,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-categories']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getEventMedias(Request $request, $id)
    {
        try {
            $pastEvents = DB::table('past_events')
                ->where('id_event', $id)
                ->orderBy('id', 'desc')
                ->get();

            foreach ($pastEvents as $pEvent) {
                $pEvent->medias = $this->attachThumbnails($this->baseContentQuery()
                    ->where('content.id_past_event', '=', $pEvent->id)
                    ->get());
            }

            return response()->json([
                'success' => true,
                'data' => $pastEvents,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-past-event', $id]);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function trackMedia(Request $request, $id)
    {
        try {
            $trackId = null;
            if (Auth::check() && in_array(Auth::user()->role->name, ['user', 'user-manager'])) {
                DB::table('content')
                    ->where('id_content', $id)
                    ->increment('views');

                $content = DB::table('content')->where('id_content', $id)->first();
                $tracking = TrkContentViews::create([
                    'user_id' => $request->user()->id,
                    'content_id' => $id,
                    'type' => $content->type,
                ]);
                if ($tracking) {
                    $trackId = $tracking->id;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $trackId,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-past-event', $id]);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function trackProgress(Request $request, $trackId)
    {
        try {
            $data = $request->validate([
                'watched_seconds' => ['required', 'integer', 'min:0'],
                'max_position'    => ['required', 'integer', 'min:0'],
                'duration'        => ['nullable', 'integer', 'min:0'],
            ]);

            $tracking = TrkContentViews::find($trackId);

            // La ligne doit exister et appartenir à l'utilisateur courant
            if (!$tracking || !Auth::check() || $tracking->user_id !== $request->user()->id) {
                return response()->json(['success' => false], 404);
            }

            $duration = $data['duration'] ?: $tracking->video_duration;

            // Les compteurs ne peuvent que progresser : un heartbeat en retard
            // (réseau lent, requêtes désordonnées) ne doit rien écraser
            $watched = max((int) $tracking->watched_seconds, $data['watched_seconds']);
            $maxPos  = max((int) $tracking->max_position, $data['max_position']);

            // Garde-fou : on ne dépasse jamais la durée réelle de la vidéo
            if ($duration > 0) {
                $watched = min($watched, $duration);
                $maxPos  = min($maxPos, $duration);
            }

            $tracking->update([
                'watched_seconds' => $watched,
                'max_position'    => $maxPos,
                'video_duration'  => $duration,
            ]);

            return response()->json(['success' => true], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false], 422);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['media-track-progress', $trackId]);

            return response()->json(['success' => false]);
        }
    }
}
