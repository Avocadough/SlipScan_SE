# 🚀 Sprint 4 — SlipScan Service
**ระยะเวลา:** Week 4 (7 วัน) | **เป้าหมาย:** Production Ready — Security, Performance, Deployment & Monitoring

> **หลักการเลือกงาน Sprint 4:** เมื่อทุก feature ครบแล้ว Sprint 4 คือการ "ทำให้พร้อมใช้จริง" — security hardening, performance tuning, CI/CD pipeline, deployment บน cloud และระบบ monitoring เพื่อให้มั่นใจว่าระบบเสถียรในการใช้งาน production

---

## 🗺️ Sprint Overview

```
Sprint 1 (Week 1)          Sprint 2 (Week 2)           Sprint 3 (Week 3)           Sprint 4 (Week 4)
────────────────────        ────────────────────         ────────────────────         ────────────────────
✅ Setup & Foundation  →    ✅ Detection Features   →    ✅ Dashboard & Export   →    🔄 Polish & Production
✅ Database Design     →    ✅ Fake Slip API         →    ✅ Analytics & Charts   →    🔄 Performance Tuning
✅ Auth System         →    ✅ Duplicate Check       →    ✅ Search & Filter      →    🔄 Security Hardening
✅ OCR Core Engine     →    ✅ QR Verification       →    ✅ Export CSV/Excel     →    🔄 CI/CD + Deployment
✅ Upload API          →    ✅ Alert System          →    ✅ Full UI Design       →    🔄 Monitoring & Logging
✅ JSON Parser         →    ✅ Batch Detection       →    ✅ Pagination           →    🔄 External Notifications
```

---

## 📋 Backlog Items ที่อยู่ใน Sprint 4

| # | Task | Priority | Estimate |
|---|------|----------|----------|
| 1 | Security Hardening (PDPA compliance, Rate Limit, Input Validation) | 🔴 Critical | 1 วัน |
| 2 | CI/CD Pipeline (GitHub Actions) | 🔴 Critical | 1 วัน |
| 3 | Docker + Cloud Deployment (Railway/Render/AWS) | 🔴 Critical | 1 วัน |
| 4 | Performance Optimization (Caching, Query, Image) | 🟡 High | 1 วัน |
| 5 | Logging + Monitoring (Error tracking, Uptime) | 🟡 High | 0.5 วัน |
| 6 | External Notifications (Email / LINE Notify) | 🟡 High | 0.5 วัน |
| 7 | End-to-End Testing | 🟢 Medium | 1 วัน |
| 8 | User Acceptance Testing + Bug Fix | 🟢 Medium | 0.5 วัน |
| 9 | Documentation + Handoff | 🟢 Medium | 0.5 วัน |

**รวม Estimate: ~7 วัน (1 สัปดาห์)**

---

## 🎯 Sprint Goal

> **"ระบบพร้อม deploy บน production, ปลอดภัยตาม PDPA, เสถียร, และ monitor ได้"**

เมื่อจบ Sprint 4 ระบบจะสามารถ:
- ✅ Deploy ขึ้น cloud server ได้ด้วย Docker
- ✅ มี CI/CD pipeline deploy อัตโนมัติเมื่อ merge PR
- ✅ ผ่าน security checklist ครบ (Rate limit, Input validation, PDPA)
- ✅ แจ้งเตือนผ่าน Email และ LINE Notify
- ✅ มี monitoring + error tracking พร้อม alert เมื่อ server ล่ม
- ✅ มี E2E test ครอบคลุม critical user journey

---

## 📅 แผนงานรายวัน (Day-by-Day Plan)

### 📌 Day 1 — Security Hardening + PDPA Compliance

**เป้าหมาย:** ระบบผ่าน security checklist ก่อน production

**1.1 Rate Limiting**
```javascript
const rateLimit = require('express-rate-limit');

// Global limiter
app.use(rateLimit({
  windowMs: 15 * 60 * 1000, // 15 นาที
  max: 100,
  message: { error: 'Too many requests, please try again later.' }
}));

// Strict limiter สำหรับ Auth endpoints
const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 10,   // 10 ครั้งต่อ 15 นาที
  skipSuccessfulRequests: true,
  message: { error: 'Too many login attempts.' }
});
app.use('/api/auth/login', authLimiter);

// OCR Upload limiter (ป้องกัน abuse)
const uploadLimiter = rateLimit({
  windowMs: 60 * 1000,  // 1 นาที
  max: 20,              // 20 ไฟล์/นาที
});
app.use('/api/slips/upload', uploadLimiter);
```

**1.2 Input Validation (Joi / Zod)**
```javascript
const Joi = require('joi');

const loginSchema = Joi.object({
  email:    Joi.string().email().required(),
  password: Joi.string().min(8).max(128).required(),
});

const uploadSchema = Joi.object({
  description: Joi.string().max(200).optional(),
});

function validate(schema) {
  return (req, res, next) => {
    const { error } = schema.validate(req.body);
    if (error) return res.status(400).json({ error: error.details[0].message });
    next();
  };
}
```

**1.3 Helmet + Security Headers**
```javascript
const helmet = require('helmet');

app.use(helmet());
app.use(helmet.contentSecurityPolicy({
  directives: {
    defaultSrc: ["'self'"],
    imgSrc:     ["'self'", "data:", "blob:"],
    scriptSrc:  ["'self'"],
  }
}));

// CORS configuration
app.use(cors({
  origin: process.env.ALLOWED_ORIGINS?.split(',') || ['http://localhost:3000'],
  credentials: true,
}));
```

**1.4 PDPA Compliance Checklist**

| ข้อกำหนด | วิธีปฏิบัติ | Status |
|---|---|---|
| Mask ข้อมูลบัญชี | เก็บ `xxx-x-x1234-x` แทน full number | [ ] |
| Encrypt ภาพสลิป | AES-256 ก่อน upload S3 | [ ] |
| HTTPS ทุก endpoint | SSL cert บน production | [ ] |
| Audit Log | บันทึกการ access ข้อมูลสำคัญ | [ ] |
| Data Retention Policy | ลบสลิปเก่ากว่า 2 ปีอัตโนมัติ | [ ] |
| Consent UI | แสดง consent ก่อน upload ครั้งแรก | [ ] |

**1.5 Audit Logging**
```javascript
// Middleware บันทึกทุก access ต่อข้อมูล sensitive
async function auditLog(req, res, next) {
  await db.query(
    `INSERT INTO audit_logs (user_id, action, resource, ip, user_agent, created_at)
     VALUES ($1, $2, $3, $4, $5, NOW())`,
    [
      req.user?.id,
      `${req.method} ${req.path}`,
      req.params.id || null,
      req.ip,
      req.headers['user-agent']
    ]
  );
  next();
}

// เพิ่ม table
CREATE TABLE audit_logs (
  id         SERIAL PRIMARY KEY,
  user_id    INT,
  action     TEXT NOT NULL,
  resource   TEXT,
  ip         TEXT,
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT NOW()
);
```

**Definition of Done:**
- [ ] Rate limit ทุก endpoint สำคัญ
- [ ] Helmet + security headers ใส่ครบ
- [ ] Validation ทุก request body
- [ ] Account masking ใน DB ครบ
- [ ] Audit log ทำงาน
- [ ] HTTPS ใช้งานได้บน staging

---

### 📌 Day 2 — CI/CD Pipeline

**เป้าหมาย:** Code push → test → build → deploy อัตโนมัติ

**2.1 GitHub Actions Workflow**
```yaml
# .github/workflows/deploy.yml
name: CI/CD Pipeline

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: testpass
          POSTGRES_DB: slipscan_test
        ports: ['5432:5432']

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: Run migrations
        run: npm run db:migrate
        env:
          DATABASE_URL: postgresql://postgres:testpass@localhost:5432/slipscan_test

      - name: Run tests
        run: npm test
        env:
          DATABASE_URL: postgresql://postgres:testpass@localhost:5432/slipscan_test
          JWT_SECRET: test_secret

      - name: Check code coverage
        run: npm run test:coverage
        # Fail หาก coverage < 70%

  build:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3

      - name: Build Docker image
        run: docker build -t slipscan-api:${{ github.sha }} .

      - name: Push to Registry
        run: |
          docker tag slipscan-api:${{ github.sha }} ghcr.io/${{ github.repository }}/api:latest
          docker push ghcr.io/${{ github.repository }}/api:latest

  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Deploy to production
        run: |
          curl -X POST "${{ secrets.DEPLOY_WEBHOOK_URL }}"
```

**2.2 Docker Configuration**
```dockerfile
# Dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production

FROM node:20-alpine
WORKDIR /app
COPY --from=builder /app/node_modules ./node_modules
COPY . .
EXPOSE 3000
CMD ["node", "src/index.js"]
```

```yaml
# docker-compose.yml (local dev)
version: '3.8'
services:
  api:
    build: ./backend
    ports: ['3000:3000']
    environment:
      DATABASE_URL: postgresql://postgres:password@db:5432/slipscan
      REDIS_URL: redis://redis:6379
    depends_on: [db, redis]

  frontend:
    build: ./frontend
    ports: ['5173:5173']

  db:
    image: postgres:15-alpine
    environment:
      POSTGRES_PASSWORD: password
      POSTGRES_DB: slipscan
    volumes: ['pgdata:/var/lib/postgresql/data']

  redis:
    image: redis:7-alpine

volumes:
  pgdata:
```

**Definition of Done:**
- [ ] Push to main → tests รัน → deploy อัตโนมัติ
- [ ] PR ต้องผ่าน test ก่อน merge ได้
- [ ] Docker build สำเร็จ
- [ ] Environment variables จัดการผ่าน GitHub Secrets

---

### 📌 Day 3 — Cloud Deployment

**เป้าหมาย:** ระบบ live บน production URL พร้อมใช้งาน

**3.1 Cloud Architecture**
```
Internet
    │
    ▼
[Cloudflare CDN + SSL]
    │
    ▼
[Railway / Render / AWS ECS]
    ├── [Frontend — Vercel / Netlify]
    ├── [Backend API — Docker Container]
    │       ├── [PostgreSQL — Railway / RDS]
    │       ├── [Redis — Upstash / ElastiCache]
    │       └── [File Storage — AWS S3 / Cloudflare R2]
    └── [Google Cloud Vision API]
```

**3.2 Environment Configuration**
```bash
# .env.production
NODE_ENV=production
PORT=3000

# Database
DATABASE_URL=postgresql://user:pass@host:5432/slipscan

# Redis
REDIS_URL=redis://default:pass@host:6379

# Storage
AWS_BUCKET_NAME=slipscan-slips
AWS_REGION=ap-southeast-1
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...

# OCR
GOOGLE_CLOUD_KEY_JSON=...

# Auth
JWT_SECRET=<strong-random-256-bit>
JWT_EXPIRES_IN=24h

# Notifications
SENDGRID_API_KEY=...
LINE_NOTIFY_TOKEN=...

# Monitoring
SENTRY_DSN=...
```

**3.3 Database Migration Script**
```bash
# npm scripts
"scripts": {
  "db:migrate":    "node src/db/migrate.js up",
  "db:rollback":   "node src/db/migrate.js down",
  "db:seed:test":  "node src/db/seed.test.js",
  "start":         "node src/index.js",
  "dev":           "nodemon src/index.js"
}
```

**3.4 Health Check Endpoint**
```javascript
app.get('/health', async (req, res) => {
  const checks = {
    api:      'ok',
    database: 'unknown',
    redis:    'unknown',
  };

  try {
    await db.query('SELECT 1');
    checks.database = 'ok';
  } catch { checks.database = 'error'; }

  try {
    await redis.ping();
    checks.redis = 'ok';
  } catch { checks.redis = 'error'; }

  const isHealthy = Object.values(checks).every(v => v === 'ok');
  res.status(isHealthy ? 200 : 503).json({
    status: isHealthy ? 'healthy' : 'degraded',
    checks,
    timestamp: new Date().toISOString(),
    version: process.env.npm_package_version,
  });
});
```

**Definition of Done:**
- [ ] API live ที่ production URL (HTTPS)
- [ ] Frontend deploy บน Vercel
- [ ] Database migrate ครบบน production
- [ ] Health check endpoint ตอบสนองได้
- [ ] ทดสอบ upload สลิปบน production สำเร็จ

---

### 📌 Day 4 — Performance Optimization

**เป้าหมาย:** ระบบรองรับ load และ response ไว

**4.1 Image Processing Optimization**
```javascript
const sharp = require('sharp');

async function preprocessImage(inputPath) {
  const outputPath = inputPath.replace(/\.(jpg|png|webp)$/, '_processed.jpg');

  await sharp(inputPath)
    .resize(1500, null, { withoutEnlargement: true }) // max width 1500px
    .grayscale()
    .normalise()
    .sharpen()
    .jpeg({ quality: 85, progressive: true })
    .toFile(outputPath);

  return outputPath;
}

// สร้าง thumbnail สำหรับ list view (ไม่ต้อง load ภาพเต็ม)
async function createThumbnail(inputPath) {
  const thumbPath = inputPath.replace(/\.(jpg|png|webp)$/, '_thumb.jpg');
  await sharp(inputPath)
    .resize(200, 200, { fit: 'cover' })
    .jpeg({ quality: 70 })
    .toFile(thumbPath);
  return thumbPath;
}
```

**4.2 Queue System สำหรับ OCR (Bull + Redis)**
```javascript
const Queue = require('bull');
const ocrQueue = new Queue('ocr-processing', process.env.REDIS_URL);

// Producer — เพิ่ม job เมื่อรับภาพ
async function queueOCRJob(slipId, imagePath) {
  await ocrQueue.add({ slipId, imagePath }, {
    attempts: 3,
    backoff: { type: 'exponential', delay: 2000 }
  });
}

// Consumer — Worker ประมวลผล OCR
ocrQueue.process(async (job) => {
  const { slipId, imagePath } = job.data;
  try {
    const processed = await preprocessImage(imagePath);
    const rawText   = await extractTextFromSlip(processed);
    const ocrData   = parseSlipFields(rawText);
    const detection = await runDetectionLayers(slipId, imagePath, ocrData);

    await db.query(
      `UPDATE slips SET raw_ocr=$1, sender_name=$2, amount=$3, detection_score=$4
       WHERE id=$5`,
      [rawText, ocrData.sender_name, ocrData.amount, detection.score, slipId]
    );
  } catch (err) {
    await db.query(`UPDATE slips SET ocr_status='failed' WHERE id=$1`, [slipId]);
    throw err;
  }
});
```

**4.3 Connection Pooling**
```javascript
const { Pool } = require('pg');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: 20,              // จำนวน connection สูงสุด
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
  ssl: process.env.NODE_ENV === 'production' ? { rejectUnauthorized: false } : false
});
```

**4.4 Performance Targets**

| Endpoint | Target Response Time | Load Test |
|---|---|---|
| POST /api/slips/upload | < 3s (รอ OCR async) | 10 concurrent |
| GET /api/dashboard/summary | < 500ms (cached) | 50 concurrent |
| GET /api/slips (search) | < 1s | 30 concurrent |
| GET /api/slips/export/xlsx | < 5s (1000 records) | 5 concurrent |

**Definition of Done:**
- [ ] Dashboard summary โหลด < 500ms ด้วย Redis cache
- [ ] Upload response < 500ms (OCR ทำงาน async ใน queue)
- [ ] Image thumbnail สร้างอัตโนมัติ
- [ ] Load test ผ่าน target ข้างต้น

---

### 📌 Day 5 — Monitoring + Logging

**เป้าหมาย:** รู้เมื่อระบบมีปัญหาก่อนที่ user จะรายงาน

**5.1 Error Tracking (Sentry)**
```javascript
const Sentry = require('@sentry/node');

Sentry.init({
  dsn: process.env.SENTRY_DSN,
  environment: process.env.NODE_ENV,
  tracesSampleRate: 0.1, // sample 10% ของ requests
});

app.use(Sentry.Handlers.requestHandler());
// ... routes ...
app.use(Sentry.Handlers.errorHandler());

// Custom error capture
function captureError(err, context = {}) {
  Sentry.withScope((scope) => {
    scope.setContext('additional', context);
    Sentry.captureException(err);
  });
}
```

**5.2 Structured Logging (Winston)**
```javascript
const winston = require('winston');

const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.Console(),
    new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
    new winston.transports.File({ filename: 'logs/combined.log' }),
  ],
});

// ตัวอย่าง log
logger.info('Slip uploaded', {
  slip_id: 42,
  user_id: 1,
  bank: 'กสิกรไทย',
  amount: 1500,
  detection_score: 92,
  duration_ms: 1240
});
```

**5.3 Uptime Monitoring**

ใช้บริการ **UptimeRobot** (ฟรี) หรือ **Better Uptime** ตรวจ `/health` ทุก 5 นาที
- Alert ทาง Email เมื่อ downtime
- Dashboard แสดง uptime ย้อนหลัง 30 วัน

**5.4 Metrics Dashboard (Optional)**
```javascript
const promClient = require('prom-client');

// Custom metrics
const ocrDuration = new promClient.Histogram({
  name: 'ocr_processing_duration_seconds',
  help: 'OCR processing time in seconds',
  buckets: [0.5, 1, 2, 5, 10]
});

const fakeSlipCounter = new promClient.Counter({
  name: 'fake_slips_detected_total',
  help: 'Total number of fake slips detected'
});

app.get('/metrics', (req, res) => {
  res.set('Content-Type', promClient.register.contentType);
  res.end(promClient.register.metrics());
});
```

**Definition of Done:**
- [ ] Sentry รับ error จาก production
- [ ] Winston log ทุก request สำคัญ (OCR, detection, auth)
- [ ] UptimeRobot monitor `/health` ทุก 5 นาที
- [ ] Alert Email เมื่อ server down

---

### 📌 Day 6 — External Notifications

**เป้าหมาย:** แจ้งเตือนผ่าน Email และ LINE เมื่อพบสลิปน่าสงสัย

**6.1 Email Notification (SendGrid)**
```javascript
const sgMail = require('@sendgrid/mail');
sgMail.setApiKey(process.env.SENDGRID_API_KEY);

async function sendFakeSlipEmail(userEmail, slipData) {
  await sgMail.send({
    to:      userEmail,
    from:    'alerts@slipscan.app',
    subject: '🚨 พบสลิปน่าสงสัย — SlipScan',
    html: `
      <div style="font-family: sans-serif; max-width: 480px;">
        <h2 style="color: #DC2626">⚠️ พบสลิปน่าสงสัย</h2>
        <p>ระบบตรวจพบสลิปที่อาจมีความผิดปกติ</p>
        <table style="border-collapse: collapse; width: 100%">
          <tr><td><b>ธนาคาร</b></td><td>${slipData.bank_name}</td></tr>
          <tr><td><b>ยอดเงิน</b></td><td>฿${slipData.amount.toLocaleString()}</td></tr>
          <tr><td><b>คะแนน</b></td><td>${slipData.detection_score}/100</td></tr>
        </table>
        <a href="${process.env.FRONTEND_URL}/slips/${slipData.id}"
           style="background:#4F46E5;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;">
          ตรวจสอบสลิป
        </a>
      </div>
    `
  });
}
```

**6.2 LINE Notify Integration**
```javascript
const axios = require('axios');

async function sendLineNotify(message, token) {
  await axios.post(
    'https://notify-api.line.me/api/notify',
    new URLSearchParams({ message }),
    { headers: { Authorization: `Bearer ${token}` } }
  );
}

// ใช้งาน
await sendLineNotify(
  `🚨 [SlipScan] พบสลิปน่าสงสัย!\n` +
  `ธนาคาร: ${slip.bank_name}\n` +
  `ยอด: ฿${slip.amount.toLocaleString()}\n` +
  `คะแนน: ${slip.detection_score}/100\n` +
  `ดูรายละเอียด: ${process.env.FRONTEND_URL}/slips/${slip.id}`,
  user.line_notify_token
);
```

**6.3 Notification Settings UI**
```
Settings > Notifications
┌──────────────────────────────────────┐
│ Email Notifications                  │
│ ✅ แจ้งเตือนเมื่อพบสลิปปลอม          │
│ ✅ แจ้งเตือนเมื่อพบสลิปซ้ำ           │
│ ☐  สรุปรายวัน (Daily Digest)         │
│                                      │
│ LINE Notify                          │
│ Token: [__________________] [เชื่อม]  │
│ ✅ แจ้งเตือนสลิปน่าสงสัย             │
└──────────────────────────────────────┘
```

**Database เพิ่มเติม:**
```sql
ALTER TABLE users ADD COLUMN line_notify_token TEXT;
ALTER TABLE users ADD COLUMN notify_fake        BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN notify_duplicate   BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN notify_daily_digest BOOLEAN DEFAULT FALSE;
```

**Definition of Done:**
- [ ] Email แจ้งเตือนส่งได้เมื่อ detection_score < 40
- [ ] LINE Notify ส่งได้เมื่อผู้ใช้ผูก token
- [ ] Settings UI บันทึก preference ได้
- [ ] ทดสอบ notification ครบทั้งสองช่องทาง

---

### 📌 Day 7 — E2E Testing + Documentation + Handoff

**เป้าหมาย:** ทดสอบ full journey และเตรียม docs สำหรับ handoff

**7.1 E2E Test Scenarios (Playwright)**
```javascript
// tests/e2e/upload-flow.spec.js
test('Complete slip upload flow', async ({ page }) => {
  // Login
  await page.goto('/login');
  await page.fill('[name=email]', 'test@example.com');
  await page.fill('[name=password]', 'password123');
  await page.click('button[type=submit]');
  await expect(page).toHaveURL('/dashboard');

  // Upload slip
  await page.goto('/upload');
  await page.setInputFiles('input[type=file]', 'tests/fixtures/real_slip.jpg');
  await page.click('button:text("อัพโหลด")');

  // ตรวจผลลัพธ์
  await expect(page.locator('[data-testid=slip-result]')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('[data-testid=detection-verdict]')).toContainText('ผ่านการตรวจสอบ');
});

test('Fake slip detection', async ({ page }) => {
  await loginAs(page, 'test@example.com');
  await page.setInputFiles('input[type=file]', 'tests/fixtures/fake_slip.jpg');
  await page.click('button:text("อัพโหลด")');
  await expect(page.locator('[data-testid=alert-badge]')).toBeVisible({ timeout: 10000 });
});

test('Duplicate slip rejection', async ({ page }) => {
  await loginAs(page, 'test@example.com');
  // Upload ครั้งแรก
  await uploadSlip(page, 'tests/fixtures/real_slip.jpg');
  // Upload ซ้ำ
  await uploadSlip(page, 'tests/fixtures/real_slip.jpg');
  await expect(page.locator('[data-testid=error-message]')).toContainText('สลิปซ้ำ');
});

test('Export CSV', async ({ page }) => {
  await loginAs(page, 'test@example.com');
  await page.goto('/slips');
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.click('button:text("Export CSV")')
  ]);
  expect(download.suggestedFilename()).toMatch(/\.csv$/);
});
```

**7.2 Final Checklist ก่อน Go-Live**

**Security**
- [ ] HTTPS บน production domain
- [ ] Rate limiting ทุก endpoint
- [ ] Input validation และ sanitization
- [ ] SQL injection protection (parameterized queries)
- [ ] XSS protection (Helmet + CSP)
- [ ] Audit log สำหรับ sensitive data access
- [ ] ข้อมูลบัญชีถูก mask

**Performance**
- [ ] Dashboard load < 500ms (cached)
- [ ] Upload response < 500ms (async queue)
- [ ] Database indexes ครบ
- [ ] Image thumbnail สร้างอัตโนมัติ

**Reliability**
- [ ] Health check endpoint ตอบสนอง
- [ ] Error handling ครบทุก endpoint
- [ ] Queue retry mechanism ทำงาน
- [ ] Database connection pool configured

**Monitoring**
- [ ] Sentry รับ error จาก production
- [ ] UptimeRobot monitor active
- [ ] Logs บันทึกลง file/service

**7.3 README + Documentation**
```markdown
# SlipScan Service

## Quick Start
1. `cp .env.example .env` และใส่ config
2. `docker-compose up -d`
3. `npm run db:migrate`
4. เปิด http://localhost:3000

## Architecture
[อธิบาย stack + flow]

## API Reference
[link to Postman collection / Swagger]

## Environment Variables
[ตาราง variables ทั้งหมด]

## Deployment
[ขั้นตอน deploy บน Railway/AWS]
```

---

## 🧪 Definition of Done — Sprint 4

ถือว่า Sprint 4 (และโปรเจกต์) เสร็จสมบูรณ์เมื่อ:

- [ ] **Security** — Rate limit, Helmet, Validation, HTTPS, Audit Log ครบ
- [ ] **PDPA** — Mask account, encrypt images, consent UI, retention policy
- [ ] **CI/CD** — Push → test → build → deploy อัตโนมัติ
- [ ] **Production** — API และ Frontend live บน HTTPS URL
- [ ] **Health** — `/health` endpoint ตอบสนองและ UptimeRobot monitor แล้ว
- [ ] **Sentry** — รับ error จาก production จริง
- [ ] **Notifications** — Email + LINE Notify ส่งได้เมื่อพบสลิปปลอม
- [ ] **E2E Tests** — 4 critical journeys ผ่านทั้งหมด
- [ ] **Docs** — README ครบ, Postman collection ส่งออกได้
- [ ] **Performance** — ผ่าน load test target ทุกข้อ

---

## 📁 โครงสร้าง Project (สมบูรณ์)

```
slipscan/
├── .github/workflows/
│   └── deploy.yml              ← 🆕 CI/CD
├── backend/
│   ├── src/
│   │   ├── controllers/        ← auth, slips, dashboard, export, admin
│   │   ├── services/           ← ocr, parser, detection, duplicate, qr, alert, dashboard, export
│   │   ├── middlewares/        ← authGuard, rateLimit, validate, auditLog
│   │   ├── routes/             ← auth, slips, dashboard, export, alerts, admin
│   │   ├── db/
│   │   │   ├── migrate.sql
│   │   │   └── migrate.js
│   │   ├── queues/
│   │   │   └── ocrQueue.js     ← 🆕 Bull queue
│   │   └── utils/
│   │       ├── logger.js       ← 🆕 Winston
│   │       └── sentry.js       ← 🆕
│   ├── tests/
│   │   ├── unit/
│   │   ├── integration/
│   │   └── e2e/                ← 🆕 Playwright
│   ├── Dockerfile              ← 🆕
│   └── .env.example
├── frontend/
│   ├── src/
│   │   ├── pages/              ← Login, Dashboard, SlipList, SlipDetail, Upload, Alerts, Settings
│   │   └── components/
├── docker-compose.yml          ← 🆕
└── README.md                   ← 🆕
```

---

## ⚠️ ความเสี่ยงและแนวทางรับมือ

| ความเสี่ยง | โอกาส | แนวทางรับมือ |
|---|---|---|
| Google Vision API ค่าใช้จ่ายสูงเมื่อ traffic เพิ่ม | กลาง | ตั้ง budget alert บน GCP, เตรียม Tesseract fallback |
| Database ใหญ่ขึ้นเรื่อย ๆ | สูง | Data retention policy 2 ปี + archive strategy |
| LINE Notify ปิดบริการ | ต่ำ | รองรับ Webhook ทั่วไปแทน |
| S3 costs เพิ่มขึ้น | กลาง | ลบภาพ original หลัง OCR เสร็จ, เก็บแค่ thumbnail |
| Cold start บน free tier | กลาง | ใช้ paid plan หรือ keep-alive ping |

---

## 📊 Sprint Velocity ที่คาดหวัง

```
Day 1  ████████████░░░  Security Hardening        (85% done)
Day 2  ████████████░░░  CI/CD Pipeline            (85% done)
Day 3  ████████████░░░  Cloud Deployment          (80% done)
Day 4  ████████████░░░  Performance Optimization  (80% done)
Day 5  ████████████░░░  Monitoring + Logging      (85% done)
Day 6  ████████████░░░  External Notifications    (85% done)
Day 7  ████████████░░░  E2E Test + Docs + Handoff (90% done)
```

---

## 🎉 Product Launch Checklist

เมื่อจบ Sprint 4 ระบบ **SlipScan** พร้อมใช้งาน production ด้วยความสามารถครบถ้วน:

| Feature | Sprint |
|---|---|
| ✅ OCR อ่านสลิปไทย/อังกฤษ | Sprint 1 |
| ✅ Auth + Upload API | Sprint 1 |
| ✅ ตรวจสลิปปลอม (4 layers) | Sprint 2 |
| ✅ ตรวจสลิปซ้ำ (Hash) | Sprint 2 |
| ✅ QR Verification | Sprint 2 |
| ✅ Alert System | Sprint 2 |
| ✅ Dashboard + Charts | Sprint 3 |
| ✅ Search + Filter + Pagination | Sprint 3 |
| ✅ Export CSV + Excel | Sprint 3 |
| ✅ Security + PDPA | Sprint 4 |
| ✅ CI/CD + Deployment | Sprint 4 |
| ✅ Monitoring + Logging | Sprint 4 |
| ✅ Email + LINE Notifications | Sprint 4 |

**Sprint Review:** Demo ระบบ live production ครบ 4 Sprint — ตั้งแต่ upload สลิปไปจนถึงรับ LINE แจ้งเตือนสลิปปลอมบนมือถือ 🚀
