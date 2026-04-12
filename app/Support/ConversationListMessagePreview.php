<?php

namespace Phunky\Support;

use Illuminate\Support\Str;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;

final class ConversationListMessagePreview
{
    /**
     * Subtitle line for the conversation list (body snippet or attachment summary).
     */
    public static function subtitle(Message $message): string
    {
        $body = trim((string) $message->body);
        if ($body !== '') {
            return Str::limit($body, 75);
        }

        $message->loadMissing('attachments');

        return self::attachmentsSummary($message);
    }

    /**
     * @return list<string>
     */
    private static function partsFromAttachments(Message $message): array
    {
        if ($message->attachments->isEmpty()) {
            return [];
        }

        $counts = [];
        foreach ($message->attachments as $row) {
            if (! $row instanceof MessageAttachment) {
                continue;
            }
            $type = (string) $row->type;
            if (! MessageAttachmentTypeRegistry::has($type)) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $parts = [];
        foreach (['image', 'video', 'voice_note', 'document'] as $type) {
            $n = $counts[$type] ?? 0;
            if ($n < 1) {
                continue;
            }
            $parts[] = match ($type) {
                'image' => $n === 1 ? __('Photo') : __(':count photos', ['count' => $n]),
                'video' => $n === 1 ? __('Video') : __(':count videos', ['count' => $n]),
                'voice_note' => $n === 1 ? __('Voice message') : __(':count voice messages', ['count' => $n]),
                'document' => $n === 1 ? __('Document') : __(':count documents', ['count' => $n]),
                default => '',
            };
        }

        return array_values(array_filter($parts));
    }

    private static function attachmentsSummary(Message $message): string
    {
        $parts = self::partsFromAttachments($message);
        if ($parts === []) {
            return '';
        }

        return self::joinNaturalLanguage($parts);
    }

    /**
     * @param  list<string>  $parts
     */
    private static function joinNaturalLanguage(array $parts): string
    {
        $c = count($parts);
        if ($c === 0) {
            return '';
        }
        if ($c === 1) {
            return $parts[0];
        }
        if ($c === 2) {
            return $parts[0].' '.__('and').' '.$parts[1];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' '.__('and').' '.$last;
    }
}
