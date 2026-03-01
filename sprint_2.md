# 🚀 Sprint 2 — SlipScan Service
**ระยะเวลา:** Week 2 (7 วัน) | **เป้าหมาย:** Fake Slip Detection + Duplicate Check + Alert System

> **หลักการเลือกงาน Sprint 2:** ต่อยอดจาก Core Engine ที่ Sprint 1 สร้างไว้ โดยเพิ่มความสามารถ "ตรวจสอบ" ทุกรูปแบบ — สลิปปลอม, สลิปซ้ำ, QR Verification รวมถึงระบบแจ้งเตือน เพื่อให้ระบบมีคุณค่าทางธุรกิจจริงก่อนทำ Dashboard

---

## 🗺️ Sprint Overview

```
Sprint 1 (Week 1)          Sprint 2 (Week 2)           Sprint 3 (Week 3)           Sprint 4 (Week 4)
────────────────────        ────────────────────         ────────────────────         ────────────────────
✅ Setup & Foundation  →    🔄 Detection Features   →    Dashboard & Export      →    Polish & Production
✅ Database Design     →    🔄 Fake Slip API         →    Analytics & Charts      →    Performance
✅ Auth System         →    🔄 Duplicate Check       →    Search & Filter         →    Security Hardening
✅ OCR Core Engine     →    🔄 QR Verification       →    Export CSV/Excel        →    Deployment
✅ Upload API          →    🔄 Alert System          →    Full UI Design          →    Monitoring
✅ JSON Parser         →    🔄 Batch Detection       →    Pagination              →    User Feedback
```

---

## 📋 Backlog Items ที่อยู่ใน Sprint 2

| # | Task | Priority | Estimate |
|---|------|----------|----------|
| 1 | API ตรวจสลิปจริง/ปลอม (Visual + Metadata) | 🔴 Critical | 2 วัน |
| 2 | ระบบตรวจสอบสลิปซ้ำ (Duplicate Hash) | 🔴 Critical | 1 วัน |
| 3 | QR Code Decode + Verification | 🟡 High | 1 วัน |
| 4 | ระบบแจ้งเตือนสลิปปลอม (Alert) | 🟡 High | 1 วัน |
| 5 | Fake Slip Flag + Admin Review Flow | 🟡 High | 0.5 วัน |
| 6 | Integration Test + Edge Cases | 🟢 Medium | 1 วัน |
| 7 | API Documentation (Swagger/Postman) | 🟢 Medium | 0.5 วัน |

**รวม Estimate: ~7 วัน (1 สัปดาห์)**

---

## 🎯 Sprint Goal

> **"ระบบสามารถตรวจจับสลิปปลอมและสลิปซ้ำได้อัตโนมัติ พร้อมแจ้งเตือนผู้ใช้ทันทีเมื่อพบความผิดปกติ"**

เมื่อจบ Sprint 2 ระบบจะสามารถ:
- ✅ ตรวจสอบสลิปว่าน่าสงสัย/ปลอม ผ่าน Visual Analysis และ Metadata Check
- ✅ Decode QR Code บนสลิปและเปรียบเทียบกับข้อมูล OCR
- ✅ ตรวจสลิปซ้ำด้วย Hash และปฏิเสธอัตโนมัติ
- ✅ แจ้งเตือนผู้ใช้ผ่าน In-app Notification เมื่อพบสลิปน่าสงสัย
- ✅ Admin สามารถ review และ flag สลิปได้

---

## 📅 แผนงานรายวัน (Day-by-Day Plan)

### 📌 Day 1-2 — Fake Slip Detection Engine

**เป้าหมาย:** ระบบตรวจสอบความถูกต้องของสลิปหลายชั้น

**1.1 Database — เพิ่ม Column สำหรับ Detection**
```sql
ALTER TABLE slips ADD COLUMN detection_score  FLOAT DEFAULT NULL;
ALTER TABLE slips ADD COLUMN detection_flags  JSONB DEFAULT '[]';
ALTER TABLE slips ADD COLUMN reviewed_by      INT REFERENCES users(id);
ALTER TABLE slips ADD COLUMN reviewed_at      TIMESTAMP;

-- ตาราง alert log
CREATE TABLE slip_alerts (
  id          SERIAL PRIMARY KEY,
  slip_id     INT REFERENCES slips(id),
  alert_type  TEXT NOT NULL,   -- 'fake_suspected', 'duplicate', 'qr_mismatch'
  message     TEXT,
  is_read     BOOLEAN DEFAULT FALSE,
  created_at  TIMESTAMP DEFAULT NOW()
);
```

**1.2 Detection Layers (หลายชั้น)**

```
Layer 1: Metadata Check
    ↓ ตรวจ EXIF data — ภาพที่แก้ไขมักสูญเสีย EXIF หรือมี Software tag
    ↓ ตรวจขนาดไฟล์ผิดปกติ (สลิปจริงมักอยู่ที่ 100-800 KB)

Layer 2: Visual Consistency Check
    ↓ ตรวจ font ความสม่ำเสมอ (Sharp Pixel Edge Detection)
    ↓ ตรวจ logo ธนาคาร (ความคมชัด, ขนาด, ตำแหน่ง)
    ↓ ตรวจ background gradient ที่ผิดปกติ

Layer 3: Data Consistency Check
    ↓ เปรียบเทียบ OCR data กับ QR Code data
    ↓ ตรวจว่าวันที่/เวลาสมเหตุสมผล (ไม่ใช่อนาคต)
    ↓ ตรวจรูปแบบ Ref No. ว่าตรงกับ pattern ของธนาคารนั้น

Layer 4: Duplicate Detection
    ↓ Hash check กับ database
```

**1.3 Visual Consistency Check (Sharp/Jimp)**
```javascript
const Jimp = require('jimp');

async function analyzeSlipVisual(imagePath) {
  const image = await Jimp.read(imagePath);
  const flags = [];

  // ตรวจความละเอียด — สลิปปลอมมักมี resolution ต่ำผิดปกติ
  if (image.getWidth() < 300 || image.getHeight() < 500) {
    flags.push({ type: 'low_resolution', severity: 'medium' });
  }

  // ตรวจ color depth / noise pattern
  const colorVariance = computeColorVariance(image);
  if (colorVariance < THRESHOLD_VARIANCE) {
    flags.push({ type: 'low_color_variance', severity: 'low' });
  }

  return flags;
}
```

**1.4 EXIF Metadata Check**
```javascript
const ExifReader = require('exifreader');

async function checkImageMetadata(imagePath) {
  const tags = ExifReader.load(fs.readFileSync(imagePath));
  const flags = [];

  // สลิปปลอมมักถูกสร้างจาก Photoshop / image editor
  const software = tags['Software']?.description || '';
  const suspiciousSoftware = ['photoshop', 'gimp', 'paint', 'canva', 'picsart'];
  if (suspiciousSoftware.some(s => software.toLowerCase().includes(s))) {
    flags.push({ type: 'edited_software', detail: software, severity: 'high' });
  }

  // ตรวจ DateTime ว่า match กับข้อมูล OCR ไหม
  if (tags['DateTimeOriginal']) {
    flags.push({ type: 'has_original_datetime', detail: tags['DateTimeOriginal'].description });
  }

  return flags;
}
```

**1.5 Data Consistency Validator**
```javascript
function validateDataConsistency(ocrData) {
  const flags = [];

  // ตรวจวันที่ไม่ใช่อนาคต
  const slipDate = new Date(ocrData.slip_date);
  if (slipDate > new Date()) {
    flags.push({ type: 'future_date', severity: 'high' });
  }

  // ตรวจ amount ว่าสมเหตุสมผล
  if (ocrData.amount <= 0 || ocrData.amount > 10_000_000) {
    flags.push({ type: 'suspicious_amount', severity: 'medium' });
  }

  // ตรวจ Ref No. pattern ตามธนาคาร
  const refPatterns = {
    'กสิกรไทย': /^[A-Z]{3}\d{13}$/,
    'ไทยพาณิชย์': /^\d{15}$/,
    'กรุงไทย':  /^[A-Z0-9]{12,16}$/,
  };
  const pattern = refPatterns[ocrData.bank_name];
  if (pattern && ocrData.ref_no && !pattern.test(ocrData.ref_no)) {
    flags.push({ type: 'invalid_ref_format', severity: 'high' });
  }

  return flags;
}
```

**1.6 Detection Score Algorithm**
```javascript
function computeDetectionScore(allFlags) {
  const severityWeights = { high: 40, medium: 20, low: 5 };
  let score = 100; // เริ่มที่ 100 (น่าเชื่อถือมากที่สุด)

  for (const flag of allFlags) {
    score -= severityWeights[flag.severity] || 0;
  }

  return Math.max(0, score); // 0–100, ยิ่งต่ำยิ่งน่าสงสัย
}

// threshold:
// score >= 70  → ✅ Pass (likely real)
// score 40–69  → ⚠️  Suspicious (needs review)
// score < 40   → 🚨 Likely Fake
```

**1.7 API Endpoint**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/slips/detect` | ตรวจสลิปใหม่ (run all layers) |
| `GET`  | `/api/slips/:id/detection` | ดูผลการตรวจของสลิปที่มีอยู่แล้ว |
| `PATCH`| `/api/slips/:id/review` | Admin mark as confirmed fake/real |

**Response ตัวอย่าง:**
```json
{
  "slip_id": 42,
  "detection_score": 35,
  "verdict": "likely_fake",
  "flags": [
    { "type": "edited_software", "detail": "Adobe Photoshop", "severity": "high" },
    { "type": "future_date", "severity": "high" },
    { "type": "invalid_ref_format", "severity": "high" }
  ],
  "requires_review": true
}
```

**Definition of Done:**
- [ ] Detection รัน 4 layers ครบ
- [ ] คืน score 0–100 และ verdict ได้
- [ ] บันทึก flags ลง DB ใน JSONB column
- [ ] Test กับสลิปปลอมจาก test set ผ่าน >85%

---

### 📌 Day 3 — Duplicate Slip Detection

**เป้าหมาย:** ป้องกันการส่งสลิปเดิมซ้ำหลายครั้ง

**3.1 Hash Algorithm**
```javascript
const crypto = require('crypto');

function generateSlipHash(ocrData) {
  // Hash จาก field ที่ unique ที่สุด
  const hashInput = [
    ocrData.ref_no        || '',
    ocrData.amount        || '0',
    ocrData.slip_date     || '',
    ocrData.slip_time     || '',
    ocrData.sender_name   || '',
    ocrData.receiver_name || '',
  ].join('|').toLowerCase().replace(/\s+/g, '');

  return crypto.createHash('sha256').update(hashInput).digest('hex');
}
```

**3.2 Duplicate Check Flow**
```javascript
async function checkDuplicate(ocrData, userId) {
  const hash = generateSlipHash(ocrData);

  // ตรวจ global (ทุก user) — ป้องกันส่งสลิปเดิมข้ามบัญชี
  const existing = await db.query(
    'SELECT slip_id, created_at FROM slip_hashes WHERE hash = $1',
    [hash]
  );

  if (existing.rows.length > 0) {
    return {
      isDuplicate: true,
      originalSlipId: existing.rows[0].slip_id,
      firstSeenAt: existing.rows[0].created_at
    };
  }

  // ไม่ซ้ำ — บันทึก hash
  await db.query(
    'INSERT INTO slip_hashes (slip_id, hash) VALUES ($1, $2)',
    [/* slip_id จาก DB */, hash]
  );

  return { isDuplicate: false };
}
```

**3.3 Integration ใน Upload Flow**
```
POST /api/slips/upload
    ↓
[OCR + Parse Fields]   ← Sprint 1
    ↓
[generateSlipHash]     ← Sprint 2 ใหม่
    ↓
[checkDuplicate]
    ↓  ← ซ้ำ?
    ├── YES → Return 409 Conflict + originalSlipId + triggerAlert
    └── NO  → [runDetectionLayers] → Save to DB → Return 200
```

**Definition of Done:**
- [ ] ส่งสลิปเดิมซ้ำ → ได้รับ 409 พร้อม originalSlipId
- [ ] Hash บันทึกลง slip_hashes ทุกครั้ง
- [ ] ทดสอบ edge case: ข้อมูลเหมือนกันแต่ภาพต่างกัน → ยัง detect ได้

---

### 📌 Day 4 — QR Code Verification

**เป้าหมาย:** Decode QR บนสลิปและเปรียบเทียบกับข้อมูล OCR

**4.1 QR Decode**
```javascript
const Jimp = require('jimp');
const jsQR = require('jsqr');

async function decodeQRFromSlip(imagePath) {
  const image = await Jimp.read(imagePath);
  const { data, width, height } = image.bitmap;
  const code = jsQR(data, width, height);

  if (!code) return null;
  return code.data; // string ข้อมูลใน QR
}
```

**4.2 Thai Bank QR Payload Parser**
```javascript
// QR PromptPay / EMVCo format ตัวอย่าง:
// 000201010212...5802TH6304ABCD

function parseThaiQRPayload(qrString) {
  // แยก TLV (Tag-Length-Value) ตาม EMVCo spec
  const fields = {};
  let i = 0;
  while (i < qrString.length) {
    const tag = qrString.slice(i, i + 2);
    const len = parseInt(qrString.slice(i + 2, i + 4), 10);
    const val = qrString.slice(i + 4, i + 4 + len);
    fields[tag] = val;
    i += 4 + len;
  }
  return {
    amount:      fields['54'] ? parseFloat(fields['54']) : null,
    merchant_id: fields['26'] || fields['29'] || null,
    ref:         fields['62']?.slice(4) || null,
  };
}
```

**4.3 Cross-Validation OCR vs QR**
```javascript
function crossValidate(ocrData, qrData) {
  const mismatches = [];

  if (qrData.amount !== null && Math.abs(qrData.amount - ocrData.amount) > 0.01) {
    mismatches.push({
      field: 'amount',
      ocr_value: ocrData.amount,
      qr_value: qrData.amount,
      severity: 'high'
    });
  }

  if (qrData.ref && ocrData.ref_no && !ocrData.ref_no.includes(qrData.ref)) {
    mismatches.push({
      field: 'ref_no',
      ocr_value: ocrData.ref_no,
      qr_value: qrData.ref,
      severity: 'high'
    });
  }

  return mismatches;
}
```

**Definition of Done:**
- [ ] Decode QR จากสลิปได้ (กรณีมี QR)
- [ ] เปรียบเทียบ amount และ ref_no ระหว่าง OCR กับ QR ได้
- [ ] กรณี QR ไม่มีหรืออ่านไม่ได้ → ไม่ error, skip QR layer
- [ ] Mismatch → เพิ่ม flag severity high ใน detection result

---

### 📌 Day 5 — Alert System

**เป้าหมาย:** แจ้งเตือนผู้ใช้ทันทีเมื่อพบสลิปน่าสงสัย

**5.1 Alert Types**

| Alert Type | เงื่อนไข | ความรุนแรง |
|---|---|---|
| `fake_suspected` | detection_score < 40 | 🚨 Critical |
| `suspicious` | detection_score 40–69 | ⚠️ Warning |
| `duplicate` | พบ hash ซ้ำ | 🔁 Duplicate |
| `qr_mismatch` | QR ไม่ตรงกับ OCR | 🔴 High |

**5.2 Alert Service**
```javascript
async function triggerAlert(slipId, alertType, message) {
  // บันทึก alert ใน DB
  await db.query(
    `INSERT INTO slip_alerts (slip_id, alert_type, message)
     VALUES ($1, $2, $3)`,
    [slipId, alertType, message]
  );

  // อัพเดท slip flags
  await db.query(
    `UPDATE slips SET is_fake = $1 WHERE id = $2`,
    [alertType === 'fake_suspected', slipId]
  );
}
```

**5.3 In-App Notification API**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/api/alerts` | ดึง alerts ทั้งหมดของ user (unread first) |
| `PATCH`| `/api/alerts/:id/read` | Mark alert as read |
| `GET`  | `/api/alerts/count` | จำนวน unread alerts |

**5.4 Alert Response Format**
```json
{
  "alerts": [
    {
      "id": 7,
      "slip_id": 42,
      "alert_type": "fake_suspected",
      "message": "สลิปนี้น่าสงสัย: พบร่องรอยการแก้ไขจาก Adobe Photoshop และวันที่เป็นอนาคต",
      "is_read": false,
      "created_at": "2025-01-15T14:30:00Z"
    }
  ],
  "unread_count": 3
}
```

**Definition of Done:**
- [ ] Alert บันทึกลง DB เมื่อ detection score < 70
- [ ] GET /api/alerts คืนข้อมูลถูกต้อง
- [ ] Mark as read ทำงานได้
- [ ] Frontend แสดง badge จำนวน unread alerts

---

### 📌 Day 6 — Admin Review Flow + Integration

**เป้าหมาย:** Admin ตรวจสอบและ confirm สลิปน่าสงสัย + ทดสอบ flow ทั้งหมด

**6.1 Admin Endpoints**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/api/admin/slips/suspicious` | ดูสลิปทั้งหมดที่น่าสงสัย |
| `PATCH`| `/api/admin/slips/:id/review` | Confirm fake / Mark as safe |
| `GET`  | `/api/admin/stats` | สถิติ fake/real ratio |

**6.2 Review Payload**
```json
// PATCH /api/admin/slips/42/review
{
  "verdict": "confirmed_fake",   // "confirmed_fake" | "marked_safe"
  "note": "พบการแก้ไขตัวเลขจาก 1,500 เป็น 15,000"
}
```

**Definition of Done:**
- [ ] Admin สามารถ review สลิปน่าสงสัยได้
- [ ] Review log บันทึก reviewer และเวลา
- [ ] Integration test: upload → detect → alert → review flow ผ่านทั้งหมด

---

### 📌 Day 7 — Testing + API Documentation

**เป้าหมาย:** ทดสอบ edge cases และเขียน API docs

**Test Cases สำคัญ:**
- [ ] สลิปจริง → score >= 70, ไม่มี alert
- [ ] สลิปที่แก้ไขด้วย Photoshop → score < 40, มี alert
- [ ] ส่งสลิปเดิม 2 ครั้ง → ครั้งแรกผ่าน, ครั้งที่สอง 409
- [ ] QR ไม่ตรงกับจำนวนเงิน → flag qr_mismatch
- [ ] สลิปไม่มี QR → ผ่าน layer อื่น ๆ ต่อได้ปกติ
- [ ] ภาพเสีย/ไม่ใช่สลิป → graceful error

---

## 🧪 Definition of Done — Sprint 2

ถือว่า Sprint 2 เสร็จสมบูรณ์เมื่อ:

- [ ] **Detection** — รัน 4 layers และคืน score/verdict/flags ได้
- [ ] **Duplicate** — ตรวจสลิปซ้ำด้วย SHA-256 hash ได้, คืน 409 เมื่อซ้ำ
- [ ] **QR** — Decode QR และ cross-validate กับ OCR ได้ (optional ถ้า QR ไม่มี)
- [ ] **Alert** — บันทึกและคืน alert ใน API ได้, มี unread count
- [ ] **Admin** — Review endpoint ทำงาน, บันทึก reviewer + verdict
- [ ] **Integration** — Upload flow ครบ: OCR → Hash → Detect → Alert → DB
- [ ] **Docs** — Postman Collection หรือ Swagger ครบทุก endpoint ใหม่
- [ ] **Tests** — ผ่าน test cases สำคัญ 6 กรณี

---

## 🚫 Out of Scope (Sprint 2)

- ❌ Dashboard / Charts
- ❌ Export CSV / Excel
- ❌ ค้นหาและกรองสลิป
- ❌ Email / LINE notification (ทำได้ใน Sprint 4)
- ❌ ML model สำหรับ image forgery detection (ใช้ rule-based ก่อน)

---

## ⚠️ ความเสี่ยงและแนวทางรับมือ

| ความเสี่ยง | โอกาส | แนวทางรับมือ |
|---|---|---|
| QR บางรูปแบบอ่านไม่ได้ | สูง | skip QR layer gracefully, ไม่ให้ error ทั้ง request |
| EXIF ถูก strip โดยแอปมือถือ | สูง | EXIF เป็นแค่ 1 flag, ไม่ใช่ decisive |
| False positive (สลิปจริงถูกตรวจว่าปลอม) | กลาง | ปรับ threshold, เพิ่ม Admin review flow |
| Hash collision | ต่ำมาก | SHA-256 มี collision probability ต่ำมาก |

---

## 📁 โครงสร้าง Project (เพิ่มเติมจาก Sprint 1)

```
slipscan/backend/src/
├── services/
│   ├── detectionService.js    ← 🆕 4-layer fake detection
│   ├── duplicateService.js    ← 🆕 hash check
│   ├── qrService.js           ← 🆕 QR decode + validate
│   ├── alertService.js        ← 🆕 alert trigger + notify
│   ├── ocrService.js          ← Sprint 1
│   └── parserService.js       ← Sprint 1
├── routes/
│   ├── alerts.js              ← 🆕
│   ├── admin.js               ← 🆕
│   └── slips.js               ← อัพเดท
```

---

## 📊 Sprint Velocity ที่คาดหวัง

```
Day 1  ████████░░░░░░░  Detection Engine (Setup)    (55% done)
Day 2  ████████████░░░  Detection Engine (Complete)  (85% done)
Day 3  ████████████░░░  Duplicate Detection           (85% done)
Day 4  ████████████░░░  QR Verification               (80% done)
Day 5  ████████████░░░  Alert System                  (85% done)
Day 6  ████████████░░░  Admin Flow + Integration      (80% done)
Day 7  ████████████░░░  Testing + Docs                (90% done)
```

**Sprint Review:** Demo การส่งสลิปปลอม → ระบบตรวจจับได้ → แสดง alert ใน UI
