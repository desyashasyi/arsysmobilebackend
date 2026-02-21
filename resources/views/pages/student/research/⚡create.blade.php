<?php

use App\Models\ArSys\AcademicYear;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\ResearchConfigBase;
use App\Models\ArSys\ResearchFile;
use App\Models\ArSys\ResearchFiletype;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchMilestoneLog;
use App\Models\ArSys\ResearchType;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public $researchTypes;
    public $researchTypeCreate;
    public $title;
    public $abstract;
    public $file;
    public $proposalUrl;
    public $addResearch = false;

    public function render()
    {
        return $this->view();
    }

    public function mount()
    {
        $this->researchTypes = ResearchType::where('program_id', Auth::user()->student->program_id)
            ->whereHas('data', function ($query) {
                $query->where('level_id', Auth::user()->student->program->level_id);
            })
            ->where('status', 1)
            ->get();
        $this->title = '';
        $this->abstract = '';
        $this->file = '';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate([
            'title' => 'required',
            'abstract' => 'required',
        ]);

        if (ResearchConfig::where('program_id', Auth::user()->student->program_id)
            ->where('config_base_id', ResearchConfigBase::where('code', 'RESEARCH_FILE')->first()->id)
            ->first()->status == 1
        ) {
            $this->validate([
                'file' => "required|mimetypes:application/pdf|max:10000",
            ]);
        } else {
            $this->validate([
                'proposalUrl' => "required|url",
            ]);
        }

        $researchCounter = Research::where('type_id', $this->researchTypeCreate)
            ->where('student_id', Auth::user()->student->id)
            ->count();

        $code = ResearchType::where('program_id', Auth::user()->student->program_id)->where('id', $this->researchTypeCreate)->first()->data->code
            . '-' . Auth::user()->student->number . '-' . (strval($researchCounter + 1));

        Research::create([
            'student_id' => Auth::user()->student->id,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'type_id' => $this->researchTypeCreate,
            'milestone_id' => ResearchMilestone::where('research_model_id', ResearchType::where('program_id', Auth::user()->student->program_id)
                ->where('id', $this->researchTypeCreate)->first()->data->research_model_id)
                ->where('sequence', 1)->first()->id,
            'code' => $code,
            'academic_year_id' => AcademicYear::latest()->first()->id,
        ]);

        $research = Research::where('code', $code)->first();

        ResearchMilestoneLog::create([
            'research_id' => $research->id,
            'research_model_id' => ResearchType::where('program_id', Auth::user()->student->program_id)
                ->where('id', $this->researchTypeCreate)->first()->data->research_model_id,
            'milestone_id' => ResearchMilestone::where('research_model_id', ResearchType::where('program_id', Auth::user()->student->program_id)
                ->where('id', $this->researchTypeCreate)->first()->data->research_model_id)
                ->where('sequence', 1)->first()->id,
        ]);

        if (ResearchConfig::where('program_id', Auth::user()->student->program_id)
            ->where('config_base_id', ResearchConfigBase::where('code', 'RESEARCH_FILE')->first()->id)
            ->first()->status == 1
        ) {
            $filename = $this->file->storeAs('proposal', Auth::user()->student->first_name . '-' . $research->id . '-proposal.pdf', 'public');
            $file = [
                'research_id' => $research->id,
                'file_type' => ResearchFiletype::where('code', 'PRO')->first()->id,
                'filename' => $filename,
            ];
            ResearchFile::create($file);
        } else {
            Research::where('id',  $research->id)->update([
                'file' => $this->proposalUrl,
            ]);
        }

        ResearchLog::create([
            'research_id' => $research->id,
            'loger_id' => Auth::user()->id,
            'type_id' => ResearchLogType::where('code', 'CRE')->first()->id,
            'message' => ResearchLogType::where('code', 'CRE')->first()->description,
            'status' => 1,
        ]);

        $this->success('The research proposal has been added');

        return redirect()->route('student.research.idx');
    }

    public function addResearch()
    {
        if (!$this->addResearch) {
            $this->addResearch = true;
        } else {
            $this->addResearch = false;
        }
    }
};
?>

<div>
    <div x-data="{ addResearch: @entangle('addResearch') }">
        @if (!$addResearch)
            <div class="flex justify-end">
                <x-button wire:click="addResearch" label="Add research" icon="o-plus" class="btn-success" />
            </div>
        @endif
        <div x-show="addResearch">
            <x-card>
                <div class="flex justify-between">
                    <b>Create new research proposal</b>
                    <x-button wire:click="addResearch" icon="o-x-mark" class="btn-sm" />
                </div>

                <x-select label="Research type" :options="$researchTypes" wire:model="researchTypeCreate"
                    placeholder="Please select research type" />

                <div>
                    1. Select SK-Skripsi/TA if you will complete your study by research of bachelor thesis
                    <br>
                    2. Select RP-Rekognisi Publikasi if you have an article published in national journal indexing
                    (>= SINTA3),
                    <br>
                    3. Select RP-Rekognisi Kejuaraan if you have achievement such as winner of the following event: Pekan
                    Kreativitas Mahasiswa, Gemastik, etc.
                    <br>
                    4. Select SP-Seminar Program Studi if your research is TA-like product.
                    <br>
                    <i style="color:red">
                        <b>Note: Don't make inappropriate selection if you don't want to hamper the research process
                        </b>
                        <br>
                        For now, the research type could not be edited. Hence, please submit approviate research type.
                    </i>
                </div>

                <x-textarea wire:model="title" label="Title" placeholder="Insert research title..." />
                <x-textarea wire:model="abstract" label="Abstract" placeholder="Insert abstract..." />

                @if (\App\Models\ArSys\ResearchConfig::where('program_id', Auth::user()->student->program_id)->where('config_base_id', \App\Models\ArSys\ResearchConfigBase::where('code', 'RESEARCH_FILE')->first()->id)->first()->status == 1)
                    <x-file wire:model="file" label="Proposal file" placeholder="Choose a file..." />
                @else
                    <x-input wire:model="proposalUrl" label="Proposal URL" placeholder="Enter document url" />
                    <div>
                        1. Upload file of your proposal to Google Drive, and make sure the file is accesible
                        <br>
                        2. Attach the url of your proposal in the form
                        <br>
                        <i style="color:red">
                            <b>Note: Unaccesible file will cause your proposal could not be processed</b>
                        </i>
                    </div>
                @endif

                <div class="flex justify-end">
                    <x-button wire:click="save" label="Save" class="btn-success" icon="o-check" />
                </div>
            </x-card>
        </div>
    </div>
</div>
