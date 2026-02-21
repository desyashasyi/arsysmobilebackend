<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Research;
use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchReviewDecisionType;
use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResearchController extends Controller
{
    // ... index, show, getApprovals, approve, updateResearchMilestone, getReviews, getReviewDetail ...
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staff = $user->staff;

        $researchIds = DB::table('arsys_research_supervisor')
            ->where('supervisor_id', $staff->id)
            ->pluck('research_id');

        // Menggunakan withCount untuk eager load jumlah approval request yang pending
        $allResearches = Research::whereIn('id', $researchIds)
            ->whereHas('active')
            ->with(['supervisor.staff', 'student', 'milestone'])
            ->withCount(['approvalRequest']) // Eager load count of pending approvals
            ->get();

        $transformedData = [];
        foreach ($allResearches as $research) {
            if (!$research->student) continue;

            $supervisorCodes = $research->supervisor
                ->sortBy('order')
                ->pluck('staff.code')
                ->filter()
                ->implode(', ');

            $transformedData[] = [
                'id' => $research->id,
                'research_title' => $research->title,
                'student_name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')),
                'student_nim' => $research->student->number ?? null,
                'milestone_code' => $research->milestone->code ?? null,
                'milestone_phase' => $research->milestone->phase ?? null,
                'supervisor_codes' => $supervisorCodes ?: 'N/A',
                // Menggunakan hasil dari withCount
                'needs_approval' => $research->approval_request_count > 0,
            ];
        }

        usort($transformedData, function ($a, $b) {
            if (($a['needs_approval'] ?? false) !== ($b['needs_approval'] ?? false)) {
                return ($b['needs_approval'] ?? false) ? 1 : -1;
            }
            return ($a['student_nim'] ?? '') <=> ($b['student_nim'] ?? '');
        });

        $page = $request->get('page', 1);
        $perPage = 15;
        $paginatedData = new LengthAwarePaginator(
            array_slice($transformedData, ($page - 1) * $perPage, $perPage),
            count($transformedData),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginatedData);
    }

    public function show($id)
    {
        $user = Auth::user();
        $research = Research::with(['student', 'supervisor.staff'])->find($id);
        if (!$research) { return response()->json(['message' => 'Research not found.'], 404); }

        $isSupervisor = $research->supervisor->pluck('supervisor_id')->contains($user->staff->id);
        $isKaprodi = $user->hasRole('kaprodi');

        if (!$isSupervisor && !$isKaprodi) {
            return response()->json(['message' => 'You are not authorized to view this research.'], 403);
        }

        $transformedDetail = [
            'id' => $research->id,
            'title' => $research->title,
            'student' => ['name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')), 'nim' => $research->student->number ?? 'N/A'],
            'supervisors' => $research->supervisor->map(function ($spv) {
                $staffName = 'N/A';
                $staffCode = 'N/A';
                if ($spv->staff) {
                    $staffName = trim(($spv->staff->first_name ?? '') . ' ' . ($spv->staff->last_name ?? ''));
                    $staffCode = $spv->staff->code ?? 'N/A';
                }
                return ['name' => $staffName, 'code' => $staffCode, 'role' => 'Pembimbing ' . $spv->order];
            }),
            'milestone_code' => $research->milestone->code ?? null,
            'milestone_phase' => $research->milestone->phase ?? null,
        ];
        return response()->json($transformedDetail);
    }

    public function getApprovals($id)
    {
        try {
            $user = Auth::user();
            $currentStaffId = $user->staff->id;

            if (!$user || !$currentStaffId) {
                return response()->json(['message' => 'Unauthorized.'], 401);
            }

            $research = Research::with([
                'milestone',
                'defenseApproval.staff',
                'defenseApproval.defenseModel'
            ])->find($id);

            if (!$research) {
                return response()->json(['message' => 'Research not found.'], 404);
            }

            $currentMilestone = $research->milestone;

            $transformedApprovals = $research->defenseApproval
                ->sortBy('id')
                ->map(function ($approval) use ($currentStaffId, $currentMilestone) {
                    return $this->transformApproval($approval, $currentStaffId, $currentMilestone);
                })
                ->values();

            return response()->json($transformedApprovals);

        } catch (\Exception $e) {
            Log::error('getApprovals error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return response()->json(['message' => 'Error loading approvals: ' . $e->getMessage()], 500);
        }
    }

    private function transformApproval($approval, $currentStaffId, $currentMilestone) {
        $staffName = 'Unknown';
        $staffCode = 'N/A';

        if ($approval->staff) {
            $firstName = $approval->staff->first_name ?? '';
            $lastName = $approval->staff->last_name ?? '';
            $staffName = trim($firstName . ' ' . $lastName) ?: 'Unknown';
            $staffCode = $approval->staff->code ?? 'N/A';
        }

        $approvalType = $approval->defenseModel->description ?? 'unknown';
        $isLocked = false;

        if ($currentMilestone) {
            $approvalMilestoneCode = str_replace(' ', '-', $approvalType);
            if (
                $currentMilestone->code != $approvalMilestoneCode ||
                $currentMilestone->phase == 'Approved'
            ) {
                $isLocked = true;
            }
        }

        return [
            'id' => $approval->id,
            'type' => $approvalType,
            'approver_name' => $staffName,
            'approver_code' => $staffCode,
            'is_approved' => !is_null($approval->decision),
            'is_current_user' => $approval->approver_id == $currentStaffId,
            'is_locked' => $isLocked,
        ];
    }

    public function approve(Request $request, $approvalId)
    {
        $approval = DefenseApproval::find($approvalId);
        if (!$approval) {
            return response()->json(['message' => 'Approval request not found.'], 404);
        }

        if (is_null($approval->decision)) {
            $approval->decision = 1;
            $approval->approval_date = Carbon::now();
        } else {
            $approval->decision = null;
            $approval->approval_date = null;
        }
        $approval->save();

        $research = Research::find($approval->research_id);
        if ($research) {
            $this->updateResearchMilestone($research);
        }

        return response()->json(['message' => 'Approval status updated successfully.']);
    }

    private function updateResearchMilestone(Research $research)
    {
        $milestone = null;

        $research->loadCount(['predefenseApproval', 'predefenseApproved', 'finaldefenseApproval', 'finaldefenseApproved']);

        $allPreDefenseApproved = $research->predefense_approval_count > 0 && $research->predefense_approval_count == $research->predefense_approved_count;
        $allFinalDefenseApproved = $research->finaldefense_approval_count > 0 && $research->finaldefense_approval_count == $research->finaldefense_approved_count;

        if ($allFinalDefenseApproved) {
            $milestone = ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Approved')->first();
        } elseif ($allPreDefenseApproved) {
            $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'Approved')->first();
        } else {
            if ($research->milestone && $research->milestone->code === 'Final-defense' && $research->milestone->phase === 'Approved') {
                $milestone = ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Submitted')->first();
            } elseif ($research->milestone && $research->milestone->code === 'Pre-defense' && $research->milestone->phase === 'Approved') {
                $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'Submitted')->first();
            }
        }

        if ($milestone && $research->milestone_id != $milestone->id) {
            $research->update(['milestone_id' => $milestone->id]);
        }
    }

    public function getReviews(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => []], 401);
        }
        $staffId = $user->staff->id;

        $researches = Research::whereHas('reviewers', function($query) use ($staffId) {
            $query->where('reviewer_id', $staffId)
                  ->whereNull('decision_id');
        })
        ->whereHas('milestone', function($query) {
            $query->where('code', 'Proposal')->where('phase', 'Review');
        })
        ->with(['student', 'milestone', 'reviewers.staff', 'reviewers.decision'])
        ->orderBy('student_id', 'ASC')
        ->paginate(15);

        $transformedData = $researches->getCollection()->map(function ($research) {
            return [
                'id' => $research->id,
                'research_title' => $research->title,
                'student_name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')),
                'student_nim' => $research->student->number ?? 'N/A',
                'milestone_code' => $research->milestone->code ?? 'N/A',
                'milestone_phase' => $research->milestone->phase ?? 'N/A',
                'reviewers' => $research->reviewers->map(function ($reviewer) {
                    return [
                        'reviewer_code' => $reviewer->staff->code ?? 'N/A',
                        'decision' => $reviewer->decision->description ?? 'Not Defined',
                    ];
                }),
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $researches->currentPage(),
            'last_page' => $researches->lastPage(),
            'total' => $researches->total(),
        ]);
    }

    public function getReviewDetail($id)
    {
        $user = Auth::user();
        $research = Research::with(['student', 'reviewers.staff', 'reviewers.decision'])->find($id);

        if (!$research) {
            return response()->json(['message' => 'Research not found.'], 404);
        }

        $fileUrl = $research->file ?? null;

        return response()->json([
            'id' => $research->id,
            'research_title' => $research->title,
            'student_info' => [
                'name' => trim(($research->student->first_name ?? '') . ' ' . ($research->student->last_name ?? '')),
                'nim' => $research->student->number ?? 'N/A',
            ],
            'reviewers' => $research->reviewers->map(function ($reviewer) {
                return [
                    'name' => trim(($reviewer->staff->first_name ?? '') . ' ' . ($reviewer->staff->last_name ?? 'Unknown')),
                    'code' => $reviewer->staff->code ?? 'N/A',
                    'decision' => $reviewer->decision->description ?? 'Not Defined',
                ];
            }),
            'abstract' => $research->abstract,
            'file_url' => $fileUrl,
        ]);
    }

    public function submitReview(Request $request, $researchId)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
        ]);

        $review = ResearchReview::where('research_id', $researchId)
            ->where('reviewer_id', $user->staff->id)
            ->first();

        if (!$review) {
            return response()->json(['message' => 'Review not found or you are not authorized.'], 404);
        }

        $decisionCode = ($validated['decision'] == 'approve') ? 'APP' : 'RJC';
        $decision = ResearchReviewDecisionType::where('code', $decisionCode)->first();

        if (!$decision) {
            return response()->json(['message' => 'Decision type not found.'], 500);
        }

        $review->decision_id = $decision->id;
        $review->save();

        return response()->json(['message' => 'Review submitted successfully.']);
    }
}
