<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDocumentNumberRequest;
use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\DocumentNumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentNumberingController extends Controller
{
    public function __construct(private DocumentNumberingService $numbering) {}

    public function ledger(Request $request)
    {
        $type = $request->input('type', 'outgoing');
        abort_unless(in_array($type, ['incoming', 'outgoing', 'internal', 'leave'], true), 422);

        $userDepartment = $request->user()->department;
        $department = $request->input('department', $userDepartment);
        $defaultYear = $this->numbering->fiscalYear(now());
        $year = (int) $request->input('year', $defaultYear);
        [$start, $end] = $this->numbering->fiscalRange($year);

        if ($type === 'leave') {
            $documents = LeaveRequest::with(['user', 'numberedBy'])
                ->whereBetween('numbered_at', [$start, $end])
                ->whereNotNull('running_number')
                ->orderBy('running_number')->get();
        } else {
            $documents = Document::where('doc_type', $type)
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('running_number')
                ->when($type === 'internal', function ($query) use ($department) {
                    $query->whereHas('creator', fn ($users) => $users->where('department', $department));
                })
                ->orderBy('running_number')->get();
        }

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

        $fiscalYear = $this->numbering->fiscalYear(now());
        [$start, $end] = $this->numbering->fiscalRange($fiscalYear);

        if ($type === 'leave') {
            $max = LeaveRequest::whereBetween('numbered_at', [$start, $end])->max('running_number') ?? 0;
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
            ? 'ลา/'.$number.'/'.($fiscalYear + 543)
            : 'ยล 77301/'.$number;

        return response()->json(['next_number' => $number, 'formatted' => $formatted]);
    }

    public function reserveSlot(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:incoming,outgoing,internal'],
            'running_number' => ['required', 'integer', 'min:1'],
            'doc_number' => ['required', 'string', 'max:255'],
        ]);

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
        $year = $request->input('year', date('Y'));
        $search = $request->input('search');
        $base = Document::query()
            ->when($year, fn ($query) => $query->whereYear('created_at', $year))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('doc_number', 'like', "%{$search}%");
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
}
