<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\RoomBookingController;
use App\Http\Controllers\DocumentAccessController;
use App\Http\Controllers\DocumentNumberingController;
use App\Http\Controllers\AuditLogController;

// =============================================================================
// หน้าแรก redirect ไปหน้า login
// =============================================================================
Route::get('/', function () {
    return redirect('/login');
});

Auth::routes();

// =============================================================================
// ทุก Route ด้านล่างต้องผ่าน auth middleware
// =============================================================================
Route::middleware(['auth'])->group(function () {

    // =========================================================================
    // Dashboard
    // =========================================================================
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    
    // 🌟 Dashboard สำหรับผู้บริหาร (จำกัดสิทธิ์เฉพาะคนที่เกี่ยวข้อง)
    Route::get('/dashboard/executive', [DashboardController::class, 'executiveDashboard'])
        ->name('dashboard.executive')
        ->middleware(['role:super-admin|executive|palad|deputy-palad|head|saraban']);

    // =========================================================================
    // โปรไฟล์ของฉัน (Profile & Password) — ทุก role เข้าได้
    // =========================================================================
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',                    [ProfileController::class, 'index'])->name('index');
        Route::post('/',                   [ProfileController::class, 'update'])->name('update');
        Route::post('/change-password',    [ProfileController::class, 'changePassword'])->name('change_password');
        Route::delete('/signature/delete', [ProfileController::class, 'deleteSignature'])->name('signature.delete');
        
        // 🌟 แก้ไขแล้ว: ตัด /profile/ และ profile. ออก เพราะมันมี Group ครอบอยู่แล้ว
        Route::post('/request-pin-reset',  [ProfileController::class, 'requestPinReset'])->name('request_pin_reset');
    });

    // =========================================================================
    // ระบบจองห้องประชุม — ทุก role ดู/จองได้ แก้/ลบเฉพาะเจ้าของ (จัดการใน Controller)
    // =========================================================================
    Route::resource('bookings', RoomBookingController::class)->only(['index', 'create', 'store', 'destroy']);

    Route::middleware(['role:super-admin'])->prefix('admin/rooms')->name('admin.rooms.')->group(function () {
        Route::get('/', [RoomBookingController::class, 'rooms'])->name('index');
        Route::post('/', [RoomBookingController::class, 'storeRoom'])->name('store');
        Route::put('/{room}', [RoomBookingController::class, 'updateRoom'])->name('update');
        Route::delete('/{room}', [RoomBookingController::class, 'destroyRoom'])->name('destroy');
    });

    // =========================================================================
    // ระบบใบลา — ฝั่งผู้ขอลา (ทุก role ยื่นและดูประวัติตัวเองได้)
    // =========================================================================
    Route::prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/history',               [LeaveRequestController::class, 'index'])->name('index');
        Route::get('/create',                [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/',                     [LeaveRequestController::class, 'store'])->name('store');
        Route::put('/{id}/cancel',           [LeaveRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/delegate-action', [LeaveRequestController::class, 'delegateAction'])->name('delegateAction');
        Route::post('/{id}/reassign-delegate', [LeaveRequestController::class, 'reassignDelegate'])->name('reassign_delegate');
    });

    // =========================================================================
    // วันหยุดพิเศษ
    // =========================================================================
    Route::prefix('holidays')->name('holidays.')->group(function () {
        Route::get('/', [HolidayController::class, 'index'])->name('index');

        Route::middleware(['role:super-admin|palad'])->group(function () {
            Route::post('/',          [HolidayController::class, 'store'])->name('store');
            Route::delete('/{id}',    [HolidayController::class, 'destroy'])->name('destroy');
            Route::post('/sync',      [HolidayController::class, 'sync'])->name('sync');
        });
    });

    // =========================================================================
        // เอกสาร — ดูรายการและรายละเอียด
    // =========================================================================
    Route::prefix('documents')->name('documents.')->group(function () {

        // --- Static routes (ต้องประกาศก่อน dynamic {id}) ---
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        
        // 🌟 ย้าย Static Routes ของสมุดคุมเลขมาไว้ด้านบน เพื่อความปลอดภัย
        Route::middleware('role:super-admin|palad|deputy-palad|saraban')->group(function () {
            Route::get('/number-ledger', [DocumentNumberingController::class, 'ledger'])->name('number_ledger');
            Route::get('/api/next-number', [DocumentNumberingController::class, 'next'])->name('api_next_number');
        });
        Route::post('/reserve-number-slot', [DocumentNumberingController::class, 'reserveSlot'])
            ->middleware('role:super-admin|palad|saraban')
            ->name('reserve_number_slot');

        Route::post('/auto-extract', [\App\Http\Controllers\DocumentController::class, 'autoExtract'])->name('auto_extract');
        Route::get('/auto-extract/{taskId}', [\App\Http\Controllers\DocumentController::class, 'autoExtractStatus'])
            ->name('auto_extract_status');

        // -------------------------------------------------------------------------
        // โซนสร้าง / แก้ไข / อัปโหลดเอกสาร
        // -------------------------------------------------------------------------
        Route::middleware([
            'role:super-admin|executive|palad|deputy-palad|head|saraban|officer|finance|parcel|hr|analyst|engineer|education|health|disaster|auditor|teacher'
        ])->group(function () {
            Route::get('/create',          [DocumentController::class, 'create'])->name('create');
            Route::get('/create-incoming', [DocumentController::class, 'createIncoming'])->name('create_incoming');
            Route::get('/create-upload',   [DocumentController::class, 'createUpload'])->name('create_upload');
            Route::get('/create-outgoing', [DocumentController::class, 'createOutgoing'])->name('create_outgoing');
            Route::get('/assigned',        [DocumentController::class, 'assignedList'])->name('assigned');

            Route::post('/',               [DocumentController::class, 'store'])->name('store');
            Route::post('/incoming',       [DocumentController::class, 'storeIncoming'])->name('store_incoming');
            Route::post('/outgoing',       [DocumentController::class, 'storeOutgoing'])->name('store_outgoing');
            Route::post('/store-upload',   [DocumentController::class, 'storeUpload'])->name('store_upload');

            // --- Dynamic {id} routes ---
            Route::get('/{id}/edit',             [DocumentController::class, 'edit'])->name('edit');
            Route::put('/{id}',                  [DocumentController::class, 'update'])->name('update');
            Route::delete('/{id}',               [DocumentController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/sign',            [DocumentController::class, 'sign'])->name('sign');
            
            // 🌟 ระบบประทับลายเซ็น
            Route::post('/{id}/stamp-signature', [DocumentController::class, 'stampSignatureToPdf'])->name('stamp_signature');
            
            Route::post('/{id}/upload',          [DocumentController::class, 'uploadAttachment'])->name('upload');
            Route::get('/{id}/download-signed',  [DocumentController::class, 'downloadSignedPdf'])->name('download_signed');
            Route::post('/{id}/reserve-number',  [DocumentNumberingController::class, 'reserve'])->name('reserve_number');
        });

        // -------------------------------------------------------------------------
        // โซนพิจารณา / อนุมัติเอกสาร
        // -------------------------------------------------------------------------
        // ผู้ใช้ทุกบทบาทอาจถูกเลือกเป็นผู้พิจารณาใน dynamic workflow ได้
        // Controller จะตรวจอีกชั้นว่าต้องเป็นคิวปัจจุบันของผู้ใช้นั้นจริง
        Route::get('/approve-list', [DocumentController::class, 'approveList'])->name('approve_list');
        Route::post('/{id}/review', [DocumentController::class, 'reviewDocument'])->name('review');

        // 🌟 ระบบรับทราบคำสั่ง (สำหรับ ผอ.กอง)
        Route::post('/{id}/acknowledge', [DocumentController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/{id}/assignment-action', [DocumentController::class, 'assignmentAction'])->name('assignment_action');

        // -------------------------------------------------------------------------
        // สมุดทะเบียนเลข (ธุรการ)
        // -------------------------------------------------------------------------
        Route::middleware([
            'role:super-admin|palad|saraban'
        ])->group(function () {
            Route::get('/registry',                    [DocumentNumberingController::class, 'registry'])->name('registry');
            Route::post('/registry/{id}/assign-number',[DocumentNumberingController::class, 'assign'])->name('assign_number');
        });

        // -------------------------------------------------------------------------
        // 🌟 โซนดูเอกสาร + ระบบเอกสารลับ (Dynamic {id}) ต้องอยู่ด้านล่างสุด 🌟
        // -------------------------------------------------------------------------
        Route::get('/{id}/show',         [DocumentController::class, 'show'])->name('show');
        Route::get('/{id}/routing-slip', [DocumentController::class, 'routingSlip'])->name('routing_slip');
        
        // 🌟 ฟังก์ชันจัดการเอกสารลับ (ตัด /documents และ name(documents.) ออกเพราะอยู่ใน Group แล้ว)
        Route::post('/{id}/unlock-secret', [DocumentAccessController::class, 'unlock'])->name('unlock_secret');
        Route::post('/{id}/request-access', [DocumentAccessController::class, 'request'])->name('request_access');
        Route::post('/access-requests/{requestId}/{status}', [DocumentAccessController::class, 'decide'])->name('approve_access');
    });

    // =========================================================================
    // โซนพิจารณาใบลา (5 ด่านตามระเบียบ อบต.)
    // =========================================================================
    Route::middleware([
        'role:super-admin|executive|palad|deputy-palad|head|hr|saraban|officer'
    ])->prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/approve-list',       [LeaveRequestController::class, 'approveList'])->name('approve_list');
        Route::get('/{id}/show',          [LeaveRequestController::class, 'show'])->name('show');
        Route::post('/{id}/review-action',[LeaveRequestController::class, 'reviewAction'])->name('reviewAction');
    });

    // =========================================================================
    // Admin — จัดการผู้ใช้ (เฉพาะ super-admin)
    // =========================================================================
    Route::middleware(['role:super-admin'])->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::put('/users/{id}/restore',          [UserController::class, 'restore'])->name('users.restore');
        Route::post('/admin/users/{id}/clear-pin', [UserController::class, 'clearUserPin'])->name('admin.users.clear_pin');
    });

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:super-admin|auditor')
        ->name('admin.audit_logs.index');

    // =========================================================================
    // 🤖 AI API — สร้างร่างเอกสาร (Typhoon 2.5)
    // =========================================================================
    Route::post('/ai/generate-draft', [DocumentController::class, 'generateDraft'])
        ->middleware('throttle:20,1')
        ->name('ai.generate_draft');

    //---
    Route::get('/line/login', [App\Http\Controllers\ProfileController::class, 'redirectToLine'])->name('line.login');
    Route::get('/line/callback', [App\Http\Controllers\ProfileController::class, 'handleLineCallback'])->name('line.callback');
    Route::post('/line/unlink', [App\Http\Controllers\ProfileController::class, 'unlinkLine'])->name('line.unlink');
    
});
