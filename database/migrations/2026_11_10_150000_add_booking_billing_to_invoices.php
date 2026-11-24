<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factures des rendez-vous experts : la ligne « invoices » d'une demande devient la facture
 * (numéro VE/… donné par Odoo) quand l'expert accepte ; chaque remboursement donne un avoir
 * (RVE/…), nouvelle ligne liée à la facture d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // invoice | credit_note (avoir)
            $table->string('kind', 20)->default('invoice')->after('status');
            $table->unsignedBigInteger('booking_id')->nullable()->after('kind');
            $table->unsignedBigInteger('parent_invoice_id')->nullable()->after('booking_id');
            // Avoir : remboursement d'origine (entrée du journal), une seule fois.
            $table->unsignedBigInteger('booking_event_id')->nullable()->after('parent_invoice_id');
            $table->string('refund_method', 20)->nullable()->after('booking_event_id'); // stripe | manual
            $table->string('refund_reference', 191)->nullable()->after('refund_method');
            $table->string('refund_reason', 500)->nullable()->after('refund_reference');
            // Facture / avoir émis (numéro Odoo reçu) ; dernière erreur Odoo sinon.
            $table->dateTime('issued_at')->nullable()->after('refund_reason');
            $table->text('odoo_error')->nullable()->after('issued_at');

            $table->index('booking_id');
            $table->index('parent_invoice_id');
            $table->unique('booking_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['booking_event_id']);
            $table->dropIndex(['booking_id']);
            $table->dropIndex(['parent_invoice_id']);
            $table->dropColumn([
                'kind', 'booking_id', 'parent_invoice_id', 'booking_event_id',
                'refund_method', 'refund_reference', 'refund_reason', 'issued_at', 'odoo_error',
            ]);
        });
    }
};
