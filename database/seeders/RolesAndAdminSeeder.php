<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndAdminSeeder extends Seeder
{
    public function run()
    {
        // 🌟 1. เคลียร์แคชของระบบสิทธิก่อนรัน
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==========================================================
        // 🌟 2. กำหนดรายการสิทธิการใช้งาน (Permissions) ทั้งหมด
        // ==========================================================
        $permissions = [
            // สิทธิระดับองค์กร / ผู้บริหาร
            'view_all_documents', 'approve_document', 'sign_document', 'reject_document',
            'assign_work', 'view_dashboard', 'view_all_reports', 'view_secret_documents',
            'print_document', 'download_document', 'filter_document', 'forward_workflow',
            'track_document', 'view_all_registers', 'view_running_number', 'check_document',
            
            // สิทธิงานธุรการและสารบรรณ
            'receive_register', 'send_register', 'issue_doc_number', 'edit_register',
            'control_running_number', 'cancel_register', 'search_document', 'scan_document',
            'upload_document', 'attach_document',
            
            // สิทธิการสร้างและจัดการเอกสารทั่วไป
            'create_document', 'edit_document', 'propose_document',
            
            // สิทธิเฉพาะด้าน / เฉพาะกอง
            'view_hr_document', // งานบุคคล
            'receive_incident', 'create_incident_report', 'send_internal_doc', 'view_disaster_document', // ป้องกันฯ
            'create_project_doc', 'view_community_document', // พัฒนาชุมชน
            'approve_division_doc', 'view_division_register', 'view_financial_report', // คลัง
            'create_tax_doc', 'send_warning_doc', 'view_revenue_doc', // จัดเก็บรายได้
            'create_supply_doc', 'view_procurement_doc', 'export_supply_report', // พัสดุ
            'view_construction_plan', 'view_boq', 'approve_construction', // ช่าง
            'track_complaint', 'create_inspection_report', 'attach_photo', // สาธารณสุข
            'view_edu_report', 'view_child_center_doc', 'record_child_activity', // การศึกษา
            
            // สิทธิการดูประกาศ
            'view_internal_announcement', 'view_circular_letter', 'view_general_announcement',
            
            // สิทธิตรวจสอบภายใน
            'view_system_log', 'export_audit_report', 'audit_register', 'retrospective_audit'
        ];

        // สร้าง Permissions เข้าฐานข้อมูล
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ==========================================================
        // 🌟 3. สร้าง Roles (ตำแหน่ง) รูปแบบใหม่และผูก Permissions
        // ==========================================================
        $rolePermissions = [
            
            'executive' => [ // นายก
                'view_all_documents', 'approve_document', 'sign_document', 'reject_document',
                'assign_work', 'view_dashboard', 'view_all_reports', 'view_secret_documents',
                'print_document', 'download_document'
            ],
            
            'palad' => [ // ปลัด
                'view_all_documents', 'filter_document', 'forward_workflow', 'approve_document',
                'sign_document', 'track_document', 'view_all_registers', 'view_running_number', 'view_all_reports'
            ],
            
            'deputy-palad' => [ // รองปลัด
                'view_all_documents', 'check_document', 'forward_workflow', 'track_document', 'view_all_reports'
            ],

            'saraban' => [ // สารบรรณกลาง
                'view_all_documents', 'receive_register', 'send_register', 'issue_doc_number',
                'edit_register', 'control_running_number', 'cancel_register', 'search_document',
                'print_document', 'view_all_reports', 'forward_workflow', 'scan_document', 'upload_document', 'attach_document'
            ],

            'head' => [ // ผอ.กอง / หัวหน้าส่วน
                'approve_division_doc', 'sign_document', 'view_division_register', 'view_all_reports', 'track_document', 'forward_workflow'
            ],

            'officer' => [ // เจ้าหน้าที่ทั่วไป
                'create_document', 'edit_document', 'propose_document', 'search_document', 'print_document', 'attach_document'
            ],

            'finance' => [ // การเงิน/บัญชี
                'create_document', 'receive_register', 'propose_document', 'attach_document', 'print_document', 'view_financial_report'
            ],

            'parcel' => [ // พัสดุ
                'create_supply_doc', 'view_procurement_doc', 'export_supply_report', 'attach_document', 'search_document'
            ],

            'hr' => [ // บุคคล
                'view_hr_document', 'create_document', 'propose_document', 'print_document', 'search_document'
            ],

            'analyst' => [ // นโยบายและแผน
                'create_document', 'edit_document', 'propose_document', 'track_document', 'download_document'
            ],

            'engineer' => [ // กองช่าง
                'create_project_doc', 'view_construction_plan', 'view_boq', 'attach_document', 'track_document', 'upload_document'
            ],

            'education' => [ // การศึกษา
                'create_project_doc', 'propose_document', 'view_edu_report', 'track_document'
            ],

            'health' => [ // สาธารณสุข
                'receive_incident', 'create_inspection_report', 'attach_photo', 'send_warning_doc', 'track_complaint'
            ],

            'disaster' => [ // ปภ.
                'receive_incident', 'create_incident_report', 'send_internal_doc', 'view_disaster_document'
            ],

            'auditor' => [ // ตรวจสอบภายใน
                'view_all_documents', 'view_system_log', 'export_audit_report', 'audit_register', 'retrospective_audit'
            ],

            'teacher' => [ // ครู
                'create_document', 'propose_document', 'view_child_center_doc', 'print_document'
            ],

            'childcare' => [ // ผู้ดูแลเด็ก
                'record_child_activity', 'attach_document', 'view_child_center_doc', 'print_document'
            ],

            'worker' => [ // คนงาน/พนักงานทั่วไป
                'view_general_announcement'
            ],

            'viewer' => [ // อ่านอย่างเดียว
                'view_general_announcement', 'view_internal_announcement'
            ]
        ];

        // วนลูปสร้าง Role และ ผูกสิทธิ
        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }

        // ==========================================================
        // 🌟 4. สร้างบัญชีแอดมินสูงสุด (Super Admin)
        // ==========================================================
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@edoc.com'], // <--- อีเมลสำหรับล็อกอินเข้าแอดมิน
            [
                'name' => 'ผู้ดูแลระบบ (Super Admin)',
                'password' => Hash::make('password123'), // <--- รหัสผ่านเริ่มต้น
                'position' => 'นักวิชาการคอมพิวเตอร์',
            ]
        );

        // ผูกตำแหน่ง Super Admin ให้กับ User นี้
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole($superAdminRole);
        }
    }
}