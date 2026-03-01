# SlipScan — ระบบตรวจสอบสลิปโอนเงินด้วย AI

ระบบ OCR อ่านสลิปธนาคารไทย พร้อม Authentication และ API สำหรับบันทึกข้อมูล

---

## 🛠️ Tech Stack

| Layer | Tool |
|---|---|
| Backend | PHP 8.0+ |
| Frontend | Bootstrap 5 |
| Database | MySQL 8.0 (database: `slipscan`) |
| OCR Service | Python 3 + Flask + Typhoon OCR |

---

## 📁 โครงสร้าง Project

```
SE4AI_Project/
├── backend/                  ← PHP backend API
│   ├── src/
│   │   ├── Config/
│   │   │   └── Database.php  ← MySQL PDO connection
│   │   ├── Controllers/
│   │   │   └── AuthController.php
│   │   ├── Middleware/
│   │   │   └── AuthGuard.php ← JWT middleware
│   │   └── Routes/
│   │       └── auth.php
│   ├── db/
│   │   └── migrate.sql       ← MySQL schema
│   ├── vendor/               ← Composer packages
│   ├── .env                  ← Environment config
│   ├── composer.json
│   └── index.php             ← Entry point
├── frontend/                 ← Bootstrap UI
│   └── login.html
├── ocr_service/              ← Python Flask OCR (Day 3-4)
│   ├── app.py
│   └── requirements.txt
└── Ocr.py                    ← OCR core engine
```

---

## 🚀 การติดตั้งและ Setup

### 1. Requirements

- PHP >= 8.0 + Composer
- MySQL 8.0
- Python 3.9+ (สำหรับ OCR service)
- [Typhoon OCR API Key](https://opentyphoon.ai)

---

### 2. Backend (PHP)

```bash
# เข้าไปที่ backend
cd backend

# ติดตั้ง PHP dependencies
composer install

# คัดลอก .env
cp .env.example .env
```

แก้ไข `.env` ให้ตรงกับ MySQL ของเครื่อง:

```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=your_password
DB_NAME=slipscan

JWT_SECRET=your_secret_key_here
JWT_EXPIRE=86400
```

---

### 3. Database Setup

```bash
# Import schema ไปยัง MySQL
mysql -u root -p < db/migrate.sql
```

หรือรันใน MySQL Workbench / CLI:

```sql
source /path/to/backend/db/migrate.sql;
```

Tables ที่จะถูกสร้าง:
- `users` — ข้อมูลผู้ใช้งาน
- `slips` — ข้อมูลสลิปจาก OCR
- `slip_hashes` — fingerprint สำหรับตรวจสอบซ้ำ (Sprint 2)

---

### 4. รัน PHP Server

```bash
cd backend
php -S localhost:8000 index.php
```

API พร้อมใช้งานที่ `http://localhost:8000`

---

### 5. OCR Service (Python)

```bash
# ติดตั้ง dependencies
pip install typhoon-ocr pillow opencv-python-headless

# ตั้งค่า API key (Windows)
$env:TYPHOON_OCR_API_KEY = "your_api_key_here"

# รัน OCR แบบ standalone
python Ocr.py slip.jpg --json
```

---

## 🤖 OCR Engine (`Ocr.py`)

ไฟล์หลักที่ทำงานได้ทันที ประกอบด้วย 3 คลาส:

| คลาส | หน้าที่ |
|---|---|
| `ImagePreprocessor` | ปรับคุณภาพภาพก่อน OCR (rotate, sharpen, contrast) |
| `TyphoonOCREngine` | เรียก Typhoon OCR API คืน raw text |
| `SlipParser` | Regex engine แยก field จาก raw text |
| `SlipOCR` | Main class รวมทุกอย่างในที่เดียว |

### การใช้งาน CLI

```bash
# แสดง raw text
python Ocr.py slip.jpg

# แสดง JSON บนหน้าจอ
python Ocr.py slip.jpg --json

# Export เป็นไฟล์
python Ocr.py slip.jpg --json --export output.json

# ใช้กับ self-hosted vllm
python Ocr.py slip.jpg --local --json
```

### การใช้งาน Python API

```python
from Ocr import SlipOCR, SlipParser

# OCR + Parse เป็น JSON ในครั้งเดียว
ocr = SlipOCR(auto_parse=True)
data = ocr.read("slip.jpg")

# OCR + Auto Export
ocr = SlipOCR(auto_parse=True, auto_export=True)
data = ocr.read("slip.jpg")                         # สร้าง slip.json อัตโนมัติ
data = ocr.read("slip.jpg", output_json="out.json") # ระบุ path เอง

# Export / Pretty Print แยก
parser = SlipParser()
parser.export_json(data, "output.json")
parser.pretty_print(data)
```

### Batch Processing

```python
from pathlib import Path
from Ocr import SlipOCR, SlipParser

ocr    = SlipOCR(auto_parse=True)
parser = SlipParser()

results = []
for slip_path in Path("slips/").glob("*.jpg"):
    try:
        data = ocr.read(str(slip_path))
        results.append(data)
        parser.export_json(data, f"outputs/{slip_path.stem}.json")
    except Exception as e:
        print(f"Failed: {slip_path.name} — {e}")

parser.export_json({"total": len(results), "slips": results}, "outputs/all.json")
```

### Fields ที่แยกได้

| Field | Pattern ที่รองรับ |
|---|---|
| `amount` | `1,234.56` · `฿1,000` · `THB 500` |
| `bank_name` | KBank · SCB · KTB · BBL · TTB · GSB · BAY · CIMB · UOB |
| `slip_date` | `15/01/2568` · `15-01-25` (แปลง พ.ศ. → ค.ศ. อัตโนมัติ) |
| `slip_time` | `14:30:25` · `14:30` · `2:30 PM` |
| `ref_no` | REF + ตัวอักษร/ตัวเลข 6–20 ตัว |
| `sender_name` | ชื่อก่อน/หลังคำว่า "จาก" |
| `receiver_name` | ชื่อก่อน/หลังคำว่า "ถึง"/"หาก" |
| `receiver_account` | Pattern `xxx-x-xxxxx-x` |

---

## 🔐 Auth API Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/auth/register` | สมัครสมาชิก | ❌ |
| `POST` | `/api/auth/login` | Login รับ JWT | ❌ |
| `POST` | `/api/auth/logout` | Logout | ❌ |
| `GET`  | `/api/auth/me` | ดูข้อมูล user ปัจจุบัน | ✅ JWT |

### ตัวอย่าง Register

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"Password123!"}'
```

### ตัวอย่าง Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"Password123!"}'
```

Response:
```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiJ9...",
  "user": { "id": 1, "email": "user@example.com", "role": "user" }
}
```

### ตัวอย่าง Protected Route

```bash
curl http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer <token>"
```

---

## 📊 OCR JSON Output Format

```json
{
  "sender_name": "นาย สมชาย ใจดี",
  "bank_name": "กสิกรไทย",
  "amount": 1500.00,
  "slip_date": "2025-01-15",
  "slip_time": "14:30:25",
  "ref_no": "REF20250115001",
  "receiver_name": "ร้านค้าออนไลน์",
  "receiver_account": "xxx-x-x1234-x",
  "raw_ocr": "..."
}
```

---

## 🔧 ธนาคารที่รองรับ

KBank · SCB · KTB · BBL · TTB · GSB · BAY · TBANK · CIMB · UOB

---

## 📚 เอกสารอ้างอิง

- [Typhoon OCR API](https://opentyphoon.ai)
- Sprint Plan: `sprint_1.md`, `sprint_2.md`, `sprint_3.md`
- Research: `research.md`
