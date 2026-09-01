<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FavouriteController extends Controller
{
    private const S3_BASE_URL = 'https://content-orthomaster-prod.s3.eu-west-1.amazonaws.com/application-data/';

    /** Types de contenu pouvant être mis en favori */
    private const TYPES = ['podcast', 'video', 'infographic'];

    /** Nombre d'éléments par page par défaut */
    private const DEFAULT_LIMIT = 10;

    /** Nombre maximum d'éléments par page */
    private const MAX_LIMIT = 20;

    /**
     * Ajoute ou retire un centre d'intérêt du suivi de l'utilisateur.
     * POST /favourites/topics/toggle
     */
    public function toggleTopic(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topicId' => ['required', 'integer', 'exists:topics,id'],
        ]);

        // Une requête mal formée reste un cas nominal côté client
        if ($validator->fails()) {
            return response()->json(['success' => false], 200);
        }

        try {
            $user    = $request->user();
            $topicId = (int) $validator->validated()['topicId'];

            $isFollowed = $user->topics()->where('topics.id', $topicId)->exists();

            if ($isFollowed) {
                $user->topics()->detach($topicId);
            } else {
                $user->topics()->syncWithoutDetaching([$topicId]);
            }

            return response()->json([
                'success'  => true,
                'followed' => !$isFollowed,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['toggle-topic', $request->user()->id]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Ajoute ou retire un contenu des favoris de l'utilisateur.
     * POST /favourites/toggle
     */
    public function toggleFavourite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:' . implode(',', self::TYPES)],
            'id'   => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            Log::channel('api')->error($validator->messages(), ['toggle-favourite', $request->user()->id]);
            return response()->json(['success' => false], 200);
        }

        $data = $validator->validated();

        try {
            $userId = $request->user()->id;

            // Le contenu visé doit exister
            if (!$this->itemExists($data['type'], (int) $data['id'])) {
                return response()->json(['success' => false], 200);
            }

            $existing = DB::table('favourites')
                ->where('user_id', $userId)
                ->where('type', $data['type'])
                ->where('item_id', $data['id'])
                ->first();

            if ($existing) {
                DB::table('favourites')->where('id', $existing->id)->delete();
            } else {
                DB::table('favourites')->insert([
                    'user_id'    => $userId,
                    'type'       => $data['type'],
                    'item_id'    => $data['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success'    => true,
                'favourited' => !$existing,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['toggle-favourite', $request->user()->id]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Liste des favoris.
     *
     * Sans `type` : un échantillon de chaque catégorie, dont la taille est
     * fixée par podcastLimit, videoLimit et infographicLimit.
     * Avec `type` : la catégorie demandée, paginée et triable.
     *
     * GET /favourites
     */
    public function getFavourites(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'             => ['nullable', 'string', 'in:' . implode(',', self::TYPES)],
            'page'             => ['nullable', 'integer', 'min:1'],
            'limit'            => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_LIMIT],
            'sort'             => ['nullable', 'string', 'in:date,name'],
            'order'            => ['nullable', 'string', 'in:asc,desc'],
            'podcastLimit'     => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'videoLimit'       => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'infographicLimit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        if ($validator->fails()) {
            Log::channel('api')->error($validator->messages(), ['get-favourites', $request->user()->id]);
            return response()->json(['success' => false], 200);
        }

        $params = $validator->validated();

        try {
            $userId = $request->user()->id;

            // Les favoris orphelins sont retirés avant toute lecture
            $this->purgeOrphans($userId);

            $sort  = $params['sort'] ?? 'date';
            $order = $params['order'] ?? 'desc';

            if (!empty($params['type'])) {
                return response()->json(
                    $this->paginatedByType(
                        $userId,
                        $params['type'],
                        (int) ($params['page'] ?? 1),
                        (int) ($params['limit'] ?? self::DEFAULT_LIMIT),
                        $sort,
                        $order
                    ),
                    200
                );
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'podcast'     => $this->itemsOfType($userId, 'podcast', (int) ($params['podcastLimit'] ?? self::DEFAULT_LIMIT), $sort, $order),
                    'video'       => $this->itemsOfType($userId, 'video', (int) ($params['videoLimit'] ?? self::DEFAULT_LIMIT), $sort, $order),
                    'infographic' => $this->itemsOfType($userId, 'infographic', (int) ($params['infographicLimit'] ?? self::DEFAULT_LIMIT), $sort, $order),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-favourites', $request->user()->id]);

            return response()->json(['success' => false], 500);
        }
    }

    /* ===================== LECTURE ===================== */

    /**
     * Une catégorie, paginée.
     */
    private function paginatedByType(int $userId, string $type, int $page, int $limit, string $sort, string $order): array
    {
        $query = $this->itemsQuery($userId, $type, $sort, $order);

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get();

        return [
            'success' => true,
            'data'    => $items,
            'pagination' => [
                'page'    => $page,
                'limit'   => $limit,
                'total'   => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ];
    }

    /**
     * Un échantillon d'une catégorie, sans pagination.
     */
    private function itemsOfType(int $userId, string $type, int $limit, string $sort, string $order)
    {
        return $this->itemsQuery($userId, $type, $sort, $order)->limit($limit)->get();
    }

    /**
     * Requête d'une catégorie, jointe à sa table source.
     * Chaque type conserve les colonnes qui lui sont propres.
     */
    private function itemsQuery(int $userId, string $type, string $sort, string $order)
    {
        $query = match ($type) {
            'podcast' => DB::table('favourites as f')
                ->join('podcasts as p', 'p.id', '=', 'f.item_id')
                ->select(
                    'f.created_at as favourited_at',
                    'p.id',
                    'p.title',
                    'p.author',
                    'p.image',
                    'p.link',
                    'p.duration',
                    'p.topic_id',
                    'p.published_at'
                ),

            'infographic' => DB::table('favourites as f')
                ->join('infographics as i', 'i.id', '=', 'f.item_id')
                ->select(
                    'f.created_at as favourited_at',
                    'i.id',
                    'i.code',
                    'i.title',
                    'i.thumbnail',
                    'i.topic_id',
                    'i.published_at'
                )
                ->selectRaw("CONCAT(?, TRIM(LEADING '/' FROM i.image)) as image", [self::S3_BASE_URL]),

            default => DB::table('favourites as f')
                ->join('content as c', 'c.id_content', '=', 'f.item_id')
                ->leftJoin('videos as v', 'v.id', '=', 'c.video_id')
                ->select(
                    'f.created_at as favourited_at',
                    'c.id_content as id',
                    'c.title',
                    'c.duration',
                    'c.version',
                    'c.video_id',
                    'v.thumbnail'
                ),
        };

        $titleColumn = match ($type) {
            'podcast'     => 'p.title',
            'infographic' => 'i.title',
            default       => 'c.title',
        };

        return $query
            ->where('f.user_id', $userId)
            ->where('f.type', $type)
            ->orderBy($sort === 'name' ? $titleColumn : 'f.created_at', $order);
    }

    /* ===================== INTÉGRITÉ ===================== */

    /**
     * Vérifie qu'un contenu existe avant de l'ajouter aux favoris.
     */
    private function itemExists(string $type, int $id): bool
    {
        return match ($type) {
            'podcast'     => DB::table('podcasts')->where('id', $id)->exists(),
            'infographic' => DB::table('infographics')->where('id', $id)->exists(),
            default       => DB::table('content')->where('id_content', $id)->exists(),
        };
    }

    /**
     * Supprime les favoris dont le contenu n'existe plus.
     */
    private function purgeOrphans(int $userId): void
    {
        DB::table('favourites')
            ->where('user_id', $userId)
            ->where('type', 'podcast')
            ->whereNotIn('item_id', DB::table('podcasts')->select('id'))
            ->delete();

        DB::table('favourites')
            ->where('user_id', $userId)
            ->where('type', 'infographic')
            ->whereNotIn('item_id', DB::table('infographics')->select('id'))
            ->delete();

        DB::table('favourites')
            ->where('user_id', $userId)
            ->where('type', 'video')
            ->whereNotIn('item_id', DB::table('content')->select('id_content'))
            ->delete();
    }
}