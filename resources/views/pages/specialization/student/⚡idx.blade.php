<?php

use Livewire\Component;
use App\Models\User;
use App\Models\ArSys\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

new class extends Component {
    // No longer need `use WithPagination;` as the table component handles it.

    public string $search = '';

    // This is no longer a computed property, but a method that returns the query builder.
    public function render()
    {
        $programId = Auth::user()->staff->program_id ?? null;

        // Return the query builder, NOT the paginated result.
        $students = Student::query()
            ->when($programId, function ($query) use ($programId) {
                $query->where('program_id', $programId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhere('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('number', 'ASC')
            ->paginate(10);
        return $this->view(['students' => $students]);
    }

    public $headers =[
        ['key' => 'number', 'label' => 'NIM'],
        ['key' => 'first_name', 'label' => 'First Name'],
        ['key' => 'last_name', 'label' => 'Last Name'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-1'],
    ];

    public function loginAs($studentId)
    {
        $student = Student::find($studentId);
        if (!$student) {
            return;
        }

        $user = User::where('sso', $student->number)->first();

        if (!$user && !empty($student->email)) {
            $user = User::where('email', $student->email)->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $student->first_name . ' ' . $student->last_name,
                'email' => $student->email ?? $student->number . '@arsys.example.com',
                'sso' => $student->number,
                'password' => Hash::make(Str::random(10))
            ]);
        }

        $user->sso = $student->number;
        $user->save();

        if ($student->user_id !== $user->id) {
            $student->update(['user_id' => $user->id]);
        }

        if (!$user->hasRole('student')) {
            $user->assignRole('student');
        }

        Auth::login($user);

        return $this->redirect('/dashboard', navigate: true);
    }
};

?>

<div>
    <x-card title="Specializaton | LoginAs" shadow separator>

        <div class="space-y-4">
            <div class="w-full md:w-1/4">
                <x-input placeholder="Search by NIM or Name..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            </div>

            <x-table :headers="$headers" :rows="$students" wire:model="expanded" with-pagination>
                @scope('cell_actions', $student)
                    <div class="flex justify-end">
                        <x-button label="Login As" wire:click="loginAs({{ $student->id }})" class="btn-sm btn-primary" />
                    </div>
                @endscope
            </x-table>
        </div>
    </x-card>
</div>
