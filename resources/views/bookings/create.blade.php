@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-calendar-plus me-2 text-primary"></i>จองห้องประชุม / นัดหมาย</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('bookings.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            {{-- หัวข้อ --}}
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">หัวข้อ / วัตถุประสงค์</label>
                                <input type="text" name="title" class="form-control" placeholder="เช่น ประชุมสรุปงานประจำเดือน หรือ เข้าใช้งานห้องเพื่อตรวจงาน" required>
                            </div>

                            {{-- ประเภทการจอง --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">ประเภทการใช้งาน</label>
                                <select name="booking_type" id="booking_type" class="form-select" required onchange="toggleInvitees()">
                                    <option value="meeting">📅 นัดหมายประชุม (เชิญคนเข้าร่วม)</option>
                                    <option value="general_use">🏠 ใช้งานทั่วไป / ทำงานส่วนตัว</option>
                                    <option value="maintenance">🛠️ ปิดปรับปรุงห้อง</option>
                                </select>
                            </div>

                            {{-- เลือกห้อง --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">เลือกห้องประชุม</label>
                                <select name="room_id" class="form-select" required>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} (จุได้ {{ $room->capacity }} คน)</option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- เวลาเริ่ม --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">เริ่มเวลา</label>
                                <input type="text" name="start_time" class="form-control datetime-picker" placeholder="คลิกเพื่อเลือกวันและเวลา" required>
                            </div>

                            {{-- เวลาสิ้นสุด --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">ถึงเวลา</label>
                                <input type="text" name="end_time" class="form-control datetime-picker" placeholder="คลิกเพื่อเลือกวันและเวลา" required>
                            </div>

                            {{-- รายละเอียดเพิ่มเติม --}}
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">รายละเอียด (ถ้ามี)</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>

                            {{-- ส่วนเลือกผู้เข้าร่วม (จะโชว์เฉพาะตอนเลือก 'นัดประชุม') --}}
                            <div class="col-md-12 mb-4" id="invitees_section">
                                <label class="form-label fw-bold text-primary">เชิญผู้เข้าร่วมประชุม</label>
                                <div class="p-3 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                    @foreach($users as $user)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="invitees[]" value="{{ $user->id }}" id="user_{{ $user->id }}">
                                            <label class="form-check-label" for="user_{{ $user->id }}">
                                                {{ $user->name }} <span class="text-muted small">({{ $user->position }})</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">* รายชื่อผู้เข้าร่วมจะได้รับแจ้งเตือนในระบบ</small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end border-top pt-4">
                            <a href="{{ route('bookings.index') }}" class="btn btn-light px-4">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">บันทึกการจอง</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ฟังก์ชันสำหรับ ซ่อน/แสดง ส่วนเลือกคนเข้าร่วม
    function toggleInvitees() {
        const type = document.getElementById('booking_type').value;
        const section = document.getElementById('invitees_section');
        if (type === 'meeting') {
            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    }
    // รันครั้งแรกตอนโหลดหน้า
    window.onload = toggleInvitees;

    // 🌟 ตั้งค่าปฏิทิน
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('.datetime-picker', {
            enableTime: true,        
            time_24hr: true,         
            locale: "th",            
            dateFormat: "Y-m-d H:i", 
            altInput: true,          
            altFormat: "d/m/Y เวลา H:i น."
            // (ลบ appendTo และ position ของเก่าทิ้งไปได้เลยครับ)
        });

        // 🌟 เพิ่มคำสั่ง: เมื่อมีการเลื่อนหน้าจอ (Scroll) ให้พับเก็บปฏิทินทันที
        const contentArea = document.querySelector('.content-area');
        if (contentArea) {
            contentArea.addEventListener('scroll', function() {
                document.querySelectorAll('.datetime-picker').forEach(function(input) {
                    // ถ้าช่องไหนเปิดปฏิทินค้างไว้ ให้สั่งปิด (close)
                    if (input._flatpickr) {
                        input._flatpickr.close();
                    }
                });
            });
        }
    });
</script>

@endsection