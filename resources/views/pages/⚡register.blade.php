<?php

use App\Domain\Project\Models\Project;
use App\Models\{\User};
use App\Support\Helper;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

new
#[Layout('layouts::guest')]
#[Title('Register')]
class extends Component {

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules()
    {
        return [
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ];
    }

    public function mount()
    {
        if (Project::exists() && User::exists()) {
            return $this->redirect(route('login'), navigate: true);
        }
    }

    public function register()
    {
        $this->validate();

        if (Project::exists() && User::exists()) abort(403);

        $user = Helper::initProject($this->email, $this->password);

        Auth::login($user);

        return $this->redirect(route('home'), navigate: true);
    }
};

?>

<main class="max-w-xl w-full mx-auto p-6">
    <x-form wire:submit="register">
        <x-app-brand class="mb-6"/>

        <x-input label="Email" wire:model="email" icon="o-envelope"/>
        <x-password label="Password" wire:model="password" password-icon="o-lock-closed"/>
        <x-password label="Confirm Password" wire:model="password_confirmation" password-icon="o-lock-closed"/>

        <x-slot:actions>
            <x-button label="Register" class="btn-primary w-full" type="submit" spinner="register"/>
        </x-slot:actions>
    </x-form>
</main>
