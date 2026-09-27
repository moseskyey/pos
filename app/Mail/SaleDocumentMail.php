<?php

namespace App\Mail;

use App\Models\Sale;
use App\Services\ReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emails a sale document to the customer: tax invoice / receipt, or a
 * quotation, with the A4 PDF attached.
 */
class SaleDocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Sale $sale, public ?string $note = null) {}

    public function isQuotation(): bool
    {
        return $this->sale->status->value === 'quotation';
    }

    public function documentTitle(): string
    {
        return $this->isQuotation() ? __('Quotation') : __('Tax Invoice');
    }

    public function envelope(): Envelope
    {
        $replyTo = setting('business.email') ?: null;

        return new Envelope(
            subject: __(':doc :n from :b', ['doc' => $this->documentTitle(), 'n' => $this->sale->number, 'b' => setting('business.name')]),
            replyTo: $replyTo ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.sale-document', with: [
            'sale' => $this->sale,
            'note' => $this->note,
            'quotation' => $this->isQuotation(),
            'business' => setting('business.name'),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $data = app(ReceiptService::class)->data($this->sale) + [
            'docTitle' => $this->documentTitle(), 'title' => $this->sale->number, 'copy' => $this->sale->status->value === 'voided',
        ];
        $pdf = Pdf::loadView('pdf.invoice', $data)->setPaper('a4')->output();

        return [Attachment::fromData(fn () => $pdf, $this->sale->number.'.pdf')->withMime('application/pdf')];
    }
}
