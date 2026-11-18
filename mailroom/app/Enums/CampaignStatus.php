<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Testing = 'testing';
    case Sent = 'sent';

    public function label(): string
    {
        return $this === self::Testing ? 'A/B testing' : ucfirst($this->value);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Scheduled;
    }
}
