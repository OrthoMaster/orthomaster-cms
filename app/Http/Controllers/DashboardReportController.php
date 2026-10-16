<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardReportController extends Controller
{
    /**
     * Rapports disponibles.
     * export_only : ni période ni tableau à l'écran, seulement l'export Excel.
     */
    private const REPORTS = [
        'activity' => [
            'label'       => 'Activité et inscriptions',
            'description' => "Ouvertures de l'application, utilisateurs actifs et nouvelles inscriptions, mois par mois.",
        ],
        'revenue' => [
            'label'       => 'Revenus par produit',
            'description' => 'Répartition du chiffre d\'affaires entre les produits, au prorata de leur durée.',
        ],
        'users' => [
            'label'       => 'Liste des utilisateurs',
            'description' => "Tous les utilisateurs de l'application à date, avec leur profil et leur dernière activité. Export Excel uniquement.",
            'export_only' => true,
        ],
    ];

    /** Rôles correspondant aux utilisateurs de l'application */
    private const USER_ROLES = [2, 4];

    /** Préfixe identifiant les comptes dupliqués, exclus des inscriptions */
    private const CLONE_PREFIX = 'CLONE';

    /** Statut d'un panier finalisé */
    private const CART_SIGNED = 3;

    /** Décalage UTC → Asia/Ho_Chi_Minh, la base stockant tout en UTC */
    private const LOCAL_OFFSET = 420;

    /** Couleur d'en-tête des tableaux exportés */
    private const HEADER_COLOR = '22A7F0';

    /**
     * Formats Excel appliqués selon le type de colonne.
     * string : forcé en texte pour conserver les zéros initiaux (téléphone, code postal).
     */
    private const CELL_FORMATS = [
        'money'    => '#,##0.00 €',
        'percent'  => '0.0 "%"',
        'decimal'  => '#,##0.0',
        'int'      => '#,##0',
        'datetime' => 'dd/mm/yyyy hh:mm',
        'string'   => '@',
    ];

    /**
     * Consultation des rapports.
     * GET /dashboard/reports
     */
    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return view('dashboard.reports', [
            'reports' => self::REPORTS,
            'filters' => $f,
            // Inutile de calculer ce qui ne sera pas affiché
            'tables'  => $this->isExportOnly($f['report']) ? [] : $this->buildTables($f),
        ]);
    }

    /**
     * Export Excel : une feuille par tableau.
     * GET /dashboard/reports/export
     */
    public function export(Request $request): StreamedResponse
    {
        $f      = $this->resolveFilters($request);
        $tables = $this->buildTables($f);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle(self::REPORTS[$f['report']]['label'])
            ->setCreator('Dashboard');

        foreach (array_values($tables) as $i => $table) {
            $sheet = $i === 0
                ? $spreadsheet->setActiveSheetIndex(0)
                : $spreadsheet->createSheet();

            $this->fillSheet($sheet, $table, $f);
        }

        $spreadsheet->setActiveSheetIndex(0);

        if ($this->isExportOnly($f['report'])) {
            $filename = sprintf('%s_%s.xlsx', $f['report'], $this->localNow()->format('Ymd'));
        } else {
            $filename = sprintf(
                '%s_%s_%s.xlsx',
                $f['report'],
                str_replace('-', '', $f['from']),
                str_replace('-', '', $f['to'])
            );
        }

        return $this->streamXlsx($spreadsheet, $filename);
    }

    /* ===================== FILTRES ===================== */

    /**
     * Rapport choisi et période, exprimée en mois.
     */
    private function resolveFilters(Request $request): array
    {
        $report = $request->query('report');

        if (!array_key_exists($report, self::REPORTS)) {
            $report = array_key_first(self::REPORTS);
        }

        [$from, $to] = $this->resolveMonthRange($request);

        return [
            'report' => $report,
            'from'   => $from,
            'to'     => $to,
        ];
    }

    private function isExportOnly(string $report): bool
    {
        return !empty(self::REPORTS[$report]['export_only']);
    }

    /**
     * Heure locale courante, indépendamment du fuseau configuré dans l'application.
     */
    private function localNow(): Carbon
    {
        return Carbon::now('UTC')->addMinutes(self::LOCAL_OFFSET);
    }

    /**
     * Bornes mensuelles au format Y-m. Par défaut, les trois mois
     * précédant le mois courant.
     */
    private function resolveMonthRange(Request $request): array
    {
        $to   = $this->parseMonth($request->query('to')) ?: Carbon::now()->startOfMonth();
        $from = $this->parseMonth($request->query('from')) ?: (clone $to)->subMonths(3);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->format('Y-m'), $to->format('Y-m')];
    }

    private function parseMonth(?string $value): ?Carbon
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Bornes datetime correspondant à la période mensuelle.
     */
    private function periodBounds(array $f): array
    {
        return [
            Carbon::createFromFormat('Y-m', $f['from'])->startOfMonth()->toDateTimeString(),
            Carbon::createFromFormat('Y-m', $f['to'])->endOfMonth()->toDateTimeString(),
        ];
    }

    /**
     * Liste des mois de la période, du plus ancien au plus récent.
     */
    private function months(array $f): array
    {
        $cursor = Carbon::createFromFormat('Y-m', $f['from'])->startOfMonth();
        $end    = Carbon::createFromFormat('Y-m', $f['to'])->startOfMonth();

        $months = [];

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    private function monthLabel(string $month): string
    {
        return ucfirst(Carbon::createFromFormat('Y-m', $month)->translatedFormat('M y'));
    }

    /* ===================== TABLEAUX ===================== */

    /**
     * Tableaux du rapport sélectionné.
     * Chaque tableau devient une feuille à l'export.
     */
    private function buildTables(array $f): array
    {
        return match ($f['report']) {
            'revenue' => $this->revenueTables($f),
            'users'   => $this->usersTables(),
            default   => $this->activityTables($f),
        };
    }

    /**
     * Rapport d'activité : un tableau, une ligne par mois.
     *
     * Les moyennes sont rapportées au nombre de jours effectivement
     * présents dans le journal, et non au calendrier : un mois dont
     * le suivi a démarré en cours de route n'est pas pénalisé.
     */
    private function activityTables(array $f): array
    {
        [$start, $end] = $this->periodBounds($f);

        // DAYOFWEEK() : 1 = dimanche, 7 = samedi
        $opens = DB::table('trk_app_opens')
            ->selectRaw("DATE_FORMAT(tracking_date, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DAYOFWEEK(tracking_date) BETWEEN 2 AND 6 THEN 1 ELSE 0 END) as week_total')
            ->selectRaw('SUM(CASE WHEN DAYOFWEEK(tracking_date) IN (1, 7) THEN 1 ELSE 0 END) as weekend_total')
            ->selectRaw('COUNT(DISTINCT DATE(tracking_date)) as days')
            ->selectRaw('COUNT(DISTINCT CASE WHEN DAYOFWEEK(tracking_date) BETWEEN 2 AND 6 THEN DATE(tracking_date) END) as week_days')
            ->selectRaw('COUNT(DISTINCT CASE WHEN DAYOFWEEK(tracking_date) IN (1, 7) THEN DATE(tracking_date) END) as weekend_days')
            ->whereBetween('tracking_date', [$start, $end])
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Utilisateurs distincts ayant consulté au moins un contenu
        $active = DB::table('trk_content_views as v')
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->selectRaw("DATE_FORMAT(v.created_at, '%Y-%m') as month")
            ->selectRaw('COUNT(DISTINCT v.user_id) as total')
            ->whereIn('u.role_id', self::USER_ROLES)
            ->whereBetween('v.created_at', [$start, $end])
            ->groupBy('month')
            ->pluck('total', 'month');

        // Les comptes dupliqués ne sont pas des inscriptions
        $signups = DB::table('users')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN created_from IS NULL OR created_from <> 'WEB' THEN 1 ELSE 0 END) as from_app")
            ->whereIn('role_id', self::USER_ROLES)
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('created_from')
                    ->orWhere('created_from', 'not like', self::CLONE_PREFIX . '%');
            })
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $rows = [];

        foreach ($this->months($f) as $month) {
            $open   = $opens[$month] ?? null;
            $signup = $signups[$month] ?? null;

            $rows[] = [
                $this->monthLabel($month),
                (int) ($open->total ?? 0),
                $this->average($open->week_total ?? 0, $open->week_days ?? 0),
                $this->average($open->weekend_total ?? 0, $open->weekend_days ?? 0),
                $this->average($open->total ?? 0, $open->days ?? 0),
                (int) ($active[$month] ?? 0),
                (int) ($signup->total ?? 0),
                (int) ($signup->from_app ?? 0),
            ];
        }

        return [
            'activity' => [
                'title'  => 'Activité mensuelle',
                // Bandeau supérieur : libellé et nombre de colonnes couvertes
                'groups' => [
                    ['label' => 'Mois', 'span' => 1, 'rowspan' => true],
                    ['label' => "Ouvertures de l'application", 'span' => 4],
                    ['label' => 'Utilisateurs actifs', 'span' => 1, 'rowspan' => true],
                    ['label' => 'Nouvelles inscriptions', 'span' => 2],
                ],
                'headers' => [
                    '',
                    'Total',
                    'Moy. semaine',
                    'Moy. week-end',
                    'Moy. générale',
                    '',
                    'Total',
                    "Via l'app",
                ],
                'rows'    => $rows,
                'totals'  => $this->columnTotals($rows, [1, 5, 6, 7]),
                'columns' => ['text', 'int', 'decimal', 'decimal', 'decimal', 'int', 'int', 'int'],
            ],
        ];
    }

    /**
     * Moyenne arrondie, nulle lorsqu'aucun jour n'a été relevé.
     */
    private function average($total, $days): float
    {
        return $days > 0 ? round($total / $days, 1) : 0.0;
    }

    /**
     * Rapport de revenus : deux tableaux, la synthèse puis le détail.
     */
    private function revenueTables(array $f): array
    {
        return [
            'revenue' => $this->revenueSummaryTable($f),
            'detail'  => $this->revenueDetailTable($f),
        ];
    }

    /**
     * Synthèse : une ligne par produit, une colonne par mois.
     *
     * Le chiffre d'affaires d'un panier est réparti entre ses produits
     * au prorata de leur durée : un forfait de 6 mois dans un panier de
     * 12 mois se voit attribuer la moitié du montant hors taxes.
     */
    private function revenueSummaryTable(array $f): array
    {
        [$start, $end] = $this->periodBounds($f);
        $months        = $this->months($f);

        $lines = DB::table('carts as ct')
            ->join('cart_items as ci', 'ci.cart_id', '=', 'ct.id')
            ->join('packages as p', 'p.id', '=', 'ci.package_id')
            ->selectRaw("DATE_FORMAT(ct.updated_at, '%Y-%m') as month")
            ->selectRaw('p.id as package_id')
            ->selectRaw('SUM(ct.price_total_ht * p.duration / ct.duration_total) as revenue')
            ->where('ct.status', self::CART_SIGNED)
            ->whereBetween('ct.updated_at', [$start, $end])
            ->where('ct.duration_total', '>', 0)
            ->groupBy('month', 'p.id')
            ->get();

        // Indexation par produit puis par mois, pour un accès direct
        $revenues = [];

        foreach ($lines as $line) {
            $revenues[$line->package_id][$line->month] = (float) $line->revenue;
        }

        $packages = DB::table('packages')->orderBy('name')->get(['id', 'name']);

        $rows        = [];
        $monthTotals = array_fill_keys($months, 0.0);
        $grandTotal  = 0.0;

        // Tous les produits figurent au rapport, y compris sans vente
        foreach ($packages as $package) {
            $row     = [$package->name];
            $rowSum  = 0.0;

            foreach ($months as $month) {
                $value = $revenues[$package->id][$month] ?? 0.0;

                $row[]                = round($value, 2);
                $rowSum              += $value;
                $monthTotals[$month] += $value;
            }

            $row[]       = round($rowSum, 2);
            $grandTotal += $rowSum;

            $rows[] = $row;
        }

        $totals = ['Total'];

        foreach ($months as $month) {
            $totals[] = round($monthTotals[$month], 2);
        }

        $totals[] = round($grandTotal, 2);

        $headers = ['Produit'];

        foreach ($months as $month) {
            $headers[] = $this->monthLabel($month);
        }

        $headers[] = 'Total';

        return [
            'title'   => 'Revenus par produit',
            'headers' => $headers,
            'rows'    => $rows,
            'totals'  => $totals,
            'columns' => array_merge(['text'], array_fill(0, count($months) + 1, 'money')),
            'groups' => null
        ];
    }

    /**
     * Détail ligne à ligne : chaque produit d'un panier, avec la part
     * du chiffre d'affaires qui lui revient.
     */
    private function revenueDetailTable(array $f): array
    {
        [$start, $end] = $this->periodBounds($f);

        $lines = DB::table('carts as ct')
            ->join('cart_items as ci', 'ci.cart_id', '=', 'ct.id')
            ->join('packages as p', 'p.id', '=', 'ci.package_id')
            ->select(
                'ct.id as cart_id',
                'ct.updated_at',
                'ct.price_total_ht',
                'ct.duration_total',
                'p.name as package_name',
                'p.duration as package_duration'
            )
            ->selectRaw('ct.price_total_ht * p.duration / ct.duration_total as revenue')
            ->where('ct.status', self::CART_SIGNED)
            ->whereBetween('ct.updated_at', [$start, $end])
            ->where('ct.duration_total', '>', 0)
            ->orderByDesc('ct.updated_at')
            ->orderBy('ct.id')
            ->orderBy('p.name')
            ->get();

        $rows  = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $share = $line->duration_total > 0
                ? $line->package_duration / $line->duration_total
                : 0;

            $rows[] = [
                $line->cart_id,
                Carbon::parse($line->updated_at)->format('d/m/Y'),
                $line->package_name,
                (int) $line->package_duration,
                (int) $line->duration_total,
                round($share * 100, 1),
                round((float) $line->price_total_ht, 2),
                round((float) $line->revenue, 2),
            ];

            $total += (float) $line->revenue;
        }

        return [
            'title'   => 'Détail par panier',
            'headers' => [
                'Panier',
                'Date',
                'Produit',
                'Durée produit',
                'Durée panier',
                'Part',
                'Montant HT du panier',
                'Montant attribué',
            ],
            'rows'    => $rows,
            'totals'  => $rows === [] ? null : [
                'Total', null, null, null, null, null, null, round($total, 2),
            ],
            'columns' => ['text', 'text', 'text', 'int', 'int', 'percent', 'money', 'money'],
            'groups' => null
        ];
    }

    /**
     * Liste des utilisateurs à date, sans notion de période.
     *
     * La dernière activité retient la plus récente entre l'usage d'un
     * jeton d'appareil (ouverture de l'app) et la consultation d'un
     * contenu : l'un ou l'autre peut manquer selon l'ancienneté du compte.
     */
    private function usersTables(): array
    {
        $lastUse = DB::table('device_tokens')
            ->select('user_id')
            ->selectRaw('MAX(last_used_at) as last_used_at')
            ->groupBy('user_id');

        $lastView = DB::table('trk_content_views')
            ->select('user_id')
            ->selectRaw('MAX(created_at) as last_view_at')
            ->groupBy('user_id');

        $offset = self::LOCAL_OFFSET;

        $users = DB::table('users as u')
            ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
            ->leftJoin('funding_organizations as fo', 'fo.id', '=', 'u.funding_organization_id')
            ->leftJoinSub($lastUse, 'dt', 'dt.user_id', '=', 'u.id')
            ->leftJoinSub($lastView, 'cv', 'cv.user_id', '=', 'u.id')
            ->select(
                'u.id',
                'u.name',
                'u.first_name',
                'u.email',
                'u.phone',
                'u.postal_code',
                'u.city',
                'u.other_profession',
                'u.student_flg',
                'u.created_from'
            )
            ->selectRaw('p.name as profession')
            ->selectRaw('fo.name as funding_organization')
            // Les COALESCE croisés rendent NULL seulement si les deux sources manquent
            ->selectRaw("DATE_ADD(GREATEST(COALESCE(dt.last_used_at, cv.last_view_at), COALESCE(cv.last_view_at, dt.last_used_at)), INTERVAL {$offset} MINUTE) as last_activity")
            ->selectRaw("DATE_ADD(u.created_at, INTERVAL {$offset} MINUTE) as created_local")
            ->whereIn('u.role_id', self::USER_ROLES)
            ->orderBy('u.id')
            ->get();

        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->name,
                $user->first_name,
                $user->email,
                $user->phone,
                $user->postal_code,
                $user->city,
                $user->profession,
                $user->other_profession,
                $user->funding_organization,
                $user->student_flg === null ? null : ((bool) $user->student_flg ? 'Oui' : 'Non'),
                $user->last_activity,
                $user->created_local,
                $user->created_from,
            ];
        }

        return [
            'users' => [
                'title'   => 'Utilisateurs',
                'headers' => [
                    'ID',
                    'Nom',
                    'Prénom',
                    'Email',
                    'Téléphone',
                    'Code postal',
                    'Ville',
                    'Profession',
                    'Autre profession',
                    'Organisme de financement',
                    'Étudiant',
                    'Dernière activité',
                    'Inscription',
                    'Origine',
                ],
                'rows'    => $rows,
                'totals'  => null,
                'columns' => [
                    'text', 'text', 'text', 'text', 'string', 'string', 'text',
                    'text', 'text', 'text', 'text', 'datetime', 'datetime', 'text',
                ],
                'groups'  => null,
            ],
        ];
    }

    /**
     * Somme des colonnes numériques, pour la ligne de total.
     */
    private function columnTotals(array $rows, array $columns): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $totals = [];

        foreach (array_keys($rows[0]) as $index) {
            $totals[$index] = in_array($index, $columns, true)
                ? array_sum(array_column($rows, $index))
                : null;
        }

        return $totals;
    }

    /* ===================== EXPORT ===================== */

    /**
     * Remplit une feuille : bandeau de contexte, en-têtes, données, total.
     */
    private function fillSheet($sheet, array $table, array $f): void
    {
        $columns     = $table['columns'] ?? [];
        $columnCount = count($table['headers']);
        $lastColumn  = $this->columnLetter($columnCount);

        // Excel refuse certains caractères et limite le nom à 31 signes
        $sheet->setTitle(mb_substr(
            preg_replace('/[\\\\\/\*\?\:\[\]]/', '', $table['title']),
            0,
            31
        ));

        // Bandeau de contexte
        $sheet->setCellValue('A1', $table['title']);
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Sans période, on date l'extraction pour situer l'état des données
        if ($this->isExportOnly($f['report'])) {
            $sheet->setCellValue('A2', 'Extraction du ' . $this->localNow()->format('d/m/Y à H:i'));
        } else {
            $sheet->setCellValue('A2', sprintf(
                'Période : de %s à %s',
                $this->monthLabel($f['from']),
                $this->monthLabel($f['to'])
            ));
        }
        $sheet->mergeCells('A2:' . $lastColumn . '2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('808080');

        // En-têtes : une ou deux lignes selon la présence de groupes
        $hasGroups  = !empty($table['groups']);
        $headerRow  = 4;
        $firstRow   = $hasGroups ? 6 : 5;

        if ($hasGroups) {
            $column = 1;

            foreach ($table['groups'] as $group) {
                $letter = $this->columnLetter($column);
                $last   = $this->columnLetter($column + $group['span'] - 1);

                $sheet->setCellValue($letter . $headerRow, $group['label']);

                // Une colonne isolée occupe les deux lignes d'en-tête
                if (!empty($group['rowspan'])) {
                    $sheet->mergeCells($letter . $headerRow . ':' . $letter . ($headerRow + 1));
                } elseif ($group['span'] > 1) {
                    $sheet->mergeCells($letter . $headerRow . ':' . $last . $headerRow);
                }

                $column += $group['span'];
            }

            foreach ($table['headers'] as $i => $header) {
                if ($header === '') {
                    continue;
                }

                $sheet->setCellValue($this->columnLetter($i + 1) . ($headerRow + 1), $header);
            }

            $headerRange = 'A' . $headerRow . ':' . $lastColumn . ($headerRow + 1);
            $sheet->getRowDimension($headerRow + 1)->setRowHeight(24);
        } else {
            foreach ($table['headers'] as $i => $header) {
                $sheet->setCellValue($this->columnLetter($i + 1) . $headerRow, $header);
            }

            $headerRange = 'A' . $headerRow . ':' . $lastColumn . $headerRow;
        }

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_COLOR],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Données
        $row = $firstRow;

        foreach ($table['rows'] as $line) {
            foreach (array_values($line) as $i => $value) {
                $type = $columns[$i] ?? 'text';

                // Excel convertirait "0612…" ou "01000" en nombre et perdrait le zéro
                if ($value !== null && $type === 'string') {
                    $sheet->setCellValueExplicit($this->columnLetter($i + 1) . $row, (string) $value, DataType::TYPE_STRING);
                    continue;
                }

                // Une vraie date Excel reste triable et filtrable, contrairement à du texte
                if ($value !== null && $type === 'datetime') {
                    $value = ExcelDate::PHPToExcel(Carbon::parse($value));
                }

                $sheet->setCellValue($this->columnLetter($i + 1) . $row, $value);
            }
            $row++;
        }

        $lastRow = $row - 1;

        if ($lastRow >= $firstRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $this->applyColumnFormats($sheet, $columns, $firstRow, $lastRow);
        }

        // Ligne de total
        if (!empty($table['totals'])) {
            $sheet->setCellValue('A' . $row, 'Total');

            foreach (array_values($table['totals']) as $i => $value) {
                if ($i === 0 || $value === null) {
                    continue;
                }

                $sheet->setCellValue($this->columnLetter($i + 1) . $row, $value);
            }

            $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F4F7'],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => self::HEADER_COLOR],
                    ],
                ],
            ]);

            $this->applyColumnFormats($sheet, $columns, $row, $row);
        }

        $sheet->getColumnDimension('A')->setWidth(28);

        for ($i = 2; $i <= $columnCount; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i))->setWidth(20);
        }

        $sheet->freezePane('A' . $firstRow);
    }

    /**
     * Applique à chaque colonne le format correspondant à son type.
     */
    private function applyColumnFormats($sheet, array $columns, int $firstRow, int $lastRow): void
    {
        foreach ($columns as $i => $type) {
            if (!isset(self::CELL_FORMATS[$type])) {
                continue;
            }

            $letter = $this->columnLetter($i + 1);

            $sheet->getStyle($letter . $firstRow . ':' . $letter . $lastRow)
                ->getNumberFormat()
                ->setFormatCode(self::CELL_FORMATS[$type]);
        }
    }

    private function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Envoie le classeur en téléchargement sans passer par le disque.
     */
    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}