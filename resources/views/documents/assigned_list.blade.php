@extends('layouts.app')

@section('title', 'งานที่ได้รับมอบหมาย')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:var(--primary-dark)">
                <i class="fas fa-clipboard-list text-primary me-2"></i>งานที่ได้รับมอบหมาย
            </h4>
            <small class="text-muted">รับดำเนินการด้วยตนเอง หรือส่งต่อให้บุคลากรในฝ่ายเดียวกัน</small>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3">กลับ Dashboard</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card shadow-sm border-0" style="border-radius:14px">
        <div class="card-header bg-white py-3 fw-bold">
            <i class="fas fa-inbox text-primary me-2"></i>{{ auth()->user()->department ?: 'รายการมอบหมายของฉัน' }}
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>วันที่</th>
                        <th>เรื่อง</th>
                        <th>ผู้รับปัจจุบัน</th>
                        <th>สถานะ</th>
                        <th style="min-width:360px">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($documents as $doc)
                    @php
                        $canAct = ($doc->assigned_user_id === auth()->id())
                            || (auth()->user()->hasRole('head') && !$doc->assigned_user_id && $doc->assigned_to === auth()->user()->department);
                    @endphp
                    <tr>
                        <td class="text-muted small text-nowrap">{{ optional($doc->assigned_at ?? $doc->updated_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="fw-bold text-decoration-none text-dark">{{ $doc->title }}</a>
                            <div class="small text-muted">มอบหมายฝ่าย: {{ $doc->assigned_to ?: '-' }}</div>
                        </td>
                        <td>{{ $doc->assignee?->name ?? 'หัวหน้าฝ่าย' }}</td>
                        <td>
                            @if($doc->assignment_status === 'accepted')
                                <span class="badge bg-success">รับดำเนินการแล้ว</span>
                            @elseif($doc->assignment_status === 'delegated')
                                <span class="badge bg-info text-dark">ส่งต่อแล้ว</span>
                            @else
                                <span class="badge bg-warning text-dark">รอรับเรื่อง</span>
                            @endif
                        </td>
                        <td>
                            @if($canAct && $doc->assignment_status !== 'accepted')
                                <div class="d-flex flex-wrap gap-2">
                                    <form action="{{ route('documents.assignment_action', $doc->uuid ?? $doc->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="accept">
                                        <button class="btn btn-success btn-sm rounded-pill px-3" type="submit">
                                            <i class="fas fa-check me-1"></i>รับดำเนินการเอง
                                        </button>
                                    </form>

                                    @if($subordinates->isNotEmpty())
                                    <form action="{{ route('documents.assignment_action', $doc->uuid ?? $doc->id) }}" method="POST" class="d-flex gap-1 flex-grow-1">
                                        @csrf
                                        <input type="hidden" name="action" value="delegate">
                                        <select name="delegate_user_id" class="form-select form-select-sm" required>
                                            <option value="">เลือกบุคลากรในฝ่าย...</option>
                                            @foreach($subordinates as $subordinate)
                                                <option value="{{ $subordinate->id }}">{{ $subordinate->name }}{{ $subordinate->position ? ' — '.$subordinate->position : '' }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-primary btn-sm text-nowrap" type="submit"><i class="fas fa-share me-1"></i>ส่งต่อ</button>
                                    </form>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">
                                    @if($doc->assignment_status === 'accepted')
                                        <i class="fas fa-check-double text-success me-1"></i>{{ $doc->assignee?->name }} รับเรื่องแล้ว
                                    @elseif($doc->assignee)
                                        <i class="fas fa-share text-primary me-1"></i>ส่งต่อให้ {{ $doc->assignee->name }}
                                    @else
                                        รอดำเนินการ
                                    @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x opacity-25 mb-3"></i><br>ยังไม่มีงานที่ได้รับมอบหมาย</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
