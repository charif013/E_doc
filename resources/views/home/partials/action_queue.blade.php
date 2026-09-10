@if($documentTasks->isNotEmpty() || $leaveTasks->isNotEmpty())
<section class="card dashboard-panel dashboard-action-widget mb-4" aria-labelledby="dashboard-action-title">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="panel-title-icon panel-title-icon--action"><i class="fas fa-inbox" aria-hidden="true"></i></span>
                <div>
                    <h5 id="dashboard-action-title" class="fw-bold text-dark mb-0">งานที่ต้องดำเนินการ</h5>
                    <span class="small text-muted">เฉพาะรายการที่ถึงคิวของคุณ</span>
                </div>
            </div>
            <span class="dashboard-action-total">{{ $documentTasks->count() + $leaveTasks->count() }}</span>
        </div>

        @if($documentTasks->isNotEmpty())
            <div class="sidebar-queue-group">
                <div class="sidebar-queue-group__title">
                    <span><i class="far fa-file-lines me-2" aria-hidden="true"></i>เอกสาร</span>
                    <span>{{ $documentTasks->count() }} รายการ</span>
                </div>
                @foreach($documentTasks->take(3) as $task)
                    @php
                        $taskStatus = $task->isAtFinalApprovalStep() ? 'รออนุมัติ' : match($task->status) {
                            'DRAFT' => 'รอลงนามนำส่ง',
                            'REJECTED' => 'รอแก้ไข',
                            'WAITING_ADMIN', 'REGISTERED' => 'รอธุรการตรวจสอบ',
                            'WAITING_SUPERVISOR' => 'รอหัวหน้าพิจารณา',
                            'WAITING_PALAD' => 'รอปลัดพิจารณา',
                            'WAITING_NAYOK' => 'รอผู้ลงนามอนุมัติ',
                            'WAITING_APPROVER' => 'รออนุมัติ',
                            'WAITING_NUMBERING' => 'รอธุรการออกเลข',
                            // เอกสารรับเข้ามีเลขตั้งแต่อัปโหลด เมื่ออนุมัติแล้วรายการที่ยังอยู่ใน
                            // action queue คือภารกิจรับเรื่อง/ดำเนินการ ไม่ใช่ภารกิจออกเลขซ้ำ
                            'APPROVED', 'COMPLETED', 'ARCHIVED' => $task->numberAllocation
                                ? 'รอรับเรื่อง/ดำเนินการ'
                                : 'รอธุรการออกเลข',
                            default => 'รอดำเนินการ',
                        };
                    @endphp
                    <a href="{{ route('documents.show', $task->uuid ?? $task->id) }}" class="sidebar-queue-item">
                        <div>
                            <strong>{{ $task->title ?: 'เอกสารไม่มีชื่อเรื่อง' }}</strong>
                            <span>{{ $task->formatted_doc_number ?? $task->formatted_receive_number ?? 'ยังไม่มีเลขที่' }}</span>
                        </div>
                        <div class="sidebar-queue-item__end">
                            <span class="queue-status">{{ $taskStatus }}</span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </div>
                    </a>
                @endforeach
                @if($documentTasks->count() > 3)
                    <a href="{{ route('documents.approve_list') }}" class="sidebar-queue-more">ดูเอกสารทั้งหมดอีก {{ $documentTasks->count() - 3 }} รายการ</a>
                @endif
            </div>
        @endif

        @if($leaveTasks->isNotEmpty())
            <div class="sidebar-queue-group {{ $documentTasks->isNotEmpty() ? 'mt-3' : '' }}">
                <div class="sidebar-queue-group__title sidebar-queue-group__title--leave">
                    <span><i class="far fa-calendar-check me-2" aria-hidden="true"></i>ใบลารอพิจารณา</span>
                    <span>{{ $leaveTasks->count() }} รายการ</span>
                </div>
                @foreach($leaveTasks->take(3) as $leaveTask)
                    @php
                        $leaveStatus = match($leaveTask->workflow_status) {
                            'pending_delegate' => 'รอตอบรับงานแทน',
                            'pending_head' => 'รอหัวหน้าให้ความเห็น',
                            'pending_inspector' => 'รอตรวจสอบสิทธิ์',
                            'pending_palad' => 'รอปลัดพิจารณา',
                            'pending_nayok' => 'รอนายกอนุมัติ',
                            'pending_numbering' => 'รอธุรการลงเลข',
                            default => 'รอพิจารณา',
                        };
                    @endphp
                    <a href="{{ route('leaves.show', $leaveTask) }}" class="sidebar-queue-item">
                        <div>
                            <strong>{{ $leaveTask->user->name ?? 'ไม่ระบุชื่อผู้ลา' }} · {{ $leaveTask->leave_type }}</strong>
                            <span>{{ $leaveTask->start_date?->addYears(543)->format('d/m/Y') }} ถึง {{ $leaveTask->end_date?->addYears(543)->format('d/m/Y') }}</span>
                        </div>
                        <div class="sidebar-queue-item__end">
                            <span class="queue-status queue-status--leave">{{ $leaveStatus }}</span>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </div>
                    </a>
                @endforeach
                @if($leaveTasks->count() > 3)
                    <a href="{{ route('leaves.approve_list') }}" class="sidebar-queue-more">ดูใบลาทั้งหมดอีก {{ $leaveTasks->count() - 3 }} รายการ</a>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
