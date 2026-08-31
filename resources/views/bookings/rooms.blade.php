@extends('layouts.app')

@section('title', 'จัดการห้องประชุม')

@section('content')
<div class="container-fluid px-4 py-4">
    <h4 class="fw-bold mb-4"><i class="fas fa-door-open text-primary me-2"></i>จัดการห้องประชุม</h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3">เพิ่มห้องประชุม</div>
                <div class="card-body">
                    <form action="{{ route('admin.rooms.store') }}" method="POST">
                        @csrf
                        <div class="mb-3"><label class="form-label">ชื่อห้อง</label><input name="name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">ความจุ (คน)</label><input name="capacity" type="number" min="1" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">สถานะ</label><select name="status" class="form-select"><option value="active">เปิดใช้งาน</option><option value="inactive">ปิดใช้งาน</option></select></div>
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-plus me-1"></i>เพิ่มห้อง</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3">ห้องประชุมทั้งหมด</div>
                <div class="card-body">
                    @foreach($rooms as $room)
                    <form action="{{ route('admin.rooms.update', $room) }}" method="POST" class="row g-2 align-items-end border-bottom pb-3 mb-3">
                        @csrf @method('PUT')
                        <div class="col-md-5"><label class="form-label small">ชื่อห้อง</label><input name="name" value="{{ $room->name }}" class="form-control" required></div>
                        <div class="col-md-2"><label class="form-label small">ความจุ</label><input name="capacity" value="{{ $room->capacity }}" type="number" min="1" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label small">สถานะ</label><select name="status" class="form-select"><option value="active" @selected($room->status==='active')>เปิดใช้งาน</option><option value="inactive" @selected($room->status==='inactive')>ปิดใช้งาน</option></select></div>
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">บันทึก</button></div>
                    </form>
                    <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" class="text-end mb-3" onsubmit="return confirm('ยืนยันปิดใช้งานห้องนี้? ประวัติการจองเดิมจะยังคงอยู่')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">ปิดใช้งานและเก็บประวัติ</button>
                    </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
