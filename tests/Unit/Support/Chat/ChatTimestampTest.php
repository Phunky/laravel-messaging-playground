<?php

namespace Tests\Unit\Support\Chat;

use Illuminate\Support\Carbon;
use Phunky\Support\Chat\ChatTimestamp;
use Tests\TestCase;

class ChatTimestampTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/London']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bubble_time_returns_empty_for_null_or_blank(): void
    {
        $this->assertSame('', ChatTimestamp::bubbleTime(null));
        $this->assertSame('', ChatTimestamp::bubbleTime(''));
    }

    public function test_bubble_time_formats_in_app_timezone(): void
    {
        $this->assertSame('9:05 am', ChatTimestamp::bubbleTime('2026-04-20T08:05:00Z'));
    }

    public function test_bubble_edited_time_uses_24h_format(): void
    {
        $this->assertSame('14:32', ChatTimestamp::bubbleEditedTime('2026-04-20T13:32:00Z'));
        $this->assertSame('', ChatTimestamp::bubbleEditedTime(null));
    }

    public function test_inbox_label_returns_time_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $this->assertSame('9:05 am', ChatTimestamp::inbox('2026-04-20T08:05:00Z'));
    }

    public function test_inbox_label_returns_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $this->assertSame('Yesterday', ChatTimestamp::inbox('2026-04-19T15:00:00Z'));
    }

    public function test_inbox_label_returns_weekday_within_last_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $label = ChatTimestamp::inbox('2026-04-17T15:00:00Z');

        $this->assertNotSame('Yesterday', $label);
        $this->assertMatchesRegularExpression('/^[A-Za-z]+$/', $label);
    }

    public function test_inbox_label_falls_back_to_date_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $this->assertSame('01/04/2026', ChatTimestamp::inbox('2026-04-01T10:00:00Z'));
    }

    public function test_date_separator_returns_today_and_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $this->assertSame('Today', ChatTimestamp::dateSeparator('2026-04-20T11:00:00Z'));
        $this->assertSame('Yesterday', ChatTimestamp::dateSeparator('2026-04-19T11:00:00Z'));
    }

    public function test_date_separator_falls_back_to_date_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));

        $this->assertSame('01/04/2026', ChatTimestamp::dateSeparator('2026-04-01T10:00:00Z'));
    }

    public function test_day_bucket_returns_iso_date_in_app_timezone(): void
    {
        $this->assertSame('2026-04-20', ChatTimestamp::dayBucket('2026-04-20T00:05:00+01:00'));
        $this->assertNull(ChatTimestamp::dayBucket(null));
        $this->assertNull(ChatTimestamp::dayBucket(''));
    }
}
