<?php

use Livewire\Component;
use Livewire\Attributes\{Layout, Title, Url};
use Mary\Traits\Toast;

new #[Layout('layouts::guest')] #[Title('Confirm Reset Password')] class extends Component {

    use Toast;

    #[Url]
    public $token = '';

    #[Url]
    public $email = '';

    public $password = '', $password_confirmation = '';

    public function rules()
    {
        return [
            'password' => ['required', \Illuminate\Validation\Rules\Password::min(8), 'confirmed'],
        ];
    }

    public function resetPassword()
    {
        $this->validate();

        $status = Password::broker()->reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new \Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->success(__($status));
            return $this->redirectRoute('login', navigate: true);
        } else {
            $this->error(__($status));
        }
    }
};

?>

<main class="max-w-xl w-full mx-auto p-6">
    <x-form wire:submit="resetPassword">
        <div class="flex items-center flex-col gap-4">
            <x-app-brand class="mb-6" />
            <p class="text-xl font-semibold">Set New Password</p>
        </div>

        <x-password label="New Password" autocomplete="new-password" wire:model="password" password-icon="o-key"
            required />
        <x-password label="Confirm Password" wire:model="password_confirmation" password-icon="o-key" required />

        <x-slot:actions>
            <x-button label="Send" class="btn-primary w-full" type="submit" spinner="resetPassword" />
        </x-slot:actions>
    </x-form>
</main>