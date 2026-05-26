<?php

namespace App\Console\Commands;

use App\Mail\SendToAdProviderMail;
use App\Models\VideoAdView;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailsToAdProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:ad-provider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email to ad providers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = Carbon::yesterday()->setTime(10, 0, 0);
        $to   = now();

        $views = VideoAdView::query()
            ->with(['videoAd.adsCompany', 'user'])
            ->where('is_contacted', true)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('user_id')
            ->orderBy('created_at')
            ->get();

        if ($views->isEmpty()) {
            Log::channel('tracking')->warning('No contacts in this window. Nothing to send.', ['ads']);
            return self::SUCCESS;
        }

        $byVideo = $views
            ->groupBy('video_ads_id')
            ->map(fn($group) => $group->unique('user_id')->values());

        $sent = 0;
        $skipped = 0;

        foreach ($byVideo as $videoAdId => $videoViews) {
            $videoAd = $videoViews->first()->videoAd;
            if (!$videoAd) {
                $skipped++;
                continue;
            }

            $company = $videoAd->adsCompany;
            $companyEmail = $company?->contact_email;
            if (!$companyEmail) {
                $skipped++;
                continue;
            }

            $users = $videoViews->map(function (VideoAdView $v) {
                $u = $v->user;
                if (!$u) return null;

                return [
                    'name'       => trim(($u->name ?? '') . ' ' . ($u->first_name ?? '')),
                    'email'      => $u->email ?? null,
                    'profession' => $u->profession?->name ?? null,
                ];
            })->filter()->values()->all();

            if (empty($users)) {
                $skipped++;
                continue;
            }

            try {
                Mail::to($companyEmail)->send(
                    new SendToAdProviderMail($users, $videoAd->title)
                );

                Log::channel('tracking')->info(sprintf(
                    'Sent → %s (video "%s", %d user%s)',
                    $companyEmail,
                    $videoAd->title,
                    count($users),
                    count($users) > 1 ? 's' : ''
                ), ['ads']);
                $sent++;
            } catch (\Throwable $e) {
                Log::channel('tracking')->error('SendEmailsToAdProviders failed', [
                    'video_ads_id' => $videoAdId,
                    'company_email' => $companyEmail,
                    'error' => $e->getMessage(),
                ]);
                Log::channel('tracking')->error("Failed to send for video #{$videoAdId}: {$e->getMessage()}");
                $skipped++;
            }
        }

        Log::channel('tracking')->info("Done. Sent: {$sent}, skipped: {$skipped}.", ['ads']);
        return self::SUCCESS;
    }
}
