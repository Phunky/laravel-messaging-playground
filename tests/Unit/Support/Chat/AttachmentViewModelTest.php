<?php

namespace Tests\Unit\Support\Chat;

use Phunky\Support\Chat\AttachmentViewModel;
use Tests\TestCase;

class AttachmentViewModelTest extends TestCase
{
    private function raw(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'type' => 'image',
            'url' => 'https://cdn.test/a.jpg',
            'filename' => 'a.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ], $overrides);
    }

    public function test_from_array_hydrates_all_fields(): void
    {
        $vm = AttachmentViewModel::fromArray($this->raw([
            'id' => '42',
            'size' => '4096',
        ]));

        $this->assertSame(42, $vm->id);
        $this->assertSame('image', $vm->type);
        $this->assertSame('https://cdn.test/a.jpg', $vm->url);
        $this->assertSame('a.jpg', $vm->filename);
        $this->assertSame('image/jpeg', $vm->mimeType);
        $this->assertSame(4096, $vm->size);
    }

    public function test_from_array_normalises_blank_mime_and_size(): void
    {
        $vm = AttachmentViewModel::fromArray($this->raw([
            'mime_type' => '',
            'size' => '',
        ]));

        $this->assertNull($vm->mimeType);
        $this->assertNull($vm->size);
    }

    public function test_coerce_list_accepts_dtos_and_arrays(): void
    {
        $dto = AttachmentViewModel::fromArray($this->raw(['id' => 1]));
        $list = AttachmentViewModel::coerceList([
            $dto,
            $this->raw(['id' => 2]),
            'ignored-string',
        ]);

        $this->assertCount(2, $list);
        $this->assertSame(1, $list[0]->id);
        $this->assertSame(2, $list[1]->id);
    }

    public function test_image_slot_detection_excludes_legacy_video_as_image(): void
    {
        $image = AttachmentViewModel::fromArray($this->raw(['id' => 1]));
        $legacyVideo = AttachmentViewModel::fromArray($this->raw([
            'id' => 2,
            'mime_type' => 'video/mp4',
        ]));

        $this->assertTrue($image->isImageSlot());
        $this->assertFalse($legacyVideo->isImageSlot());
        $this->assertTrue($legacyVideo->isVideoSlot());
    }

    public function test_group_splits_images_videos_voice_and_documents(): void
    {
        $rows = [
            $this->raw(['id' => 1, 'type' => 'image']),
            $this->raw(['id' => 2, 'type' => 'image']),
            $this->raw(['id' => 3, 'type' => 'video', 'mime_type' => 'video/mp4', 'url' => 'https://cdn.test/v.mp4', 'filename' => 'v.mp4']),
            $this->raw(['id' => 4, 'type' => 'voice_note', 'mime_type' => 'audio/webm', 'url' => 'https://cdn.test/v.webm', 'filename' => 'v.webm']),
            $this->raw(['id' => 5, 'type' => 'document', 'mime_type' => 'application/pdf', 'url' => 'https://cdn.test/d.pdf', 'filename' => 'd.pdf']),
        ];

        $groups = AttachmentViewModel::groupFromArrays($rows);

        $this->assertCount(4, $groups);
        $this->assertSame(AttachmentViewModel::GROUP_IMAGES, $groups[0]['kind']);
        $this->assertCount(2, $groups[0]['items']);
        $this->assertSame(AttachmentViewModel::GROUP_VIDEO, $groups[1]['kind']);
        $this->assertSame(AttachmentViewModel::GROUP_VOICE, $groups[2]['kind']);
        $this->assertSame(AttachmentViewModel::GROUP_DOCUMENT, $groups[3]['kind']);
    }

    public function test_group_drops_items_without_url(): void
    {
        $rows = [
            $this->raw(['id' => 1, 'url' => '']),
            $this->raw(['id' => 2, 'type' => 'document', 'mime_type' => 'application/pdf', 'url' => '', 'filename' => 'x.pdf']),
        ];

        $this->assertSame([], AttachmentViewModel::groupFromArrays($rows));
    }

    public function test_image_grid_cells_single_image_spans_full_grid(): void
    {
        $cells = AttachmentViewModel::imageGridCells([$this->raw()]);

        $this->assertCount(1, $cells);
        $this->assertSame('col-span-2 row-span-2', $cells[0]['span']);
        $this->assertSame(0, $cells[0]['overflow']);
    }

    public function test_image_grid_cells_three_images_widens_third(): void
    {
        $items = [
            $this->raw(['id' => 1]),
            $this->raw(['id' => 2]),
            $this->raw(['id' => 3]),
        ];

        $cells = AttachmentViewModel::imageGridCells($items);

        $this->assertCount(3, $cells);
        $this->assertSame('col-span-1 row-span-1', $cells[0]['span']);
        $this->assertSame('col-span-1 row-span-1', $cells[1]['span']);
        $this->assertSame('col-span-2 row-span-1', $cells[2]['span']);
    }

    public function test_image_grid_cells_overflow_marks_fourth_cell(): void
    {
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = $this->raw(['id' => $i]);
        }

        $cells = AttachmentViewModel::imageGridCells($items);

        $this->assertCount(4, $cells);
        foreach ($cells as $index => $cell) {
            $this->assertSame('col-span-1 row-span-1', $cell['span']);
            $this->assertSame($index === 3 ? 2 : 0, $cell['overflow']);
        }
    }

    public function test_document_type_label_uses_extension_fallback(): void
    {
        $pdf = AttachmentViewModel::fromArray($this->raw([
            'type' => 'document',
            'filename' => 'report.pdf',
            'mime_type' => 'application/pdf',
        ]));
        $docx = AttachmentViewModel::fromArray($this->raw([
            'type' => 'document',
            'filename' => 'notes.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]));
        $other = AttachmentViewModel::fromArray($this->raw([
            'type' => 'document',
            'filename' => 'data.json',
            'mime_type' => 'application/json',
        ]));

        $this->assertSame('PDF', $pdf->documentTypeLabel());
        $this->assertSame('Word', $docx->documentTypeLabel());
        $this->assertSame('JSON', $other->documentTypeLabel());
    }

    public function test_size_label_and_meta_line(): void
    {
        $pdf = AttachmentViewModel::fromArray($this->raw([
            'type' => 'document',
            'filename' => 'report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ]));

        $this->assertNotNull($pdf->sizeLabel());
        $this->assertStringContainsString('PDF', $pdf->documentMetaLine());
    }

    public function test_open_media_payload_omits_null_message_id(): void
    {
        $vm = AttachmentViewModel::fromArray($this->raw());

        $this->assertSame(['attachmentId' => 1], $vm->openMediaPayload(null));
        $this->assertSame(['attachmentId' => 1, 'messageId' => 99], $vm->openMediaPayload(99));
    }

    public function test_wireable_round_trip(): void
    {
        $vm = AttachmentViewModel::fromArray($this->raw());
        $hydrated = AttachmentViewModel::fromLivewire($vm->toLivewire());

        $this->assertEquals($vm, $hydrated);
    }
}
