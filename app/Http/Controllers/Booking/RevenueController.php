<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord des revenus des rendez-vous experts (Tutorat).
 * GET /admin/booking/revenue?from&to&granularity&expert_id
 *
 * Revenus = rendez-vous payants débités (acceptés par l'expert, même annulés ensuite), datés
 * du jour du débit (acceptation), en heure de Paris. Pour chaque rendez-vous, le montant débité
 * (TTC) se répartit en :
 *  - part de l'expert (HT, commission déduite) ;
 *  - commission OrthoMaster (HT) ;
 *  - TVA ;
 *  - remboursé (annulation, geste) : retiré au prorata des trois autres.
 * La barre empilée d'une période fait donc le total débité TTC.
 *
 * Experts proposés au filtre : ceux qui ont un compte (experts.user_id renseigné).
 */
class RevenueController extends Controller
{
    private const TZ = 'Europe/Paris';

    public const SERIES = [
        'expert'     => ['label' => 'Part expert (HT)', 'color' => '#2ECC71'],
        'commission' => ['label' => 'Commission OrthoMaster (HT)', 'color' => '#22A7F0'],
        'tva'        => ['label' => 'TVA', 'color' => '#F39C12'],
        'refunded'   => ['label' => 'Remboursé', 'color' => '#E74C3C'],
    ];

    public function index(Request $request)
    {
        $f = $this->filters($request);
        $rows = $this->rows($f);

        return view('booking.revenue.index', [
            'filters' => $f,
            'experts' => $this->expertOptions(),
            'series'  => self::SERIES,
            'chart'   => $this->timeline($rows, $f),
            'summary' => $this->summary($rows),
            'byExpert' => $f['expert_id'] ? [] : $this->byExpert($rows),
        ]);
    }

    /* ===================== FILTRES ===================== */

    private function filters(Request $request): array
    {
        $granularity = in_array($request->query('granularity'), ['day', 'week', 'month', 'year'], true)
            ? $request->query('granularity')
            : 'month';

        $to = $request->date('to') ?: Carbon::today(self::TZ);
        $from = $request->date('from') ?: Carbon::today(self::TZ)->startOfMonth()->subMonths(11);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'granularity' => $granularity,
            'expert_id'   => $request->integer('expert_id') ?: null,
        ];
    }

    /** Experts reliés à un compte utilisateur. */
    private function expertOptions()
    {
        return DB::table('experts')
            ->whereNotNull('user_id')
            ->orderBy('name')
            ->get(['id', 'name', 'honorific']);
    }

    /* ===================== DONNÉES ===================== */

    /**
     * Rendez-vous payants débités sur la période, avec la répartition de leur montant.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function rows(array $f)
    {
        $app = config('app.timezone', 'UTC');
        $start = Carbon::parse($f['from'] . ' 00:00:00', self::TZ)->setTimezone($app);
        $end = Carbon::parse($f['to'] . ' 23:59:59', self::TZ)->setTimezone($app);

        // Date du débit : premier « accepted » du journal.
        $captured = DB::table('booking_events')
            ->select('booking_id')
            ->selectRaw('MIN(created_at) as captured_at')
            ->where('type', 'accepted')
            ->groupBy('booking_id');

        return DB::table('bookings as b')
            ->joinSub($captured, 'c', 'c.booking_id', '=', 'b.id')
            ->join('experts as e', 'e.id', '=', 'b.expert_id')
            ->whereNotNull('e.user_id')
            ->where('b.payment_required', 1)
            ->whereBetween('c.captured_at', [$start, $end])
            ->when($f['expert_id'], fn ($q) => $q->where('b.expert_id', $f['expert_id']))
            ->get([
                'b.id', 'b.expert_id', 'e.name as expert_name', 'e.honorific',
                'b.amount_ht', 'b.tva_amount', 'b.amount_ttc', 'b.refunded_amount', 'b.commission_rate',
                'c.captured_at',
            ])
            ->map(function ($r) use ($app) {
                $ttc = (float) $r->amount_ttc;
                $refunded = min($ttc, (float) ($r->refunded_amount ?? 0));
                $kept = $ttc > 0 ? ($ttc - $refunded) / $ttc : 0;
                $ht = (float) $r->amount_ht;
                $rate = (float) ($r->commission_rate ?? 0) / 100;

                $r->captured_local = Carbon::parse($r->captured_at, $app)->setTimezone(self::TZ);
                $r->ttc = $ttc;
                $r->refunded = $refunded;
                $r->expert = round($ht * (1 - $rate) * $kept, 2);
                $r->commission = round($ht * $rate * $kept, 2);
                $r->tva = round((float) $r->tva_amount * $kept, 2);

                return $r;
            });
    }

    /** Barres empilées par période (sans trou). */
    private function timeline($rows, array $f): array
    {
        $buckets = [];
        $cursor = Carbon::parse($f['from'], self::TZ);
        $end = Carbon::parse($f['to'], self::TZ);
        while ($cursor->lte($end)) {
            [$key, $label] = $this->bucketFor($cursor, $f['granularity']);
            $buckets[$key] ??= ['label' => $label] + array_fill_keys(array_keys(self::SERIES), 0.0);
            $cursor->addDay();
        }

        foreach ($rows as $r) {
            [$key] = $this->bucketFor($r->captured_local, $f['granularity']);
            if (!isset($buckets[$key])) {
                continue;
            }
            foreach (array_keys(self::SERIES) as $s) {
                $buckets[$key][$s] += $r->{$s};
            }
        }

        $out = ['labels' => array_column($buckets, 'label')];
        foreach (array_keys(self::SERIES) as $s) {
            $out[$s] = array_map(fn ($v) => round($v, 2), array_column($buckets, $s));
        }

        return $out;
    }

    private function bucketFor(Carbon $date, string $granularity): array
    {
        return match ($granularity) {
            'year'  => [$date->format('Y'), $date->format('Y')],
            'month' => [$date->format('Y-m'), ucfirst($date->locale('fr')->translatedFormat('M Y'))],
            'week'  => [$date->format('o-W'), 'S' . $date->format('W') . ' ' . $date->format('o')],
            default => [$date->toDateString(), $date->format('d/m')],
        };
    }

    private function summary($rows): array
    {
        $ttc = $rows->sum('ttc');
        $refunded = $rows->sum('refunded');

        return [
            'count'      => $rows->count(),
            'ttc'        => round($ttc, 2),
            'refunded'   => round($refunded, 2),
            'net'        => round($ttc - $refunded, 2),
            'tva'        => round($rows->sum('tva'), 2),
            'expert'     => round($rows->sum('expert'), 2),
            'commission' => round($rows->sum('commission'), 2),
        ];
    }

    /** Une ligne par expert, du plus gros total au plus petit. */
    private function byExpert($rows): array
    {
        return $rows->groupBy('expert_id')
            ->map(fn ($list) => [
                'expert_id'  => $list->first()->expert_id,
                'name'       => trim(($list->first()->honorific ? $list->first()->honorific . ' ' : '') . $list->first()->expert_name),
                'count'      => $list->count(),
                'ttc'        => round($list->sum('ttc'), 2),
                'refunded'   => round($list->sum('refunded'), 2),
                'tva'        => round($list->sum('tva'), 2),
                'expert'     => round($list->sum('expert'), 2),
                'commission' => round($list->sum('commission'), 2),
            ])
            ->sortByDesc('ttc')
            ->values()
            ->all();
    }
}
