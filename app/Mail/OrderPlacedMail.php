<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Nội dung mail "Đặt hàng thành công". Việc xếp hàng đợi do Listener lo, Mailable này không queue riêng. */
class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $code = $this->order->code ?? '#'.$this->order->id;

        return new Envelope(subject: "Đặt hàng thành công - Đơn {$code} - GardenLife");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.orders.placed');
    }
}
