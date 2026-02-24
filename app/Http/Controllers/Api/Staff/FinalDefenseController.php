<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\FinalDefenseRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinalDefenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $events = Event::where('status', 1)
            ->where('event_type_id', 2)
            ->whereHas('finaldefenseApplicantPublish', function($query) use ($staffId) {
                $query->whereHas('research', function($query) use ($staffId) {
                    $query->whereHas('supervisor', function($query) use ($staffId) {
                        $query->where('supervisor_Id', $staffId);
                    });
                })
                ->orWhereHas('room', function($query) use ($staffId) {
                    $query->whereHas('examiner', function($query) use ($staffId) {
                        $query->where('examiner_id', $staffId);
                    });
                });
            })
            ->with('program')
            ->orderBy('event_date', 'DESC')
            ->get();

        $transformedData = $events->map(function ($event) {
            $eventDate = new \DateTime($event->event_date);
            $formattedDate = $eventDate->format('dmy');
            return [
                'id' => $event->id,
                'name' => $event->name,
                'event_date' => $event->event_date,
                'event_code' => 'PUB-' . $formattedDate . '-' . $event->id,
                'program_code' => $event->program->code ?? null,
                'program_abbrev' => $event->program->abbrev ?? null,
            ];
        });

        return response()->json(['data' => $transformedData]);
    }

    public function getRooms(Request $request, $eventId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $rooms = FinalDefenseRoom::where('event_id', $eventId)
            ->where(function ($query) use ($staffId) {
                $query->whereHas('examiner', function ($q) use ($staffId) {
                    $q->where('examiner_id', $staffId);
                })->orWhereHas('applicant.research.supervisor', function ($q) use ($staffId) {
                    $q->where('supervisor_id', $staffId);
                });
            })
            ->with([
                'space',
                'session',
                'moderator',
                'examiner.staff',
                'examiner.finalDefenseExaminerPresence',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
            ])
            ->get();

        $transformedData = $rooms->map(function ($room) use ($staffId) {
            $isExaminer = $room->examiner->contains('examiner_id', $staffId);
            $isModerator = $room->moderator_id == $staffId;

            $supervisedApplicantIds = $room->applicant->filter(function ($applicant) use ($staffId) {
                return $applicant->research->supervisor->contains('supervisor_id', $staffId);
            })->pluck('id');

            return [
                'id' => $room->id,
                'room_name' => $room->space->code ?? 'N/A',
                'session_time' => $room->session->time ?? 'N/A',
                'is_examiner_or_moderator' => $isExaminer || $isModerator,
                'supervised_applicant_ids' => $supervisedApplicantIds,
                'moderator' => $room->moderator ? [
                    'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name),
                    'code' => $room->moderator->code ?? 'N/A',
                ] : null,
                'examiners' => $room->examiner->map(function ($examiner) {
                    $staff = $examiner->staff;
                    return [
                        'id' => $examiner->id,
                        'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown',
                        'code' => $staff->code ?? 'N/A',
                        'is_present' => $examiner->finalDefenseExaminerPresence->isNotEmpty(),
                    ];
                }),
                'applicants' => $room->applicant->map(function ($applicant) {
                    $student = $applicant->research->student;
                    return [
                        'id' => $applicant->id,
                        'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                        'student_nim' => $student->number ?? 'N/A',
                    ];
                }),
            ];
        });

        return response()->json(['data' => $transformedData]);
    }

    public function getRoomDetail(Request $request, $roomId)
    {
        // This can be deprecated or used for a different purpose later
        // For now, we leave it as is.
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $room = FinalDefenseRoom::with([
            'space',
            'session',
            'moderator.staff',
            'examiner.staff',
            'examiner.finalDefenseExaminerPresence',
            'applicant.research.student.program',
            'applicant.research.supervisor.staff',
        ])->find($roomId);

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $isModerator = $room->moderator_id == $staffId;

        $examiners = $room->examiner->map(function ($examiner) {
            $staff = $examiner->staff;
            return [
                'id' => $examiner->id,
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Examiner',
                'code' => $staff->code ?? 'N/A',
                'is_present' => $examiner->finalDefenseExaminerPresence->isNotEmpty(),
            ];
        });

        $applicants = $room->applicant->map(function ($applicant) {
            $student = $applicant->research->student;
            return [
                'id' => $applicant->id,
                'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_nim' => $student->number ?? 'N/A',
                'program_code' => $student->program->code ?? 'N/A',
                'research_title' => $applicant->research->title,
            ];
        });

        $data = [
            'room' => [
                'name' => $room->space->code ?? 'N/A',
                'session_time' => $room->session->time ?? 'N/A',
                'moderator' => $room->moderator ? [
                    'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name),
                    'code' => $room->moderator->code ?? 'N/A',
                ] : null,
                'examiners' => $examiners,
                'applicants' => $applicants,
            ],
            'is_moderator' => $isModerator,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
