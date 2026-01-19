<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Support\Facades\Password;

new
    #[Layout('layouts::guest')]
    #[Title('Forgot Password')]
    class extends Component {
        
    public string $email = '';
    public ?string $status = null;

    protected array $rules = [
        'email' => 'required|email',
    ];

    public function sendResetLink()
    {
        $this->validate();

        $status = Password::broker()->sendResetLink(
            ['email' => $this->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            $this->status = __($status);
        } else {
            $this->addError('email', __($status));
        }
    }
};

?>

<main class="max-w-xl w-full mx-auto p-6">
    <x-form wire:submit="sendResetLink">
        <div class="flex items-center flex-col gap-4">
            <x-app-brand class="mb-6" />
            <p class="text-xl font-semibold">Forgot Password</p>
            <p>{{ $status }}</p>
        </div>

        <x-input label="Email" wire:model="email" icon="o-envelope" />

        <x-slot:actions>
            <x-button label="Send" class="btn-primary w-full" type="submit" spinner />
        </x-slot:actions>
    </x-form>
</main>