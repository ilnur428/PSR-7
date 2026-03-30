<?php

declare(strict_types=1);

namespace Ilnur428\Psr7Decorators\WhatsApp;

enum WhatsAppMediaType: string
{
    case IMAGE = 'WhatsApp Image Keys';
    case VIDEO = 'WhatsApp Video Keys';
    case AUDIO = 'WhatsApp Audio Keys';
    case DOCUMENT = 'WhatsApp Document Keys';

    public function applicationInfo(): string
    {
        return $this->value;
    }

    public function isStreamable(): bool
    {
        return $this === self::VIDEO || $this === self::AUDIO;
    }
}