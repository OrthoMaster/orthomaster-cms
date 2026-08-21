<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Reads storage/product/rules.json and exposes helpers around the
 * filter tree.
 *
 * Responsibilities:
 *   1. Expand a flat pathology filter set with its "parents" so a product
 *      tagged generically ('age') still matches a query for a specific
 *      value ('age-u25'). [LEGACY pathology behavior, preserved.]
 *   2. Resolve measurement bucket ids → numeric (dimensionKey, min, max)
 *      so callers can check overlap with product size measurements.
 *   3. Match a product against a user's filter selection, combining
 *      pathology per-group logic and measurement range overlap.
 *
 * Caches are busted via FilterRulesService::clearCache() when rules.json
 * changes (e.g. from the rules edit page).
 *
 * Two cache layers:
 *   - Per-request memo (private properties) — productMatches() is called
 *     once per product, so without this we'd hit the cache driver (and
 *     originally re-read + re-decode rules.json) thousands of times per
 *     request.
 *   - Cache facade — survives across requests, TTL = CACHE_MINUTES.
 */
class FilterRulesService
{
    public const CACHE_KEY_GROUP_MAP            = 'filter_rules.group_map';
    public const CACHE_KEY_PARENT_MAP           = 'filter_rules.parent_map';
    public const CACHE_KEY_BUCKET_MAP           = 'filter_rules.bucket_map';
    public const CACHE_KEY_MEASUREMENT_NODE_IDS = 'filter_rules.measurement_node_ids';
    public const CACHE_KEY_PROTOCOLS            = 'filter_rules.protocols';
    public const CACHE_MINUTES                  = 60;

    /** Per-request memos. Reset naturally — the service is resolved per request. */
    private ?array $treeMemo      = null;
    private ?array $groupMapMemo  = null;
    private ?array $groupIdsMemo  = null;
    private ?array $bucketMapMemo = null;

    // ---------------------------------------------------------------

    /**
     * Groups where a missing product tag is NOT disqualifying.
     *
     * These describe the patient rather than the pathology, so a product
     * that says nothing about them is assumed to be suitable for anyone.
     * Every other group the user filters on is mandatory: if the product
     * has no tag there, it can't claim to satisfy the criterion.
     *
     * Add new lenient group ids here.
     */
    public const OPTIONAL_GROUPS = [
        'patient',
        'type-sub',
        'age-sub',
        'activ-sub',
    ];

    private ?array $optionalGroupsMemo = null;

    /** OPTIONAL_GROUPS đã array_flip để tra O(1). */
    private function optionalGroups(): array
    {
        return $this->optionalGroupsMemo ??= array_flip(self::OPTIONAL_GROUPS);
    }

    /**
     * Có ít nhất một pathology filter nằm NGOÀI OPTIONAL_GROUPS không?
     *
     * Tag lạ / không còn trong rules.json bị bỏ qua — chúng không lọc
     * được gì nên không tính là filter hợp lệ.
     */
    private function hasMandatoryFilters(array $pathologyFilters): bool
    {
        if (empty($pathologyFilters)) return false;

        $groupMap = $this->groupMap();
        $groupIds = $this->groupIds();
        $optional = $this->optionalGroups();

        foreach ($pathologyFilters as $f) {
            if (!is_string($f) || $f === '') continue;

            // Tag generic (id trùng nodeId) thuộc chính group đó.
            $gid = isset($groupIds[$f]) ? $f : ($groupMap[$f] ?? null);
            if ($gid === null) continue;              // stale/unknown

            if (!isset($optional[$gid])) return true;
        }

        return false;
    }


    /**
     * Load the raw rules tree from storage. Returns [] if missing/invalid.
     */
    public function loadTree(): array
    {
        if ($this->treeMemo !== null) return $this->treeMemo;

        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return $this->treeMemo = [];

        $decoded = json_decode(file_get_contents($path), true);
        return $this->treeMemo = is_array($decoded) ? $decoded : [];
    }

    /**
     * Load the protocols list from storage/app/product/protocols.json.
     * Returns the raw `protocols` array (not wrapped). Empty array on
     * missing/malformed file. Cached for an hour — bust with clearCache().
     *
     * The file shape:
     *   { "protocols": [ { id, title, phases: [ { id, title, groups: [...] } ] }, ... ] }
     */
    public function loadProtocols(): array
    {
        $protocols = Cache::remember(
            self::CACHE_KEY_PROTOCOLS,
            now()->addMinutes(self::CACHE_MINUTES),
            function () {
                $path = storage_path('app/product/protocols.json');
                if (!file_exists($path)) return [];

                $decoded = json_decode(file_get_contents($path), true);
                if (!is_array($decoded)) return [];

                $protocols = $decoded['protocols'] ?? [];
                return is_array($protocols) ? $protocols : [];
            }
        );

        return is_array($protocols) ? $protocols : [];
    }

    /**
     * Given a child id (e.g. "cer-trau-tor") from the rules tree, return
     * the protocolId attached to that child — or null when the child has
     * no protocol. Used by the API to decide whether to surface the
     * protocol screen on the mobile app or skip straight to products.
     */
    public function protocolIdForChild(string $childId): ?string
    {
        foreach ($this->loadTree() as $node) {
            foreach (($node['children'] ?? []) as $child) {
                if (($child['id'] ?? null) === $childId) {
                    $pid = $child['protocolId'] ?? null;
                    return (is_string($pid) && $pid !== '') ? $pid : null;
                }
            }
        }
        return null;
    }

    // ---------------------------------------------------------------
    // Derived maps
    // ---------------------------------------------------------------

    /**
     * Build a map: childId => nodeId   (which node OWNS this child).
     *
     * Each childId belongs to exactly one node in the tree (the one
     * whose `children[]` array contains it). This is the "group" the
     * child is part of for matching purposes.
     */
    public function groupMap(): array
    {
        if ($this->groupMapMemo !== null) return $this->groupMapMemo;

        $map = Cache::remember(
            self::CACHE_KEY_GROUP_MAP,
            now()->addMinutes(self::CACHE_MINUTES),
            function () {
                $map = [];
                foreach ($this->loadTree() as $node) {
                    $nodeId = $node['id'] ?? null;
                    if (!is_string($nodeId) || $nodeId === '') continue;

                    foreach (($node['children'] ?? []) as $child) {
                        $cid = $child['id'] ?? null;
                        if (is_string($cid) && $cid !== '') {
                            $map[$cid] = $nodeId;
                        }
                    }
                }
                return $map;
            }
        );

        return $this->groupMapMemo = is_array($map) ? $map : [];
    }

    /**
     * Set of node ids (array_flip'd for O(1) isset lookup) — used to
     * detect "generic" tags, i.e. a tag whose id IS a group id.
     */
    public function groupIds(): array
    {
        if ($this->groupIdsMemo !== null) return $this->groupIdsMemo;

        return $this->groupIdsMemo = array_flip(
            array_values(array_unique(array_values($this->groupMap())))
        );
    }

    /**
     * Build a map: bucketChildId => ['dimensionKey' => ..., 'min' => N, 'max' => N]
     *
     * Only includes children of measurement nodes (nodes with a
     * top-level "dimensionKey" field). Children without numeric
     * min/max are SKIPPED — they're malformed for a measurement bucket.
     */
    public function bucketMap(): array
    {
        if ($this->bucketMapMemo !== null) return $this->bucketMapMemo;

        $map = Cache::remember(
            self::CACHE_KEY_BUCKET_MAP,
            now()->addMinutes(self::CACHE_MINUTES),
            function () {
                $map = [];
                foreach ($this->loadTree() as $node) {
                    $dimKey = $node['dimensionKey'] ?? null;
                    if (!is_string($dimKey) || $dimKey === '') continue;

                    foreach (($node['children'] ?? []) as $child) {
                        $cid = $child['id'] ?? null;
                        if (!is_string($cid) || $cid === '') continue;
                        if (!isset($child['min']) || !isset($child['max'])) continue;
                        if (!is_numeric($child['min']) || !is_numeric($child['max'])) continue;

                        $map[$cid] = [
                            'dimensionKey' => $dimKey,
                            'min'          => (float) $child['min'],
                            'max'          => (float) $child['max'],
                        ];
                    }
                }
                return $map;
            }
        );

        return $this->bucketMapMemo = is_array($map) ? $map : [];
    }

    /**
     * Build a map: childId => [parentChildId, ...]
     *
     * Parent semantics:
     *   1. Every concrete child (e.g. "age-u25") has its node id ("age")
     *      as a parent IF that node id is itself a childId in some other
     *      node (e.g. "age" is a child of "patient"). This mirrors the
     *      indicator pattern.
     *   2. The node id itself ("age") is NOT a child id — only entries
     *      that appear as `children[].id` somewhere are valid filter keys.
     */
    public function parentMap(): array
    {
        $map = Cache::remember(
            self::CACHE_KEY_PARENT_MAP,
            now()->addMinutes(self::CACHE_MINUTES),
            function () {
                return $this->buildParentMap();
            }
        );

        return is_array($map) ? $map : [];
    }

    /**
     * Set of node ids that are measurement nodes (have a dimensionKey).
     * Used to detect non-measurement (pathology) tags vs measurement
     * buckets when partitioning a user's filter list.
     */
    public function measurementNodeIds(): array
    {
        $ids = Cache::remember(
            self::CACHE_KEY_MEASUREMENT_NODE_IDS,
            now()->addMinutes(self::CACHE_MINUTES),
            function () {
                $ids = [];
                foreach ($this->loadTree() as $node) {
                    $dk = $node['dimensionKey'] ?? null;
                    if (is_string($dk) && $dk !== '' && isset($node['id'])) {
                        $ids[] = $node['id'];
                    }
                }
                return $ids;
            }
        );

        return is_array($ids) ? $ids : [];
    }

    /**
     * Return all child IDs belonging to a given group (nodeId).
     */
    public function childrenOfGroup(string $nodeId): array
    {
        foreach ($this->loadTree() as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return array_values(array_filter(array_map(
                    fn($c) => $c['id'] ?? null,
                    $node['children'] ?? []
                )));
            }
        }
        return [];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_GROUP_MAP);
        Cache::forget(self::CACHE_KEY_PARENT_MAP);
        Cache::forget(self::CACHE_KEY_BUCKET_MAP);
        Cache::forget(self::CACHE_KEY_MEASUREMENT_NODE_IDS);
        Cache::forget(self::CACHE_KEY_PROTOCOLS);
    }

    // ---------------------------------------------------------------
    // Matching
    // ---------------------------------------------------------------

    /**
     * Master match function: does this product satisfy the user's
     * filter selection?
     *
     * Combines three independent checks:
     *   1. Pathology tags (per-group rule, see pathologyMatch())
     *   2. Measurement buckets (range-overlap with product.sizes, see
     *      measurementMatch())
     *   3. Protocol group (when a doctor has drilled into a protocol's
     *      group, the product must be tagged with that group id).
     *
     * Product passes if ALL active checks pass. If user supplied no
     * filter / no groupId in a category, that category's check is
     * vacuously true.
     */
    public function productMatches(Product $product, array $userFilters, ?string $groupId = null): bool
    {
        $hasFilters = !empty($userFilters);
        $hasGroup   = is_string($groupId) && $groupId !== '';
        if (!$hasFilters && !$hasGroup) return true;

        // Split user's filters into pathology tags vs measurement buckets.
        $bucketMap = $hasFilters ? $this->bucketMap() : [];
        $pathologyFilters = [];
        $measurementFilters = [];
        foreach ($userFilters as $f) {
            if (!is_string($f) || $f === '') continue;
            if (isset($bucketMap[$f])) {
                $measurementFilters[] = $f;
            } else {
                $pathologyFilters[] = $f;
            }
        }

        // Pathology check uses the product's filter tags.
        if (!empty($pathologyFilters)) {
            $tags = $product->relationLoaded('filters')
                ? $product->filters->pluck('filter_key')->all()
                : $product->filters()->pluck('filter_key')->all();
            if (!$this->pathologyMatch($tags, $pathologyFilters)) {
                return false;
            }
        }

        // Measurement check uses the product's structured sizes JSON.
        if (!empty($measurementFilters)) {
            if (!$this->measurementMatch($product->sizes, $measurementFilters)) {
                return false;
            }
        }

        // User đã gửi filter, nhưng sau khi loại OPTIONAL_GROUPS thì
        // không còn tiêu chí nào thực sự lọc → không trả product nào.
        // (Duyệt toàn bộ danh sách vẫn được: không filter gì cả thì đã
        // return true ở đầu hàm.)
        if (!$hasGroup && !$this->hasMandatoryFilters($pathologyFilters) && empty($measurementFilters)) {
            return false;
        }

        // Protocol group check — when set, product must be tagged with
        // this exact group id. Plain set membership against the product's
        // `groups` JSON column.
        if ($hasGroup) {
            $groups = $product->groups ?? [];
            if (!is_array($groups) || !in_array($groupId, $groups, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pathology per-group match.
     *
     * Rule per group:
     *   0. Product has NO valid tag at all → excluded.
     *   1. User has no filter in a group → group doesn't restrict.
     *   2. Product has no tag in a group the user filtered on →
     *      excluded, UNLESS the group is in OPTIONAL_GROUPS.
     *   3. Product has ONLY the generic tag (= the group's nodeId) →
     *      "any sub-option" → match.
     *   4. Product has at least one SPECIFIC tag in the group → ignore
     *      any generic tag it might also have; match only if the user's
     *      filter shares at least one specific tag.
     *
     * Across groups: product must pass EVERY group the user filtered on.
     */
    public function pathologyMatch(array $productTags, array $userFilters): bool
    {
        if (empty($userFilters)) return true;

        $groupMap = $this->groupMap();
        $groupIds = $this->groupIds();

        $productByGroup = $this->indexTagsByGroup($productTags, $groupMap, $groupIds);

        // Rule 0: untagged (or only stale/unknown tags) → not a candidate.
        if (empty($productByGroup)) return false;

        $userByGroup = $this->indexTagsByGroup($userFilters, $groupMap, $groupIds);

        $optional = array_flip(self::OPTIONAL_GROUPS);

        foreach ($userByGroup as $gid => $userBucket) {
            $productBucket = $productByGroup[$gid] ?? null;

            // Rule 2: product says nothing about this group.
            if ($productBucket === null) {
                if (isset($optional[$gid])) continue;   // ngoại lệ → bỏ qua
                return false;                            // bắt buộc → loại
            }

            // Rule 4: product has specifics → user must share at least one.
            if (!empty($productBucket['specifics'])) {
                $overlap = array_intersect($productBucket['specifics'], $userBucket['specifics']);
                if (empty($overlap)) return false;
                continue;
            }

            // Rule 3: product has only generic → match anything in group.
            if ($productBucket['generic']) continue;

            // Không generic cũng không specific — không nên xảy ra, loại cho chắc.
            return false;
        }

        return true;
    }

    /**
     * Helper: split a flat list of tags into per-group buckets.
     */
    private function indexTagsByGroup(array $tags, array $groupMap, array $groupIds): array
    {
        $out = [];
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '') continue;
            // Generic tag = tag id is a group id (= a node id).
            if (isset($groupIds[$tag])) {
                if (!isset($out[$tag])) $out[$tag] = ['generic' => false, 'specifics' => []];
                $out[$tag]['generic'] = true;
            } elseif (isset($groupMap[$tag])) {
                $g = $groupMap[$tag];
                if (!isset($out[$g])) $out[$g] = ['generic' => false, 'specifics' => []];
                $out[$g]['specifics'][] = $tag;
            }
            // Tags not in the map are stale/unknown — ignore.
        }
        return $out;
    }

    /**
     * Measurement match — every user-selected bucket must have at least
     * one product size option whose measurement for the same dimension
     * overlaps the bucket's [min, max] range (inclusive boundaries).
     *
     * Schema expected for $sizes:
     *   {
     *     "options": [
     *       {
     *         "label": "Taille 1",
     *         "displayRange": "19-21 cm",
     *         "measurements": {
     *           "tour_de_cheville": { "min": 19, "max": 21, "displayRange": "19 cm - 21 cm" }
     *         }
     *       },
     *       ...
     *     ]
     *   }
     *
     * Rules:
     *   - No sizes data → fails (we can't promise fit).
     *   - User picked a bucket for dimension X, but product has no
     *     option that declares X → fails for that bucket.
     *   - Otherwise: at least one option's [opt.min, opt.max] must
     *     overlap bucket's [bk.min, bk.max] (inclusive).
     */
    public function measurementMatch($sizes, array $bucketIds): bool
    {
        if (empty($bucketIds)) return true;

        $bucketMap = $this->bucketMap();

        // Without sizes data, we cannot guarantee any taille fits — fail.
        if (!is_array($sizes)) return false;
        $options = $sizes['options'] ?? null;
        if (!is_array($options) || empty($options)) return false;

        foreach ($bucketIds as $bucketId) {
            $bucket = $bucketMap[$bucketId] ?? null;
            if (!$bucket) {
                // Bucket id unknown (stale data) — be strict and fail.
                return false;
            }
            $dimKey = $bucket['dimensionKey'];
            $bMin   = $bucket['min'];
            $bMax   = $bucket['max'];

            $matched = false;
            foreach ($options as $opt) {
                if (!is_array($opt)) continue;
                $measurements = $opt['measurements'] ?? null;
                if (!is_array($measurements)) continue;
                $m = $measurements[$dimKey] ?? null;
                if (!is_array($m)) continue;
                if (!isset($m['min']) || !isset($m['max'])) continue;
                if (!is_numeric($m['min']) || !is_numeric($m['max'])) continue;

                // Inclusive overlap: a.min <= b.max && b.min <= a.max
                if ((float) $m['min'] <= $bMax && $bMin <= (float) $m['max']) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) return false;
        }

        return true;
    }

    // ---------------------------------------------------------------
    // Parent map internals
    // ---------------------------------------------------------------

    /**
     * Walk a rule and collect every (nodeId, childId) reference,
     * returning just the childIds. Used to detect explicit parent links.
     */
    private function collectRuleChildRefs($rule): array
    {
        if (!$rule || !is_array($rule)) return [];

        if (isset($rule['kind'])) {
            if ($rule['kind'] === 'selected' && isset($rule['childId'])) {
                return [$rule['childId']];
            }
            return [];
        }

        if (isset($rule['op'])) {
            $op = $rule['op'];
            if (in_array($op, ['AND', 'OR'], true)) {
                $out = [];
                foreach (($rule['rules'] ?? []) as $r) {
                    $out = array_merge($out, $this->collectRuleChildRefs($r));
                }
                return $out;
            }
            if ($op === 'NOT') {
                return $this->collectRuleChildRefs($rule['rule'] ?? null);
            }
        }
        return [];
    }

    private function buildParentMap(): array
    {
        $tree = $this->loadTree();
        $parentMap = [];

        // All child ids in the tree — for detecting indicator-pattern parents.
        $allChildIds = [];
        foreach ($tree as $node) {
            foreach (($node['children'] ?? []) as $child) {
                if (isset($child['id'])) $allChildIds[$child['id']] = true;
            }
        }

        foreach ($tree as $node) {
            $nodeId = $node['id'] ?? null;
            if ($nodeId === null) continue;

            $isIndicator = isset($allChildIds[$nodeId]);

            foreach (($node['children'] ?? []) as $child) {
                $cid = $child['id'] ?? null;
                if ($cid === null) continue;

                if (!isset($parentMap[$cid])) $parentMap[$cid] = [];

                if ($isIndicator) {
                    $parentMap[$cid][] = $nodeId;
                }

                $rule = $child['visibilityRule'] ?? null;
                foreach ($this->collectRuleChildRefs($rule) as $ref) {
                    $parentMap[$cid][] = $ref;
                }
            }
        }

        foreach ($parentMap as $k => $list) {
            $parentMap[$k] = array_values(array_unique($list));
        }

        return $parentMap;
    }

    /**
     * Expand a flat list of filter keys with all transitive parents.
     *
     * Input:  ['age-u25', 'rhumato']
     * Output: ['age-u25', 'rhumato', 'age']
     *
     * Pure function over the cached parentMap. Order is not guaranteed;
     * caller should treat it as a set.
     */
    public function expandWithParents(array $keys): array
    {
        $map = $this->parentMap();
        $result = [];
        $queue  = $keys;

        while (!empty($queue)) {
            $k = array_shift($queue);
            if (isset($result[$k])) continue;
            $result[$k] = true;

            foreach ($map[$k] ?? [] as $parent) {
                if (!isset($result[$parent])) {
                    $queue[] = $parent;
                }
            }
        }

        return array_keys($result);
    }
}
