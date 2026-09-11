# e-Doc อบต.พร่อน

ระบบบริหารงานสารบรรณ การอนุมัติเอกสาร ใบลา และการจองห้องประชุม พัฒนาด้วย Laravel 12, PHP 8.4+, MySQL และ Vite

## ความสามารถหลัก

- หนังสือภายใน หนังสือรับเข้า และหนังสือส่งออก พร้อม dynamic approval route
- ลงนาม/ประทับลายเซ็นและสร้าง PDF
- สมุดคุมเลขตามปีงบประมาณ พร้อมป้องกันเลขซ้ำระดับฐานข้อมูล
- เอกสารลับ การขอสิทธิ์ PIN timeout และ audit trail
- ใบลาแบบหลายด่านจนถึงการออกเลข
- จองห้องประชุมพร้อมป้องกันช่วงเวลาซ้อน
- แจ้งเตือน LINE ผ่าน queue และจัดเก็บเอกสารจาก QR URL แบบ asynchronous
- บันทึกประวัติการเปลี่ยนแปลงสำหรับผู้ตรวจสอบ

## ติดตั้งสำหรับพัฒนา

ข้อกำหนด: PHP 8.4+, Composer, Node.js 22+, MySQL 8+ และส่วนขยาย PHP `mbstring`, `pdo_mysql`, `gd`, `fileinfo`

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan edoc:v2-schema --force
php artisan db:seed --force
php artisan storage:link
npm run build
```

ตั้งค่า database และ URL ใน `.env` แล้วเปิดระบบด้วยเว็บเซิร์ฟเวอร์ หรือใช้:

```bash
php artisan serve
php artisan queue:work database --queue=ocr --sleep=2 --tries=1 --timeout=720
php artisan queue:work database --queue=documents,notifications,default --sleep=2 --tries=3
```

ต้องเปิด queue worker เสมอใน production มิฉะนั้น OCR/AI, LINE และการจัดเก็บไฟล์จาก QR จะค้างอยู่ในตาราง `jobs`
ค่า `QUEUE_RETRY_AFTER` ต้องมากกว่า timeout สูงสุดของ job; ค่าแนะนำของโปรเจกต์คือ 900 วินาที

## ค่าบริการภายนอก

- `LINE_LOGIN_CHANNEL_ID`, `LINE_LOGIN_SECRET`, `LINE_BOT_TOKEN` สำหรับ LINE Login/ข้อความแจ้งเตือน
- `LINE_BOT_CHANNEL_SECRET` ใช้ตรวจลายเซ็น webhook และ `LINE_OFFICIAL_ACCOUNT_ID` คือ Basic ID เช่น `@example` สำหรับปุ่มเพิ่มเพื่อน
- เชื่อม LINE Login channel กับ LINE OA ภายใต้ Provider เดียวกัน เปิด webhook ที่ `POST /line/webhook` และตั้ง Callback URL เป็น `/line/callback`
- `TYPHOON_API_KEY` สำหรับ AI ช่วยร่างหนังสือ; เว้นว่างเพื่อปิด
- `PYTHON_COMMAND` หรือ `PYTHON_EXECUTABLE` สำหรับระบบสกัดข้อมูลเอกสาร

ห้าม commit `.env`, token, PIN, ลายเซ็น หรือไฟล์เอกสารจริงลง Git

ระบบปิด public registration แล้ว บัญชีผู้ใช้ปกติต้องสร้างโดย super-admin เท่านั้น
การ provision super-admin ครั้งแรกต้องตั้ง `EDOC_ADMIN_EMAIL` และ `EDOC_ADMIN_PASSWORD`
(อย่างน้อย 16 ตัวอักษร) ก่อน seed และนำสองค่านี้ออกจาก environment หลังสำเร็จ

ไฟล์เอกสารเก็บใน private disk `storage/app/documents` และเปิดผ่าน route ที่ตรวจ Policy/PIN เท่านั้น
สำหรับระบบเดิม ให้สำรองไฟล์ก่อน แล้วรันคำสั่งตรวจรายการและย้ายแบบตรวจ SHA-256 ใน maintenance window:

```bash
php artisan edoc:secure-document-storage
php artisan edoc:secure-document-storage --commit
```

## บทบาทและสิทธิ์

สิทธิ์ endpoint ถูกควบคุมด้วย route middleware และ Policies:

- `super-admin`: จัดการระบบทั้งหมด
- `executive`, `palad`, `deputy-palad`, `head`: ขั้นอนุมัติ/สั่งการตามบทบาท
- `saraban`: ทะเบียนรับส่งและออกเลข
- `hr`: ตรวจสอบใบลา
- บทบาทเจ้าหน้าที่เฉพาะกอง: สร้างและติดตามเอกสารของตน
- `auditor`: อ่าน audit log

Policy สำคัญอยู่ใน `app/Policies`; อย่าเพิ่ม endpoint ที่แก้สถานะโดยไม่เรียก Policy หรือมี role middleware

## Workflow โดยย่อ

เอกสาร: ผู้สร้าง → ผู้พิจารณาตาม dynamic route → สั่งการ/มอบหมาย → สารบรรณออกเลข

ใบลา: ผู้รับมอบงาน (ถ้ามี) → หัวหน้า → HR → สารบรรณออกเลข → ปลัด/รองปลัด → ผู้บริหาร

การออกเลขใช้ `number_sequences` และ `number_allocations` เป็นแหล่งข้อมูลจริงเพียงชุดเดียว
ส่วน `document_number_allocations` เป็น compatibility view แบบอ่านอย่างเดียว ห้ามเขียน
`doc_number`/`running_number` โดยตรงใน workflow ใหม่

## ทดสอบและตรวจคุณภาพ

การทดสอบใช้ SQLite in-memory จึงไม่แตะฐานข้อมูลใน `.env`:

```bash
php artisan test --do-not-cache-result
npm run build
vendor/bin/pint --test
```

GitHub Actions จะรัน test และ frontend build ทุก push/PR ชุดทดสอบสำคัญครอบคลุม Policy, เลขซ้ำ, การจองซ้อน, audit log, LINE และ external archive

## Queue และการแก้ปัญหา

```bash
php artisan queue:work database --queue=ocr --sleep=2 --tries=1 --timeout=720
php artisan queue:work database --queue=documents,notifications,default --sleep=2 --tries=3
php artisan queue:failed
php artisan queue:retry all
php artisan edoc:queue-health
```

ใช้ process supervisor ให้ queue worker restart อัตโนมัติหลัง deploy และรัน `php artisan queue:restart` หลังเปลี่ยนโค้ด
หากเครื่องมีเฉพาะ Laravel scheduler ให้ตั้ง `EDOC_RUN_SCHEDULED_QUEUE_WORKER=true` ระบบจะเปิด worker
ระยะสั้นทุกนาทีสำหรับ queue `documents,notifications,default` เพื่อไม่ให้งานค้างเงียบ ๆ

## Production operations และ monitoring

ไฟล์ตัวอย่างสำหรับ systemd, Supervisor และ health timer อยู่ใน [`deploy/README.md`](deploy/README.md)
ตรวจสุขภาพระบบแบบอ่านอย่างเดียวได้ด้วย:

```bash
php artisan edoc:production-health
php artisan edoc:production-health --json
```

คำสั่งคืน exit code ที่ไม่ใช่ศูนย์เมื่อเชื่อมฐานข้อมูลไม่ได้ มีงานค้างเกินกำหนด จำนวน failed jobs
ในชั่วโมงล่าสุดเกินค่า `EDOC_FAILED_JOBS_LAST_HOUR_MAX`, พื้นที่ว่างต่ำกว่า
`EDOC_MINIMUM_FREE_DISK_MB`, private storage เขียนไม่ได้ หรือเปิด debug ใน production

## Deployment checklist

1. สำรองฐานข้อมูลและ `storage/app/public`
2. เปิด maintenance mode: `php artisan down`
3. ติดตั้ง dependency ด้วย `composer install --no-dev --optimize-autoloader` และ `npm ci && npm run build`
4. รัน `composer audit --locked` และ `npm audit --audit-level=moderate` ซึ่งต้องไม่มี advisory ที่ไม่อนุมัติ
5. รัน `php artisan edoc:v2-schema --force` ห้ามใช้ `php artisan migrate` กับฐาน V2
6. รัน `php artisan edoc:secure-document-storage` แล้ว `php artisan edoc:secure-document-storage --commit`
7. รัน `php artisan optimize` และ `php artisan queue:restart`
8. ตรวจสิทธิ์เขียน `storage`/`bootstrap/cache`, queue worker, scheduler และ HTTPS
9. ปิด maintenance mode: `php artisan up`
10. smoke-test login, เปิดเอกสาร, อนุมัติ, ออกเลข, จองห้อง และตรวจ failed jobs

## Backup และกู้คืน

สำรองทุกวันอย่างน้อยสองส่วน: database dump และ `storage/app/public` เก็บแบบเข้ารหัสคนละเครื่อง/พื้นที่ และทดสอบ restore เป็นระยะ การกู้คืนต้องใช้ฐานข้อมูลกับ storage จากเวลาเดียวกันเพื่อให้ hash/path ของเอกสารตรงกัน

ก่อน restore ให้หยุด queue worker และนำระบบเข้า maintenance mode หลัง restore ให้รัน `php artisan edoc:v2-schema --force`, `php artisan storage:link`, ล้าง cache และตรวจไฟล์ตัวอย่างเทียบค่า SHA-256 ในระบบ

## ฐานข้อมูล V2

V2 ถูกพัฒนาในฐาน `e_docv2` และ connection `mysql_v2` แยกจากฐานเดิม ห้ามเปิด write flag ใน production จนกว่า validation และ smoke test จะผ่าน

```bash
php artisan edoc:v2-schema --force
php artisan edoc:v2-migrate
php artisan edoc:v2-migrate --commit
php artisan edoc:v2-repair-leave-workflows
php artisan edoc:v2-validate
```

รายละเอียด mapping อยู่ที่ `docs/database-v2-migration.md`, ขั้นตอนสลับระบบ/ย้อนกลับอยู่ที่ `docs/database-v2-cutover-runbook.md` และนโยบาย parent/child, restore, purge อยู่ที่ `docs/data-lifecycle-policy.md`

สถานะเครื่องปัจจุบัน: default connection ใช้ `e_docv2` แล้ว โดยเปิดทั้ง V2 reads และ writes;
ฐาน `edoc_db` คงไว้ผ่าน connection `mysql_legacy_readonly` สำหรับ validation และ rollback เท่านั้น

## Audit log

ผู้ดูแลเปิด “ประวัติการใช้งานระบบ” ที่ `/admin/audit-logs` ได้ Log เป็น append-only และปกปิด password, PIN, ลายเซ็น และเนื้อหาเอกสาร ห้ามแก้/ลบ log จากหน้าระบบ หากมีกฎระยะเวลาเก็บข้อมูลควรจัด archive แยกแทนการลบโดยไม่มีหลักฐาน
