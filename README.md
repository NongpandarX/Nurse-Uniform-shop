# ร้านชุดพยาบาล มินลดา — ระบบจัดการสาขา สต็อก เช็คชื่อ และเช็คลิสต์ประจำวัน

โปรเจกต์นี้พัฒนาด้วย **Laravel 11 (PHP 8.2+) + MySQL 8 + Sanctum** ตามแบบสถาปัตยกรรม **MVC + Service Layer** ตามข้อกำหนดในเอกสาร `minlada-nurse-shop-system-spec-v2 (1).md`

คู่มือนี้จัดทำขึ้นเพื่อให้ผู้พัฒนาร่วม (คุณและเพื่อนร่วมทีม) สามารถติดตั้งและเริ่มต้นพัฒนาได้โดยตรง และเมื่อรันคำสั่ง Migration & Seed แล้ว ข้อมูลโครงสร้างตาราง (Database Schema) และชุดข้อมูลเริ่มต้น (Master Data, บัญชีผู้ใช้, สาขา, สินค้า, สต็อก) จะตรงกัน 100%

---

## 📋 สารบัญ
1. [สิ่งที่เตรียมไว้ให้ในโครงสร้างนี้](#1-สิ่งที่เตรียมไว้ให้ในโครงสร้างนี้)
2. [สิ่งที่ต้องติดตั้งก่อนเริ่ม (Prerequisites)](#2-สิ่งที่ต้องติดตั้งก่อนเริ่ม-prerequisites)
3. [ขั้นตอนการติดตั้งโปรเจกต์แบบทีละก้าว (Installation Guide)](#3-ขั้นตอนการติดตั้งโปรเจกต์แบบทีละก้าว-installation-guide)
4. [ข้อมูลตั้งต้นในฐานข้อมูลหลังรัน Seed](#4-ข้อมูลตั้งต้นในฐานข้อมูลหลังรัน-seed)
5. [โครงสร้างระบบและ Middleware Chain](#5-โครงสร้างระบบและ-middleware-chain)
6. [การทดสอบระบบ (Running Tests)](#6-การทดสอบระบบ-running-tests)
7. [แนวทางการทำงานร่วมกันกับเพื่อน (Git Workflow & Tips)](#7-แนวทางการทำงานร่วมกันกับเพื่อน-git-workflow--tips)

---


---
## การรัน
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
---
## 1. สิ่งที่เตรียมไว้ให้ในโครงสร้างนี้

- **Database Migrations ครบ 20 ตาราง**: ตาม §6.2 ของ Spec (`branches`, `users`, `user_devices`, `attendances`, `attendance_attempts`, `products`, `product_variants`, `stock_levels`, `stock_movements`, `stock_counts`, `stock_count_items`, `checklist_templates`, `checklist_template_items`, `checklist_submissions`, `checklist_submission_items`, `media`, `daily_closings`, `app_notifications`, `settings`, `personal_access_tokens`)
- **PHP Enums ครบถ้วน**: `Role`, `Size`, `StockReason`, `AttendanceStatus`, `ChecklistType`, `ChecklistStatus`, `DevicePlatform`, `ProductCategory`
- **Eloquent Models & Relationships ครบ 18 Models**: พร้อม Trait `BranchScoped` (Global Scope คัดกรองข้อมูลสาขาอัตโนมัติสำหรับพนักงาน)
- **Middleware Chain ครบถ้วน (§4)**:
  - `active.user`: ปฏิเสธการเข้าถึงหากบัญชีผู้ใช้ถูกระงับ
  - `role`: จำกัดสิทธิ์ตามบทบาท (`admin`, `employee`)
  - `branch.scope`: บังคับและตรวจสอบการเข้าถึงเฉพาะสาขาตนเอง
  - `device.bound`: ตรวจสอบอุปกรณ์ที่ลงทะเบียนและผ่านการอนุมัติ
  - `shop.open`: ตรวจสอบวันหยุดทำการของแต่ละสาขา (เช่น เชียงใหม่ปิดวันอาทิตย์)
  - `geofence`: ตรวจสอบพิกัด GPS ระยะห่าง Haversine ≤ 50 ม., ค่า accuracy และตรวจจับ Mock location
- **Seeders ครบทุกส่วน (§1, §10, §6.2)**: สาขา 5 สาขา, ผู้ใช้เริ่มต้น 6 บัญชี, แบบสินค้า 24 แบบ + ไซส์ SSS–5XL + กางเกง normal/plus + อุปกรณ์เสริม 7 รายการ, สต็อกเริ่มต้น และแม่แบบเช็คลิสต์
- **Controllers & Services Skeleton**: โครงสร้าง Method และ Signature พร้อมใช้งานในเฟสถัดไป

---

## 2. สิ่งที่ต้องติดตั้งก่อนเริ่ม (Prerequisites)

เครื่องคอมพิวเตอร์ของคุณและเพื่อนต้องมีโปรแกรมดังต่อไปนี้:

1. **PHP >= 8.2** (แนะนำ PHP 8.2 หรือ 8.3)
   - เปิด Extension ใน `php.ini`: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `curl`, `zip`
2. **Composer (v2.x)**: [ดาวน์โหลดที่ getcomposer.org](https://getcomposer.org/)
3. **MySQL Server 8.0+** หรือ **MariaDB**
4. **Node.js (v18+) & npm** (มีติดตั้งอยู่แล้ว)

> 💡 **สำหรับ Windows:** แนะนำให้ใช้ **[Laragon](https://laragon.org/)** เพราะจะแถม PHP 8.x, Composer, MySQL, Nginx/Apache มาให้ครบในตัว หรือใช้ **XAMPP (PHP 8.2+)**

---

## 3. ขั้นตอนการติดตั้งโปรเจกต์แบบทีละก้าว (Installation Guide)

### ขั้นตอนที่ 1: ตรวจสอบความพร้อมของ PHP และ Composer
เปิด Terminal (PowerShell หรือ Command Prompt) แล้วพิมพ์:
```powershell
php -v
composer -v
```
*(หากยังไม่มี PHP หรือ Composer ให้ดาวน์โหลดติดตั้งหรือเพิ่ม Path ใน Environment Variables ก่อน)*

### ขั้นตอนที่ 2: ติดตั้ง Dependencies ของ Laravel
รันคำสั่งติดตั้งแพ็กเกจ:
```bash
composer install
```

### ขั้นตอนที่ 3: คัดลอกและตั้งค่าไฟล์ `.env`
คัดลอกไฟล์ `.env.example` เป็น `.env`:
```powershell
# บน Windows PowerShell:
Copy-Item .env.example .env

# หรือบน Git Bash / Command Prompt:
cp .env.example .env
```

### ขั้นตอนที่ 4: สร้าง Application Encryption Key
```bash
php artisan key:generate
```

### ขั้นตอนที่ 5: ตั้งค่าฐานข้อมูลใน `.env`
เปิดไฟล์ `.env` และตั้งค่าการเชื่อมต่อฐานข้อมูลของคุณ เช่น:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=minlada_nurse_shop
DB_USERNAME=root
DB_PASSWORD=
```
*(อย่าลืมสร้างฐานข้อมูลเปล่าชื่อ `minlada_nurse_shop` ใน MySQL/phpMyAdmin/HeidiSQL)*

### ขั้นตอนที่ 6: รัน Database Migration และ Seeder
รันคำสั่งสร้างตารางและใส่ข้อมูลตั้งต้นทั้งหมด:
```bash
php artisan migrate:fresh --seed
```
คำสั่งนี้จะสร้าง 20 ตารางและรัน Seeder ทุกตัว ทำให้ฐานข้อมูลของคุณกับเพื่อนตรงกันทันที!

### ขั้นตอนที่ 7: ทดสอบรันเซิร์ฟเวอร์
```bash
php artisan serve
```
เปิดเบราว์เซอร์ไปที่ `http://127.0.0.1:8000` จะเข้าสู่หน้า Login ของระบบ

---

## 4. ข้อมูลตั้งต้นในฐานข้อมูลหลังรัน Seed

### 👤 4.1 บัญชีผู้ใช้สำหรับทดสอบ (รหัสผ่านเริ่มต้น: `password123`)

| รหัสพนักงาน (`employee_code`) | บทบาท (`role`) | สาขา | สิทธิ์ |
|---|---|---|---|
| `ADMIN-001` | `admin` | ผู้ดูแลระบบกลาง (`null`) | จัดการทุกอย่าง ทุกสาขา ไม่ติด Geofence |
| `KKC-001` | `employee` | ขอนแก่น (ร้านหลัก) | จัดการสต็อก/เข้างานสาขาขอนแก่น |
| `NMA-001` | `employee` | โคราช | จัดการสต็อก/เข้างานสาขาโคราช |
| `UBP-001` | `employee` | อุบลราชธานี | จัดการสต็อก/เข้างานสาขาอุบลฯ |
| `CNX-001` | `employee` | เชียงใหม่ (1) นครพิงค์ | จัดการสต็อก/เข้างานสาขาเชียงใหม่ 1 |
| `CNX-002` | `employee` | เชียงใหม่ (2) | สาขายังไม่เปิด (`is_active = false`) |

---

### 🏢 4.2 ข้อมูลสาขา (`branches`)

| รหัส | ชื่อสาขา | เวลาเปิด-ปิด | วันหยุด | พิกัด Lat, Lng | รัศมี | สถานะ |
|---|---|---|---|---|---|---|
| `KKC` | ขอนแก่น (ร้านหลัก) | 09:00 - 18:00 | ไม่มี (เปิดทุกวัน) | `16.4390693, 102.8277712` | 50 ม. | เปิดใช้งาน |
| `NMA` | โคราช | 09:00 - 18:30 | ไม่มี (เปิดทุกวัน) | `14.9887613, 102.1046383` | 50 ม. | เปิดใช้งาน |
| `UBP` | อุบลราชธานี | 09:00 - 18:30 | ไม่มี (เปิดทุกวัน) | `15.2357186, 104.8677354` | 50 ม. | เปิดใช้งาน |
| `CNX1` | เชียงใหม่ (1) นครพิงค์ | 08:30 - 18:30 | อาทิตย์ (`[0]`) | `18.8536451, 98.9686832` | 50 ม. | เปิดใช้งาน |
| `CNX2` | เชียงใหม่ (2) | 08:30 - 18:30 | อาทิตย์ (`[0]`) | ยังไม่มี (`NULL`) | 50 ม. | รอเปิดสาขา |

---

### 👗 4.3 สินค้า Master Data (`products` & `product_variants`)
- **เสื้อพยาบาล 21 แบบ**: กรุงเทพ ยาว/สั้น, บัวมน, เทเลอมน, ปีกนก, เทเลอแหลม สั้น/ยาว, บัวป้าย วัฒนา, จีนแยก, คอปีน, ลานนา, จีนป้าย, จีนตรง, ริ้วฟ้าคอปก, กวนู่คอปก, พิธีการ
- **กระโปรง 2 แบบ**: ทรงเอ, ทรงตรง
- **กางเกง 1 แบบ**: แบ่งเป็นตัวเลือก `normal` และ `plus`
- **ไซส์มาตรฐาน**: `SSS`, `SS`, `S`, `M`, `L`, `XL`, `XXL`, `3XL`, `4XL`, `5XL`
- **อุปกรณ์เสริม 7 รายการ**: เข็มขัด, กระดุมแขนพิธีการ, กระดุมกระทรวง, เข็มพยาบาล, เข็มผู้ช่วย, หมวกผู้ช่วย, หมวกพยาบาล
- **เกณฑ์สต็อกขั้นต่ำ (`min_qty`)**:
  - เสื้อ/กระโปรง/กางเกงทุกไซส์: **7 ชิ้น**
  - อุปกรณ์เสริมและหมวก: **5 ชิ้น**
- **ยอดยกมาเริ่มต้น (สาขาขอนแก่น)**: มีการบันทึก `stock_movements` (reason: `initial`) ตามตัวเลขในใบสั่งชุด §10

---

### 📝 4.4 แม่แบบเช็คลิสต์ (`checklist_templates`)
1. **เช็คลิสต์เปิดร้าน (`opening`)**: ตรวจความพร้อมหน้าร้าน (ต้องถ่ายรูป), เปิดไฟ/แอร์, พนักงานแต่งกายเรียบร้อย
2. **เช็คลิสต์ปิดร้าน (`closing`)**: นำขยะไปทิ้ง (ต้องถ่ายรูป), ปิดไฟ/แอร์/ถอดปลั๊ก/ล็อกประตู, ตรวจความปลอดภัย
3. **เช็คลิสต์ความสะอาดประจำวัน (`cleanliness`)**: กวาดและถูพื้น (ต้องถ่ายรูป), เช็ดกระจก, จัดชุดตามไซส์, ตรวจป้ายราคา, ทำความสะอาดห้องลองชุด (ต้องถ่ายรูป)

---

### ⚙️ 4.5 ตั้งค่าระบบ (`settings`)
- `max_accuracy_m`: `40` (เมตร)
- `geofence_required_for_count`: `true`
- `geofence_required_for_checklist`: `true`
- `geofence_required_for_closing`: `true`
- `abnormal_drop_qty`: `10` (ชิ้น)
- `abnormal_drop_percent`: `50` (%)
- `media_retention_days`: `60` (วัน)
- `default_min_qty`: `7`
- `default_min_qty_accessory`: `5`

---

## 5. โครงสร้างระบบและ Middleware Chain

### 🛡️ Middleware Aliases (ลงทะเบียนใน `bootstrap/app.php`)

```php
$middleware->alias([
    'active.user'   => \App\Http\Middleware\EnsureActiveUser::class,
    'role'          => \App\Http\Middleware\EnsureRole::class,
    'branch.scope'  => \App\Http\Middleware\BranchScope::class,
    'device.bound'  => \App\Http\Middleware\EnsureDeviceBound::class,
    'shop.open'     => \App\Http\Middleware\EnsureShopOpen::class,
    'geofence'      => \App\Http\Middleware\EnsureWithinGeofence::class,
]);
```

- **`active.user`**: บัญชีต้อง `is_active = true` หากถูกปิดจะตอบกลับ 403
- **`role:admin` หรือ `role:employee`**: บังคับบทบาทผู้ใช้งาน
- **`branch.scope`**: ป้องกันพนักงานเข้าถึงหรือส่งข้อมูลข้ามสาขา
- **`device.bound`**: บังคับว่าต้องใช้เครื่องที่ได้รับการอนุมัติแล้วเท่านั้น (Admin ข้ามได้)
- **`shop.open`**: เช็คว่าวันนั้นสาขาเปิดหรือไม่ (วันอาทิตย์สาขาเชียงใหม่จะถูกบล็อก)
- **`geofence`**: คำนวณระยะห่างด้วย Haversine Formula ต้องห่างจากพิกัดสาขาไม่เกิน 50 ม. และไม่อนุญาต Mock location

### 🔒 กฎเหล็กของสต็อก:
> ⚠️ **ห้ามแก้ `stock_levels.qty` ตรง ๆ เด็ดขาด!**  
> การแก้ไขจำนวนสต็อกต้องผ่าน Method `StockService::change(...)` เท่านั้น เพื่อรับประกันว่ามีการ Lock Row (`lockForUpdate`), บันทึก Ledger (`stock_movements`), และยิง Event แจ้งเตือนของใกล้หมด/ลดผิดปกติ

---

## 6. การทดสอบระบบ (Running Tests)

เมื่อตั้งค่าเสร็จสิ้น สามารถรัน Feature Test เพื่อตรวจสอบความถูกต้องของระบบได้:
```bash
php artisan test
```

ชุดการทดสอบที่มีให้:
- `tests/Feature/AuthTest.php`: ตรวจสอบการ Login ของทั้ง 2 Role (`admin` และ `employee`)
- `tests/Feature/BranchScopeTest.php`: ตรวจสอบว่าพนักงานถูกจำกัดสิทธิ์เฉพาะสาขาตนเองอย่างสมบูรณ์

---

## 7. แนวทางการทำงานร่วมกันกับเพื่อน (Git Workflow & Tips)

เพื่อให้การทำงานเป็นคู่เป็นไปอย่างราบรื่น ไม่เกิดปัญหาข้อมูลชนกัน:

1. **อย่า Commit ไฟล์ `.env` ขึ้น Git**: แต่ละคนจะมีไฟล์ `.env` ที่เชื่อม Database เครื่องตัวเอง
2. **ห้ามแก้ไฟล์ Migration เก่าที่มีอยู่แล้ว**: หากต้องการเพิ่มฟิลด์หรือตาราง ให้สร้างไฟล์ Migration ใหม่ด้วยคำสั่ง `php artisan make:migration ...`
3. **เมื่อดึงโค้ดใหม่ (git pull)**: ให้รันคำสั่งอัปเดตเสมอ:
   ```bash
   composer install
   php artisan migrate
   ```
4. **หากต้องการรีเซ็ตข้อมูลให้ตรงกันใหม่ทั้งหมด**:
   ```bash
   php artisan migrate:fresh --seed
   ```
5. **แบ่งงานตามโครงสร้าง Phase ใน Spec (§12)**:
   - ตัวอย่าง: เพื่อนทำส่วน API เข้างาน/Geofence (Phase 2), คุณทำส่วนจัดการสินค้า/สต็อก (Phase 3) เนื่องจากไฟล์ Controller/Service ถูกแยกโครงสร้างไว้พร้อมแล้ว จะไม่ทับซ้อนกันแน่นอน!


---

## 8. การจัดการบัญชีพนักงาน (Admin Employee Management)

ระบบกำหนดให้ผู้ดูแลระบบ (Admin) สามารถจัดการบัญชีพนักงานประจำสาขาผ่านหน้าเว็บแอดมินได้ที่เส้นทาง `/admin/employees` โดยมีข้อกำหนดความปลอดภัยดังนี้:

1. **เพิ่มได้เฉพาะบทบาทพนักงาน (`role: employee`) เท่านั้น:**
   - ป้องกันการยกระดับสิทธิ์ โดยหน้าจัดการพนักงานจะไม่เปิดให้สร้างหรือเปลี่ยนบทบาทเป็น `admin` ผ่านฟอร์มนี้
2. **ต้องผูกกับสาขาประจำร้านเสมอ (`branch_id`):**
   - พนักงาน 1 คนต้องสังกัด 1 สาขา ตามข้อกำหนดในเอกสาร §2
3. **ฟังก์ชันการจัดการครบถ้วน:**
   - ดูรายชื่อพนักงานทั้งหมด กรองเฉพาะพนักงานประจำสาขา
   - เพิ่มพนักงานใหม่พร้อมกำหนดรหัสพนักงาน (`employee_code`), ชื่อ, เบอร์โทร และสาขา
   - แก้ไขข้อมูลส่วนตัวหรือโยกย้ายสาขาประจำร้าน
   - เปิด/ปิด (ระงับ) บัญชีพนักงาน (`toggleActive`)
   - รีเซ็ตรหัสผ่านพนักงาน (`resetPassword`)

---

## 9. ระบบเช็คชื่อเข้า-ออกงานด้วย GPS Geofence (Attendance System)

ระบบลงเวลาทำงานของพนักงานหน้าร้าน ถูกควบคุมด้วยพิกัดตำแหน่งจริงผ่าน `GeofenceService`:

1. **เงื่อนไขความปลอดภัยของ Geofence (§1, §4, §5.2):**
   - **รัศมี (Radius):** ต้องอยู่ห่างจากพิกัดสาขาไม่เกิน **50 เมตร** (คำนวณด้วยสูตร Haversine)
   - **ความแม่นยำ (Accuracy):** สัญญาณ GPS ต้องมีความคลาดเคลื่อนไม่เกิน **40 เมตร** (`max_accuracy_m`)
   - **ตรวจจับ Mock Location:** ปฏิเสธการเช็คชื่อทันทีหากตรวจพบการใช้แอปจำลองตำแหน่ง
   - **บันทึกประวัติความพยายาม:** ความพยายามที่อยู่นอกพื้นที่หรือพิกัดไม่ผ่าน จะถูกบันทึกประวัติลงตาราง `attendance_attempts`
2. **กฎเวลาเข้า-ออกงาน:**
   - **เข้างาน (Check-in):** สามารถลงเวลาได้ตั้งแต่ก่อนร้านเปิด 60 นาทีจนถึงเวลาเปิดร้าน หากเกินเวลาเปิด + Grace Period จะถูกบันทึกสถานะ "สาย" พร้อมส่ง Event `StaffLate` แจ้งเตือนผู้ดูแลระบบ
   - **ป้องกันการเข้าซ้ำ:** พนักงาน 1 คนสามารถเช็คชื่อเข้างานได้วันละ 1 ครั้ง หากกดซ้ำจะตอบกลับ `409 Conflict`
   - **ออกงาน (Check-out):** ต้องมีการเช็คชื่อเข้างานก่อนเสมอ หากออกก่อนเวลาปิดร้านต้องระบุเหตุผล (สถานะ: `early_leave`)
3. **หน้าทดสอบบนเบราว์เซอร์ (`/checkin`):**
   - ขอสิทธิ์และดึงพิกัด GPS จริงจากเซนเซอร์เครื่องผู้ใช้ผ่าน Browser Geolocation API
   - มีปุ่มเครื่องมือช่วยทดสอบสำหรับจำลองพิกัดสาขาให้ตรงกับจุดที่ยืนอยู่จริง
   - รองรับโหมดทดสอบบนคอมพิวเตอร์ (ผ่อนปรนค่า Accuracy Wi-Fi ชั่วคราวสำหรับการทดสอบบนเบราว์เซอร์ PC)

---

## 10. สรุปภาพรวมสถานะระบบและสิ่งที่ต้องทำในเฟสถัดไป (Project Status & Roadmap)

### ✅ สิ่งที่พัฒนาเสร็จเรียบร้อยแล้ว:
- [x] **โครงสร้างระบบหลัก (Architecture):** Laravel 11, SQLite/MySQL, Database Migrations 20 ตาราง, Enums, Models & Relationships
- [x] **Middleware Security Chain:** `active.user`, `role`, `branch.scope`, `device.bound`, `shop.open`, `geofence`
- [x] **ระบบแจ้งเตือนสต็อกสินค้า (Low Stock System):**
  - Event & Listeners: สินค้าต่ำกว่าเกณฑ์ (`min_qty`), สินค้าหมดสต็อก, สต็อกลดผิดปกติ
  - Artisan Command: `php artisan stock:low-digest` สรุปของใกล้หมดประจำวันเวลา 09:00 น.
  - หน้าเว็บ Admin แสดงตารางสต็อกและการเคลื่อนไหว (Ledger)
- [x] **ระบบเช็คชื่อเข้า-ออกงาน (Attendance & Geofence):** Backend Service, คำนวณสาย, ตีขาดงานอัตโนมัติ, หน้าทดสอบ GPS จริง
- [x] **ระบบจัดการพนักงานสำหรับแอดมิน (Employee Management):** สร้างบัญชีเฉพาะพนักงาน, ย้ายสาขา, ระงับบัญชี, รีเซ็ตรหัสผ่าน

### 📌 สิ่งที่ต้องทำต่อในเฟสถัดไป (ตามเอกสาร System Spec):
1. **ระบบนับสต็อกสินค้าประจำวัน (Daily Stock Count - §5.3):**
   - เปิดรอบนับสต็อกรายวันอัตโนมัติ (Daily Draft)
   - บันทึกการนับทีละแถว (Autosave) และตรวจสอบส่วนต่างก่อนส่ง
2. **ระบบเช็คลิสต์ประจำวันและบีบอัดรูปภาพ (Daily Checklist & Media - §5.5, §5.6):**
   - ดึงแม่แบบเช็คลิสต์ (เปิดร้าน, ปิดร้าน, ความสะอาด)
   - ฟังก์ชันอัปโหลดรูปภาพหลักฐานและบีบอัดเป็น WebP (ด้านยาว $\le 1280$px, ขนาด $\le 300$ KB)
3. **ระบบแจ้งปิดร้านประจำวัน (Daily Closing - §5.4):**
   - ตรวจความพร้อม (ต้องนับสต็อกและส่งเช็คลิสต์ปิดร้านครบก่อน)
   - สรุปภาพรวมประจำวันและบันทึกลง `daily_closings`
4. **ระบบจัดการและอนุมัติเครื่องพนักงาน (Device Approval - §5.1):**
   - แอดมินตรวจสอบและอนุมัติเครื่องของพนักงาน (`user_devices`) เพื่อผูกเครื่องกับบัญชี
5. **ระบบแจ้งเตือนภายนอก (External Notification):**
   - เชื่อมต่อแจ้งเตือนผ่าน LINE Official Account (Messaging API) สำหรับแจ้งเตือนผู้บริหาร
