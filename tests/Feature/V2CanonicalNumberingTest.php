<?php

namespace Tests\Feature;

use App\Models\V2\LeaveRequest;
use App\Models\V2\Document;
use App\Services\V2\DocumentNumberingService;
use App\Services\V2\NumberAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class V2CanonicalNumberingTest extends TestCase
{
    use UsesV2Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootV2Database();
        $this->seedV2User();
    }

    public function test_leave_number_is_written_only_to_canonical_tables(): void
    {
        $db = DB::connection('mysql_v2');
        $leaveTypeId = $db->table('leave_types')->insertGetId([
            'code' => 'ANNUAL', 'name' => 'Annual leave', 'is_active' => true,
            'requires_attachment' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $leave = LeaveRequest::create([
            'user_id' => 1, 'leave_type_id' => $leaveTypeId,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-10',
            'total_days' => 1, 'reason' => 'Canonical numbering test',
        ]);

        app(NumberAllocationService::class)->allocate(
            $leave, 'leave_request_id', 'leave', 'สำนักงานปลัด', 2026,
            1, 'ลา/สำนักงานปลัด/1/2569', 1, now(), 'leave_number'
        );

        $this->assertDatabaseHas('number_sequences', [
            'number_type' => 'leave', 'scope' => 'สำนักงานปลัด',
            'fiscal_year' => 2026, 'last_number' => 1,
        ], 'mysql_v2');
        $this->assertDatabaseHas('number_allocations', [
            'leave_request_id' => $leave->id, 'document_id' => null,
            'running_number' => 1, 'formatted_number' => 'ลา/สำนักงานปลัด/1/2569',
        ], 'mysql_v2');
        $this->assertDatabaseHas('document_number_allocations', [
            'leave_request_id' => $leave->id, 'running_number' => 1,
            'formatted_number' => 'ลา/สำนักงานปลัด/1/2569',
        ], 'mysql_v2');
    }

    public function test_canonical_sequence_rejects_duplicate_number(): void
    {
        $db = DB::connection('mysql_v2');
        $leaveTypeId = $db->table('leave_types')->insertGetId([
            'code' => 'SICK', 'name' => 'Sick leave', 'is_active' => true,
            'requires_attachment' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = $this->leave($leaveTypeId, 1);
        $second = $this->leave($leaveTypeId, 2);
        $service = app(NumberAllocationService::class);
        $service->allocate($first, 'leave_request_id', 'leave', 'กองช่าง', 2026, 7, 'ลา/กองช่าง/7/2569', 1);

        $this->expectException(ValidationException::class);
        $service->allocate($second, 'leave_request_id', 'leave', 'กองช่าง', 2026, 7, 'ลา/กองช่าง/7/2569', 1);
    }

    public function test_numbered_approved_document_is_not_labeled_as_waiting_for_numbering(): void
    {
        $this->seedV2Document();
        $document = Document::findOrFail(1);
        app(DocumentNumberingService::class)->allocate(
            $document, 1, 12, 'ยล 77301/12', 'organization'
        );
        $document->update(['status' => 'APPROVED']);
        $document->load('numberAllocation');

        $html = view('home.partials.action_queue', [
            'documentTasks' => collect([$document]),
            'leaveTasks' => collect(),
        ])->render();

        $this->assertStringContainsString('รอรับเรื่อง/ดำเนินการ', $html);
        $this->assertStringNotContainsString('รอธุรการออกเลข', $html);
    }

    private function leave(int $leaveTypeId, int $id): LeaveRequest
    {
        return LeaveRequest::create([
            'id' => $id, 'user_id' => 1, 'leave_type_id' => $leaveTypeId,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-10',
            'total_days' => 1, 'reason' => "Leave {$id}",
        ]);
    }
}
