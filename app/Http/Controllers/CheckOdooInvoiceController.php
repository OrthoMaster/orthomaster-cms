<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CheckOdooInvoiceController extends Controller
{
    public function __construct(
        protected OdooService $odooService
    ) {}

    protected array $paymentStateLabels = [
        'not_paid'   => 'Non payée',
        'in_payment' => 'En cours de paiement',
        'paid'       => 'Payée',
        'partial'    => 'Partiellement payée',
        'reversed'   => 'Annulée',
    ];

    /**
     * Lấy toàn bộ hoá đơn + avoir khách hàng (account.move) đã posted
     * có invoice_date trong năm 2025, kèm field liên quan Apprenants/Devis
     */
    public function getInvoiceList2025(): array
    {
        $domain = [
            ['move_type', 'in', ['out_invoice', 'out_refund']],
            ['state', '=', 'posted'],
            ['invoice_date', '>=', '2025-01-01'],
            ['invoice_date', '<=', '2025-12-31'],
        ];

        $fields = [
            'id',
            'name',
            'move_type',
            'partner_id',
            'invoice_date',
            'amount_total',
            'payment_state',
            'state',
            'x_studio_bnficiaires',
            'x_studio_bon_de_commande_associ',
        ];

        try {
            return $this->odooService->searchRead('account.move', $domain, $fields, 0);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'list-2025']);
            return [];
        }
    }

    /**
     * Lấy tổng quantity theo từng invoice (loại bỏ dòng section/note)
     *
     * @param array $invoiceIds
     * @return array  [invoice_id => total_qty]
     */
    protected function getQuantitiesByInvoice(array $invoiceIds): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        try {
            $lines = $this->odooService->searchRead(
                'account.move.line',
                [
                    ['move_id', 'in', $invoiceIds],
                    ['display_type', '=', false], // loại section/note, chỉ lấy dòng sản phẩm
                ],
                ['move_id', 'quantity']
            );
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'quantities']);
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            $moveId = $line['move_id'][0] ?? null;
            if ($moveId === null) {
                continue;
            }
            $result[$moveId] = ($result[$moveId] ?? 0) + ($line['quantity'] ?? 0);
        }

        return $result;
    }

    /**
     * Chuẩn bị dữ liệu export: mỗi invoice -> danh sách rows (1 row / apprenant)
     *
     * @return array
     */
    public function getInvoiceExportData2025(): array
    {
        $invoices = $this->getInvoiceList2025();

        if (empty($invoices)) {
            return [];
        }

        $invoiceIds = array_column($invoices, 'id');
        $quantities = $this->getQuantitiesByInvoice($invoiceIds);

        // Gom toàn bộ apprenant IDs cần đọc thông tin (Bénéficiaires hoặc chính Client)
        $apprenantIds = [];
        foreach ($invoices as $inv) {
            $beneficiaires = $inv['x_studio_bnficiaires'] ?? [];
            if (!empty($beneficiaires)) {
                foreach ($beneficiaires as $pid) {
                    $apprenantIds[] = $pid;
                }
            } elseif (!empty($inv['partner_id'])) {
                $apprenantIds[] = $inv['partner_id'][0];
            }
        }
        $apprenantIds = array_values(array_unique($apprenantIds));

        // Đọc info Apprenants (tên + organisme financeur) trong 1 lần gọi
        $apprenantsMap = [];
        if (!empty($apprenantIds)) {
            try {
                $partners = $this->odooService->searchRead(
                    'res.partner',
                    [['id', 'in', $apprenantIds]],
                    ['id', 'name', 'x_studio_organisme_financeur']
                );
                foreach ($partners as $p) {
                    $apprenantsMap[$p['id']] = [
                        'name'               => $p['name'] ?? '',
                        'organisme_financeur' => $p['x_studio_organisme_financeur'] ?? '',
                    ];
                }
            } catch (\Exception $e) {
                Log::channel('api')->error($e->getMessage(), ['odoo', 'invoice', 'apprenants-info']);
            }
        }

        // Build rows
        $rows = [];
        foreach ($invoices as $inv) {
            $beneficiaires = $inv['x_studio_bnficiaires'] ?? [];

            // Case rỗng -> apprenant = chính partner_id
            if (empty($beneficiaires) && !empty($inv['partner_id'])) {
                $beneficiaires = [$inv['partner_id'][0]];
            }
            if (empty($beneficiaires)) {
                $beneficiaires = [null]; // vẫn tạo 1 row để không mất invoice
            }

            $devisNo = $inv['x_studio_bon_de_commande_associ'][1] ?? '';
            $clientName = $inv['partner_id'][1] ?? '';
            $qty = $quantities[$inv['id']] ?? 0;
            $paymentState = $inv['payment_state'] ?? '';
            $statusLabel = $this->paymentStateLabels[$paymentState] ?? $paymentState;

            $isFirstRowOfInvoice = true;
            $apprenantCount = count($beneficiaires);

            foreach ($beneficiaires as $apprenantId) {
                $apprenantInfo = $apprenantsMap[$apprenantId] ?? ['name' => '', 'organisme_financeur' => ''];

                $rows[] = [
                    'invoice_id'          => $inv['id'],
                    'invoice_no'          => $inv['name'],
                    'devis_no'            => $devisNo,
                    'invoice_date'        => $inv['invoice_date'],
                    'status'              => $statusLabel,
                    'quantity'            => $qty,
                    'amount_total'        => $inv['amount_total'],
                    'client'              => $clientName,
                    'apprenant'           => $apprenantInfo['name'],
                    'organisme_financeur' => $apprenantInfo['organisme_financeur'],
                    '_is_first_row'       => $isFirstRowOfInvoice,
                    '_row_span'           => $apprenantCount,
                ];

                $isFirstRowOfInvoice = false;
            }
        }

        return $rows;
    }

    /**
     * Xuất file Excel danh sách invoice 2025
     * Avoir (out_refund / RVE/...) -> montant négatif
     */
    public function exportInvoicesToExcel2025()
    {
        $rows = $this->getInvoiceExportData2025();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Factures 2025');

        // ===== Header =====
        $headers = [
            'A' => 'N° Facture',
            'B' => 'N° Devis',
            'C' => 'Date facturation',
            'D' => 'Statut',
            'E' => 'Quantité',
            'F' => 'Montant total',
            'G' => 'Client',
            'H' => 'Apprenant',
            'I' => 'Organisme financeur',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }

        $headerStyle = $sheet->getStyle('A1:I1');
        $headerStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2E5C8A');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Các cột cấp invoice sẽ được merge khi có nhiều apprenants
        $invoiceLevelColumns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        $rowIndex = 2;
        $mergeStartRow = 2;

        foreach ($rows as $row) {
            // ===== Xác định avoir =====
            $invoiceNo = (string) ($row['invoice_no'] ?? '');
            $isRefund  = !empty($row['is_refund']) || str_starts_with($invoiceNo, 'RVE/');

            $amount = (float) ($row['amount_total'] ?? 0);
            if ($isRefund) {
                $amount = -abs($amount);
            }

            // ===== Ghi dữ liệu =====
            $sheet->setCellValue('A' . $rowIndex, $invoiceNo);
            $sheet->setCellValue('B' . $rowIndex, $row['devis_no']);
            $sheet->setCellValue('C' . $rowIndex, $row['invoice_date']);
            $sheet->setCellValue('D' . $rowIndex, $row['status']);
            $sheet->setCellValue('E' . $rowIndex, $row['quantity']);
            $sheet->setCellValue('F' . $rowIndex, $amount);
            $sheet->setCellValue('G' . $rowIndex, $row['client']);
            $sheet->setCellValue('H' . $rowIndex, $row['apprenant']);
            $sheet->setCellValue('I' . $rowIndex, $row['organisme_financeur']);

            // Số dương bình thường, số âm màu đỏ có dấu -
            $sheet->getStyle('F' . $rowIndex)->getNumberFormat()
                ->setFormatCode('#,##0.00 "€";[Red]-#,##0.00 "€"');

            // ===== Merge các cột invoice-level nếu có nhiều apprenants =====
            if ($row['_is_first_row']) {
                $mergeStartRow = $rowIndex;
            }

            $rowSpan = (int) $row['_row_span'];
            $isLastRowOfGroup = $rowSpan > 1
                && ($rowIndex - $mergeStartRow + 1 === $rowSpan);

            if ($isLastRowOfGroup) {
                foreach ($invoiceLevelColumns as $col) {
                    $sheet->mergeCells($col . $mergeStartRow . ':' . $col . $rowIndex);
                    $sheet->getStyle($col . $mergeStartRow)
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
            }

            $rowIndex++;
        }

        // ===== Border toàn bộ bảng =====
        $lastRow = $rowIndex - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('A1:I' . $lastRow)
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // ===== Auto width =====
        foreach (array_keys($headers) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ===== Lưu file & trả về =====
        $fileName = 'factures_2025_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = storage_path('app/tmp/' . $fileName);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
}
