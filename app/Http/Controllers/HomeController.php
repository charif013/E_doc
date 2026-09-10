<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\V2\DashboardDocumentService as V2DashboardDocumentService;
use App\Services\V2\DocumentReadService as V2DocumentReadService;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(
        Request $request,
        V2DashboardDocumentService $v2Documents,
        V2DocumentReadService $v2DocumentReads,
    )
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (config('edoc.v2.document_reads')) {
            return $this->v2Index($request, $user, $v2Documents, $v2DocumentReads);
        }
        $query = Document::query();

        // 🌟 1. กำหนดสิทธิ์การมองเห็นเอกสารในหน้า Dashboard
        if (! $user->hasRole('super-admin')) {
            $query->where(function ($q) use ($user) {
                // กฎข้อ 1: ทุกคนจะเห็นเอกสารที่ตัวเองเป็นคนสร้าง
                $q->where('created_by', $user->id);

                // ผู้พิจารณาจะเห็นเอกสารเมื่อ workflow เดินมาถึงคิวของตนแล้วเท่านั้น
                // ผู้ที่ดำเนินการผ่านไปแล้วสามารถย้อนกลับมาดูประวัติได้
                $q->orWhereHas('routes', function ($routeQuery) use ($user) {
                    $routeQuery->where('user_id', $user->id)
                        ->whereColumn('document_routes.step_order', '<=', 'documents.current_step');
                });

                $q->orWhere(function ($directAssignment) use ($user) {
                    $directAssignment->where('assigned_user_id', $user->id)
                        ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                        ->whereIn('assignment_status', ['pending', 'accepted', 'delegated', 'in_progress', 'completed']);
                });
                if ($user->hasRole('head')) {
                    $q->orWhere(function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->department)
                            ->whereNull('assigned_user_id')
                            ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                            ->whereIn('assignment_status', ['pending', 'accepted', 'delegated', 'in_progress', 'completed']);
                    });
                }

                // กฎข้อ 2: ถ้าเป็นผู้บริหาร หรือสารบรรณ ให้มองเห็นเอกสารที่ส่งมาถึงด่านตัวเองและด่านถัดไป
                if ($user->hasRole('executive')) {
                    $q->orWhereIn('status', ['WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasAnyRole(['palad', 'deputy-palad'])) {
                    $q->orWhereIn('status', ['WAITING_PALAD', 'WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasRole('head')) {
                    $q->orWhereIn('status', ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasRole('saraban')) {
                    // 🌟 สารบรรณ จะเห็นเอกสารที่เข้าระบบมาแล้วทั้งหมด (ยกเว้น Draft ของคนอื่น)
                    $q->orWhereIn('status', ['WAITING_ADMIN', 'WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED', 'REJECTED']);
                }
            });
        }

        // ค้นหาเฉพาะเอกสารที่ผู้ใช้มีสิทธิ์มองเห็นจาก query หลักด้านบน
        $search = trim((string) $request->query('q', ''));
        $filter = in_array($request->query('filter'), ['all', 'waiting', 'approved', 'rejected'], true)
            ? $request->query('filter')
            : 'all';
        $listQuery = clone $query;
        $this->applyDashboardFilter($listQuery, $filter);
        $searchResults = collect();
        if ($search !== '') {
            $searchResults = $listQuery
                ->with('creator')
                ->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('doc_number', 'like', "%{$search}%")
                        ->orWhere('receive_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('doc_from', 'like', "%{$search}%")
                        ->orWhere('doc_to', 'like', "%{$search}%")
                        ->orWhere('reference_doc', 'like', "%{$search}%");
                })
                ->latest()
                ->limit(30)
                ->get();
        }

        // 🌟 2. คำนวณตัวเลขสถิติช่อง "กำลังรอดำเนินการ" (นับเฉพาะที่รอคนๆ นั้นเซ็น/จัดการ)
        $dynamicWaitingCount = Document::whereHas('routes', function ($routeQuery) use ($user) {
            $routeQuery->where('user_id', $user->id)
                ->where('status', 'pending')
                ->whereColumn('document_routes.step_order', 'documents.current_step');
        })->count();

        $assignmentWaitingCount = Document::where(function ($assignmentQuery) use ($user) {
            $assignmentQuery->where(function ($ownQuery) use ($user) {
                $ownQuery->where('assigned_user_id', $user->id)
                    ->where('assignment_status', '!=', 'accepted');
            });
            if ($user->hasRole('head')) {
                $assignmentQuery->orWhere(function ($headQuery) use ($user) {
                    $headQuery->where('assigned_to', $user->department)
                        ->whereNull('assigned_user_id');
                });
            }
        })->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
            ->whereIn('assignment_status', ['pending', 'accepted', 'delegated', 'in_progress', 'completed'])
            ->count();

        // ร่างและเอกสารที่ถูกตีกลับเป็นงานที่เจ้าของต้องกลับมาลงนาม/แก้ไข
        // หากมี dynamic route ที่กำลังรอเจ้าของอยู่แล้ว จะถูกนับใน dynamicWaitingCount จึงไม่นับซ้ำ
        $creatorActionCount = Document::where('created_by', $user->id)
            ->whereIn('status', ['DRAFT', 'REJECTED'])
            ->whereDoesntHave('routes', function ($routeQuery) use ($user) {
                $routeQuery->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('document_routes.step_order', 'documents.current_step');
            })
            ->count();

        $waitingCount = 0;
        if ($user->hasRole('super-admin')) {
            // แอดมินเห็นเอกสารที่กำลังวิ่งอยู่ในระบบทั้งหมด
            $waitingCount = Document::whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELED'])->count()
                + $creatorActionCount;
        } elseif ($user->hasRole('executive')) {
            $waitingCount = Document::where('status', 'WAITING_NAYOK')->count() + $dynamicWaitingCount + $assignmentWaitingCount + $creatorActionCount;
        } elseif ($user->hasAnyRole(['palad', 'deputy-palad'])) {
            $waitingCount = Document::where('status', 'WAITING_PALAD')->count() + $dynamicWaitingCount + $assignmentWaitingCount + $creatorActionCount;
        } elseif ($user->hasRole('head')) {
            $waitingCount = Document::where('status', 'WAITING_SUPERVISOR')->count() + $dynamicWaitingCount + $assignmentWaitingCount + $creatorActionCount;
        } elseif ($user->hasRole('saraban')) {
            // 🌟 สารบรรณ จะขึ้นแจ้งเตือนเฉพาะด่านที่ 1 (รอรับเรื่อง) และด่านสุดท้าย (รอลงเลข)
            $waitingCount = Document::whereIn('status', ['WAITING_ADMIN', 'WAITING_NUMBERING'])->count() + $dynamicWaitingCount + $assignmentWaitingCount + $creatorActionCount;
        } else {
            // สำหรับพนักงานทั่วไป (นับเอกสารของตัวเองที่ยังไม่เสร็จสิ้น)
            $waitingCount = $dynamicWaitingCount + $assignmentWaitingCount + $creatorActionCount;
        }

        $documentTasks = $this->legacyDocumentTasks($user);
        $leaveTasks = $this->leaveTasks($user);
        $waitingCount = $documentTasks->count() + $leaveTasks->count();

        // 🌟 3. สรุปตัวเลขทั้งหมดส่งไปให้หน้าเว็บ
        $stats = [
            'total' => (clone $query)->count(),
            'waiting' => $waitingCount,
            'approved' => (clone $query)->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])->count(),
            'rejected' => (clone $query)->where('status', 'REJECTED')->count(),
        ];

        // 🌟 4. ดึงประวัติเอกสารล่าสุด 10 รายการ
        $recentDocs = $listQuery->with('creator')->latest()->take(10)->get();

        // 🌟 5. ดึงข้อมูลการจองห้องเฉพาะของ "วันนี้" เรียงตามเวลาจากเช้าไปเย็น
        $todayBookings = RoomBooking::with(['room', 'creator', 'invitees'])
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereDate('start_time', Carbon::today())
            ->orderBy('start_time', 'asc')
            ->get();

        // 🌟 ดึงนัดประชุมที่ "ฉัน (User ที่ล็อกอินอยู่)" ถูกเชิญ
        $myMeetings = RoomBooking::with(['room', 'creator', 'invitees'])
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereHas('invitees', function ($q) use ($user) {
                $q->where('user_id', $user->id); // กรองเอาเฉพาะที่มีชื่อเราในนั้น
            })
            ->whereDate('start_time', '>=', Carbon::today()) // เอาตั้งแต่วันนี้เป็นต้นไป
            ->orderBy('start_time', 'asc')
            ->get();

        // 🌟 6. รวมตัวแปรทั้งหมดส่งไปพร้อมกันใน return เดียว
        return view('home', compact(
            'stats', 'recentDocs', 'todayBookings', 'myMeetings', 'search',
            'searchResults', 'filter', 'documentTasks', 'leaveTasks'
        ));
    }

    private function v2Index(
        Request $request,
        $user,
        V2DashboardDocumentService $documents,
        V2DocumentReadService $documentReads,
    )
    {
        $search = trim((string) $request->query('q', ''));
        $filter = in_array($request->query('filter'), ['all', 'waiting', 'approved', 'rejected'], true)
            ? $request->query('filter')
            : 'all';
        ['stats' => $stats, 'recentDocs' => $recentDocs, 'searchResults' => $searchResults]
            = $documents->data($user, $search, $filter);
        $documentTasks = $documentReads->approvalQueue($user);
        $leaveTasks = $this->leaveTasks($user);
        $stats['waiting'] = $documentTasks->count() + $leaveTasks->count();

        // Bookings remain on the legacy connection until the booking module's
        // own read flag is enabled and its views have passed parity tests.
        $todayBookings = RoomBooking::with(['room', 'creator', 'invitees'])
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereDate('start_time', Carbon::today())->orderBy('start_time')->get();
        $myMeetings = RoomBooking::with(['room', 'creator', 'invitees'])
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereHas('invitees', fn ($query) => $query->where('user_id', $user->id))
            ->whereDate('start_time', '>=', Carbon::today())->orderBy('start_time')->get();

        return view('home', compact(
            'stats', 'recentDocs', 'todayBookings', 'myMeetings', 'search',
            'searchResults', 'filter', 'documentTasks', 'leaveTasks'
        ));
    }

    private function legacyDocumentTasks($user)
    {
        if ($user->hasRole('super-admin')) {
            return Document::with(['creator', 'routes'])
                ->whereNotIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED', 'CANCELED'])
                ->latest()->get();
        }

        return Document::with(['creator', 'routes'])->where(function ($query) use ($user) {
            $query->where(function ($own) use ($user) {
                $own->where('created_by', $user->id)->whereIn('status', ['DRAFT', 'REJECTED']);
            });

            if ($user->hasRole('saraban')) {
                $query->orWhereIn('status', ['WAITING_ADMIN', 'WAITING_NUMBERING']);
            }
            if ($user->hasRole('head') && $user->department) {
                $query->orWhere(function ($head) use ($user) {
                    $head->where('status', 'WAITING_SUPERVISOR')
                        ->whereHas('creator', fn ($creator) => $creator->where('department', $user->department));
                })->orWhere(function ($unitAssignment) use ($user) {
                    $unitAssignment->where('assigned_to', $user->department)
                        ->whereNull('assigned_user_id')
                        ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                        ->where('assignment_status', 'pending');
                });
            }
            if ($user->hasAnyRole(['palad', 'deputy-palad'])) {
                $query->orWhere('status', 'WAITING_PALAD');
            }
            if ($user->hasRole('executive')) {
                $query->orWhere('status', 'WAITING_NAYOK');
            }

            $query->orWhereHas('routes', function ($routes) use ($user) {
                $routes->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('document_routes.step_order', 'documents.current_step');
            })->orWhere(function ($assigned) use ($user) {
                $assigned->where('assigned_user_id', $user->id)
                    ->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                    ->whereIn('assignment_status', ['pending', 'delegated']);
            });
        })->latest()->get();
    }

    private function leaveTasks($user)
    {
        return LeaveRequest::with('user')->where(function ($query) use ($user) {
            if ($user->hasRole('super-admin')) {
                $query->atWorkflowStage([
                    'pending_delegate', 'pending_head', 'pending_inspector',
                    'pending_palad', 'pending_nayok', 'pending_numbering',
                ]);
                return;
            }

            // เริ่มด้วยเงื่อนไขว่าง แล้วเติมคิวที่ตรงกับผู้ใช้แต่ละบทบาท
            $query->whereRaw('1 = 0')
                ->orWhere(function ($delegate) use ($user) {
                    $delegate->atWorkflowStage('pending_delegate')
                        ->assignedToDelegate($user->id);
                });

            if ($user->hasRole('head') && $user->department) {
                $query->orWhere(function ($head) use ($user) {
                    $head->atWorkflowStage('pending_head')
                        ->whereHas('user', fn ($owner) => $owner->where('department', $user->department));
                });
            }
            if ($user->hasRole('hr')) {
                $query->orWhere(fn ($leaves) => $leaves->atWorkflowStage('pending_inspector'));
            }
            if ($user->hasAnyRole(['palad', 'deputy-palad'])) {
                $query->orWhere(fn ($leaves) => $leaves->atWorkflowStage('pending_palad'));
            }
            if ($user->hasRole('executive')) {
                $query->orWhere(fn ($leaves) => $leaves->atWorkflowStage('pending_nayok'));
            }
            if ($user->hasRole('saraban')) {
                $query->orWhere(fn ($leaves) => $leaves->atWorkflowStage('pending_numbering'));
            }
        })->latest()->get();
    }

    private function applyDashboardFilter($query, string $filter): void
    {
        if ($filter === 'waiting') {
            $query->whereIn('status', [
                'DRAFT', 'REGISTERED', 'PROCESSING', 'IN_REVIEW', 'WAITING_REVIEWER',
                'WAITING_APPROVER', 'WAITING_ADMIN', 'WAITING_SUPERVISOR', 'WAITING_PALAD',
                'WAITING_NAYOK', 'WAITING_NUMBERING',
            ]);
        } elseif ($filter === 'approved') {
            $query->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED']);
        } elseif ($filter === 'rejected') {
            $query->where('status', 'REJECTED');
        }
    }
}
