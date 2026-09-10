# Data lifecycle and soft-delete policy

นโยบายนี้ใช้กับฐานข้อมูล V2 และกำหนดพฤติกรรมของ parent/child ให้แน่นอนดังนี้

## เอกสารและใบลา

- `documents` และ `leave_requests` ใช้ Soft Delete โดย `deleted_at` เป็น Source of Truth ของการมองเห็นรายการ
- การ Soft Delete parent ไม่ลบ child ทันที ความสัมพันธ์ เช่น workflow, files, assignments และ number allocation ยังคงอยู่เพื่อให้กู้คืน parent ได้ครบ
- query ปกติต้องซ่อน parent ที่ถูกลบ ส่วนหน้ากู้คืนหรือ purge เท่านั้นที่ใช้ `withTrashed` / `onlyTrashed`
- การ restore parent ทำให้ child เดิมกลับมาใช้งานได้ โดยไม่สร้าง workflow หรือเลขเอกสารใหม่
- การลบถาวรทำได้เฉพาะ parent ที่ Soft Delete แล้วและพ้นระยะเก็บรักษา ผ่าน `DataLifecycleService` หรือคำสั่ง `edoc:purge-retention` เท่านั้น ห้ามลบตรงจาก controller
- ระยะเก็บรักษากำหนดด้วย `EDOC_RETENTION_MONTHS` (ค่าเริ่มต้น 3 เดือน) และควรรัน `--dry-run` ก่อน purge จริง

## เมื่อมีการลบถาวร

- operational child ที่ผูกด้วย `ON DELETE CASCADE` ถูกลบพร้อม parent เช่น document files, assignments, workflow instance/steps และ number allocation
- ไฟล์ธุรกิจของเอกสารถูกย้ายไป staging ก่อน transaction ลบฐานข้อมูล หาก transaction ล้มเหลวระบบย้ายไฟล์กลับ และลบไฟล์จาก storage หลัง transaction สำเร็จเท่านั้น
- `workflow_action_evidence` และ audit log เป็นหลักฐานแบบ append-only และต้องคงอยู่ แม้ parent/workflow ถูกลบถาวร โดย foreign key ไปยัง workflow ใช้ `SET NULL` และเก็บ resource/actor/action/signature snapshot ไว้ในแถวหลักฐาน
- ห้าม purge ลายเซ็นหลักฐานตามไฟล์เอกสาร เว้นแต่มีนโยบายกฎหมายเฉพาะและ migration ที่ตรวจสอบย้อนหลังได้

## ห้องและการจอง

- `rooms` ใช้ Soft Delete เพื่อหยุดใช้งานห้องโดยไม่ทำลายประวัติ
- `room_bookings` ไม่ใช้ Soft Delete; การยกเลิกให้เปลี่ยน `status` เป็น `CANCELED` พร้อมเวลาและผู้ดำเนินการ
- ประวัติการจองเก็บ `room_name_snapshot` จึงยังอ่านได้เมื่อห้องถูก Soft Delete

## การตรวจสอบการปฏิบัติตามนโยบาย

ก่อน deploy ให้รัน:

```bash
php artisan edoc:v2-schema --force
php artisan edoc:v2-validate
php artisan edoc:purge-retention --dry-run
```

การเปลี่ยน retention, cascade rule หรือประเภทข้อมูลที่เก็บเป็นหลักฐาน ต้องผ่าน migration และเพิ่ม automated test สำหรับ delete, restore และ purge ทุกครั้ง
