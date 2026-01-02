<?php

use function Livewire\Volt\{state, layout, mount, rules};
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

layout('components.layouts.guest');

state(['email', 'password']);

rules([
    'email' => 'required|email',
    'password' => 'required',
]);

mount(function() {
    if (!Project::exists()) {
        return $this->redirect(route('register'), navigate: true);
    }
});

$login = function() {
    $this->validate();

    if (!Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    session()->regenerate();

    return $this->redirect(route('home'), navigate: true);
};

?>

<main class="max-w-xl w-full mx-auto p-6">
    <x-form wire:submit="login">
        <x-app-brand class="mb-6" />
        
        <x-input label="Email" wire:model="email" icon="o-envelope" />
        <x-password label="Password" wire:model="password" password-icon="o-key" />

        <x-slot:actions>
            <x-button label="Login" class="btn-primary w-full" type="submit" spinner="login" />
        </x-slot:actions>
    </x-form>
</main>