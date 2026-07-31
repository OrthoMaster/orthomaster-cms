<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Bulk pathology-filter assignment via Excel round-trip.
 *
 * Flow:
 *   1. export()        → .xlsx with 2 sheets (Produits + Groupes)
 *   2. admin fills the `pathology_filters` column with a group code
 *   3. importPreview() → parse + validate, nothing written
 *   4. importApply()   → backup, then overwrite product_filters
 *
 * Groups live ONLY in the workbook (sheet "Groupes"). Nothing is
 * persisted server-side — rules.json stays the single source of truth
 * for what a valid key is.
 */
class ProductPreDataController extends Controller
{
    private const SHEET_PRODUCTS = 'Produits';
    private const SHEET_GROUPS   = 'Groupes';
    private const GROUP_ROWS     = 500;   // dropdown range size on sheet 2
    private const CACHE_PREFIX   = 'predata.import.';
    private const CACHE_MINUTES  = 30;

    // ===============================================================
    // Screens
    // ===============================================================

    public function index()
    {
        return view('products.predata.index', [
            'productCount' => Product::count(),
        ]);
    }

    // ===============================================================
    // Export
    // ===============================================================

    public function export()
    {
        $products = Product::with('manufacturer')->orderBy('id')->get();

        $book = new Spreadsheet();

        // index 0 — REUSE the default sheet instead of creating one,
        // otherwise an empty "Worksheet" survives in the output.
        $sheetProducts = $book->getSheet(0);
        $sheetProducts->setTitle(self::SHEET_PRODUCTS);
        $this->buildProductsSheet($sheetProducts, $products);

        // index 1
        $sheetGroups = $book->createSheet(1);
        $sheetGroups->setTitle(self::SHEET_GROUPS);
        $this->buildGroupsSheet($sheetGroups);

        // Safety net: drop any leftover sheet.
        foreach ($book->getSheetNames() as $name) {
            if (!in_array($name, [self::SHEET_PRODUCTS, self::SHEET_GROUPS], true)) {
                $book->removeSheetByIndex($book->getIndex($book->getSheetByName($name)));
            }
        }

        $book->setActiveSheetIndex(0);

        $filename = 'products_pathology_' . now()->format('Ymd_His') . '.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'predata');
        (new Xlsx($book))->save($tmp);
        $book->disconnectWorksheets();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function buildProductsSheet(Worksheet $sheet, $products): void
    {
        $headers = ['id', 'name', 'fabricant', 'pathology_filters', 'source'];
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, 'A1:E1', 'FF1D3557');

        $row = 2;

        foreach ($products as $p) {
            $sheet->setCellValueExplicit("A{$row}", $p->id, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("B{$row}", (string) $p->name);
            $sheet->setCellValue("C{$row}", (string) optional($p->manufacturer)->name);
            $sheet->setCellValue("D{$row}", '');                       // to fill
            $sheet->setCellValueExplicit("E{$row}", (string) ($p->source ?? ''), DataType::TYPE_STRING);
            $row++;
        }

        $lastRow = max(2, $row - 1);

        // Dropdown on column D → Groupes!$A$2:$A$500
        // NOTE: no leading "=" — PhpSpreadsheet adds it; leaving it in
        // makes Excel flag the file and silently strip the validation.
        for ($r = 2; $r <= $lastRow; $r++) {
            $dv = $sheet->getCell("D{$r}")->getDataValidation();
            $dv->setType(DataValidation::TYPE_LIST)
               ->setErrorStyle(DataValidation::STYLE_INFORMATION)
               ->setAllowBlank(true)
               ->setShowDropDown(true)
               ->setShowInputMessage(true)
               ->setShowErrorMessage(true)
               ->setErrorTitle('Code inconnu')
               ->setError("Choisissez un code défini dans l'onglet « " . self::SHEET_GROUPS . " ».")
               ->setPromptTitle('Groupe de pathologies')
               ->setPrompt('Laisser vide = produit ignoré à l\'import.')
               ->setFormula1(self::SHEET_GROUPS . '!$A$2:$A$' . self::GROUP_ROWS);
        }

        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("D2:D{$lastRow}")->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF9E0');

        foreach (['A' => 8, 'B' => 46, 'C' => 20, 'D' => 30, 'E' => 46] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:E{$lastRow}");
    }

    private function buildGroupsSheet(Worksheet $sheet): void
    {
        $sheet->fromArray(['code', 'label', 'keys'], null, 'A1');
        $this->styleHeader($sheet, 'A1:C1', 'FF2A9D8F');

        $samples = $this->sampleGroups();

        foreach ($samples as $i => $g) {
            $r = $i + 2;
            $sheet->setCellValueExplicit("A{$r}", $g['code'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$r}", $g['label']);
            $sheet->setCellValueExplicit("C{$r}", implode(',', $g['keys']), DataType::TYPE_STRING);
        }

        // Visually flag the sample rows.
        $lastSample = count($samples) + 1;
        $sheet->getStyle("A2:C{$lastSample}")->getFill()
              ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAF7F5');
        $sheet->getStyle("A2:C{$lastSample}")->getFont()->getColor()->setARGB('FF3D6B65');

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(110);
        $sheet->freezePane('A2');

        $help = [
            'EXEMPLES ci-dessus — modifiez-les ou ajoutez vos lignes en dessous.',
            '',
            'code   : identifiant libre, apparaît dans la liste déroulante de l\'onglet Produits.',
            'label  : description, non utilisée à l\'import.',
            'keys   : liste copiée depuis « Edit product » → bouton Copy sous Pathology filters.',
            '',
            'La casse est ignorée. Collez la liste ENTIÈRE : les options parentes',
            '(zone, type de pathologie, segment…) sont indispensables, sinon les',
            'pathologies enfants sont rejetées à l\'import.',
        ];
        foreach ($help as $i => $line) {
            $sheet->setCellValue('E' . ($i + 2), $line);
        }
        $sheet->getStyle('E2')->getFont()->setBold(true);
        $sheet->getStyle('E2:E' . (count($help) + 1))->getFont()->getColor()->setARGB('FF7D8A9C');
        $sheet->getColumnDimension('E')->setWidth(80);
    }

    private function styleHeader(Worksheet $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    /**
     * Two ready-made rows so the file is usable out of the box. Both
     * chains are verified visible against the current rules.json.
     */
    private function sampleGroups(): array
    {
        return [
            [
                'code'  => 'TRONC-CERV-TRAU',
                'label' => 'Tronc / Cervical irradiant / Traumato',
                'keys'  => [
                    'tronc', 'traumato', 'tronc-rac-hau', 'rach-hau-cerv', 'tronc-cerv-1',
                    'path-tronc-cerv-1-trau-1', 'path-tronc-cerv-1-trau-2',
                ],
            ],
            [
                'code'  => 'GENOU-LCA-TRAU',
                'label' => 'Membre inf. / Genou global / LCA / Traumato',
                'keys'  => [
                    'mem-inf', 'traumato', 'mem-inf-gen', 'gen-glob', 'memi-gegl-1',
                    'path-gegllca-trau-1', 'path-gegllca-trau-2', 'path-gegllca-trau-3',
                ],
            ],
        ];
    }

    // ===============================================================
    // Import — preview
    // ===============================================================

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        $path = $request->file('file')->getRealPath();

        try {
            $parsed = $this->parseWorkbook($path);
        } catch (\Throwable $e) {
            return back()->with('message', 'Lecture impossible : ' . $e->getMessage())
                         ->with('alert-type', 'error');
        }

        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX . $token, $parsed, now()->addMinutes(self::CACHE_MINUTES));

        return view('products.predata.preview', [
            'token'    => $token,
            'groups'   => $parsed['groups'],
            'changes'  => $parsed['changes'],
            'errors'   => $parsed['errors'],
            'warnings' => $parsed['warnings'],
            'skipped'  => $parsed['skipped'],
        ]);
    }

    /**
     * Parse both sheets and diff against the DB. Writes nothing.
     *
     * Returns:
     *   groups   => [code => ['label'=>, 'keys'=>[]]]
     *   rows     => [productId => [key, ...]]     (what would be written)
     *   changes  => [ ['id','name','code','added'=>[],'removed'=>[],'total'=>n], ... ]
     *   errors   => ['Ligne 12 : ...']
     *   warnings => [...]
     *   skipped  => int
     */
    private function parseWorkbook(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $errors = [];
        $warnings = [];

        // ---- sheet 2: groups -------------------------------------
        $gSheet = $book->getSheetByName(self::SHEET_GROUPS);
        if (!$gSheet) {
            throw new \RuntimeException('Onglet « ' . self::SHEET_GROUPS . ' » introuvable.');
        }

        $validKeys = $this->validPathologyKeys();
        $bucketIds = array_flip($this->bucketIds());
        $groups = [];

        foreach ($gSheet->getRowIterator(2) as $row) {
            $r    = $row->getRowIndex();
            $code = $this->normalizeCode((string) $gSheet->getCell("A{$r}")->getValue());
            $raw  = (string) $gSheet->getCell("C{$r}")->getValue();
            if ($code === '' && trim($raw) === '') continue;

            if ($code === '') {
                $errors[] = "Groupes L{$r} : clés renseignées sans code.";
                continue;
            }
            if (isset($groups[$code])) {
                $errors[] = "Groupes L{$r} : code « {$code} » dupliqué.";
                continue;
            }

            $keys = $this->normalizeKeyList($raw);
            if (empty($keys)) {
                $errors[] = "Groupes L{$r} : code « {$code} » n'a aucune clé.";
                continue;
            }

            $unknown = array_values(array_diff($keys, $validKeys));
            $buckets = array_values(array_filter($keys, fn($k) => isset($bucketIds[$k])));

            if ($buckets) {
                $errors[] = "Groupes L{$r} ({$code}) : clés de mesure interdites — " . implode(', ', $buckets);
                continue;
            }
            if ($unknown) {
                $errors[] = "Groupes L{$r} ({$code}) : clés inconnues dans rules.json — " . implode(', ', $unknown);
                continue;
            }

            $invisible = $this->invisibleKeys($keys);
            if ($invisible) {
                $warnings[] = "Groupe {$code} : clé(s) non atteignable(s) sans parent — "
                            . implode(', ', $invisible) . ". Le produit risque de ne jamais matcher.";
            }

            $groups[$code] = [
                'label' => trim((string) $gSheet->getCell("B{$r}")->getValue()),
                'keys'  => $keys,
            ];
        }

        if (empty($groups)) {
            throw new \RuntimeException('Aucun groupe valide dans l\'onglet « ' . self::SHEET_GROUPS . ' ».');
        }

        // ---- sheet 1: products -----------------------------------
        $pSheet = $book->getSheetByName(self::SHEET_PRODUCTS);
        if (!$pSheet) {
            throw new \RuntimeException('Onglet « ' . self::SHEET_PRODUCTS . ' » introuvable.');
        }

        $rows = [];
        $seen = [];
        $skipped = 0;

        foreach ($pSheet->getRowIterator(2) as $row) {
            $r    = $row->getRowIndex();
            $id   = trim((string) $pSheet->getCell("A{$r}")->getValue());
            $code = $this->normalizeCode((string) $pSheet->getCell("D{$r}")->getValue());

            if ($id === '' && $code === '') continue;

            if ($code === '') { $skipped++; continue; }

            if (!ctype_digit($id)) {
                $errors[] = "Produits L{$r} : id « {$id} » invalide.";
                continue;
            }
            $id = (int) $id;

            if (isset($seen[$id])) {
                $errors[] = "Produits L{$r} : produit #{$id} présent plusieurs fois.";
                continue;
            }
            if (!isset($groups[$code])) {
                $errors[] = "Produits L{$r} : code « {$code} » absent de l'onglet " . self::SHEET_GROUPS . '.';
                continue;
            }

            $seen[$id] = $r;
            $rows[$id] = ['code' => $code, 'keys' => $groups[$code]['keys']];
        }

        // ---- diff against DB -------------------------------------
        $ids = array_keys($rows);
        $products = Product::whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');
        $current = ProductFilter::whereIn('product_id', $ids)
            ->get(['product_id', 'filter_key'])
            ->groupBy('product_id')
            ->map(fn($g) => $g->pluck('filter_key')->all());

        $changes = [];
        $final = [];

        foreach ($rows as $id => $info) {
            if (!isset($products[$id])) {
                $errors[] = "Produit #{$id} introuvable en base.";
                continue;
            }
            $old = $current[$id] ?? [];
            $new = $info['keys'];

            $final[$id] = $new;
            $added   = array_values(array_diff($new, $old));
            $removed = array_values(array_diff($old, $new));

            if (empty($added) && empty($removed)) continue;   // no-op

            $changes[] = [
                'id'      => $id,
                'name'    => $products[$id]->name,
                'code'    => $info['code'],
                'added'   => $added,
                'removed' => $removed,
                'total'   => count($new),
            ];
        }

        $book->disconnectWorksheets();

        return [
            'groups'   => $groups,
            'rows'     => $final,
            'changes'  => $changes,
            'errors'   => $errors,
            'warnings' => $warnings,
            'skipped'  => $skipped,
        ];
    }

    // ===============================================================
    // Import — apply
    // ===============================================================

    public function importApply(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);

        $parsed = Cache::get(self::CACHE_PREFIX . $request->token);
        if (!$parsed) {
            return redirect()->route('products.predata.index')
                ->with('message', 'Aperçu expiré. Relancez l\'import.')
                ->with('alert-type', 'error');
        }
        if (!empty($parsed['errors'])) {
            return redirect()->route('products.predata.index')
                ->with('message', 'Import bloqué : corrigez les erreurs d\'abord.')
                ->with('alert-type', 'error');
        }

        $rows = $parsed['rows'] ?? [];
        if (empty($rows)) {
            return redirect()->route('products.predata.index')
                ->with('message', 'Rien à appliquer.')
                ->with('alert-type', 'warning');
        }

        $ids = array_keys($rows);
        $backupPath = $this->backup($ids);

        $usesTimestamps = (new ProductFilter)->usesTimestamps();
        $now = now();

        DB::transaction(function () use ($ids, $rows, $usesTimestamps, $now) {
            foreach (array_chunk($ids, 500) as $chunk) {
                ProductFilter::whereIn('product_id', $chunk)->delete();
            }

            $insert = [];
            foreach ($rows as $pid => $keys) {
                foreach ($keys as $k) {
                    $r = ['product_id' => $pid, 'filter_key' => $k];
                    if ($usesTimestamps) {
                        $r['created_at'] = $now;
                        $r['updated_at'] = $now;
                    }
                    $insert[] = $r;
                }
            }
            foreach (array_chunk($insert, 1000) as $chunk) {
                ProductFilter::insert($chunk);
            }
        });

        Cache::forget(self::CACHE_PREFIX . $request->token);

        return redirect()->route('products.predata.index')
            ->with('message', count($ids) . " produit(s) mis à jour. Sauvegarde : {$backupPath}")
            ->with('alert-type', 'success');
    }

    private function backup(array $ids): string
    {
        $data = ProductFilter::whereIn('product_id', $ids)
            ->get(['product_id', 'filter_key'])
            ->groupBy('product_id')
            ->map(fn($g) => $g->pluck('filter_key')->values()->all())
            ->all();

        $path = 'backups/product_filters_' . now()->format('Ymd_His') . '.json';
        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $path;
    }

    // ===============================================================
    // rules.json helpers
    // ===============================================================

    private function tree(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $path = storage_path('app/product/rules.json');
        if (!file_exists($path)) return $cached = [];
        $decoded = json_decode(file_get_contents($path), true);
        return $cached = (is_array($decoded) ? $decoded : []);
    }

    /** Every child id that is NOT a measurement bucket. */
    private function validPathologyKeys(): array
    {
        $buckets = array_flip($this->bucketIds());
        $out = [];
        foreach ($this->tree() as $node) {
            foreach (($node['children'] ?? []) as $c) {
                $id = $c['id'] ?? null;
                if (is_string($id) && $id !== '' && !isset($buckets[$id])) $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    /** Children of nodes carrying a dimensionKey — never valid filter keys. */
    private function bucketIds(): array
    {
        $ids = [];
        foreach ($this->tree() as $node) {
            $dk = $node['dimensionKey'] ?? null;
            if (!is_string($dk) || $dk === '') continue;
            foreach (($node['children'] ?? []) as $c) {
                if (isset($c['id']) && is_string($c['id'])) $ids[] = $c['id'];
            }
        }
        return $ids;
    }

    private function childToNode(): array
    {
        $map = [];
        foreach ($this->tree() as $node) {
            foreach (($node['children'] ?? []) as $c) {
                if (isset($c['id'])) $map[$c['id']] = $node['id'];
            }
        }
        return $map;
    }

    /**
     * PHP mirror of the JS evaluator in _form_scripts.blade.php.
     * Flags keys that would be pruned by the edit screen — i.e. the
     * admin copied a partial list without its parent chain.
     */
    private function invisibleKeys(array $keys): array
    {
        $childToNode = $this->childToNode();
        $keySet = array_flip($keys);

        $sel = [];
        foreach ($keys as $k) {
            $n = $childToNode[$k] ?? null;
            if ($n !== null) $sel[$n][] = $k;
        }

        $bad = [];
        foreach ($this->tree() as $node) {
            $nodeOk = $this->evaluateRule($node['visibilityRule'] ?? null, $sel);
            foreach (($node['children'] ?? []) as $c) {
                $cid = $c['id'] ?? null;
                if (!isset($keySet[$cid])) continue;
                if (!$nodeOk || !$this->evaluateRule($c['visibilityRule'] ?? null, $sel)) {
                    $bad[] = $cid;
                }
            }
        }
        return array_values(array_unique($bad));
    }

    private function evaluateRule($rule, array $sel): bool
    {
        if (!$rule || !is_array($rule)) return true;

        if (isset($rule['kind'])) {
            if ($rule['kind'] === 'selected') {
                $list = $sel[$rule['nodeId'] ?? ''] ?? [];
                return in_array($rule['childId'] ?? '', $list, true);
            }
            if ($rule['kind'] === 'any_selected') {
                return !empty($sel[$rule['nodeId'] ?? ''] ?? []);
            }
            return false;
        }

        $op = $rule['op'] ?? null;
        if ($op === 'AND') {
            foreach (($rule['rules'] ?? []) as $r) {
                if (!$this->evaluateRule($r, $sel)) return false;
            }
            return true;
        }
        if ($op === 'OR') {
            foreach (($rule['rules'] ?? []) as $r) {
                if ($this->evaluateRule($r, $sel)) return true;
            }
            return false;
        }
        if ($op === 'NOT') {
            return !$this->evaluateRule($rule['rule'] ?? null, $sel);
        }
        return false;
    }

    // ===============================================================
    // Normalization — Excel mangles case and separators
    // ===============================================================

    private function normalizeCode(?string $s): string
    {
        return mb_strtoupper(trim((string) $s), 'UTF-8');
    }

    /**
     * Accepts "a,b,c", "A, B, C", newline-separated, or a pasted JSON
     * array. Everything is lowercased — Excel autocapitalizes the first
     * letter of a cell and all ids in rules.json are lowercase.
     */
    private function normalizeKeyList(?string $raw): array
    {
        $raw = (string) $raw;
        if (trim($raw) === '') return [];

        $raw = str_replace(["\r", "\n", "\t", ';', '|'], ',', $raw);
        $raw = trim($raw, " \t\n\r\0\x0B[]");

        $out = [];
        foreach (explode(',', $raw) as $p) {
            $p = trim($p, " \t\n\r\0\x0B\"'[]");
            if ($p === '') continue;
            $out[] = mb_strtolower($p, 'UTF-8');
        }
        return array_values(array_unique($out));
    }
}