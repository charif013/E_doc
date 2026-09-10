<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDocumentNumberRequest;
use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\DocumentNumberingService;
use App\Services\V2\DocumentNumberingService as V2DocumentNumberingService;
use App\Services\V2\DocumentReadService as V2DocumentReadService;
use App\Services\V2\DocumentWriteService as V2DocumentWriteService;
use App\Models\V2\Document as V2Document;
use App\Models\V2\NumberSequence as V2NumberSequence;
use App\Models\V2\User as V2User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentNumberingController extends Controller
{
    public function __construct(
        private DocumentNumberingService $numbering,
        private V2DocumentNumberingService $v2Numbering,
        private V2DocumentReadService $v2Documents,
        private V2DocumentWriteService $v2Writes,
    ) {}

    public function ledger(Request $request)
    {
        $type = $request->input('type', 'outgoing');
        abort_unless(in_array($type, ['incoming', 'outgoing', 'internal', 'leave'], true), 422);

        if ($type === 'leave' && config('edoc.v2.document_reads')) {
            return $this->v2LeaveLedger($request);
        }

        if (config('edoc.v2.document_reads')) {
            return $this->v2Ledger($request, $type);
        }

        $userDepartment = $request->user()->department;
        $department = $request->input('department', $userDepartment);
        $defaultYear = $this->numbering->fiscalYear(now());
        $year = (int) $request->input('year', $defaultYear);
        [$start, $end] = $this->numbering->fiscalRange($year);

        $documents = Document::where('doc_type', $type)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('running_number')
            ->when($type === 'internal', function ($query) use ($department) {
                $query->whereHas('creator', fn ($users) => $users->where('department', $department));
            })
            ->orderBy('running_number')->get();

        $usedNumbers = $documents->keyBy('running_number');
        $maxNumber = (int) ($documents->max('running_number') ?? 0);
        $ledger = [];

        for ($number = 1; $number <= max(50, $maxNumber + 20); $number++) {
            $document = $usedNumbers->get($number);
            $status = 'ว่าง';
            if ($document) {
                $status = $type === 'leave' || $document->status === 'APPROVED'
                    ? 'ออกเลขแล้ว'
                    : match ($document->status) {
                        'CANCELED' => 'ยกเลิก',
                        'RESERVED' => 'จองเลขมือ',
                        default => 'จองรออนุมัติ',
                    };
            }
            $ledger[$number] = ['status' => $status, 'doc' => $document];
        }

        $departments = User::select('department')->distinct()->pluck('department')->filter();
        $dept = $department;

        return view('documents.number_ledger', compact('ledger', 'type', 'year', 'maxNumber', 'dept', 'departments'));
    }

    public function next(Request $request)
    {
        $type = $request->input('type', 'outgoing');
        abort_unless(in_array($type, ['incoming', 'outgoing', 'internal', 'leave'], true), 422);

        if (config('edoc.v2.document_reads') && $type !== 'leave') {
            $documentId = trim((string) $request->input('document'));
            if ($documentId !== '') {
                $document = $this->v2Documents->find($documentId);
                $this->authorize('number', $document);
                $type = $document->doc_type;
                $scope = $type === 'internal' ? ($document->creator?->department ?: 'organization') : 'organization';
                $year = $this->v2Numbering->fiscalYear($document->created_at ?? now());
            } else {
                $actor = V2User::findOrFail($request->user()->id);
                $scope = $type === 'internal' ? ($actor->department ?: 'organization') : 'organization';
                $year = $this->v2Numbering->fiscalYear(now());
            }
            $max = V2NumberSequence::where(['number_type' => $type, 'scope' => $scope, 'fiscal_year' => $year])->value('last_number') ?? 0;
            $number = (int) $max + 1;

            return response()->json([
                'next_number' => $number,
                'formatted' => 'ยล 77301/'.$number,
                'fiscal_year' => $year,
                'scope' => $scope,
            ]);
        }

        if ($type === 'leave' && config('edoc.v2.document_reads')) {
            $leave = LeaveRequest::with('user')->findOrFail($request->integer('leave_id'));
            $this->authorize('view', $leave);
            $scope = $leave->user?->department ?: 'ไม่ระบุสังกัด';
            $year = $this->v2Numbering->fiscalYear(now());
            $max = V2NumberSequence::where([
                'number_type' => 'leave', 'scope' => $scope, 'fiscal_year' => $year,
            ])->value('last_number') ?? 0;
            $number = (int) $max + 1;

            return response()->json([
                'next_number' => $number,
                'formatted' => 'ลา/'.$scope.'/'.$number.'/'.($year + 543),
                'fiscal_year' => $year,
                'scope' => $scope,
            ]);
        }

        $fiscalYear = $this->numbering->fiscalYear(now());
        [$start, $end] = $this->numbering->fiscalRange($fiscalYear);

        if ($type === 'leave') {
            $leave = LeaveRequest::with('user')->findOrFail($request->integer('leave_id'));
            $this->authorize('view', $leave);
            $department = $leave->user?->department ?: 'ไม่ระบุสังกัด';
            $max = LeaveRequest::whereBetween('numbered_at', [$start, $end])
                ->whereHas('user', fn ($users) => $users->where('department', $department))
                ->max('running_number') ?? 0;
        } else {
            $query = Document::where('doc_type', $type)->whereBetween('created_at', [$start, $end]);
            if ($type === 'internal') {
                $department = $request->user()->department;
                $query->whereHas('creator', fn ($users) => $users->where('department', $department));
            }
            $max = $query->max('running_number') ?? 0;
        }

        $number = (int) $max + 1;
        $formatted = $type === 'leave'
            ? 'ลา/'.$department.'/'.$number.'/'.($fiscalYear + 543)
            : 'ยล 77301/'.$number;

        return response()->json([
            'next_number' => $number,
            'formatted' => $formatted,
            'scope' => $type === 'leave' ? $department : 'organization',
        ]);
    }

    public function reserveSlot(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:incoming,outgoing,internal'],
            'running_number' => ['required', 'integer', 'min:1'],
            'doc_number' => ['required', 'string', 'max:255'],
        ]);

        if (config('edoc.v2.document_reads')) {
            $this->v2Writes->reserveSlot($data['type'], $request->user()->id, (int) $data['running_number'], $data['doc_number']);
            return back()->with('success', 'จองเลขเอกสารใน V2 เรียบร้อยแล้ว');
        }

        DB::transaction(function () use ($data, $request) {
            $document = Document::create([
                'doc_type' => $data['type'],
                'doc_number' => null,
                'title' => '[จองเลขเอกสารล่วงหน้า]',
                'status' => 'RESERVED',
                'created_by' => $request->user()->id,
                'doc_date' => now()->toDateString(),
            ]);

            $this->numbering->allocate(
                $document,
                $request->user(),
                (int) $data['running_number'],
                $data['doc_number'],
                'RESERVED'
            );
        }, 3);

        return back()->with('success', 'จองเลขเอกสารล่วงหน้าเรียบร้อยแล้ว');
    }

    public function registry(Request $request)
    {
        if (config('edoc.v2.document_reads')) {
            $year = $request->input('year', date('Y'));
            $search = $request->input('search');
            $base = V2Document::with(['type', 'numberAllocation', 'creator'])
                ->when($year, fn ($query) => $query->whereYear('created_at', $year))
                ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('title', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('receive_number', 'like', "%{$search}%")));
            $pendingDocuments = (clone $base)->where('status', 'APPROVED')->whereDoesntHave('numberAllocation')->oldest('updated_at')->get();
            $outgoingDocuments = (clone $base)->whereIn('status', ['COMPLETED', 'CANCELED'])
                ->whereHas('type', fn ($types) => $types->whereIn('code', ['INTERNAL', 'OUTGOING']))
                ->whereNotNull('document_number')->latest('updated_at')->get();
            $incomingDocuments = (clone $base)->whereHas('type', fn ($types) => $types->where('code', 'INCOMING'))
                ->whereNotNull('receive_number')->latest('updated_at')->get();
            $summary = ['pending' => $pendingDocuments->count(), 'outgoing' => $outgoingDocuments->count(), 'incoming' => $incomingDocuments->count()];
            return view('documents.registry', compact('pendingDocuments', 'outgoingDocuments', 'incomingDocuments', 'summary', 'year', 'search'));
        }
        $year = $request->input('year', date('Y'));
        $search = $request->input('search');
        $base = Document::with('creator')
            ->when($year, fn ($query) => $query->whereYear('created_at', $year))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('doc_number', 'like', "%{$search}%")
                        ->orWhere('receive_number', 'like', "%{$search}%")
                        ->orWhere('reference_doc', 'like', "%{$search}%")
                        ->orWhere('doc_from', 'like', "%{$search}%")
                        ->orWhere('doc_to', 'like', "%{$search}%");
                });
            });

        $pendingDocuments = (clone $base)->where('status', 'WAITING_NUMBERING')->oldest('updated_at')->get();
        $outgoingDocuments = (clone $base)->whereIn('status', ['APPROVED', 'CANCELED'])
            ->whereIn('doc_type', ['internal', 'outgoing'])->whereNotNull('doc_number')->latest('updated_at')->get();
        $incomingDocuments = (clone $base)->where('doc_type', 'incoming')
            ->whereNotNull('receive_number')->latest('updated_at')->get();
        $summary = [
            'pending' => $pendingDocuments->count(),
            'outgoing' => $outgoingDocuments->count(),
            'incoming' => $incomingDocuments->count(),
        ];

        return view('documents.registry', compact(
            'pendingDocuments', 'outgoingDocuments', 'incomingDocuments', 'summary', 'year', 'search'
        ));
    }

    public function assign(AssignDocumentNumberRequest $request, string $id)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $this->v2Documents->find($id);
            $this->authorize('number', $document);
            if ($document->status !== 'APPROVED' || $document->numberAllocation) {
                return back()->with('error', 'เอกสารนี้ออกเลขแล้วหรือยังไม่ผ่านการอนุมัติ');
            }
            $formatted = (string) $request->input('doc_number');
            $running = $request->integer('running_number') ?: $this->numberFromFormatted($formatted);
            $this->v2Writes->allocateNumber($document, $request->user()->id, $running, $formatted);
            return back()->with('success', 'ลงทะเบียนเลขเอกสารใน V2 เรียบร้อยแล้ว');
        }
        $document = Document::whereIdentifier($id)->firstOrFail();
        $this->authorize('number', $document);

        if ($document->status !== 'WAITING_NUMBERING') {
            return back()->with('error', 'เอกสารฉบับนี้เสร็จสิ้นกระบวนการออกเลขแล้ว ไม่สามารถออกเลขซ้ำได้');
        }

        $formatted = (string) $request->input('doc_number');
        $running = $request->integer('running_number') ?: $this->numberFromFormatted($formatted);
        $this->numbering->allocate($document, $request->user(), $running, $formatted, 'APPROVED');

        return back()->with('success', 'ลงทะเบียนเลขที่เอกสารเรียบร้อยแล้ว');
    }

    public function reserve(AssignDocumentNumberRequest $request, string $id)
    {
        if (config('edoc.v2.document_reads')) {
            $document = $this->v2Documents->find($id);
            abort_unless($request->user()->can('update', $document) || $request->user()->can('number', $document), 403);
            $formatted = (string) $request->input('doc_number');
            $running = $request->integer('running_number') ?: $this->numberFromFormatted($formatted);
            if (! $running) { throw \Illuminate\Validation\ValidationException::withMessages(['running_number' => 'กรุณารันเลขก่อนจอง']); }
            $this->v2Writes->allocateNumber($document, $request->user()->id, $running, $formatted);
            return back()->with('success', 'จองเลขเอกสารใน V2 เรียบร้อยแล้ว');
        }
        $document = Document::whereIdentifier($id)->firstOrFail();
        abort_unless($request->user()->can('update', $document) || $request->user()->can('number', $document), 403);

        $formatted = (string) $request->input('doc_number');
        $running = $request->integer('running_number') ?: $this->numberFromFormatted($formatted);
        if (! $running) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'running_number' => 'กรุณารันเลขก่อนจองเลขเอกสาร',
            ]);
        }
        $this->numbering->allocate($document, $request->user(), $running, $formatted);

        return back()->with('success', 'จองเลขที่หนังสือเรียบร้อยแล้ว');
    }

    private function numberFromFormatted(string $number): ?int
    {
        return preg_match('/(\d+)(?!.*\d)/', $number, $matches) ? (int) $matches[1] : null;
    }

    private function v2Ledger(Request $request, string $type)
    {
        $actor = V2User::findOrFail($request->user()->id);
        $department = $request->input('department', $actor->department);
        $year = (int) $request->input('year', $this->v2Numbering->fiscalYear(now()));
        $query = V2Document::with(['type', 'numberAllocation', 'creator'])
            ->whereHas('type', fn ($types) => $types->where('code', strtoupper($type)))
            ->whereHas('numberAllocation.sequence', fn ($sequences) => $sequences->where('fiscal_year', $year));
        if ($type === 'internal' && $department) {
            $query->whereHas('numberAllocation.sequence', fn ($sequences) => $sequences->where('scope', $department));
        }
        $documents = $query->get()->sortBy('running_number');
        $usedNumbers = $documents->keyBy('running_number');
        $maxNumber = (int) ($documents->max('running_number') ?? 0);
        $ledger = [];
        for ($number = 1; $number <= max(50, $maxNumber + 20); $number++) {
            $document = $usedNumbers->get($number);
            $ledger[$number] = ['status' => $document ? ($document->status === 'CANCELED' ? 'ยกเลิก' : 'ออกเลขแล้ว') : 'ว่าง', 'doc' => $document];
        }
        $departments = V2User::with('organizationUnit.parent')->get()->pluck('department')->filter()->unique()->values();
        $dept = $department;
        return view('documents.number_ledger', compact('ledger', 'type', 'year', 'maxNumber', 'dept', 'departments'));
    }

    private function v2LeaveLedger(Request $request)
    {
        $type = 'leave';
        $department = $request->input('department', $request->user()->department);
        $year = (int) $request->input('year', $this->v2Numbering->fiscalYear(now()));
        $db = DB::connection(config('edoc.v2.connection', 'mysql_v2'));
        $allocations = $db->table('number_allocations as allocations')
            ->join('number_sequences as sequences', 'sequences.id', '=', 'allocations.sequence_id')
            ->join('leave_requests as leaves', 'leaves.id', '=', 'allocations.leave_request_id')
            ->join('users', 'users.id', '=', 'leaves.user_id')
            ->where('sequences.number_type', 'leave')
            ->where('sequences.fiscal_year', $year)
            ->when($department, fn ($query) => $query->where('sequences.scope', $department))
            ->orderBy('allocations.running_number')
            ->get([
                'allocations.leave_request_id', 'allocations.running_number',
                'allocations.formatted_number', 'allocations.allocated_by', 'allocations.allocated_at',
            ]);
        $models = LeaveRequest::with(['user', 'numberAllocation'])
            ->whereIn('id', $allocations->pluck('leave_request_id'))->get()->keyBy('id');
        $documents = $allocations->map(function ($allocation) use ($models) {
            $leave = $models->get($allocation->leave_request_id);
            if (! $leave) {
                return null;
            }
            $leave->setAttribute('running_number', $allocation->running_number);
            $leave->setAttribute('leave_number', $allocation->formatted_number);
            $leave->setAttribute('numbered_by', $allocation->allocated_by);
            $leave->setAttribute('numbered_at', $allocation->allocated_at);

            return $leave;
        })->filter()->values();
        $usedNumbers = $documents->keyBy('running_number');
        $maxNumber = (int) ($allocations->max('running_number') ?? 0);
        $ledger = [];
        for ($number = 1; $number <= max(50, $maxNumber + 20); $number++) {
            $document = $usedNumbers->get($number);
            $ledger[$number] = ['status' => $document ? 'ออกเลขแล้ว' : 'ว่าง', 'doc' => $document];
        }
        $departments = User::select('department')->distinct()->pluck('department')->filter();
        $dept = $department;

        return view('documents.number_ledger', compact('ledger', 'type', 'year', 'maxNumber', 'dept', 'departments'));
    }
}
