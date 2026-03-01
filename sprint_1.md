# 🚀 Sprint 1 — SlipScan Service
**ระยะเวลา:** Week 1 (7 วัน) | **เป้าหมาย:** วางรากฐาน Core Engine ให้พร้อมใช้งาน

> **หลักการเลือกงาน Sprint 1:** เน้น Foundation ที่ Sprint 2 และ 3 ต้องพึ่งพา ได้แก่ Database, Auth, OCR Pipeline และ API หลัก — ทุกอย่างต้องพร้อมก่อนที่จะทำ Dashboard หรือ Feature ซับซ้อนได้

---

## 🗺️ Sprint Overview

```
Sprint 1 (Week 1)          Sprint 2 (Week 2)           Sprint 3 (Week 3)
────────────────────        ────────────────────         ────────────────────
✅ Setup & Foundation  →    Detection Features      →    Dashboard & Export
✅ Database Design     →    Fake Slip API           →    Analytics & Charts
✅ Auth System         →    Duplicate Check         →    Search & Filter
✅ OCR Core Engine     →    Alert System            →    Export CSV/Excel
✅ Upload API          →    Batch Upload            →    Design / Polish
✅ JSON Parser         →    Dashboard (ข้อมูล)      →    Production Ready
```

---

## 📋 Backlog Items ที่อยู่ใน Sprint 1

| # | Task | Column เดิม | Priority | Estimate |
|---|------|------------|----------|----------|
| 1 | ออกแบบ Database | To Do | 🔴 Critical | 1 วัน |
| 2 | Setup ระบบ Login / Authentication | To Do | 🔴 Critical | 1 วัน |
| 3 | เชื่อมต่อ Database กับหน้าเว็บ | To Do | 🔴 Critical | 0.5 วัน |
| 4 | OCR อ่านข้อมูลจากสลิป (TH/EN) | Doing | 🔴 Critical | 2 วัน |
| 5 | แยกข้อมูลสำคัญจากสลิป | Doing | 🔴 Critical | 1 วัน |
| 6 | แปลงผลลัพธ์ OCR เป็น JSON | Doing | 🔴 Critical | 0.5 วัน |
| 7 | ระบบอัพสลิปหลายครูป | Product Backlog | 🟡 High | 1 วัน |

**รวม Estimate: ~7 วัน (1 สัปดาห์)**

---

## 🎯 Sprint Goal

> **"ผู้ใช้สามารถ Login เข้าระบบ, อัพโหลดรูปสลิป, และรับข้อมูล JSON กลับมาได้อย่างถูกต้อง"**

เมื่อจบ Sprint 1 ระบบจะสามารถ:
- ✅ Login / Logout ได้ด้วย JWT
- ✅ รับภาพสลิป 1 ใบหรือหลายใบพร้อมกัน
- ✅ ส่งภาพผ่าน OCR และแยก field สำคัญออกมา
- ✅ คืนผล JSON พร้อม field ครบถ้วน
- ✅ บันทึกข้อมูลลง Database

---

## 📅 แผนงานรายวัน (Day-by-Day Plan)

### 📌 Day 1 — Database Design + Project Setup

**เป้าหมาย:** โครงสร้าง project และ database พร้อมทั้งหมด

**งานที่ต้องทำ:**

**1.1 Project Initialization**
```bash
# Backend
mkdir slipscan-api && cd slipscan-api
npm init -y
npm install express pg jsonwebtoken bcrypt multer dotenv cors

# หรือ Python
pip install fastapi uvicorn sqlalchemy psycopg2 python-jose bcrypt python-multipart
```

**1.2 ออกแบบ Database Schema**
```sql
-- users table
CREATE TABLE users (
  id          SERIAL PRIMARY KEY,
  email       TEXT UNIQUE NOT NULL,
  password    TEXT NOT NULL,
  role        TEXT DEFAULT 'user',  -- 'admin' | 'user'
  created_at  TIMESTAMP DEFAULT NOW()
);

-- slips table
CREATE TABLE slips (
  id            SERIAL PRIMARY KEY,
  user_id       INT REFERENCES users(id),
  image_path    TEXT NOT NULL,
  sender_name   TEXT,
  bank_name     TEXT,
  amount        DECIMAL(15,2),
  slip_date     DATE,
  slip_time     TIME,
  ref_no        TEXT,
  receiver_name TEXT,
  receiver_acct TEXT,
  raw_ocr       JSONB,
  is_fake       BOOLEAN DEFAULT FALSE,
  is_duplicate  BOOLEAN DEFAULT FALSE,
  created_at    TIMESTAMP DEFAULT NOW()
);

-- slip_hashes table (ใช้ Sprint 2)
CREATE TABLE slip_hashes (
  id        SERIAL PRIMARY KEY,
  slip_id   INT REFERENCES slips(id),
  hash      TEXT UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT NOW()
);
```

**Definition of Done:**
- [x] Database รัน local ได้ (MySQL)
- [x] Tables ทั้งหมด migrate สำเร็จ
- [x] `.env` file พร้อม config DB connection
- [x] README project มีขั้นตอน setup

---

### 📌 Day 2 — Authentication System

**เป้าหมาย:** ระบบ Login / Register / JWT พร้อมใช้งาน

**2.1 API Endpoints ที่ต้องทำ**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/auth/register` | สมัครสมาชิกใหม่ |
| `POST` | `/api/auth/login` | Login รับ JWT token |
| `POST` | `/api/auth/logout` | Logout / invalidate token |
| `GET`  | `/api/auth/me` | ดูข้อมูล user ปัจจุบัน |

**2.2 ตัวอย่าง Response**
```json
// POST /api/auth/login
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "role": "user"
  }
}
```

**2.3 Security Requirements**
- Password hash ด้วย `bcrypt` (saltRounds: 12)
- JWT expire ใน 24 ชั่วโมง
- Middleware `authGuard` สำหรับ protect routes

**Definition of Done:**
- [x] Register / Login ทำงานได้
- [x] JWT token ถูก validate ใน protected routes
- [x] Password ไม่เก็บ plain text
- [x] Test ด้วย Postman / Thunder Client ผ่านทุก case

---

### 📌 Day 3-4 — OCR Core Engine (หัวใจหลักของระบบ)

**เป้าหมาย:** อ่านสลิปภาษาไทย/อังกฤษ และแยก field ออกมาได้แม่นยำ

**3.1 Image Upload + Preprocessing**
```javascript
// รับไฟล์ผ่าน multer
const upload = multer({
  dest: 'uploads/',
  limits: { fileSize: 10 * 1024 * 1024 }, // 10MB
  fileFilter: (req, file, cb) => {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    cb(null, allowed.includes(file.mimetype));
  }
});
```

**3.2 OCR Integration (Google Cloud Vision)**
```javascript
const vision = require('@google-cloud/vision');
const client = new vision.ImageAnnotatorClient();

async function extractTextFromSlip(imagePath) {
  const [result] = await client.documentTextDetection(imagePath);
  return result.fullTextAnnotation.text;
}
```

**3.3 Field Parser (Regex Engine)**
```javascript
function parseSlipFields(rawText) {
  return {
    amount:        extractAmount(rawText),       // ดึง 1,234.56 หรือ 1234.56
    bank_name:     extractBank(rawText),         // กสิกร, SCB, กรุงไทย ฯลฯ
    sender_name:   extractSenderName(rawText),
    receiver_name: extractReceiverName(rawText),
    slip_date:     extractDate(rawText),         // DD/MM/YYYY, YYYY-MM-DD
    slip_time:     extractTime(rawText),         // HH:MM:SS
    ref_no:        extractRefNo(rawText),        // ตัวเลข/ตัวอักษร 10-20 chars
  };
}

// ตัวอย่าง Regex
const AMOUNT_REGEX   = /(?:จำนวน|amount|฿|THB)?\s*([\d,]+\.?\d{0,2})/gi;
const DATE_REGEX     = /(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/g;
const REF_REGEX      = /(?:ref|อ้างอิง|หมายเลข)[.\s:]*([A-Z0-9]{6,20})/gi;
```

**3.4 Bank Name Dictionary (Thai Banks)**
```javascript
const BANK_PATTERNS = {
  'กสิกรไทย':  ['kbank', 'กสิกร', 'kasikorn'],
  'ไทยพาณิชย์': ['scb', 'ไทยพาณิชย์', 'siam commercial'],
  'กรุงไทย':   ['ktb', 'กรุงไทย', 'krungthai'],
  'กรุงเทพ':   ['bbl', 'กรุงเทพ', 'bangkok bank'],
  'ทหารไทยธนชาต': ['ttb', 'tmb', 'ทหารไทย', 'ธนชาต'],
  'ออมสิน':    ['gsb', 'ออมสิน', 'government savings'],
};
```

**Definition of Done:**
- [ ] อ่านสลิปจาก KBank, SCB, KTB ได้อย่างน้อย
- [ ] แยก amount, bank, date, ref_no ได้ถูกต้อง >80%
- [ ] คืน JSON format ครบ 7 fields
- [ ] จัดการ error กรณีภาพไม่ชัด หรืออ่านไม่ได้

---

### 📌 Day 4 — เชื่อมต่อ Database กับ API

**เป้าหมาย:** Flow ครบ: Upload → OCR → Parse → Save DB → Return JSON

**4.1 API Endpoint หลัก**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/slips/upload` | อัพโหลดสลิป 1 ใบ (auth required) |
| `POST` | `/api/slips/upload-batch` | อัพโหลดหลายใบพร้อมกัน |
| `GET`  | `/api/slips/:id` | ดูข้อมูลสลิปตาม ID |
| `GET`  | `/api/slips` | ดูรายการสลิปทั้งหมดของ user |

**4.2 Upload Flow**
```
POST /api/slips/upload
    ↓
[Validate JWT]
    ↓
[รับไฟล์ภาพ (multer)]
    ↓
[บันทึกไฟล์ → cloud storage / local]
    ↓
[เรียก OCR Engine]
    ↓
[Parse Fields จาก raw text]
    ↓
[Save ลง PostgreSQL]
    ↓
[Return JSON Response]
```

**4.3 Response Format**
```json
{
  "success": true,
  "slip_id": 42,
  "data": {
    "sender_name": "นาย สมชาย ใจดี",
    "bank_name": "กสิกรไทย",
    "amount": 1500.00,
    "slip_date": "2025-01-15",
    "slip_time": "14:30:25",
    "ref_no": "REF20250115001",
    "receiver_name": "ร้านค้าออนไลน์",
    "receiver_acct": "xxx-x-x1234-x"
  },
  "raw_ocr": "...",
  "warnings": []
}
```

**Definition of Done:**
- [ ] Upload 1 สลิป → บันทึก DB → คืน JSON ได้
- [ ] DB มีข้อมูลครบทุก field หลังจาก upload
- [ ] Error handling: file too large, unsupported format, OCR fail

---

### 📌 Day 5 — Batch Upload + Frontend Minimal UI

**เป้าหมาย:** รองรับการอัพโหลดหลายสลิป + UI พื้นฐานสำหรับทดสอบ

**5.1 Batch Upload Logic**
```javascript
// POST /api/slips/upload-batch
// รับ multipart/form-data: files[] (max 20 ไฟล์)

async function batchUpload(files) {
  const results = await Promise.allSettled(
    files.map(file => processSlip(file))
  );
  return {
    total:    files.length,
    success:  results.filter(r => r.status === 'fulfilled').length,
    failed:   results.filter(r => r.status === 'rejected').length,
    items:    results.map(r => r.status === 'fulfilled' ? r.value : { error: r.reason })
  };
}
```

**5.2 Design หน้าเว็บ (Minimal)**

สำหรับ Sprint 1 ทำ UI เบื้องต้นเพื่อทดสอบ ครอบคลุม:
- หน้า Login
- หน้า Upload สลิป (drag & drop + เลือกหลายไฟล์)
- แสดงผล JSON ที่ได้จาก OCR

**Definition of Done:**
- [ ] Batch upload ไม่เกิน 20 ไฟล์ได้
- [ ] แสดงสถานะ success/failed แต่ละไฟล์
- [ ] หน้า Login ทำงานได้
- [ ] หน้า Upload แสดงผล JSON ได้

---

## 🧪 Definition of Done — Sprint 1

ถือว่า Sprint 1 เสร็จสมบูรณ์เมื่อ:

- [ ] **Database** — migrate สำเร็จ, ทุก table ครบ
- [ ] **Auth** — Register/Login/JWT ทำงานได้, password encrypted
- [ ] **OCR** — อ่านสลิปไทยได้ถูกต้อง >80% จาก test set 10 ใบ
- [ ] **Parser** — แยก amount, date, bank, ref_no ครบ
- [ ] **API Upload** — POST 1 ใบ และ batch upload ทำงานได้
- [ ] **Database Save** — ข้อมูล OCR บันทึกลง DB ได้ครบ
- [ ] **JSON Response** — format ถูกต้อง ครบ 7 fields
- [ ] **Error Handling** — handle กรณี OCR ล้มเหลว, ไฟล์ผิดนามสกุล
- [ ] **Code Review** — ผ่าน review อย่างน้อย 1 คน

---

## 🚫 Out of Scope (Sprint 1)

สิ่งเหล่านี้จะทำใน Sprint 2–3 เท่านั้น:

- ❌ ตรวจสลิปปลอม / API ตรวจสอบ
- ❌ ตรวจสลิปซ้ำ (Duplicate Check)
- ❌ ระบบแจ้งเตือน
- ❌ Dashboard / Charts
- ❌ Export CSV / Excel
- ❌ ค้นหาและกรองสลิป
- ❌ Design หน้าเว็บแบบ full

---

## ⚠️ ความเสี่ยงและแนวทางรับมือ

| ความเสี่ยง | โอกาส | แนวทางรับมือ |
|---|---|---|
| OCR ความแม่นยำต่ำสำหรับบางธนาคาร | สูง | ทดสอบกับสลิปจริงหลายธนาคารตั้งแต่วันแรก |
| Google Vision API quota หมด | กลาง | เตรียม fallback เป็น Tesseract ไว้ |
| สลิปภาพไม่ชัด/เอียง | สูง | เพิ่ม preprocessing (rotate, sharpen) |
| เวลาไม่พอใน 1 สัปดาห์ | กลาง | ตัด Batch Upload ออกก่อน ทำ 1 ใบให้สมบูรณ์ก่อน |

---

## 📁 โครงสร้าง Project แนะนำ

```
slipscan/
├── backend/
│   ├── src/
│   │   ├── controllers/
│   │   │   ├── authController.js
│   │   │   └── slipController.js
│   │   ├── services/
│   │   │   ├── ocrService.js       ← OCR + Preprocessing
│   │   │   ├── parserService.js    ← Field Extraction
│   │   │   └── slipService.js      ← Business Logic
│   │   ├── middlewares/
│   │   │   └── authGuard.js
│   │   ├── models/
│   │   │   ├── User.js
│   │   │   └── Slip.js
│   │   ├── routes/
│   │   │   ├── auth.js
│   │   │   └── slips.js
│   │   └── db/
│   │       └── migrate.sql
│   ├── uploads/                    ← temp files
│   ├── .env
│   └── package.json
└── frontend/
    ├── src/
    │   ├── pages/
    │   │   ├── Login.jsx
    │   │   └── Upload.jsx
    │   └── components/
    └── package.json
```

---

## 📊 Sprint Velocity ที่คาดหวัง

```
Day 1  ████████████░░░  Database + Setup         (80% done)
Day 2  ████████████░░░  Auth System              (85% done)
Day 3  ████████░░░░░░░  OCR Engine               (50% done)
Day 4  ████████████░░░  OCR + DB Integration     (85% done)
Day 5  ████████████░░░  Batch Upload + UI        (80% done)
```

**Sprint Review:** วันสุดท้ายของ Week 1 — demo การ upload สลิปจริงและรับ JSON กลับมา
