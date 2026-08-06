<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating invoice PDFs with proper ISP letterhead.
 * All amounts are in poysha (BDT x 100), but displayed as BDT in the PDF.
 */
class InvoicePdfService
{
    protected PDF $pdf;

    public function __construct(PDF $pdf)
    {
        $this->pdf = $pdf;
    }

    /**
     * Generate PDF for an invoice and save it to storage.
     */
    public function generateAndSave(Invoice $invoice): string
    {
        $pdfContent = $this->generate($invoice);

        $filename = $this->getFilename($invoice);
        $path = 'invoices/' . $filename;

        Storage::disk('public')->put($path, $pdfContent);

        return $path;
    }

    /**
     * Generate PDF content for an invoice.
     */
    public function generate(Invoice $invoice): string
    {
        $this->pdf->loadView('billing.invoice_pdf', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
            'companyName' => config('app.name', 'Fiberloop'),
            'companyAddress' => $this->getCompanyAddress(),
            'companyContact' => $this->getCompanyContact(),
        ]);

        $this->pdf->setPaper('a4', 'portrait');
        $this->pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $this->pdf->output();
    }

    /**
     * Get the filename for the invoice PDF.
     */
    protected function getFilename(Invoice $invoice): string
    {
        return sprintf(
            'invoice_%s_%s.pdf',
            $invoice->invoice_number,
            now()->format('Ymd_His')
        );
    }

    /**
     * Get company address for the invoice.
     */
    protected function getCompanyAddress(): string
    {
        return "Fiberloop Internet Services\n"
            . "123 ISP Tower, Dhaka-1205\n"
            . "Bangladesh";
    }

    /**
     * Get company contact information.
     */
    protected function getCompanyContact(): string
    {
        return "Phone: +880 1234 567890\n"
            . "Email: billing@fiberloop.com\n"
            . "Website: https://fiberloop.com.bd";
    }

    /**
     * Format amount from poysha to BDT.
     */
    public function formatAmount(int $poysha): string
    {
        $bdt = $poysha / 100;
        return number_format($bdt, 2);
    }
}
