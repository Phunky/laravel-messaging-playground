<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::app')]
#[Title('Login')]
class extends Component {};
?>

<div class="flex min-h-[calc(100vh-0px)] flex-col items-center justify-center px-4 py-12">
    <flux:card class="w-full max-w-md space-y-6 p-8">
        <div>
            <flux:heading size="lg">{{ __('Sign in') }}</flux:heading>
            <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Use a seeded account (password is') }}
                <flux:badge color="zinc">password</flux:badge>).
            </div>
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <flux:field>
                <flux:label>{{ __('Email') }}</flux:label>
                <flux:input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:input name="password" type="password" required autocomplete="current-password" />
                <flux:error name="password" />
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox name="remember" label="{{ __('Remember me') }}" />
            </flux:field>

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Log in') }}
            </flux:button>
        </form>
    </flux:card>
</div>
