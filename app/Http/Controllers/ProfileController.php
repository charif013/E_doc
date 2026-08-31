<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Services\LineMessagingService;
use App\Services\NotificationDispatcher;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('profile.index', compact('user'));
    }

    /**
     * สำหรับอัปเดต LINE ID, PIN และ ลายเซ็น
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // ===== 1. อัปเดต LINE ID =====
        if ($request->has('line_id')) { // เช็คแบบ has เพื่อให้ลบไอดีทิ้งได้ (ถ้าส่งค่าว่างมา)
            $request->validate([
                'line_id' => 'nullable|string|max:255',
            ]);
            $user->line_id = $request->line_id;
        }

        // ===== 2. อัปเดต PIN =====
        if ($request->filled('pin')) {
            $request->validate([
                'pin'         => 'required|digits:6',
                'current_pin' => 'nullable|digits:6'
            ], [
                'pin.digits'         => 'รหัส PIN ต้องเป็นตัวเลข 6 หลักเท่านั้น',
                'current_pin.digits' => 'รหัส PIN ปัจจุบันต้องเป็นตัวเลข 6 หลักเท่านั้น'
            ]);
            
            // ถ้าระบบมีรหัส PIN เดิมอยู่แล้ว ต้องบังคับเช็ครหัสเดิมก่อน
            if (!empty($user->pin)) {
                if (!$request->filled('current_pin')) {
                    return back()->with('error', 'กรุณากรอกรหัส PIN ปัจจุบันเพื่อยืนยันตัวตน!');
                }

                // เช็คความถูกต้องของรหัส PIN เดิม (รองรับทั้งแบบ Hash และตัวเลขธรรมดา)
                $isPinCorrect = Hash::check($request->current_pin, $user->pin) || ($request->current_pin === $user->pin);

                if (!$isPinCorrect) {
                    return back()->with('error', 'รหัส PIN ปัจจุบันไม่ถูกต้อง ไม่สามารถเปลี่ยนใหม่ได้!');
                }
            }

            // ถ้ารหัสเดิมถูกต้อง (หรือเพิ่งตั้งครั้งแรก) ค่อยบันทึกรหัสใหม่
            $user->pin = Hash::make($request->pin);
        }

        // ===== 3. อัปเดตลายเซ็น =====
        if ($request->filled('signature')) {
            $user->signature = $request->signature;
        }

        $user->save();

        return back()->with('success', 'อัปเดตข้อมูลโปรไฟล์และลายเซ็นเรียบร้อยแล้ว');
    }

    /**
     * สำหรับเปลี่ยนรหัสผ่านเข้าสู่ระบบ
     */
    public function changePassword(Request $request)
    {
        // 1. ตรวจสอบข้อมูลที่ส่งมา
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed', // password_confirmation ต้องตรงกัน
        ], [
            'current_password.required' => 'กรุณากรอกรหัสผ่านปัจจุบัน',
            'new_password.required'     => 'กรุณากรอกรหัสผ่านใหม่',
            'new_password.min'          => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร',
            'new_password.confirmed'    => 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // 2. เช็คว่ารหัสผ่านปัจจุบันที่กรอกมา ถูกต้องตรงกับในฐานข้อมูลหรือไม่
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
        }

        // 3. ป้องกันการตั้งรหัสผ่านใหม่ซ้ำกับรหัสผ่านเดิม
        if (Hash::check($request->new_password, $user->password)) {
            return back()->with('error', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านปัจจุบัน!');
        }

        // 4. บันทึกรหัสผ่านใหม่
        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'เปลี่ยนรหัสผ่านเข้าสู่ระบบเรียบร้อยแล้ว');
    }

    public function deleteSignature()
    {
        /** @var User $user */ 
        $user = Auth::user();
        
        $user->signature = null;
        $user->save(); 

        return back()->with('success', 'ลบลายเซ็นเรียบร้อยแล้ว');
    }

    public function requestPinReset()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        $user->pin_reset_requested = true; // เปลี่ยนสถานะเป็น: ส่งคำขอแล้ว
        $user->save();

        $message = "🔐 มีคำขอล้างรหัส PIN\nผู้ขอ: {$user->name}\nหน่วยงาน: " . ($user->department ?: '-') . "\n\nเปิดระบบเพื่อดำเนินการ:\n" . route('users.index');
        app(NotificationDispatcher::class)->toUsers(
            app(LineMessagingService::class)->usersWithRoles('super-admin'),
            $message
        );

        return back()->with('success', 'ส่งคำขอล้างรหัส PIN ไปยังผู้ดูแลระบบแล้ว กรุณารอการอนุมัติ');
    }

    // =========================================================
    // 🌟 โซนผูกบัญชี LINE Login
    // =========================================================

    public function redirectToLine()
    {
        $clientId = config('services.line.login_channel_id');
        $redirectUri = urlencode((string) config('services.line.redirect_uri'));
        $state = csrf_token(); // สร้างรหัสกันการแฮก
        
        $url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}&state={$state}&scope=profile&bot_prompt=aggressive";
        
        return redirect($url);
    }

    public function handleLineCallback(Request $request)
    {
        if (!$request->filled('state') || !hash_equals((string) session()->token(), (string) $request->state)) {
            return redirect()->route('profile.index')->with('error', 'ไม่สามารถยืนยันคำขอเชื่อมต่อ LINE ได้ กรุณาลองใหม่');
        }

        // ถ้าผู้ใช้กดยกเลิกในหน้า LINE
        if ($request->has('error')) {
            return redirect()->route('profile.index')->with('error', 'คุณยกเลิกการเชื่อมต่อ LINE');
        }

        $code = $request->code;
        $clientId = config('services.line.login_channel_id');
        $clientSecret = config('services.line.login_secret');
        $redirectUri = (string) config('services.line.redirect_uri');

        // 1. นำ Code ไปแลกเป็น Access Token จาก LINE
        $response = Http::asForm()->post('https://api.line.me/oauth2/v2.1/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful()) {
            $accessToken = $response->json('access_token');

            // 2. เอา Token ไปขอดึงข้อมูลโปรไฟล์ (เพื่อเอา userId ที่ขึ้นต้นด้วย U)
            $profileResponse = Http::withToken($accessToken)
                ->get('https://api.line.me/v2/profile');

            if ($profileResponse->successful()) {
                $lineId = $profileResponse->json('userId'); 

                // 3. บันทึกไอดีลงฐานข้อมูลให้ User อัตโนมัติ
                /** @var User $user */
                $user = Auth::user();
                $user->line_id = $lineId;
                $user->save();

                app(NotificationDispatcher::class)->toUser(
                    $user,
                    "✅ เชื่อมต่อระบบ e-Doc กับ LINE สำเร็จ\nนับจากนี้คุณจะได้รับแจ้งเตือนเอกสาร การประชุม และใบลาที่เกี่ยวข้องกับคุณ"
                );

                return redirect()->route('profile.index')->with('success', 'เชื่อมต่อบัญชี LINE สำเร็จ! ระบบจะส่งแจ้งเตือนให้คุณทาง LINE นับจากนี้');
            }
        }

        return redirect()->route('profile.index')->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูลจาก LINE');
    }

    public function unlinkLine()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->line_id = null;
        $user->save();
        
        return back()->with('success', 'ยกเลิกการเชื่อมต่อ LINE เรียบร้อยแล้ว');
    }
}
