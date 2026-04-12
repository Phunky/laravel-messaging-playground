<?php

use Phunky\Actions\Fortify\UpdateUserProfileInformation;
use Phunky\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Profile')]
class extends Component {
    public string $name = '';

    public string $email = '';

    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(UpdateUserProfileInformation $updater): void
    {
        $this->saved = false;

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag();

        try {
            $updater->update($user, [
                'name' => $this->name,
                'email' => $this->email,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->messages() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }

        $this->saved = true;
    }
};
?>

<div class="mx-auto max-w-lg px-4 py-12">
    <div class="mb-8 flex items-center justify-between gap-4">
        <flux:heading size="xl">{{ __('Profile') }}</flux:heading>
        <flux:button size="sm" variant="ghost" href="{{ url('/') }}" tag="a">
            {{ __('Back to chat') }}
        </flux:button>
    </div>

    <flux:card class="space-y-6 p-8">
        @if ($saved)
            <flux:callout variant="success" icon="check-circle">
                {{ __('Profile saved.') }}
            </flux:callout>
        @endif

        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" type="text" required autocomplete="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email') }}</flux:label>
                <flux:input wire:model="email" type="email" required autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                {{ __('Save changes') }}
            </flux:button>
        </form>
    </flux:card>
</div>
