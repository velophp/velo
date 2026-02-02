<?php

use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

new
#[Layout('layouts::guest')]
#[Title('Login')]
class extends Component {

    public bool $remember = false;
    public string $password = 'password';
    public string $email = 'admin@velobase.dev';

    public function mount()
    {
        if (!Project::exists()) {
            return $this->redirect(route('register'), navigate: true);
        }
    }

    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public function login()
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        session()->regenerate();

        return $this->redirect(route('home'), navigate: true);
    }

};

?>

<main class="max-w-xl w-full mx-auto p-6">
    <x-form wire:submit="login">
        <div class="flex justify-center">
            <x-app-brand class="mb-6"/>
        </div>

        <x-input label="Email" wire:model="email" icon="o-envelope"/>
        <x-password label="Password" wire:model="password" password-icon="o-key"/>

        <div class="flex justify-between flex-wrap">
            <x-toggle label="Remember Me" wire:model="remember"/>
            <a class="link link-hover" href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <x-slot:actions>
            <x-button label="Login" class="btn-primary w-full" type="submit" spinner="login"/>
        </x-slot:actions>
    </x-form>
</main>
