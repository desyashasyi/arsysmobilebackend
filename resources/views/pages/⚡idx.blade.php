<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new class extends Component {
    public $roles;

    public function mount()
    {
        $this->roles = auth()->user()->getRoleNames();
    }

};

?>

<x-card title="Auth | Dashboard" shadow separator>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="mb-4">{{ __("Selamat datang di Dashboard utama!") }}</p>

                    <h3 class="text-lg font-semibold mb-2">Peran Anda:</h3>
                    <div class="flex flex-wrap gap-2">
                        @forelse($roles as $role)
                            <x-badge :value="$role" class="badge-primary" />
                        @empty
                            <x-badge value="Tidak ada peran yang ditetapkan" class="badge-ghost" />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-card>
