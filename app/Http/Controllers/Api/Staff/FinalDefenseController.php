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
            ->where('event_type_id', 2) // Assuming 2 is for Final Defense/Publication
            ->whereHas('finaldefenseApplicantPublish', function($q) use ($staffId) {
                $q->whereHas('research', function($subQuery) use ($staffId) {
                    $subQuery->whereHas('supervisor', function($ssq) use ($staffId) {
                        $ssq->where('supervisor_Id', $staffId);
                    });
                })
                ->orWhereHas('room', function($subQuery) use ($staffId) {
                    $subQuery->whereHas('examiner', function($ssq) use ($staffId) {
                        $ssq->where('examiner_id', $staffId);
                    });
                });
            })
            ->with(['type', 'program'])
            ->orderBy('event_date', 'DESC')
            ->paginate($request->get('limit', 15));

        $transformedData = $events->getCollection()->map(function ($event) {
            return [
                'id' => $event->id,
                'event_id_string' => sprintf('%s-%s-%s', $event->type->code ?? 'EVT', \Carbon\Carbon::parse($event->event_date)->format('dmy'), $event->id),
                'event_date' => \Carbon\Carbon::parse($event->event_date)->isoFormat('dddd, D MMM YYYY'),
                'program_code' => $event->program->code ?? '',
                'program_abbrev' => $event->program->abbrev ?? '',
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
        ]);
    }

    public function getDetail($id)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }
        $staffId = $user->staff->id;

        $rooms = FinalDefenseRoom::where('event_id', $id)
            ->whereHas('examiner', function($query) use ($staffId) {
                $query->where('examiner_id', $staffId);
            })
            ->with([
                'session',
                'space',
                'moderator',
                'examiner.staff',
                'participant.research.student'
            ])
            ->get();

        $transformedRooms = $rooms->map(function ($room) {
            return [
                'id' => $room->id,
                'session' => $room->session,
                'space' => $room->space,
                'moderator' => $room->moderator,
                'examiners' => $room->examiner->map(function ($ex) {
                    return ['name' => $ex->staff->name ?? 'Unknown'];
                }),
                'participants' => $room->participant->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'student' => [
                            'number' => $p->research->student->number ?? 'N/A',
                            'first_name' => $p->research->student->first_name ?? '',
                            'last_name' => $p->research->student->last_name ?? '',
                        ]
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'rooms' => $transformedRooms,
            ]
        ]);
    }
}
