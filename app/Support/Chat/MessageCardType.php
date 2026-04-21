<?php

namespace Phunky\Support\Chat;

/**
 * Top-level message chrome: which shell and bubble pattern the row uses.
 *
 * {@see MessageBubbleLayout} describes inner layout (text vs attachments vs caption)
 * inside {@see MessageCardType::StandardBubble} only.
 */
enum MessageCardType
{
    /**
     * Single padded message container (rounded card) for text, voice, images, documents,
     * regular video, and mixed content.
     */
    case StandardBubble;

    /**
     * Video-note-only row: circular clip, reactions beside it, optional caption bubble below.
     * Does not wrap the primary media in the standard bubble card.
     */
    case VideoNoteTray;
}
