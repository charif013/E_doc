<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
     * สำหรับอัปเดต PIN และลายเซ็น (LINE ID เปลี่ยนได้ผ่าน OAuth เท่านั้น)
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // ===== 1. อัปเดต PIN =====
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

        // ===== 2. อัปเดตลายเซ็น =====
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
        $redirectUri = config('services.line.redirect_uri');

        if (empty($clientId) || empty($redirectUri)) {
            return redirect()->route('profile.index')
                ->with('error', 'ผู้ดูแลระบบยังตั้งค่า LINE Login ไม่ครบ');
        }

        $state = Str::random(64);
        session()->put('line_oauth_state', $state);

        $url = 'https://access.line.me/oauth2/v2.1/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'profile openid',
            'bot_prompt' => 'aggressive',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect($url);
    }

    public function handleLineCallback(Request $request)
    {
        $expectedState = (string) session()->pull('line_oauth_state', '');
        if (!$request->filled('state') || $expectedState === '' || !hash_equals($expectedState, (string) $request->state)) {
            return redirect()->route('profile.index')->with('error', 'ไม่สามารถยืนยันคำขอเชื่อมต่อ LINE ได้ กรุณาลองใหม่');
        }

        // ถ้าผู้ใช้กดยกเลิกในหน้า LINE
        if ($request->has('error')) {
            return redirect()->route('profile.index')->with('error', 'คุณยกเลิกการเชื่อมต่อ LINE');
        }

        if (!$request->filled('code')) {
            return redirect()->route('profile.index')->with('error', 'LINE ไม่ได้ส่งรหัสยืนยันกลับมา กรุณาลองใหม่');
        }

        $code = $request->string('code')->toString();
        $clientId = config('services.line.login_channel_id');
        $clientSecret = config('services.line.login_secret');
        $redirectUri = (string) config('services.line.redirect_uri');

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            return redirect()->route('profile.index')->with('error', 'ผู้ดูแลระบบยังตั้งค่า LINE Login ไม่ครบ');
        }

        // 1. นำ Code ไปแลกเป็น Access Token จาก LINE
        try {
            $response = Http::asForm()->timeout(10)->post('https://api.line.me/oauth2/v2.1/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.index')->with('error', 'ไม่สามารถติดต่อ LINE ได้ กรุณาลองใหม่');
        }

        if (!$response->successful() || !$response->json('access_token')) {
            return redirect()->route('profile.index')->with('error', 'ไม่สามารถยืนยันตัวตนกับ LINE ได้ กรุณาลองใหม่');
        }

        $accessToken = (string) $response->json('access_token');

        try {
            $profileResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.line.me/v2/profile');
            $friendshipResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.line.me/friendship/v1/status');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.index')->with('error', 'ไม่สามารถตรวจสอบสถานะ LINE OA ได้ กรุณาลองใหม่');
        }

        $lineId = $profileResponse->json('userId');
        if (!$profileResponse->successful() || !is_string($lineId) || !preg_match('/^U[0-9a-f]{32}$/i', $lineId)) {
            return redirect()->route('profile.index')->with('error', 'ไม่สามารถอ่านข้อมูลบัญชี LINE ได้ กรุณาลองใหม่');
        }

        /** @var User $user */
        $user = Auth::user();
        $alreadyLinked = User::query()
            ->where('line_id', $lineId)
            ->whereKeyNot($user->getKey())
            ->exists();
        if ($alreadyLinked) {
            return redirect()->route('profile.index')->with('error', 'บัญชี LINE นี้เชื่อมกับผู้ใช้อื่นในระบบแล้ว');
        }

        $isFriend = $friendshipResponse->successful()
            && $friendshipResponse->json('friendFlag') === true;

        // บางระบบยังไม่ได้ผูก OA กับ LINE Login channel สมบูรณ์ ทำให้ Friendship API
        // ตอบ false ทั้งที่ผู้ใช้เพิ่ม OA แล้ว จึงยืนยันซ้ำด้วย Messaging API ของ OA เดียวกัน
        if (!$isFriend) {
            $isFriend = app(LineMessagingService::class)->canAccessUserProfile($lineId);
        }

        $user->forceFill([
            'line_id' => $lineId,
            'line_friend_status' => $isFriend,
            'line_connected_at' => now(),
            'line_followed_at' => $isFriend ? now() : null,
        ])->save();

        if (!$isFriend) {
            return redirect()->route('profile.index')->with(
                'warning',
                'เชื่อมบัญชี LINE แล้ว แต่ยังรับการแจ้งเตือนไม่ได้ กรุณาเพิ่ม LINE Official Account ของระบบเป็นเพื่อน'
            );
        }

        app(NotificationDispatcher::class)->toUser(
            $user,
            "✅ เชื่อมต่อระบบ e-Doc กับ LINE สำเร็จ\nนับจากนี้คุณจะได้รับแจ้งเตือนเอกสาร การประชุม และใบลาที่เกี่ยวข้องกับคุณ"
        );

        return redirect()->route('profile.index')->with('success', 'เชื่อมต่อ LINE และเปิดรับการแจ้งเตือนเรียบร้อยแล้ว');
    }

    public function refreshLineStatus()
    {
        /** @var User $user */
        $user = Auth::user();
        if (empty($user->line_id)) {
            return back()->with('error', 'กรุณาเชื่อมต่อบัญชี LINE ก่อนตรวจสอบสถานะ');
        }

        $isReachable = app(LineMessagingService::class)->canAccessUserProfile((string) $user->line_id);
        $user->forceFill([
            'line_friend_status' => $isReachable,
            'line_followed_at' => $isReachable ? now() : null,
        ])->save();

        if (!$isReachable) {
            return back()->with('warning', 'Bot ยังมองไม่เห็นบัญชีนี้ กรุณาเพิ่ม LINE OA ด้วยบัญชีเดียวกับที่ใช้เชื่อมต่อ');
        }

        app(NotificationDispatcher::class)->toUser(
            $user,
            "✅ ตรวจสอบการเชื่อมต่อ e-Doc สำเร็จ\nบัญชีของคุณพร้อมรับการแจ้งเตือนแล้ว"
        );

        return back()->with('success', 'ตรวจสอบสำเร็จ บัญชี LINE พร้อมรับการแจ้งเตือนแล้ว');
    }

    public function unlinkLine()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->forceFill([
            'line_id' => null,
            'line_friend_status' => false,
            'line_connected_at' => null,
            'line_followed_at' => null,
        ])->save();
        
        return back()->with('success', 'ยกเลิกการเชื่อมต่อ LINE เรียบร้อยแล้ว');
    }
}
