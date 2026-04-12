<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $sentAt = null;

    #[Computed]
    public function label(): string
    {
        if ($this->sentAt === null || $this->sentAt === '') {
            return '';
        }

        $timezone = (string) config('app.timezone');
        $d = Carbon::parse($this->sentAt)->timezone($timezone)->startOfDay();
        $today = Carbon::now()->timezone($timezone)->startOfDay();

        if ($d->equalTo($today)) {
            return __('Today');
        }

        if ($d->equalTo($today->copy()->subDay())) {
            return __('Yesterday');
        }

        $sevenDaysAgo = $today->copy()->subDays(7)->startOfDay();
        $twoDaysAgo = $today->copy()->subDays(2)->startOfDay();

        if ($d->betweenIncluded($sevenDaysAgo, $twoDaysAgo)) {
            return $d->translatedFormat('l');
        }

        return $d->format('d/m/Y');
    }
};
?>

<div class="sticky top-0 flex items-center z-10">
    <flux:spacer />
    <flux:badge size="sm" variant="solid" rounded class="min-w-[100px] justify-around">{{ $this->label }}</flux:badge>
    <flux:spacer />
</div>
