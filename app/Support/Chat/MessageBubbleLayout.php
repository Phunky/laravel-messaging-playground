<?php

namespace Phunky\Support\Chat;

/**
 * Inner layout inside a {@see MessageCardType::StandardBubble} card: how body, attachments,
 * and timestamps share the padded bubble (not used for {@see MessageCardType::VideoNoteTray}).
 */
enum MessageBubbleLayout
{
    /** Body only; no attachments. */
    case TextOnly;

    /** Attachments only; timestamp sits below media (no caption). */
    case AttachmentsOnly;

    /** Body plus attachments; timestamp floats on the caption line. */
    case Captioned;
}
