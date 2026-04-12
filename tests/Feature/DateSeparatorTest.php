<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DateSeparatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_renders_today_yesterday_weekday_and_numeric_labels(): void
    {
        config(['app.timezone' => 'Australia/Sydney']);
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'Australia/Sydney'));

        Livewire::test('chat.date-separator', ['sentAt' => '2026-02-11T09:00:00+11:00'])
            ->assertSee(__('Today'), false);

        Livewire::test('chat.date-separator', ['sentAt' => '2026-02-10T09:00:00+11:00'])
            ->assertSee(__('Yesterday'), false);

        $feb9 = Carbon::parse('2026-02-09T09:00:00+11:00', 'Australia/Sydney');
        Livewire::test('chat.date-separator', ['sentAt' => '2026-02-09T09:00:00+11:00'])
            ->assertSee($feb9->translatedFormat('l'), false);

        $feb4 = Carbon::parse('2026-02-04T09:00:00+11:00', 'Australia/Sydney');
        Livewire::test('chat.date-separator', ['sentAt' => '2026-02-04T09:00:00+11:00'])
            ->assertSee($feb4->translatedFormat('l'), false);

        Livewire::test('chat.date-separator', ['sentAt' => '2026-02-03T09:00:00+11:00'])
            ->assertSee('03/02/2026', false);
    }
}
