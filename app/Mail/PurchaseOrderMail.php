<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a purchase order to the supplier with the PO PDF attached.
 */
class PurchaseOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        $replyTo = setting('business.email') ?: null;

        return new Envelope(
            subject: __('Purchase order :n from :b', ['n' => $this->order->number, 'b' => setting('business.name')]),
            replyTo: $replyTo ? [$replyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.purchase-order', with: [
            'order' => $this->order,
            'note' => $this->note,
            'business' => setting('business.name'),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $order = $this->order->loadMissing(['items.product.unit', 'supplier', 'branch', 'creator']);
        $pdf = Pdf::loadView('pdf.purchase-order', ['order' => $order, 'branch' => $order->branch, 'title' => $order->number, 'docTitle' => __('Purchase order')])
            ->setPaper('a4')->output();

        return [Attachment::fromData(fn () => $pdf, $order->number.'.pdf')->withMime('application/pdf')];
    }
}
