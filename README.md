# SlipScan — ระบบตรวจสอบสลิปโอนเงินด้วย AI

ระบบ OCR อ่านสลิปธนาคารไทย พร้อม Authentication และ API สำหรับบันทึกข้อมูล

---

## 🛠️ Tech Stack

| Layer | Tool |
|---|---|
| Backend API | Python 3 + Flask (port 8000) |
| Frontend + OCR Service | Python 3 + Flask (port 5000) |
| Database | Supabase PostgreSQL (cloud) |
| OCR Engine | Typhoon OCR API |
| Auth | JWT (PyJWT + bcrypt) |

---

## 📁 โครงสร้าง Project

```
SlipScan_SE/
├── backend_flask/            ← Flask Backend API (Day 6)
│   ├── routes/
│   │   ├── auth.py           ← register, login, logout, me
│   │   └── slips.py          ← upload, list, get slip
│   ├── db/
│   │   ├── migrate.sql       ← PostgreSQL schema (รันใน Supabase SQL Editor)
│   │   └── seed.py           ← สร้าง test users
│   ├── app.py                ← Entry point (port 8000)
│   ├── config.py             ← Supabase DB connection
│   ├── auth_guard.py         ← JWT middleware
│   ├── .env                  ← (ไม่ถูก commit) ดู .env.example
│   ├── .env.example          ← template config
│   └── requirements.txt
├── ocr_service/              ← Flask OCR + Frontend server (port 5000)
│   ├── app.py
│   ├── .env                  ← (ไม่ถูก commit) ดู .env.example
│   └── requirements.txt
├── frontend/                 ← Bootstrap UI (served โดย ocr_service)
│   ├── login.html
│   ├── register.html
│   └── upload.html
├── Ocr.py                    ← OCR core engine
├── start.bat                 ← รัน services ทั้งหมดในครั้งเดียว (Windows)
└── venv/                     ← Python virtual environment
```

---

## 🚀 Setup Guide สำหรับ developer ใหม่

### 1. Requirements

- Python 3.10+
- [Typhoon OCR API Key](https://opentyphoon.ai)
- [Thunder Solution API Key](https://api.thunder.in.th/) สำหรับตรวจสอบสลิปปลอม
- [Supabase](https://supabase.com) account (free tier ได้)

---

### 2. Clone & Virtual Environment

```bash
git clone <repo-url>
cd SlipScan_SE

# สร้าง virtual environment
python -m venv venv

# Activate (Windows)
venv\Scripts\activate

# ติดตั้ง dependencies ทั้งหมด
pip install -r requirements.txt
pip install -r backend_flask/requirements.txt
pip install -r ocr_service/requirements.txt
```

---

### 3. ตั้งค่า Supabase Database

#### 3.1 สร้าง Supabase Project

1. ไปที่ [supabase.com](https://supabase.com) → สร้าง project ใหม่
2. เลือก Region: **Asia-Pacific (Singapore)**
3. จด **Database Password** ไว้

#### 3.2 Migrate Schema

1. ไปที่ **Supabase Dashboard → SQL Editor**
2. Copy เนื้อหาจาก `backend_flask/db/migrate.sql` แล้ว paste แล้วกด Run
3. จะได้ 3 tables: `users`, `slips`, `slip_hashes`

#### 3.3 หา Connection String

1. ไปที่ **Project Settings → Database → Connect**
2. เลือก **Method: Session Pooler** (รองรับ IPv4 ทั่วไป)
3. Copy URI ที่ได้

---

### 4. ตั้งค่า Environment Files

#### `backend_flask/.env`

```bash
cp backend_flask/.env.example backend_flask/.env
```

แก้ไข `backend_flask/.env`:

```env
DATABASE_URL=postgresql://postgres.[project-ref]:[YOUR-PASSWORD]@aws-0-ap-southeast-1.pooler.supabase.com:5432/postgres
JWT_SECRET=ใส่_random_string_ยาวๆ_ที่นี่
JWT_EXPIRE=86400
BACKEND_PORT=8000
APP_DEBUG=false
OCR_SERVICE_URL=http://localhost:5000/ocr
THUNDER_API_KEY=ใส่_KEY_ของ_THUNDER_SOLUTION_ที่นี่
```

> ⚠️ **ข้อควรระวังอย่างยิ่ง:** ห้ามนำ API Key ของจริง (ทั้งเบอร์, token, หรือคีย์ต่างๆ) มาวางลงในโค้ดหรือไฟล์ที่ถูกอัพขึ้น Git เด็ดขาด! ให้ใส่ผ่านไฟล์ `.env` ที่ไม่อยู่ใน Git เท่านั้น

#### `ocr_service/.env`

```bash
cp ocr_service/.env.example ocr_service/.env
```

แก้ไข `ocr_service/.env`:

```env
TYPHOON_OCR_API_KEY=sk-xxxxxxxxxxxxxxxxxxxx
OCR_SERVICE_PORT=5000
OCR_MAX_FILE_SIZE_MB=10
THUNDER_API_KEY=ใส่_KEY_ของ_THUNDER_SOLUTION_ที่นี่
```

> ขอ API Key สำหรับ OCR ได้ที่ [opentyphoon.ai](https://opentyphoon.ai)
> ขอ API Key สำหรับตรวจสอบสลิปได้ที่ [Thunder Solution](https://api.thunder.in.th/)
>
> ⚠️ **ข้อควรระวังอย่างยิ่ง:** ห้ามนำภาพ API Key หรือข้อความ API Key ของจริงมาแนบหรือระบุลงใน Source Code คอลัมน์ที่ถูก Commit เด็ดขาด

---

### 5. Seed ข้อมูล Test Users

```bash
venv\Scripts\python.exe backend_flask\db\seed.py
```

จะได้ test accounts:
| Email | Password | Role |
|---|---|---|
| admin@slipscan.test | admin1234 | admin |
| user@slipscan.test | user1234 | user |

---

### 6. รัน Services

#### วิธีที่ 1 — ดับเบิ้ลคลิก `start.bat` (ง่ายสุด)

ระบบจะเปิด 2 terminal อัตโนมัติ

#### วิธีที่ 2 — รัน Manual (เปิด 2 terminal)

**Terminal 1 — OCR + Frontend (port 5000):**
```bash
venv\Scripts\python.exe ocr_service\app.py
```

**Terminal 2 — Backend API (port 8000):**
```bash
venv\Scripts\python.exe backend_flask\app.py
```

---

### 7. ทดสอบ

| URL | คำอธิบาย |
|---|---|
| http://localhost:5000 | หน้าเว็บ (Login) |
| http://localhost:8000/health | Backend health check |
| http://localhost:5000/health | OCR service health check |

---

## 🔐 API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/auth/register` | สมัครสมาชิก | ❌ |
| `POST` | `/api/auth/login` | Login รับ JWT | ❌ |
| `POST` | `/api/auth/logout` | Logout | ❌ |
| `GET`  | `/api/auth/me` | ดูข้อมูล user | ✅ JWT |
| `POST` | `/api/slips/upload` | อัปโหลดสลิป 1 ใบ | ✅ JWT |
| `POST` | `/api/slips/upload-batch` | อัปโหลดสลิปหลายใบ | ✅ JWT |
| `GET`  | `/api/slips` | ดูรายการสลิปทั้งหมด | ✅ JWT |
| `GET`  | `/api/slips/<id>` | ดูสลิปตาม ID | ✅ JWT |

---

## 🤖 OCR Engine (`Ocr.py`)

| คลาส | หน้าที่ |
|---|---|
| `ImagePreprocessor` | ปรับคุณภาพภาพก่อน OCR (rotate, sharpen, contrast) |
| `TyphoonOCREngine` | เรียก Typhoon OCR API คืน raw text |
| `SlipParser` | Regex engine แยก field จาก raw text |
| `SlipOCR` | Main class รวมทุกอย่างในที่เดียว |

### Fields ที่แยกได้

| Field | Pattern ที่รองรับ |
|---|---|
| `amount` | `1,234.56` · `฿1,000` · `THB 500` |
| `bank_name` | KBank · SCB · KTB · BBL · TTB · GSB · BAY · CIMB · UOB |
| `slip_date` | `15/01/2568` · `15-01-25` (แปลง พ.ศ. → ค.ศ. อัตโนมัติ) |
| `ref_no` | REF + ตัวอักษร/ตัวเลข 6–20 ตัว |

---

## 📝 Changelog

### Day 6 (Sprint 1) — วันนี้
- 🔄 **เปลี่ยน Backend** จาก PHP → **Python Flask**
- 🔄 **เปลี่ยน Database** จาก MySQL (local) → **Supabase PostgreSQL** (cloud)
- ✅ สร้าง `backend_flask/` พร้อม routes, config, auth, seed
- ✅ อัปเดต `start.bat` ให้รัน Flask backend แทน PHP
- ✅ แก้ไข frontend ให้ API_BASE ชี้ `localhost` แทน hardcode IP
- ✅ อัปเดต `.gitignore` ให้ครอบคลุม `.env` ทุกตัว

### Day 5 (Sprint 1)
- เพิ่ม Register page
- LAN access support
- Bootstrap Upload UI + Batch upload frontend

### Day 3-4 (Sprint 1)
- OCR Service (Flask) serve frontend HTML
- Typhoon OCR integration

---

## 📚 เอกสารอ้างอิง

- [Typhoon OCR API](https://opentyphoon.ai)
- [Supabase Docs](https://supabase.com/docs)
