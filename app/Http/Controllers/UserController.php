<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\AuditLogger;

class UserController extends Controller
{
    // ── กำหนดรายชื่อตำแหน่งหน้าที่ทั้งหมดใน อบต.พร่อน ──
    protected $positions = [
        'แอดมิน', 'นักวิชาการคอมพิวเตอร์',
        'นายก อบต.', 'รองนายก อบต.', 'เลขานุการนายก',
        'ปลัด อบต.', 'รองปลัด อบต.',
        'หัวหน้าสำนักปลัด อบต.', 'เจ้าพนักงานธุรการ', 'ผช.เจ้าพนักงานธุรการ', 'พนักงานขับรถยนต์', 'คนงาน', 'ภารโรง',
        'นักวิเคราะห์นโยบายและแผน', 'นักทรัพยากรบุคคล', 'ผช.เจ้าพนักงานป้องกันและบรรเทาสาธารณภัย', 'นักพัฒนาชุมชน',
        'ผู้อำนวยการกองคลัง', 'เจ้าพนักงานการเงินและบัญชี', 'ผช.เจ้าพนักงานการเงินและบัญชี', 'นักวิชาการจัดเก็บรายได้', 'ผช.เจ้าพนักงานจัดเก็บรายได้', 'เจ้าพนักงานพัสดุ', 'ผช.เจ้าพนักงานพัสดุ',
        'ผู้อำนวยการกองช่าง', 'นายช่างโยธา', 'ผช.นายช่างโยธา',
        'ผู้อำนวยการกองสาธารณสุขฯ', 'นักวิชาการสุขาภิบาล',
        'ผู้อำนวยการกองการศึกษาฯ', 'นักวิชาการศึกษา', 'ครู', 'ผช.ครูผู้ดูแลเด็ก', 'ผู้ดูแลเด็ก',
        'นักวิชาการตรวจสอบภายใน'
    ];

    public function index()
    {
        // ดึงรายชื่อผู้ใช้ที่ยังไม่ถูกลบ พร้อมโหลด Role มาแสดง
        $users = User::with('roles')->get();
        $roles = Role::all();
        return view('users.index', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        // 1. ดักจับอีเมลที่เคยถูกลบ (Soft Deleted)
        $trashedUser = User::onlyTrashed()->where('email', $request->email)->first();
        if ($trashedUser) {
            return back()->withInput()->with('restore_prompt', true)->with('restore_id', $trashedUser->id)
                ->with('error', "อีเมล {$request->email} นี้เคยถูกลบไปแล้ว คุณต้องการกู้คืนบัญชีเดิมหรือไม่?");
        }

        // 2. ตรวจสอบข้อมูลใหม่
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->whereNull('deleted_at')],
            'password' => 'required|string|min:6',
            'department' => 'required|string',
            'division' => 'required|string',
            'position' => ['required', 'string', Rule::in($this->positions)],
            'name_prefix' => 'required|in:นาย,นาง,นางสาว',
        ]);

        // 3. บันทึกข้อมูล
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'department' => $request->department,
            'division' => $request->division,
            'position' => $request->position,
            'name_prefix' => $request->name_prefix,
            'gender' => $request->name_prefix === 'นาย' ? 'male' : 'female',
        ]);

        // 4. จ่าย Role อัตโนมัติ
        $this->assignRolesToUser($user, $request->position);

        return redirect()->route('users.index')->with('success', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'name_prefix' => 'required|in:นาย,นาง,นางสาว',
            'role' => 'nullable|string'
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->name_prefix = $request->name_prefix;
        $user->gender = $request->name_prefix === 'นาย' ? 'male' : 'female';

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // อัปเดตสิทธิ์ (ถ้าเลือกใหม่ใน Modal ให้ใช้ตามนั้น ถ้าไม่เลือกให้เช็คตามตำแหน่งเดิม)
        if ($request->filled('role')) {
            $oldRoles = $user->getRoleNames()->values()->all();
            $user->syncRoles([$request->role]);
            app(AuditLogger::class)->log('user.roles_changed', $user, ['roles' => $oldRoles], [
                'roles' => $user->getRoleNames()->values()->all(),
            ]);
        }

        return redirect()->route('users.index')->with('success', 'อัปเดตข้อมูลเรียบร้อยแล้ว');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // ห้ามลบตัวเอง
        if (auth()->id() == $user->id) {
            return response()->json(['message' => 'ไม่สามารถลบบัญชีของตัวเองได้'], 403);
        }

        $user->delete(); // Soft Delete
        return response()->json(['message' => 'ระงับการใช้งานบัญชีเรียบร้อยแล้ว (ย้ายไปถังขยะ)']);
    }

    /**
     * 🌟 ฟังก์ชันกู้คืนบัญชี (Restore) 🌟
     */
    public function restore($id)
    {
        // ค้นหา User จากใน "ถังขยะ (Trashed)"
        $user = User::onlyTrashed()->findOrFail($id);
        
        // สั่งกู้คืนบัญชี
        $user->restore(); 

        return redirect()->route('users.index')->with('success', "กู้คืนบัญชีของ {$user->name} สำเร็จเรียบร้อยแล้ว");
    }

    /**
     * ── ฟังก์ชันช่วยเหลือ: จ่ายสิทธิ์ตามตำแหน่ง ──
     */
    private function assignRolesToUser($user, $pos)
    {
        $roles = [];

        if (in_array($pos, ['แอดมิน', 'นักวิชาการคอมพิวเตอร์'])) {
            $roles[] = 'super-admin';
        } elseif (in_array($pos, ['นายก อบต.', 'รองนายก อบต.', 'เลขานุการนายก'])) {
            $roles[] = 'executive';
        } elseif ($pos === 'ปลัด อบต.') {
            $roles[] = 'palad';
        } elseif ($pos === 'รองปลัด อบต.') {
            $roles[] = 'deputy-palad';
        } elseif (str_contains($pos, 'ผู้อำนวยการ') || $pos === 'หัวหน้าสำนักปลัด อบต.') {
            $roles[] = 'head';
        } elseif (str_contains($pos, 'การเงิน') || str_contains($pos, 'จัดเก็บรายได้')) {
            $roles[] = 'finance';
        } elseif (str_contains($pos, 'พัสดุ')) {
            $roles[] = 'parcel';
        } elseif ($pos === 'นักทรัพยากรบุคคล') {
            $roles[] = 'hr';
        } elseif ($pos === 'นักวิเคราะห์นโยบายและแผน') {
            $roles[] = 'analyst';
        } elseif (str_contains($pos, 'ช่างโยธา')) {
            $roles[] = 'engineer';
        } elseif ($pos === 'นักวิชาการศึกษา') {
            $roles[] = 'education';
        } elseif ($pos === 'ครู') {
            $roles[] = 'teacher';
        } elseif (in_array($pos, ['ผช.ครูผู้ดูแลเด็ก', 'ผู้ดูแลเด็ก'])) {
            $roles[] = 'childcare';
        } elseif ($pos === 'นักวิชาการสุขาภิบาล') {
            $roles[] = 'health';
        } elseif (str_contains($pos, 'ป้องกันและบรรเทาสาธารณภัย')) {
            $roles[] = 'disaster';
        } elseif ($pos === 'นักวิชาการตรวจสอบภายใน') {
            $roles[] = 'auditor';
        } elseif (in_array($pos, ['เจ้าพนักงานธุรการ', 'ผช.เจ้าพนักงานธุรการ'])) {
            $roles[] = ($user->department === 'สำนักงานปลัด') ? 'saraban' : 'officer';
        } elseif (in_array($pos, ['พนักงานขับรถยนต์', 'คนงาน', 'ภารโรง'])) {
            $roles[] = 'worker';
        } else {
            $roles[] = 'officer';
        }

        $user->syncRoles($roles);
        app(AuditLogger::class)->log('user.roles_changed', $user, [], ['roles' => $roles]);
    }

    // =========================================================================
    // 🌟 ฟังก์ชันสำหรับล้างรหัส PIN ให้ผู้ใช้งาน (Admin)
    // =========================================================================
    public function clearUserPin($id)
    {
        // ดึงข้อมูล User จากฐานข้อมูล (ใส่ \App\Models\User เพื่อป้องกันหาคลาสไม่เจอ)
        $user = \App\Models\User::findOrFail($id);

        // เช็คความปลอดภัย: ถ้า User ไม่ได้กดขอมา แอดมินจะลบไม่ได้ (ป้องกันแอดมินลบเอง)
        if (!$user->pin_reset_requested) {
            return back()->with('error', 'ไม่สามารถล้างรหัสได้ เนื่องจากผู้ใช้ไม่ได้ส่งคำขอมา!');
        }

        // ทำการล้างค่า PIN และเคลียร์สถานะคำขอให้กลับเป็นค่าเริ่มต้น
        $user->pin = null;
        $user->pin_reset_requested = false;
        $user->save();

        return back()->with('success', 'ล้างรหัส PIN ให้ผู้ใช้งาน '.$user->name.' เรียบร้อยแล้ว ระบบได้เปิดให้ผู้ใช้ตั้งรหัสใหม่ได้ทันที');
    }
}
