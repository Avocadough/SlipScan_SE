# SlipScan Service — Research Summary

> ระบบอ่านและตรวจสอบสลิปโอนเงินธนาคารด้วย OCR พร้อม Dashboard วิเคราะห์รายได้

---

## 1. ภาพรวมระบบ (System Overview)

จากกระดาน Product Backlog ระบบ **SlipScan** มีฟีเจอร์หลัก 3 กลุ่ม:

| กลุ่ม | ฟีเจอร์ |
|---|---|
| **OCR Core** | อ่านข้อมูลจากสลิป (TH/EN), แยกข้อมูลสำคัญ, แปลงเป็น JSON |
| **ตรวจสอบ** | API ตรวจสลิปจริง/ปลอม, ตรวจสอบสลิปซ้ำ, แจ้งเตือนสลิปปลอม |
| **Dashboard** | แสดงรายได้/ต้นทุน, Ranking ธนาคาร, กราฟแนวโน้ม, Export ข้อมูล |

---

## 2. OCR สำหรับสลิปธนาคารไทย

### 2.1 ความท้าทายของ OCR ภาษาไทย
- ภาษาไทยมีพยัญชนะ สระ และวรรณยุกต์ซ้อนกันหลายระดับในคำเดียว ทำให้ยากกว่าภาษาละตินมาก
- ข้อความในสลิปมีทั้งภาษาไทยและอังกฤษ รวมถึงตัวเลขอารบิกในรูปแบบผสม
- ภาพสลิปที่ได้รับมักเป็น screenshot จากมือถือ ซึ่งมี resolution และ contrast หลากหลาย

### 2.2 เปรียบเทียบ OCR Engine

| Engine | ความแม่นยำ (Thai) | จุดเด่น | ข้อด้อย |
|---|---|---|---|
| **Google Cloud Vision API** | ~84% (Thai doc) | ดีที่สุดสำหรับ multilingual, layout detection | ค่าใช้จ่ายสูง, ต้องส่งข้อมูลออก cloud |
| **Tesseract OCR** | ~47% (Thai doc) | Open-source, ฟรี, ปรับแต่งได้ | ความแม่นยำต่ำกว่าสำหรับ Thai |
| **iApp OCR API (TH)** | สูง (Thai-specific) | ออกแบบมาเฉพาะเอกสารไทย | API ของไทย, ต้องสมัคร key |
| **ABBYY FineReader** | 99.8% (general) | แม่นยำสูงมาก, ดีสำหรับ compliance | ราคาสูงที่สุด |
| **Azure AI OCR** | ดี | integrate กับ Microsoft ecosystem ได้ดี | ต้องใช้ Azure subscription |

**คำแนะนำ:** สำหรับสลิปธนาคารไทย ควรใช้ **Google Cloud Vision API (DOCUMENT_TEXT_DETECTION)** หรือ **iApp OCR API** เป็นหลัก โดย Google Vision รองรับ 200+ ภาษา และมี Free tier 1,000 units/เดือน

### 2.3 เทคนิคปรับปรุงความแม่นยำ OCR
- **Image Preprocessing:** Grayscale conversion → Adaptive Threshold → Median Filter → Sharpening
- **Segmentation:** ใช้ YOLOv8 หรือ U-Net เพื่อแบ่ง zone ของสลิป (header/body/footer)
- **Post-processing:** ใช้ Regex ดึงข้อมูลสำคัญ เช่น เลขบัญชี, จำนวนเงิน, วันที่, Ref No.
- **Language Hint:** ระบุ `["th", "en"]` ใน Google Vision API เพื่อเพิ่มความแม่นยำ

### 2.4 ข้อมูลที่ต้องสกัดจากสลิป (Key Fields)
```json
{
  "sender_name": "ชื่อผู้โอน",
  "bank_name": "ธนาคาร",
  "amount": 1000.00,
  "date": "2025-01-15",
  "time": "14:30:25",
  "ref_no": "REF20250115001",
  "receiver_name": "ชื่อผู้รับ",
  "receiver_account": "xxx-x-xxxxx-x"
}
```

---

## 3. การตรวจสอบสลิปปลอม (Fake Slip Detection)

### 3.1 ขนาดของปัญหา
- สลิปปลอมกลายเป็นภัยใหญ่ในไทย โดยเฉพาะกับร้านค้าออนไลน์และผู้รับโอนผ่าน PromptPay
- AI เช่น ChatGPT สามารถสร้างสลิปปลอมที่สมจริงได้ รวมถึงตัวเลข Ref No. ที่ดูน่าเชื่อถือ
- มีกรณีที่นำสลิป 23,000 บาทแก้ไขเป็น 400,000 บาทได้ในเวลาอันสั้น

### 3.2 ประเภทของสลิปปลอม
1. **Photoshop/Image Edit** — แก้ไขตัวเลขจำนวนเงิน ชื่อ หรือวันที่
2. **App สร้างสลิปปลอม** — แอปที่ออกแบบมาเพื่อสร้างสลิปหน้าตาเหมือนธนาคาร
3. **AI-generated** — ใช้ Generative AI สร้างสลิปใหม่ทั้งฉบับ
4. **Scheduled Transfer Screenshot** — ตั้งโอนล่วงหน้าแล้วถ่ายหน้า "รอโอน" มาแสดง

### 3.3 วิธีตรวจสอบสลิปปลอม

**วิธีที่ 1: QR Code Verification**
- สลิปธนาคารทุกใบมี QR Code ที่เข้ารหัสข้อมูลธุรกรรม
- Scan QR แล้วเปรียบเทียบกับข้อมูลที่แสดงบนสลิป
- ธนาคารกรุงไทยมี feature ใน NEXT App ให้ scan QR บนสลิปเพื่อตรวจสอบ

**วิธีที่ 2: Visual Inspection (ML-based)**
- ตรวจความสม่ำเสมอของ font, ขนาดตัวเลข, ระยะห่าง
- ตรวจ logo ธนาคารว่าชัดเจนหรือมีความผิดปกติ
- ตรวจ metadata ของไฟล์ภาพ (EXIF)

**วิธีที่ 3: API ธนาคาร (Best Practice)**
- บางธนาคารมี API สำหรับตรวจสอบธุรกรรมโดยตรง
- ใช้ Ref No. ที่ OCR อ่านได้ไป query ยืนยันกับ backend ธนาคาร
- ธนาคารไทยพาณิชย์, กสิกรไทย มี Payment Verification API สำหรับ merchant

**วิธีที่ 4: Duplicate Slip Detection**
- Hash ข้อมูลสลิป (ชื่อ + จำนวน + วันเวลา + Ref No.) เก็บใน database
- เปรียบเทียบทุกครั้งที่รับสลิปใหม่เพื่อป้องกันการส่งซ้ำ

### 3.4 Regulatory Context
- ธนาคารแห่งประเทศไทย (BOT) สั่งให้ธนาคารใช้ Biometric Authentication สำหรับโอนเกิน 50,000 บาท (2023)
- พระราชกฤษฎีกาใหม่ (2025) บังคับธนาคารใช้ Behavioral Analytics ตรวจจับการฉ้อโกง
- โปรแกรม NDID (National Digital ID) สำหรับ verify identity แบบ blockchain ในไทย

---

## 4. Architecture แนะนำ

### 4.1 Tech Stack
```
Frontend:   React.js / Next.js
Backend:    Node.js (Express) หรือ Python (FastAPI)
Database:   PostgreSQL (ข้อมูลสลิป) + Redis (cache/session)
OCR:        Google Cloud Vision API หรือ iApp OCR API
File Store: AWS S3 / Google Cloud Storage
Auth:       JWT + bcrypt (Login/Authentication)
```

### 4.2 Flow การทำงาน OCR
```
[รับภาพสลิป] → [Preprocess Image] → [OCR API] 
    → [Parse/Extract Fields] → [แปลงเป็น JSON] 
    → [Validate Data] → [Save to DB]
```

### 4.3 Flow การตรวจสลิปปลอม
```
[รับสลิป] → [QR Decode] → [เปรียบเทียบกับ OCR Data]
    → [ตรวจ Duplicate Hash] → [Visual Analysis (optional)]
    → [ส่ง Alert หากสงสัย]
```

### 4.4 Database Schema (ตัวอย่าง)
```sql
-- ตาราง slips
CREATE TABLE slips (
  id          SERIAL PRIMARY KEY,
  image_path  TEXT,
  sender_name TEXT,
  bank_name   TEXT,
  amount      DECIMAL(15,2),
  slip_date   TIMESTAMP,
  ref_no      TEXT UNIQUE,
  is_fake     BOOLEAN DEFAULT FALSE,
  raw_ocr     JSONB,
  created_at  TIMESTAMP DEFAULT NOW()
);

-- ตาราง slip_hashes (ตรวจสลิปซ้ำ)
CREATE TABLE slip_hashes (
  id        SERIAL PRIMARY KEY,
  slip_id   INT REFERENCES slips(id),
  hash      TEXT UNIQUE,
  created_at TIMESTAMP DEFAULT NOW()
);
```

---

## 5. Dashboard & Analytics

### 5.1 ฟีเจอร์ Dashboard ที่ต้องพัฒนา
- **รายได้รวม:** แสดงยอดเงินรวมของวัน/สัปดาห์/เดือน
- **Ranking ธนาคาร:** ธนาคารไหนมียอดโอนมาสูงสุด (Pie/Bar Chart)
- **กราฟแนวโน้ม:** Line Chart แสดง revenue trend ตามช่วงเวลา
- **ตรวจสอบยอดขาด/เกิน:** เปรียบเทียบยอดที่รับกับที่คาดหวัง
- **Export:** CSV / Excel สำหรับการทำบัญชี

### 5.2 Library แนะนำ
- **Chart.js** หรือ **Recharts** (React) สำหรับ visualization
- **SheetJS (xlsx)** สำหรับ export Excel
- **Papa Parse** สำหรับ CSV

---

## 6. ข้อพิจารณาด้านความปลอดภัยและ PDPA

- เก็บภาพสลิปใน encrypted storage (AES-256)
- ไม่เก็บข้อมูลบัญชีธนาคารแบบ full number ใน database (mask บางส่วน)
- ใช้ HTTPS ทุก endpoint
- Log การเข้าถึงข้อมูลทุกครั้ง (Audit Trail)
- ปฏิบัติตาม PDPA (พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562)
- มี role-based access control สำหรับ admin vs. user

---

## 7. OCR API เปรียบเทียบค่าใช้จ่าย

| API | Free Tier | ราคาถัดไป |
|---|---|---|
| Google Cloud Vision | 1,000 units/เดือน | ~$1.50 per 1,000 units |
| iApp OCR (TH) | มี trial | ติดต่อบริษัท |
| Azure AI Vision | 5,000 transactions/เดือน | $1.00 per 1,000 |
| Tesseract | ฟรีทั้งหมด | $0 (self-hosted) |

**คำแนะนำต้นทุนต่ำ:** เริ่มด้วย Tesseract + fine-tuning สำหรับ prototype → เปลี่ยนเป็น Google Cloud Vision เมื่อต้องการ production accuracy

---

## 8. References

- Google Cloud Vision API Documentation — https://cloud.google.com/vision/docs/ocr
- iApp Thai OCR API — https://iapp.co.th/docs/thai-document-optical-character-recognition/receipt
- Thammarak et al. (2022): *Comparative analysis of Tesseract and Google Cloud Vision for Thai vehicle registration* — IJECE
- n8n workflow: Thai Bank Slip Data Extraction via OCR to Google Sheets — toolify.ai
- KGP: Fake Slip Detection Guide — kasikornglobalpayment.com
- Thailand Nation: *Vendors warned of possible fake transaction slips created by AI* (March 2025)
- BOT: Anti-fraud measures update, biometric authentication mandate (2023-2024)
