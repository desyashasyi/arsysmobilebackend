<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\DefenseExaminer;
use App\Models\ArSys\DefenseExaminerPresence;
use App\Models\ArSys\DefenseScoreGuide;
use App\Models\ArSys\Staff;
use App\Models\ArSys\DefenseSupervisorPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreDefenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $events = Event::where('status', 1)
            ->whereHas('type', function ($q) {
                $q->where('code', 'PRE');
            })
            ->whereHas('defenseApplicant', function ($q) use ($staffId) {
                $q->whereHas('research.supervisor', function ($subQuery) use ($staffId) {
                    $subQuery->where('supervisor_id', $staffId);
                })
                ->orWhereHas('defenseExaminer', function ($subQuery) use ($staffId) {
                    $subQuery->where('examiner_id', $staffId);
                });
            })
            ->with(['type', 'program', 'defenseApplicant.research.supervisor.staff', 'defenseApplicant.session'])
            ->orderBy('event_date', 'DESC')
            ->paginate($request->get('limit', 15));

        $transformedData = $events->getCollection()->map(function ($event) {
            $supervisorCodes = $event->defenseApplicant->flatMap(function ($applicant) {
                return $applicant->research->supervisor->pluck('staff.code');
            })->unique()->implode(', ');

            return [
                'id' => $event->id,
                'event_id_string' => sprintf('%s-%s-%s', $event->type->code ?? 'EVT', \Carbon\Carbon::parse($event->event_date)->format('dmy'), $event->id),
                'event_date' => \Carbon\Carbon::parse($event->event_date)->isoFormat('dddd, D MMM YYYY'),
                'program_code' => $event->program->code ?? '',
                'program_abbrev' => $event->program->abbrev ?? '',
                'supervisor_codes' => $supervisorCodes,
                'session' => $event->defenseApplicant->first()->session->name ?? 'N/A',
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
        ]);
    }

    public function getParticipants($id)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participants = EventApplicantDefense::where('event_id', $id)
            ->where(function ($query) use ($staffId) {
                $query->whereHas('research', function($q) use ($staffId) {
                    $q->whereHas('supervisor', function($sq) use ($staffId) {
                        $sq->where('supervisor_id', $staffId);
                    });
                })
                ->orWhereHas('defenseExaminer', function ($subQuery) use ($staffId) {
                    $subQuery->where('examiner_id', $staffId);
                });
            })
            ->with(['research.student', 'space', 'session', 'research.student.program'])
            ->get();

        $transformedData = $participants->map(function ($participant) {
            if (!$participant->research || !$participant->research->student) return null;

            return [
                'id' => (int) $participant->id,
                'student_name' => trim(($participant->research->student->first_name ?? '') . ' ' . ($participant->research->student->last_name ?? '')),
                'student_nim' => $participant->research->student->number ?? 'N/A',
                'program_code' => $participant->research->student->program->code ?? 'N/A',
                'research_title' => $participant->research->title,
                'room_name' => $participant->space->code ?? 'N/A',
                'session_time' => $participant->session->time ?? 'N/A',
            ];
        })->filter();

        return response()->json(['data' => $transformedData->values()]);
    }

    public function getParticipantDetail($id)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participant = EventApplicantDefense::with([
            'research.student.program',
            'research.supervisor.staff',
            'research.supervisor.defenseSupervisorPresence',
            'defenseExaminer.staff',
            'defenseExaminer.defenseExaminerPresence',
            'space',
            'session'
        ])->find($id);

        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found'], 404);
        }

        $isSupervisor = $participant->research->supervisor->contains('supervisor_id', $staffId);
        $examiner = $participant->defenseExaminer->where('examiner_id', $staffId)->first();
        $isExaminer = $examiner ? true : false;
        $isExaminerPresent = $isExaminer && $examiner->defenseExaminerPresence;

        $myScore = null;
        $myRemark = null;

        if ($isSupervisor) {
            $supervisor = $participant->research->supervisor->where('supervisor_id', $staffId)->first();
            $myScore = $supervisor->defenseSupervisorPresence->score ?? null;
            $myRemark = $supervisor->defenseSupervisorPresence->remark ?? null;
        } elseif ($isExaminerPresent) {
            $myScore = $examiner->defenseExaminerPresence->score ?? null;
            $myRemark = $examiner->defenseExaminerPresence->remark ?? null;
        }

        $supervisors = $participant->research->supervisor->map(function ($supervisor) {
            $staff = $supervisor->staff;
            return [
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Supervisor',
                'code' => $staff->code ?? 'N/A',
            ];
        });

        $examiners = $participant->defenseExaminer->map(function ($examiner) {
            $staff = $examiner->staff;
            return [
                'id' => $examiner->id,
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Examiner',
                'code' => $staff->code ?? 'N/A',
                'is_present' => $examiner->defenseExaminerPresence ? true : false,
            ];
        });

        $student = $participant->research->student;
        $data = [
            'participant' => [
                'research' => [
                    'title' => $participant->research->title,
                    'student' => [
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'number' => $student->number,
                        'program_code' => $student->program->code ?? 'N/A',
                    ],
                    'supervisor' => $supervisors,
                ],
                'defense_examiner' => $examiners,
                'room_name' => $participant->space->code ?? 'N/A',
                'session_time' => $participant->session->time ?? 'N/A',
            ],
            'is_supervisor' => $isSupervisor,
            'is_examiner' => $isExaminer,
            'is_examiner_present' => $isExaminerPresent,
            'my_score' => $myScore,
            'my_remark' => $myRemark,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function toggleExaminerPresence(Request $request, $examinerId)
    {
        $presence = DefenseExaminerPresence::where('defense_examiner_id', $examinerId)->first();

        if ($presence) {
            $presence->delete();
        } else {
            DefenseExaminerPresence::create(['defense_examiner_id' => $examinerId]);
        }

        return response()->json(['success' => true]);
    }

    public function searchStaff(Request $request)
    {
        $query = $request->input('query');
        if (empty($query)) {
            return response()->json([]);
        }

        $staff = Staff::where('code', 'LIKE', "%{$query}%")
                      ->orWhere('first_name', 'LIKE', "%{$query}%")
                      ->orWhere('last_name', 'LIKE', "%{$query}%")
                      ->limit(10)
                      ->get(['id', 'code', 'first_name', 'last_name']);

        return response()->json($staff);
    }

    public function addExaminer(Request $request, $participantId)
    {
        $request->validate([
            'staff_id' => 'required|integer|exists:arsys_staff,id',
        ]);

        $participant = EventApplicantDefense::find($participantId);
        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found.'], 404);
        }

        $isExaminer = DefenseExaminer::where('applicant_id', $participantId)
                                     ->where('examiner_id', $request->staff_id)
                                     ->exists();

        if ($isExaminer) {
            return response()->json(['success' => false, 'message' => 'This staff member is already an examiner for this applicant.'], 409);
        }

        DefenseExaminer::create([
            'applicant_id' => $participantId,
            'examiner_id' => $request->staff_id,
            'event_id' => $participant->event_id,
            'additional' => 1,
        ]);

        return response()->json(['success' => true, 'message' => 'Examiner added successfully.']);
    }

    public function getScoreGuide()
    {
        $scoreGuide = DefenseScoreGuide::orderBy('sequence', 'ASC')->get();
        return response()->json($scoreGuide);
    }

    public function submitScore(Request $request, $participantId)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participant = EventApplicantDefense::find($participantId);
        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found'], 404);
        }

        $supervisor = $participant->research->supervisor->where('supervisor_id', $staffId)->first();
        if ($supervisor) {
            $supervisor->defenseSupervisorPresence()->updateOrCreate(
                ['research_supervisor_id' => $supervisor->id],
                ['score' => $request->score, 'remark' => $request->remark]
            );
        }

        $examiner = $participant->defenseExaminer->where('examiner_id', $staffId)->first();
        if ($examiner && $examiner->defenseExaminerPresence) {
            $examiner->defenseExaminerPresence->score = $request->score;
            $examiner->defenseExaminerPresence->remark = $request->remark;
            $examiner->defenseExaminerPresence->save();
        }

        return response()->json(['success' => true]);
    }
}
