<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;
use Livewire\Attributes\On;

new class extends Component
{
    use Toast;

    public $addResearch = false;
    public $isParking = false;

    public function render()
    {
        if ($this->isParking) {
            return $this->view();
        }

        $researchs = Research::where('student_id', Auth::user()->student->id)
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return $this->view(compact('researchs'));
    }

    public function mount()
    {
        if (is_null(Auth::user())) {
            return redirect()->route('arsys.home');
        } else {
            if (is_null(Student::where('number', Auth::user()->sso)->first())) {
                return redirect()->route('arsys.user.profile.create');
            } else {
                Student::where('number', Auth::user()->sso)->update([
                    'user_id' => Auth::user()->id,
                ]);
            }
        }

        if (ResearchConfig::where('program_id', Auth::user()->student->program_id)->get()->isEmpty()) {
            $this->isParking = true;
        }

        $this->addResearch = false;
    }

    #[On('addResearch_ArSysStudentResearchPage')]
    public function addResearch_ArSysStudentResearchPage()
    {
        if (!$this->addResearch) {
            $this->addResearch = true;
        } else {
            $this->addResearch = false;
        }
    }
};
?>

<x-card title="Student | Research" shadow separator>
    @if($isParking)
        <div>
            This page is not available for your program.
        </div>
    @else
        <div>
            <div class="row">
                <div class="text-right col-md-12 offset-md-0">
                    <livewire:pages::student.research.create />
                </div>
            </div>
            <br>
            <div class="row">
                <div class="text-left col-md-12">
                    @if (isset($researchs) && $researchs->isNotEmpty())
                        <x-table :headers="[
                            ['key' => 'no', 'label' => 'No'],
                            ['key' => 'student', 'label' => 'Student'],
                            ['key' => 'title', 'label' => 'Title'],
                            ['key' => 'rev_spv', 'label' => 'Rev/Spv'],
                            ['key' => 'milestone', 'label' => 'Milestone'],
                        ]" :rows="$researchs" with-pagination expandable>
                            @scope('cell_no', $research)
                                {{ $loop->index + 1 }}
                            @endscope

                            @scope('cell_student', $research)
                                {{ $research->student->first_name }} {{ $research->student->last_name }}
                                <br>
                                {{ $research->student->program->code }}.{{ $research->student->number }}
                            @endscope

                            @scope('cell_title', $research)
                                {{ $research->code }}-{{ $research->id }}
                                <br>
                                {{ $research->title }}
                            @endscope

                            @scope('cell_rev_spv', $research)
                                @if ($research->review)
                                    @foreach ($research->reviewer as $reviewer)
                                        {{ $reviewer->staff->code }}
                                        <br>
                                    @endforeach
                                @else
                                    @if ($research->supervisor->isNotEmpty())
                                        @foreach ($research->supervisor as $supervisor)
                                            {{ $supervisor->staff->code }}
                                            <br>
                                        @endforeach
                                    @endif
                                    @if ($research->supervisorexternal)
                                        {{ $research->supervisorexternal->institution }}
                                    @endif
                                @endif
                            @endscope

                            @scope('cell_milestone', $research)
                                @if ($research->milestone)
                                    <b>{{ $research->milestone->code }}</b>
                                @endif
                                <br>
                                @if ($research->freeze)
                                    {{ $research->freeze->message }}
                                @elseif($research->renewal)
                                    {{ $research->renewal->message }}
                                @elseif($research->SIASPro && !$research->programSeminar)
                                    {{ $research->SIASPro->message }}
                                @elseif($research->rejected)
                                    {{ $research->rejected->message }}
                                @else
                                    @if ($research->milestone)
                                        {{ $research->milestone->phase }}
                                    @endif
                                @endif
                                <hr>
                                @if ($research->reviewer->isNotEmpty())
                                    @foreach ($research->reviewer as $reviewer)
                                        @if ($reviewer->decision_id == \App\Models\ArSys\ResearchReviewDecisionType::where('code', 'RJC')->first()->id)
                                            <x-badge :value="$reviewer->staff->code . '-' . $reviewer->decision->description" class="badge-danger" />
                                        @endif
                                    @endforeach
                                @endif
                            @endscope

                            @scope('expansion', $research)
                                <livewire:pages::student.research.view :researchId="$research->id" />
                            @endscope
                        </x-table>
                    @else
                        No data
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-card>
