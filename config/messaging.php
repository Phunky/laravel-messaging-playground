<?php

use Phunky\Extensions\ExampleExtension;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\LaravelMessagingAttachments\AttachmentExtension;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessagingGroups\GroupsExtension;
use Phunky\LaravelMessagingReactions\ReactionsExtension;

return [

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    | Prefix applied to all package database tables (core + bundled extensions).
    */
    'table_prefix' => 'messaging_',

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Cursor pagination is recommended for chat interfaces.
    */
    'pagination' => [
        'type' => 'cursor', // 'cursor' | 'offset'
        'per_page' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    | When enabled, core messaging events broadcast on presence channels:
    | {channel_prefix}.conversation.{conversationId}. Inbox updates broadcast
    | to each participant's configured private user channel.
    */
    'broadcasting' => [
        'enabled' => (bool) env('MESSAGING_BROADCASTING_ENABLED', false),
        'channel_prefix' => env('MESSAGING_BROADCASTING_CHANNEL_PREFIX', 'messaging'),
        'inbox_channel_pattern' => env('MESSAGING_INBOX_CHANNEL_PATTERN', 'App.Models.User.{id}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message media (attachments)
    |--------------------------------------------------------------------------
    | Disk name from config/filesystems.php used for uploads and signed URLs.
    */
    'media_disk' => env('MESSAGING_MEDIA_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Video note recording (in-app camera)
    |--------------------------------------------------------------------------
    |
    | Maximum length for a single recorded video note clip (browser capture).
    |
    */
    'video_note_max_record_seconds' => max(5, min(600, (int) env('MESSAGING_VIDEO_NOTE_MAX_RECORD_SECONDS', 60))),

    /*
    |--------------------------------------------------------------------------
    | Message attachment kinds (stored as media `type`)
    |--------------------------------------------------------------------------
    |
    | Keys are persisted on media rows. Each kind defines the file input accept
    | attribute, per-file validation rules, and the maximum number of files per
    | message for that kind.
    |
    */
    'media_attachment_types' => [
        'image' => [
            'label' => 'Images',
            'accept' => 'image/*',
            'max_files' => 10,
            'rules' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/heic,image/heif',
                'max:51200',
            ],
        ],
        'video' => [
            'label' => 'Videos',
            'accept' => 'video/*',
            'max_files' => 1,
            'rules' => [
                'file',
                'mimes:mp4,mov,webm,avi,wmv,mkv,3gp,3g2,ogv,m4v,mpeg,mpg,ogg',
                'max:51200',
            ],
        ],
        'document' => [
            'label' => 'Documents',
            'accept' => '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'max_files' => 10,
            'rules' => ['file', 'mimes:pdf,doc,docx', 'max:20480'],
        ],
        'voice_note' => [
            'label' => 'Voice note',
            'accept' => 'audio/webm,audio/mp4,audio/mpeg,audio/ogg,audio/wav,.webm,.m4a,.mp3,.ogg,.wav',
            'max_files' => 1,
            'rules' => ['file', 'mimes:webm,weba,m4a,mp3,ogg,wav,mp4', 'max:8192'],
        ],
        'video_note' => [
            'label' => 'Video note',
            'accept' => 'video/webm,video/mp4,video/quicktime,video/ogg,.webm,.mp4,.mov',
            'max_files' => 1,
            'rules' => [
                'file',
                'mimes:webm,mp4,mov',
                'max:16384',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document attachment icons (message bubble preview)
    |--------------------------------------------------------------------------
    |
    | Flux icon names (Heroicons) shown beside the filename for document
    | attachments. Keys in mimes/extensions may overlap; MIME is checked first,
    | then file extension, then default.
    |
    */
    'document_attachment_icons' => [
        'default' => 'document',
        'mimes' => [
            'application/pdf' => 'document',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        ],
        'extensions' => [
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    | Swap out core models with your own implementations.
    | Each replacement must implement the corresponding contract.
    | `group` applies when the Groups extension is enabled.
    */
    'models' => [
        'conversation' => Conversation::class,
        'participant' => Participant::class,
        'message' => Message::class,
        'event' => MessagingEvent::class,
        'group' => Group::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extensions
    |--------------------------------------------------------------------------
    | Each extension must implement MessagingExtension.
    */
    'extensions' => [
        GroupsExtension::class,
        ReactionsExtension::class,
        AttachmentExtension::class,
        // No-op reference for building custom MessagingExtension implementations.
        ExampleExtension::class,
    ],

];
