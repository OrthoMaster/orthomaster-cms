<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardUserController extends Controller
{
    /** Rôles considérés comme "utilisateurs actifs" */
    private const USER_ROLES = [2, 4];

    /** Libellé pour les users sans profession */
    private const PROFESSION_FALLBACK = 'Non renseigné';

    /** Détection de plateforme à partir du user_agent */
    private const RE_IOS     = 'iPhone|iPad|iPod|CFNetwork|Darwin|ios';
    private const RE_ANDROID = 'Android|okhttp';

    /**
     * Trang dashboard utilisateurs.
     * GET /dashboard/users
     */
    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return view('dashboard.users.index', [
            'filters'  => $f,
            'global'   => $this->buildGlobalStats(),
            'chart'    => $this->buildChartData($f),
            'periodKp' => $this->buildPeriodSummary($f),
        ]);
    }

    /**
     * Endpoint JSON (fetch dynamique si besoin).
     * GET /dashboard/users/data
     */
    public function chartData(Request $request)
    {
        $f = $this->resolveFilters($request);

        return response()->json(
            $this->buildChartData($f) + ['summary' => $this->buildPeriodSummary($f)]
        );
    }

    /* ===================== FILTRES ===================== */

    /**
     * Filtres normalisés : période + granularité.
     */
    private function resolveFilters(Request $request): array
    {
        [$from, $to] = $this->resolveDateRange($request);

        return [
            'from'        => $from,
            'to'          => $to,
            'granularity' => $this->resolveGranularity($request),
        ];
    }

    /**
     * Granularité valide : day (défaut) / month / year.
     */
    private function resolveGranularity(Request $request): string
    {
        return in_array($request->query('granularity'), ['day', 'month', 'year'], true)
            ? $request->query('granularity')
            : 'day';
    }

    /**
     * Khoảng ngày. `to` mặc định hôm nay ; `from` vide = depuis toujours.
     */
    private function resolveDateRange(Request $request): array
    {
        $to   = $request->date('to')   ?: Carbon::today($this->displayTz());
        $from = $request->date('from') ?: (clone $to)->subDays(29);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /* ===================== TIMEZONE ===================== */

    private function displayTz(): string
    {
        return config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /**
     * Décalage (minutes) entre le TZ de stockage et le TZ d'affichage.
     */
    private function tzOffsetMinutes(): int
    {
        $app  = config('app.timezone', 'UTC');
        $disp = $this->displayTz();

        return (int) round(Carbon::now($disp)->utcOffset() - Carbon::now($app)->utcOffset());
    }

    /**
     * Expression SQL : colonne datetime convertie en heure locale.
     */
    private function localExpr(string $column): string
    {
        $m = $this->tzOffsetMinutes();

        return $m === 0 ? $column : "DATE_ADD({$column}, INTERVAL {$m} MINUTE)";
    }

    /**
     * Bornes [début, fin] en heure de stockage. `$from` null => pas de borne basse.
     */
    private function storageBounds(?string $from, string $to): array
    {
        $disp = $this->displayTz();
        $app  = config('app.timezone', 'UTC');

        return [
            $from
                ? Carbon::parse($from . ' 00:00:00', $disp)->setTimezone($app)->format('Y-m-d H:i:s')
                : null,
            Carbon::parse($to . ' 23:59:59', $disp)->setTimezone($app)->format('Y-m-d H:i:s'),
        ];
    }

    /* ===================== QUERIES DE BASE ===================== */

    /**
     * Ouvertures d'application sur la période.
     */
    private function appOpensQuery(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('trk_app_opens as o')
            ->when($start, fn ($q) => $q->where('o.created_at', '>=', $start))
            ->where('o.created_at', '<=', $end);
    }

    /**
     * Inscriptions validées sur la période.
     */
    private function newUsersQuery(array $f)
    {
        [$start, $end] = $this->storageBounds($f['from'], $f['to']);

        return DB::table('users as u')
            ->where('u.status', 1)
            ->whereIn('u.role_id', self::USER_ROLES)
            ->when($start, fn ($q) => $q->where('u.created_at', '>=', $start))
            ->where('u.created_at', '<=', $end);
    }

    /**
     * Expression SQL classant un user_agent en ios / android / web.
     */
    private function platformExpr(string $column): string
    {
        $ios     = self::RE_IOS;
        $android = self::RE_ANDROID;

        return "CASE
                    WHEN {$column} REGEXP '{$ios}' THEN 'ios'
                    WHEN {$column} REGEXP '{$android}' THEN 'android'
                    ELSE 'web'
                END";
    }

    /* ===================== STATS GLOBALES ===================== */

    /**
     * Chiffres "tout l'historique" : utilisateurs actifs, utilisateurs
     * cumulés (dernier rapport quotidien), sociétés actives.
     */
    private function buildGlobalStats(): array
    {
        $activeUsers = DB::table('users')
            ->whereIn('role_id', self::USER_ROLES)
            ->count();

        $totalUsers = DB::table('rpt_daily_app_opens')
            ->orderByDesc('date')
            ->value('total_users');

        $companies = DB::table('companies')
            ->where('active_flg', 1)
            ->count();

        return [
            'active_users' => (int) $activeUsers,
            'total_users'  => (int) ($totalUsers ?? 0),
            'companies'    => (int) $companies,
            'professions'  => $this->professionsSeries(),
            'devices'      => $this->devicesFromReport(),
        ];
    }

    /**
     * Chart 1 : répartition des utilisateurs par profession.
     */
    private function professionsSeries(): array
    {
        $rows = DB::table('users as u')
            ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
            ->whereIn('u.role_id', self::USER_ROLES)
            ->selectRaw('COALESCE(p.name, ?) as profession', [self::PROFESSION_FALLBACK])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('profession')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('profession')->all(),
            'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * Chart 2 : répartition des accès par appareil, d'après le rapport système.
     */
    private function devicesFromReport(): array
    {
        $rows = DB::table('rpt_system_controls')
            ->whereIn('label', ['ios', 'android', 'web'])
            ->pluck('data_int', 'label');

        return $this->deviceSeries([
            'ios'     => (int) ($rows['ios'] ?? 0),
            'android' => (int) ($rows['android'] ?? 0),
            'web'     => (int) ($rows['web'] ?? 0),
        ]);
    }

    /**
     * Met en forme les 3 plateformes pour un camembert.
     */
    private function deviceSeries(array $counts): array
    {
        return [
            'labels' => ['iOS', 'Android', 'Web'],
            'values' => [$counts['ios'], $counts['android'], $counts['web']],
            'total'  => array_sum($counts),
        ];
    }

    /* ===================== KPI PÉRIODE ===================== */

    /**
     * Totaux affichés à côté du filtre : connexions + inscriptions.
     */
    private function buildPeriodSummary(array $f): array
    {
        return [
            'connections' => (int) $this->appOpensQuery($f)->count(),
            'new_users'   => (int) $this->newUsersQuery($f)->count(),
        ];
    }

    /* ===================== CHARTS PÉRIODE ===================== */

    /**
     * Séries des graphiques 3 à 6.
     */
    private function buildChartData(array $f): array
    {
        return [
            'activity'    => $this->activitySeries($f),
            'devices'     => $this->devicesFromLogs($f),
            'hours'       => $this->hoursSeries($f),
            'weekdays'    => $this->weekdaysSeries($f),
            'granularity' => $f['granularity'],
            'from'        => $f['from'],
            'to'          => $f['to'],
        ];
    }

    /**
     * Chart 3 : ouvertures d'app + inscriptions par bucket temporel.
     * Les deux séries partagent les mêmes labels mais deux axes distincts.
     */
    private function activitySeries(array $f): array
    {
        $opens = $this->dailyCounts(
            $this->appOpensQuery($f),
            $this->localExpr('o.created_at')
        );

        $signups = $this->dailyCounts(
            $this->newUsersQuery($f),
            $this->localExpr('u.created_at')
        );

        $buckets = [];
        $cursor  = Carbon::parse($f['from']);
        $end     = Carbon::parse($f['to']);

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();

            [$bucketKey, $label] = $this->bucketFor($cursor, $f['granularity']);

            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = ['label' => $label, 'opens' => 0, 'signups' => 0];
            }

            $buckets[$bucketKey]['opens']   += (int) (optional($opens->get($key))->total ?? 0);
            $buckets[$bucketKey]['signups'] += (int) (optional($signups->get($key))->total ?? 0);

            $cursor->addDay();
        }

        return [
            'labels'  => array_column($buckets, 'label'),
            'opens'   => array_column($buckets, 'opens'),
            'signups' => array_column($buckets, 'signups'),
        ];
    }

    /**
     * Comptage par jour local, indexé sur la date.
     */
    private function dailyCounts($query, string $expr)
    {
        return $query
            ->selectRaw("DATE({$expr}) as stat_date")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->stat_date)->toDateString());
    }

    /**
     * Chart 4 : répartition par appareil sur la période, d'après les logs.
     */
    private function devicesFromLogs(array $f): array
    {
        $rows = $this->appOpensQuery($f)
            ->selectRaw($this->platformExpr('o.user_agent') . ' as platform')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('platform')
            ->pluck('total', 'platform');

        return $this->deviceSeries([
            'ios'     => (int) ($rows['ios'] ?? 0),
            'android' => (int) ($rows['android'] ?? 0),
            'web'     => (int) ($rows['web'] ?? 0),
        ]);
    }

    /**
     * Chart 5 : connexions réparties sur les 24 heures (heure locale).
     */
    private function hoursSeries(array $f): array
    {
        $expr = $this->localExpr('o.created_at');

        $rows = $this->appOpensQuery($f)
            ->selectRaw("HOUR({$expr}) as h")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('h')
            ->get()
            ->keyBy('h');

        $labels = [];
        $values = [];

        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02dh', $h);
            $values[] = (int) (optional($rows->get($h))->total ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Chart 6 : connexions par jour de la semaine (lundi -> dimanche).
     */
    private function weekdaysSeries(array $f): array
    {
        $expr = $this->localExpr('o.created_at');

        // WEEKDAY() : 0 = lundi ... 6 = dimanche
        $rows = $this->appOpensQuery($f)
            ->selectRaw("WEEKDAY({$expr}) as d")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $values = [];

        $monday = Carbon::parse('2024-01-01');

        for ($d = 0; $d < 7; $d++) {
            $labels[] = ucfirst((clone $monday)->addDays($d)->translatedFormat('l'));
            $values[] = (int) (optional($rows->get($d))->total ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * [clé_bucket, label_affiché] selon la granularité.
     */
    private function bucketFor(Carbon $date, string $granularity): array
    {
        return match ($granularity) {
            'year'  => [$date->format('Y'), $date->format('Y')],
            'month' => [$date->format('Y-m'), $date->translatedFormat('M Y')],
            default => [$date->toDateString(), $date->format('d/m')],
        };
    }

    /* ===================== EXPORT ===================== */

    /**
     * Export Excel des données du graphique d'activité : une feuille pour
     * les ouvertures d'application, une pour les nouvelles inscriptions.
     * GET /dashboard/users/export
     */
    public function export(Request $request): StreamedResponse
    {
        $f     = $this->resolveFilters($request);
        $chart = $this->activitySeries($f);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Connexions et inscriptions')
            ->setCreator('Dashboard');

        $this->fillSheet(
            $spreadsheet->setActiveSheetIndex(0),
            'Ouvertures',
            $chart['labels'],
            $chart['opens'],
            "Ouvertures de l'application",
            '22A7F0',
            $f
        );

        $this->fillSheet(
            $spreadsheet->createSheet(),
            'Nouveaux inscrits',
            $chart['labels'],
            $chart['signups'],
            'Nouveaux inscrits',
            'F39C12',
            $f
        );

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf(
            'connexions_%s_%s.xlsx',
            str_replace('-', '', $f['from']),
            str_replace('-', '', $f['to'])
        );

        return $this->streamXlsx($spreadsheet, $filename);
    }

    /**
     * Remplit une feuille : en-tête de contexte, colonnes, total.
     */
    private function fillSheet(
        $sheet,
        string $title,
        array $labels,
        array $values,
        string $valueHeader,
        string $color,
        array $f
    ): void {
        $sheet->setTitle($title);

        // Bandeau de contexte
        $sheet->setCellValue('A1', $valueHeader);
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', sprintf(
            'Période : du %s au %s — granularité : %s',
            Carbon::parse($f['from'])->format('d/m/Y'),
            Carbon::parse($f['to'])->format('d/m/Y'),
            $this->granularityLabel($f['granularity'])
        ));
        $sheet->mergeCells('A2:B2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('808080');

        // En-têtes de colonnes
        $sheet->setCellValue('A4', 'Période');
        $sheet->setCellValue('B4', $valueHeader);

        $sheet->getStyle('A4:B4')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $color],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Données
        $row = 5;

        foreach ($labels as $i => $label) {
            $sheet->setCellValue('A' . $row, $label);
            $sheet->setCellValue('B' . $row, (int) ($values[$i] ?? 0));
            $row++;
        }

        $lastRow = $row - 1;

        if ($lastRow >= 5) {
            $sheet->getStyle('A4:B' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'D9D9D9'],
                    ],
                ],
            ]);

            $sheet->getStyle('B5:B' . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        // Ligne de total
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->setCellValue('B' . $row, array_sum($values));

        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F4F7'],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color'       => ['rgb' => $color],
                ],
            ],
        ]);

        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->freezePane('A5');
    }

    private function granularityLabel(string $granularity): string
    {
        return match ($granularity) {
            'year'  => 'année',
            'month' => 'mois',
            default => 'jour',
        };
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
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"'
        );
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}