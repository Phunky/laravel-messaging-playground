<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Phunky\Support\DocumentAttachmentIcon;
use Tests\TestCase;

class DocumentAttachmentIconTest extends TestCase
{
    public function test_resolves_default_icon_for_unknown_mime_and_extension(): void
    {
        $this->assertSame('document', DocumentAttachmentIcon::resolve(null, 'file.unknownext'));
    }

    public function test_resolves_from_configured_mime(): void
    {
        Config::set('messaging.document_attachment_icons.mimes.application/pdf', 'clipboard-document-list');

        $this->assertSame('clipboard-document-list', DocumentAttachmentIcon::resolve('application/pdf', 'ignored.txt'));
    }

    public function test_resolves_from_extension_when_mime_not_in_config(): void
    {
        Config::set('messaging.document_attachment_icons.mimes', []);
        Config::set('messaging.document_attachment_icons.extensions.pdf', 'clipboard-document-list');

        $this->assertSame('clipboard-document-list', DocumentAttachmentIcon::resolve(null, 'x.pdf'));
    }

    public function test_empty_filename_falls_back_to_default(): void
    {
        $this->assertSame('document', DocumentAttachmentIcon::resolve('application/octet-stream', ''));
    }
}
