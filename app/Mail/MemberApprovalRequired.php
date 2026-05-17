<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberApprovalRequired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $applicant,
        public readonly string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【{$this->companyName}】新成員申請待審核",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-approval-required',
        );
    }
}
