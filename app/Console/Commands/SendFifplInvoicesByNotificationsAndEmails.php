<?php

namespace App\Console\Commands;

use App\Jobs\SendExpoNotificationJob;
use App\Mail\SendFifplNoPayInvoiceMail;
use App\Mail\SendFifplCompletedDocumentMail;
use App\Mail\ResendFifplNoPayInvoiceMail;
use App\Models\Invoice;
use App\Models\SystemNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendFifplInvoicesByNotificationsAndEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fifpl:send-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send fifpl invoices by notifications and emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoices = Invoice::whereDate('sent_at', today())
            ->with('user')
            ->get();

        if ($invoices->isEmpty()) {
            Log::channel('tracking')->warning('No fifpli Invoice to send.', ['fifpl-invoice']);
            return Command::FAILURE;
        }

        foreach ($invoices as $invoice) {
            if ($invoice->status == 'pending') {
                if ($invoice->sent_cnt >= 1) {
                    
                    // Resend email
                    Mail::to($invoice->user->email)->send(new ResendFifplNoPayInvoiceMail($invoice->user_id, $invoice));

                } else {
                    // Send email
                    Mail::to($invoice->user->email)->send(new SendFifplNoPayInvoiceMail($invoice->user_id, $invoice));


                    $title = 'La facture ' . $invoice->no;
                    $body = 'Une nouvelle facture est disponible. Merci de procéder à son règlement. !';

                    // Create and send notification
                    $sysNoti = SystemNotification::create([
                        'user_id' => $invoice->user_id,
                        'type' => 'warning',
                        'entity_type' => 'FifplInvoice',
                        'entity_id' => $invoice->id,
                        'title' => $title,
                        'content' => $body,
                    ]);

                    if ($sysNoti) {
                        SendExpoNotificationJob::dispatch(
                            userIds: [$invoice->user_id],
                            title: $title,
                            body: $body,
                            data: ['action' => 'notification'],
                            options: ['badge' => 1]
                        );
                    }
                }

                // Set schedule for next time
                $invoice->increment('sent_cnt');
                $invoice->sent_at = $invoice->sent_cnt == 1 ? $invoice->sent_at->addMonth() : $invoice->sent_at->addDays(15);
                $invoice->save();
            }

            // Send final documents
            if ($invoice->status == 'paid') {
                // Send email
                Mail::to($invoice->user->email)->send(new SendFifplCompletedDocumentMail($invoice->user_id, $invoice));

                $invoice->sent_at = null;
                $invoice->save();
            }
        }

        Log::channel('tracking')->info('Completed job.', ['fifpl-invoice']);
        $this->info('Done!');
        return Command::SUCCESS;
    }
}
