<?php

namespace App\Console\Commands;

use App\Models\VideoAdTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SnapshotVideoAdTargets extends Command
{
    protected $signature = 'video-ads:snapshot {--period= : Mois à figer, au format AAAA-MM}';

    protected $description = 'Fige la répartition des points des publicités vidéo pour le mois écoulé';

    public function handle(): int
    {
        $period = $this->option('period') ?: now()->format('Y-m');

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Période invalide.');
            return Command::FAILURE;
        }

        // La répartition retenue pour le mois est celle en vigueur à sa
        // dernière journée : un seul jeu de points par mois et par publicité
        $lastDay = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        $adIds = DB::table('video_ads')->pluck('id')->all();
        $dates = VideoAdTarget::activeDates($adIds, $lastDay);

        if (empty($dates)) {
            $this->warn('Aucune répartition à figer.');
            Log::channel('tracking')->warning('No targets to snapshot.', ['video-ads-snapshot', $period]);
            return Command::SUCCESS;
        }

        $query = DB::table('video_ad_targets as t')
            ->join('video_ads as a', 'a.id', '=', 't.video_ads_id')
            // L'enveloppe vient de la même répartition que les points
            ->join('video_ad_budgets as b', function ($join) {
                $join->on('b.video_ads_id', '=', 't.video_ads_id')
                    ->on('b.effective_from', '=', 't.effective_from');
            })
            ->leftJoin('professions as p', function ($join) {
                $join->on('p.id', '=', 't.target_id')
                    ->where('t.target_type', '=', VideoAdTarget::TYPE_PROFESSION);
            })
            ->leftJoin('topics as tp', function ($join) {
                $join->on('tp.id', '=', 't.target_id')
                    ->where('t.target_type', '=', VideoAdTarget::TYPE_TOPIC);
            })
            ->select(
                't.video_ads_id',
                'a.ads_company_id',
                'b.points_total',
                't.target_type',
                't.target_id',
                't.points'
            )
            // Le libellé est recopié : renommer une cible plus tard ne doit
            // pas réécrire l'histoire du rapport
            ->selectRaw('COALESCE(p.name, tp.name) as target_name')
            ->where('t.points', '>', 0);

        $query->where(function ($outer) use ($dates) {
            foreach ($dates as $adId => $date) {
                $outer->orWhere(function ($inner) use ($adId, $date) {
                    $inner->where('t.video_ads_id', $adId)
                        ->where('t.effective_from', $date);
                });
            }
        });

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->warn('Aucune répartition à figer.');
            Log::channel('tracking')->warning('No targets to snapshot.', ['video-ads-snapshot', $period]);
            return Command::SUCCESS;
        }

        $now = now();

        $payload = $rows->map(fn($r) => [
            'period'         => $period,
            'video_ads_id'   => $r->video_ads_id,
            'ads_company_id' => $r->ads_company_id,
            'points_total'   => $r->points_total,
            'target_type'    => $r->target_type,
            'target_id'      => $r->target_id,
            'target_name'    => $r->target_name ?? '—',
            'points'         => $r->points,
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->all();

        // Une relance sur le même mois remplace la photographie précédente
        DB::transaction(function () use ($period, $payload) {
            DB::table('video_ad_target_snapshots')->where('period', $period)->delete();

            foreach (array_chunk($payload, 500) as $chunk) {
                DB::table('video_ad_target_snapshots')->insert($chunk);
            }
        });

        $this->info(count($payload) . ' lignes figées pour ' . $period . '.');
        Log::channel('tracking')->info('Snapshot done.', ['video-ads-snapshot', $period, count($payload)]);

        return Command::SUCCESS;
    }
}