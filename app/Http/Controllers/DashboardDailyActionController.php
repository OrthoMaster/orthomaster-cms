<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardDailyActionController extends Controller
{
    /** Rôles considérés comme utilisateurs de l'application */
    private const USER_ROLES = [2, 4];

    /**
     * Vue d'ensemble des actions du jour : podcasts, quiz et infographies.
     * GET /dashboard/daily-actions
     */
    public function index()
    {
        $summary = $this->buildSummary();

        return view('dashboard.daily-actions', [
            'summary'      => $summary,
            'distribution' => $this->buildDistribution(),
        ]);
    }

    /* ===================== SUMMARY ===================== */

    /**
     * Volumes publiés à ce jour et couverture des centres d'intérêt.
     */
    private function buildSummary(): array
    {
        $totalUsers = DB::table('users')
            ->whereIn('role_id', self::USER_ROLES)
            ->count();

        // Utilisateurs ayant sélectionné au moins un centre d'intérêt
        $usersWithTopics = DB::table('users as u')
            ->whereIn('u.role_id', self::USER_ROLES)
            ->whereExists(function ($sub) {
                $sub->from('topic_user as tu')
                    ->whereColumn('tu.user_id', 'u.id')
                    ->selectRaw('1');
            })
            ->count();

        // Utilisateurs acceptant les notifications
        $usersWithNotifications = DB::table('users')
            ->whereIn('role_id', self::USER_ROLES)
            ->where('notification_flg', true)
            ->count();

        return [
            'podcasts'                 => $this->publishedCount('podcasts'),
            'quizzes'                  => $this->publishedCount('daily_questions'),
            'infographics'             => $this->publishedCount('infographics'),
            'total_users'              => $totalUsers,
            'users_with_topics'        => $usersWithTopics,
            'users_with_notifications' => $usersWithNotifications,
            'users_rate'               => $this->rate($usersWithTopics, $totalUsers),
            'notifications_rate'       => $this->rate($usersWithNotifications, $totalUsers),
        ];
    }

    /**
     * Éléments dont la date de publication est atteinte.
     */
    private function publishedCount(string $table): int
    {
        return DB::table($table)
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', now()->toDateString())
            ->count();
    }

    /* ===================== RÉPARTITION PAR CENTRE D'INTÉRÊT ===================== */

    /**
     * Croisement centres d'intérêt x (podcasts, quiz, utilisateurs).
     * Chaque taux est calculé sur le total de sa propre colonne.
     */
    private function buildDistribution(): array
    {
        $today = now()->toDateString();

        $podcasts     = $this->countByTopic('podcasts', $today);
        $quizzes      = $this->countByTopic('daily_questions', $today);
        $infographics = $this->countByTopic('infographics', $today);
        $users        = $this->usersByTopic();

        $topics = DB::table('topics')->orderBy('name')->get(['id', 'name']);

        $totals = [
            'podcasts'     => array_sum($podcasts->all()),
            'quizzes'      => array_sum($quizzes->all()),
            'infographics' => array_sum($infographics->all()),
            'users'        => array_sum($users->all()),
        ];

        // Les maximums servent à moduler l'intensité des couleurs
        $max = [
            'podcasts'     => $podcasts->max() ?: 0,
            'quizzes'      => $quizzes->max() ?: 0,
            'infographics' => $infographics->max() ?: 0,
            'users'        => $users->max() ?: 0,
        ];

        $rows = $topics->map(function ($topic) use ($podcasts, $quizzes, $infographics, $users, $totals) {
            $p = (int) ($podcasts[$topic->id] ?? 0);
            $q = (int) ($quizzes[$topic->id] ?? 0);
            $i = (int) ($infographics[$topic->id] ?? 0);
            $u = (int) ($users[$topic->id] ?? 0);

            return [
                'id'                => $topic->id,
                'name'              => $topic->name,
                'podcasts'          => $p,
                'quizzes'           => $q,
                'infographics'      => $i,
                'users'             => $u,
                'podcasts_rate'     => $this->rate($p, $totals['podcasts']),
                'quizzes_rate'      => $this->rate($q, $totals['quizzes']),
                'infographics_rate' => $this->rate($i, $totals['infographics']),
                'users_rate'        => $this->rate($u, $totals['users']),
            ];
        });

        return [
            'rows'   => $rows,
            'totals' => $totals,
            'max'    => $max,
        ];
    }

    /**
     * Nombre d'éléments publiés par centre d'intérêt.
     */
    private function countByTopic(string $table, string $today)
    {
        return DB::table($table)
            ->select('topic_id')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('topic_id')
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', $today)
            ->groupBy('topic_id')
            ->pluck('total', 'topic_id');
    }

    /**
     * Nombre d'utilisateurs abonnés à chaque centre d'intérêt.
     */
    private function usersByTopic()
    {
        return DB::table('topic_user as tu')
            ->join('users as u', 'u.id', '=', 'tu.user_id')
            ->whereIn('u.role_id', self::USER_ROLES)
            ->select('tu.topic_id')
            ->selectRaw('COUNT(DISTINCT tu.user_id) as total')
            ->groupBy('tu.topic_id')
            ->pluck('total', 'topic_id');
    }

    private function rate(int $value, int $total): float
    {
        return $total > 0 ? round($value / $total * 100, 1) : 0;
    }
}