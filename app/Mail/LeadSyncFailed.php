<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Báo dev khi một lead không đẩy được sang Mautic sau mọi lần thử. */
class LeadSyncFailed extends Mailable
{
    public function __construct(public int $leadId, public int $tries, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Page vệ tinh] Lead #{$this->leadId} không đồng bộ được sang Mautic");
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Lead #'.e((string) $this->leadId).' chưa vào Mautic sau '.$this->tries.' lần thử.</p>'
            .'<p>Lỗi cuối: '.e($this->reason).'</p>'
            .'<p>Lead vẫn an toàn trong CMS. Sửa lỗi kết nối rồi bấm "Đồng bộ lại" trong trang quản trị.</p>');
    }
}
