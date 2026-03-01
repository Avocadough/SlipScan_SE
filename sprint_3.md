# 🚀 Sprint 3 — SlipScan Service
**ระยะเวลา:** Week 3 (7 วัน) | **เป้าหมาย:** Dashboard, Analytics, Export และ Full UI

> **หลักการเลือกงาน Sprint 3:** เมื่อ Core Engine และ Detection พร้อมแล้ว Sprint 3 เน้นทำให้ระบบ "ใช้งานได้จริง" ในชีวิตประจำวัน — ผู้ใช้เห็นสถิติรายได้, ค้นหาสลิปได้, และ export ข้อมูลไปทำบัญชีได้

---

## 🗺️ Sprint Overview

```
Sprint 1 (Week 1)          Sprint 2 (Week 2)           Sprint 3 (Week 3)           Sprint 4 (Week 4)
────────────────────        ────────────────────         ────────────────────         ────────────────────
✅ Setup & Foundation  →    ✅ Detection Features   →    🔄 Dashboard & Export   →    Polish & Production
✅ Database Design     →    ✅ Fake Slip API         →    🔄 Analytics & Charts   →    Performance
✅ Auth System         →    ✅ Duplicate Check       →    🔄 Search & Filter      →    Security Hardening
✅ OCR Core Engine     →    ✅ QR Verification       →    🔄 Export CSV/Excel     →    Deployment
✅ Upload API          →    ✅ Alert System          →    🔄 Full UI Design       →    Monitoring
✅ JSON Parser         →    ✅ Batch Detection       →    🔄 Pagination           →    User Feedback
```

---

## 📋 Backlog Items ที่อยู่ใน Sprint 3

| # | Task | Priority | Estimate |
|---|------|----------|----------|
| 1 | Dashboard API — รายได้รวม, สถิติรายวัน/สัปดาห์/เดือน | 🔴 Critical | 1 วัน |
| 2 | Dashboard UI — Charts (Line, Bar, Pie) | 🔴 Critical | 1.5 วัน |
| 3 | Bank Ranking (ยอดโอนรวมต่อธนาคาร) | 🟡 High | 0.5 วัน |
| 4 | ค้นหาและกรองสลิป (Search + Filter) | 🟡 High | 1 วัน |
| 5 | Pagination สำหรับรายการสลิป | 🟡 High | 0.5 วัน |
| 6 | Export CSV | 🟡 High | 0.5 วัน |
| 7 | Export Excel (XLSX) | 🟡 High | 0.5 วัน |
| 8 | Full UI Polish + Responsive Design | 🟢 Medium | 1 วัน |
| 9 | Slip Detail Page | 🟢 Medium | 0.5 วัน |

**รวม Estimate: ~7 วัน (1 สัปดาห์)**

---

## 🎯 Sprint Goal

> **"ผู้ใช้มองเห็นภาพรวมรายได้ทั้งหมด, ค้นหาสลิปได้, และ export ข้อมูลออกไปใช้งานต่อได้"**

เมื่อจบ Sprint 3 ระบบจะสามารถ:
- ✅ แสดง Dashboard พร้อม Chart รายได้รายวัน/สัปดาห์/เดือน
- ✅ แสดง Ranking ธนาคารที่มียอดโอนสูงสุด (Pie + Bar)
- ✅ ค้นหาสลิปตามชื่อ, ธนาคาร, ช่วงวันที่, จำนวนเงิน
- ✅ Export ข้อมูลสลิปเป็น CSV และ Excel
- ✅ UI ดูดีบน Desktop และ Mobile

---

## 📅 แผนงานรายวัน (Day-by-Day Plan)

### 📌 Day 1 — Dashboard API

**เป้าหมาย:** Backend พร้อมข้อมูลสำหรับ Dashboard ทุกประเภท

**1.1 Dashboard Endpoints**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/dashboard/summary` | ยอดรวมวันนี้/สัปดาห์นี้/เดือนนี้ |
| `GET` | `/api/dashboard/trend?period=7d` | ข้อมูล trend ตามช่วงเวลา |
| `GET` | `/api/dashboard/bank-ranking` | Ranking ธนาคาร (ยอดและจำนวน) |
| `GET` | `/api/dashboard/fake-stats` | สัดส่วน fake/real/suspicious |

**1.2 Summary Query**
```sql
-- ยอดรวมรายได้ตามช่วงเวลา
SELECT
  COUNT(*)                                           AS total_slips,
  SUM(amount)                                        AS total_amount,
  COUNT(*) FILTER (WHERE is_fake = TRUE)             AS fake_count,
  COUNT(*) FILTER (WHERE is_duplicate = TRUE)        AS duplicate_count,
  SUM(amount) FILTER (WHERE is_fake = FALSE
                         AND is_duplicate = FALSE)   AS verified_amount
FROM slips
WHERE user_id = $1
  AND slip_date >= CURRENT_DATE - INTERVAL '30 days';
```

**1.3 Trend Data Query**
```sql
-- Revenue trend รายวัน (ย้อนหลัง 30 วัน)
SELECT
  slip_date::DATE             AS date,
  COUNT(*)                    AS slip_count,
  SUM(amount)                 AS daily_amount,
  COUNT(DISTINCT bank_name)   AS bank_count
FROM slips
WHERE user_id = $1
  AND slip_date >= NOW() - INTERVAL $2    -- '7 days', '30 days', '90 days'
  AND is_fake = FALSE
  AND is_duplicate = FALSE
GROUP BY slip_date::DATE
ORDER BY date ASC;
```

**1.4 Bank Ranking Query**
```sql
SELECT
  bank_name,
  COUNT(*)       AS slip_count,
  SUM(amount)    AS total_amount,
  AVG(amount)    AS avg_amount
FROM slips
WHERE user_id = $1
  AND is_fake = FALSE
  AND slip_date >= NOW() - INTERVAL '30 days'
GROUP BY bank_name
ORDER BY total_amount DESC;
```

**1.5 Response Format**
```json
{
  "summary": {
    "today":   { "slips": 12, "amount": 45200.00, "fake": 1 },
    "week":    { "slips": 67, "amount": 218500.00, "fake": 3 },
    "month":   { "slips": 254, "amount": 847300.00, "fake": 8 }
  },
  "top_bank": "กสิกรไทย",
  "verified_rate": 96.8
}
```

**Definition of Done:**
- [ ] 4 endpoints คืนข้อมูลถูกต้อง
- [ ] Query มี index บน (user_id, slip_date) เพื่อ performance
- [ ] ทดสอบด้วย data จำลอง >100 records

---

### 📌 Day 2-3 — Dashboard UI + Charts

**เป้าหมาย:** หน้า Dashboard สวยงาม ใช้งานง่าย พร้อม Charts

**2.1 Tech Stack สำหรับ Charts**
```bash
npm install recharts          # Line, Bar, Pie charts
npm install @tremor/react     # Dashboard UI components (optional)
```

**2.2 Summary Cards Component**
```jsx
// KPI Cards แถวบนสุด
function SummaryCards({ summary }) {
  return (
    <div className="grid grid-cols-4 gap-4 mb-6">
      <KPICard
        title="รายได้วันนี้"
        value={`฿${summary.today.amount.toLocaleString()}`}
        change="+12%"
        icon={<TrendingUp />}
        color="green"
      />
      <KPICard
        title="สลิปทั้งหมด (เดือนนี้)"
        value={summary.month.slips}
        icon={<FileText />}
        color="blue"
      />
      <KPICard
        title="สลิปน่าสงสัย"
        value={summary.month.fake}
        icon={<AlertTriangle />}
        color="red"
      />
      <KPICard
        title="อัตราผ่านการตรวจ"
        value={`${summary.verified_rate}%`}
        icon={<CheckCircle />}
        color="teal"
      />
    </div>
  );
}
```

**2.3 Revenue Trend Line Chart**
```jsx
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

function RevenueTrendChart({ data, period, onPeriodChange }) {
  return (
    <div className="bg-white rounded-xl p-6 shadow-sm">
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold">แนวโน้มรายได้</h3>
        <PeriodSelector value={period} onChange={onPeriodChange} options={['7d','30d','90d']} />
      </div>
      <ResponsiveContainer width="100%" height={300}>
        <LineChart data={data}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
          <XAxis dataKey="date" tickFormatter={d => format(new Date(d), 'dd/MM')} />
          <YAxis tickFormatter={v => `฿${(v/1000).toFixed(0)}k`} />
          <Tooltip formatter={(v) => [`฿${v.toLocaleString()}`, 'รายได้']} />
          <Line
            type="monotone"
            dataKey="daily_amount"
            stroke="#4F46E5"
            strokeWidth={2}
            dot={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
```

**2.4 Bank Ranking Chart (Bar + Pie)**
```jsx
import { PieChart, Pie, Cell, BarChart, Bar, Legend } from 'recharts';

const BANK_COLORS = {
  'กสิกรไทย':  '#1BA345',
  'ไทยพาณิชย์': '#4A148C',
  'กรุงไทย':   '#0052B4',
  'กรุงเทพ':   '#1565C0',
  'ทหารไทยธนชาต': '#FF8F00',
  'ออมสิน':   '#F57F17',
};

function BankRankingChart({ data }) {
  return (
    <div className="grid grid-cols-2 gap-6">
      {/* Pie Chart */}
      <div className="bg-white rounded-xl p-6 shadow-sm">
        <h3 className="text-lg font-semibold mb-4">สัดส่วนตามธนาคาร</h3>
        <PieChart width={300} height={250}>
          <Pie data={data} dataKey="total_amount" nameKey="bank_name" cx="50%" cy="50%" outerRadius={100}>
            {data.map((entry) => (
              <Cell key={entry.bank_name} fill={BANK_COLORS[entry.bank_name] || '#888'} />
            ))}
          </Pie>
          <Legend />
        </PieChart>
      </div>

      {/* Bar Chart */}
      <div className="bg-white rounded-xl p-6 shadow-sm">
        <h3 className="text-lg font-semibold mb-4">ยอดโอนต่อธนาคาร</h3>
        <BarChart data={data} width={300} height={250}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="bank_name" tick={{ fontSize: 11 }} />
          <YAxis tickFormatter={v => `${(v/1000).toFixed(0)}k`} />
          <Tooltip formatter={(v) => `฿${v.toLocaleString()}`} />
          <Bar dataKey="total_amount" fill="#4F46E5" radius={[4,4,0,0]} />
        </BarChart>
      </div>
    </div>
  );
}
```

**Definition of Done:**
- [ ] Dashboard โหลดข้อมูลจาก API จริง
- [ ] Line Chart แสดง trend ได้ตามช่วงเวลาที่เลือก
- [ ] Pie + Bar Chart แสดง bank ranking ได้
- [ ] KPI Cards แสดงยอดถูกต้อง
- [ ] ปุ่ม toggle ช่วงเวลา (7วัน / 30วัน / 90วัน) ทำงานได้

---

### 📌 Day 4 — Search + Filter + Pagination

**เป้าหมาย:** ค้นหาและกรองสลิปตามเงื่อนไขต่าง ๆ ได้

**4.1 Search API**

```
GET /api/slips?
  q=สมชาย               ← full-text search (ชื่อผู้โอน/รับ)
  &bank=กสิกรไทย        ← filter ธนาคาร
  &from=2025-01-01      ← วันที่เริ่มต้น
  &to=2025-01-31        ← วันที่สิ้นสุด
  &min_amount=100       ← จำนวนเงินขั้นต่ำ
  &max_amount=10000     ← จำนวนเงินสูงสุด
  &status=fake          ← all | fake | safe | suspicious
  &page=1               ← หน้า
  &limit=20             ← จำนวนต่อหน้า
  &sort=amount_desc     ← เรียงลำดับ
```

**4.2 Dynamic Query Builder**
```javascript
async function searchSlips(userId, filters) {
  const conditions = ['user_id = $1'];
  const params = [userId];
  let p = 2;

  if (filters.q) {
    conditions.push(`(sender_name ILIKE $${p} OR receiver_name ILIKE $${p})`);
    params.push(`%${filters.q}%`);
    p++;
  }
  if (filters.bank) {
    conditions.push(`bank_name = $${p++}`);
    params.push(filters.bank);
  }
  if (filters.from) {
    conditions.push(`slip_date >= $${p++}`);
    params.push(filters.from);
  }
  if (filters.to) {
    conditions.push(`slip_date <= $${p++}`);
    params.push(filters.to);
  }
  if (filters.min_amount) {
    conditions.push(`amount >= $${p++}`);
    params.push(filters.min_amount);
  }
  if (filters.status === 'fake')       conditions.push('is_fake = TRUE');
  if (filters.status === 'safe')       conditions.push('is_fake = FALSE AND is_duplicate = FALSE');
  if (filters.status === 'suspicious') conditions.push('detection_score < 70 AND is_fake = FALSE');

  const sortMap = {
    'amount_desc': 'amount DESC',
    'amount_asc':  'amount ASC',
    'date_desc':   'slip_date DESC',
    'date_asc':    'slip_date ASC',
  };
  const orderBy = sortMap[filters.sort] || 'created_at DESC';

  const offset = ((filters.page || 1) - 1) * (filters.limit || 20);
  const limit  = filters.limit || 20;

  const sql = `
    SELECT *, COUNT(*) OVER() AS total_count
    FROM slips
    WHERE ${conditions.join(' AND ')}
    ORDER BY ${orderBy}
    LIMIT ${limit} OFFSET ${offset}
  `;

  return db.query(sql, params);
}
```

**4.3 Search UI Component**
```jsx
function SlipSearchBar({ onSearch }) {
  const [filters, setFilters] = useState({
    q: '', bank: '', from: '', to: '',
    min_amount: '', max_amount: '', status: 'all'
  });

  return (
    <div className="bg-white rounded-xl p-4 shadow-sm mb-4">
      <div className="flex gap-3 flex-wrap">
        <input
          type="text" placeholder="ค้นหาชื่อผู้โอน/รับ..."
          className="border rounded-lg px-3 py-2 flex-1 min-w-48"
          value={filters.q}
          onChange={e => setFilters({...filters, q: e.target.value})}
        />
        <BankSelector value={filters.bank} onChange={v => setFilters({...filters, bank: v})} />
        <DateRangePicker
          from={filters.from} to={filters.to}
          onChange={(from, to) => setFilters({...filters, from, to})}
        />
        <StatusFilter value={filters.status} onChange={v => setFilters({...filters, status: v})} />
        <button
          onClick={() => onSearch(filters)}
          className="bg-indigo-600 text-white px-4 py-2 rounded-lg"
        >
          ค้นหา
        </button>
      </div>
    </div>
  );
}
```

**Definition of Done:**
- [ ] ค้นหาด้วย full-text ได้
- [ ] กรองด้วย bank, date range, amount, status ได้
- [ ] Pagination ทำงานได้ (page, limit)
- [ ] เรียงลำดับได้ 4 แบบ
- [ ] คืน total_count สำหรับแสดงจำนวนผลลัพธ์

---

### 📌 Day 5 — Export CSV + Excel

**เป้าหมาย:** Export ข้อมูลสลิปออกมาเป็นไฟล์

**5.1 Export API**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/slips/export/csv` | Export เป็น CSV |
| `GET` | `/api/slips/export/xlsx` | Export เป็น Excel |

รองรับ query params เดียวกับ search (date range, bank, status) เพื่อ export เฉพาะ filtered data

**5.2 CSV Export**
```javascript
const { Parser } = require('json2csv');

async function exportCSV(userId, filters, res) {
  const slips = await searchSlips(userId, { ...filters, limit: 10000 });

  const fields = [
    { label: 'วันที่', value: 'slip_date' },
    { label: 'เวลา', value: 'slip_time' },
    { label: 'ธนาคาร', value: 'bank_name' },
    { label: 'ชื่อผู้โอน', value: 'sender_name' },
    { label: 'ชื่อผู้รับ', value: 'receiver_name' },
    { label: 'จำนวนเงิน', value: 'amount' },
    { label: 'Ref No.', value: 'ref_no' },
    { label: 'สถานะ', value: row => row.is_fake ? 'ปลอม' : row.is_duplicate ? 'ซ้ำ' : 'ผ่าน' },
  ];

  const parser = new Parser({ fields, withBOM: true }); // BOM สำหรับ UTF-8 ภาษาไทย
  const csv = parser.parse(slips.rows);

  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', `attachment; filename="slips_${Date.now()}.csv"`);
  res.send(csv);
}
```

**5.3 Excel Export (SheetJS)**
```javascript
const XLSX = require('xlsx');

async function exportExcel(userId, filters, res) {
  const slips = await searchSlips(userId, { ...filters, limit: 10000 });

  const wsData = [
    ['วันที่', 'เวลา', 'ธนาคาร', 'ผู้โอน', 'ผู้รับ', 'จำนวนเงิน', 'Ref No.', 'สถานะ', 'คะแนนตรวจ'],
    ...slips.rows.map(s => [
      s.slip_date, s.slip_time, s.bank_name,
      s.sender_name, s.receiver_name, s.amount,
      s.ref_no,
      s.is_fake ? 'ปลอม' : s.is_duplicate ? 'ซ้ำ' : 'ผ่าน',
      s.detection_score
    ])
  ];

  const ws = XLSX.utils.aoa_to_sheet(wsData);

  // Style header row
  ws['!cols'] = [10,10,14,20,20,12,18,10,12].map(wch => ({ wch }));

  // Summary sheet
  const summaryData = [
    ['รายงานสรุปสลิป SlipScan'],
    ['วันที่ export:', new Date().toLocaleDateString('th-TH')],
    ['จำนวนสลิปทั้งหมด:', slips.rows.length],
    ['ยอดรวม:', slips.rows.reduce((sum, s) => sum + parseFloat(s.amount), 0)],
  ];
  const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, wsSummary, 'สรุป');
  XLSX.utils.book_append_sheet(wb, ws, 'รายการสลิป');

  const buffer = XLSX.write(wb, { type: 'buffer', bookType: 'xlsx' });

  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  res.setHeader('Content-Disposition', `attachment; filename="slips_${Date.now()}.xlsx"`);
  res.send(buffer);
}
```

**Definition of Done:**
- [ ] Export CSV เปิดได้ใน Excel/Google Sheets พร้อม ภาษาไทยถูกต้อง (BOM)
- [ ] Export Excel มี 2 sheet (สรุป + รายการ)
- [ ] Export รองรับ filter เดียวกับ search
- [ ] ปุ่ม Export บน UI ทำงานได้

---

### 📌 Day 6 — Slip Detail Page + Full UI Polish

**เป้าหมาย:** UI ครบสมบูรณ์, ดูดี, ใช้งานง่าย

**6.1 หน้า Slip Detail**
```
┌─────────────────────────────────────┐
│  ← กลับ    สลิป #42    [Verified ✅] │
├─────────────────────────────────────┤
│  [ภาพสลิป]    │  ข้อมูลจาก OCR      │
│               │  ธนาคาร: กสิกรไทย   │
│               │  ผู้โอน: นาย สมชาย  │
│               │  จำนวน: ฿1,500.00  │
│               │  วันที่: 15 ม.ค. 68 │
│               │  Ref: REF2025...    │
├─────────────────────────────────────┤
│  ผลการตรวจสอบ                       │
│  Score: 92/100  ✅ ผ่านการตรวจสอบ   │
│  QR: ✅ ตรงกัน  Duplicate: ✅ ไม่ซ้ำ│
└─────────────────────────────────────┘
```

**6.2 UI Checklist**
- [ ] Responsive Design (Mobile, Tablet, Desktop)
- [ ] Loading skeleton สำหรับทุก async component
- [ ] Empty state เมื่อไม่มีสลิป
- [ ] Error state พร้อมปุ่ม retry
- [ ] Toast notification เมื่อ upload สำเร็จ/ล้มเหลว
- [ ] Dark mode support (optional)

**6.3 Navigation Structure**
```
/login                  ← หน้า Login
/dashboard              ← Dashboard + Charts
/slips                  ← รายการสลิป (search + filter)
/slips/:id              ← Slip Detail
/upload                 ← Upload หน้า
/alerts                 ← รายการ Alerts
/admin/slips            ← Admin review (role guard)
```

---

### 📌 Day 7 — Integration Test + Performance

**เป้าหมาย:** ทดสอบ flow ทั้งหมดและปรับ performance

**7.1 Database Indexes**
```sql
-- Index สำหรับ search และ dashboard queries
CREATE INDEX idx_slips_user_date     ON slips(user_id, slip_date DESC);
CREATE INDEX idx_slips_bank          ON slips(bank_name);
CREATE INDEX idx_slips_amount        ON slips(amount);
CREATE INDEX idx_slips_status        ON slips(is_fake, is_duplicate);
CREATE INDEX idx_slips_sender        ON slips USING GIN(to_tsvector('simple', sender_name));
```

**7.2 API Response Caching (Redis)**
```javascript
// Cache dashboard summary 5 นาที
async function getDashboardSummary(userId) {
  const cacheKey = `dashboard:${userId}:summary`;
  const cached = await redis.get(cacheKey);
  if (cached) return JSON.parse(cached);

  const data = await computeDashboardSummary(userId);
  await redis.setex(cacheKey, 300, JSON.stringify(data)); // 5 min TTL
  return data;
}
```

---

## 🧪 Definition of Done — Sprint 3

ถือว่า Sprint 3 เสร็จสมบูรณ์เมื่อ:

- [ ] **Dashboard API** — 4 endpoints คืนข้อมูลถูกต้อง
- [ ] **Charts** — Line, Bar, Pie chart แสดงข้อมูลจริงจาก API
- [ ] **Period Filter** — toggle 7d / 30d / 90d ทำงานได้
- [ ] **Search** — ค้นหาด้วยชื่อ, ธนาคาร, วันที่, จำนวนเงิน, สถานะ
- [ ] **Pagination** — เลือก page และ limit ได้
- [ ] **Export CSV** — เปิดได้ใน Excel พร้อมภาษาไทย
- [ ] **Export Excel** — มี 2 sheet, style column ครบ
- [ ] **Slip Detail** — แสดงภาพ + ข้อมูล OCR + ผลการตรวจสอบ
- [ ] **UI** — Responsive, มี loading/error/empty state
- [ ] **Performance** — Dashboard โหลด < 2 วินาที (with Redis cache)

---

## 🚫 Out of Scope (Sprint 3)

- ❌ Email / LINE Notification
- ❌ Multi-user / Team Management
- ❌ Custom Webhook
- ❌ Mobile App
- ❌ CI/CD Pipeline (ทำใน Sprint 4)

---

## ⚠️ ความเสี่ยงและแนวทางรับมือ

| ความเสี่ยง | โอกาส | แนวทางรับมือ |
|---|---|---|
| Chart ไม่ render บนมือถือ | กลาง | ใช้ ResponsiveContainer, ทดสอบ Mobile ก่อน |
| Export ไฟล์ใหญ่ timeout | กลาง | Limit export 10,000 records, แจ้ง user |
| ภาษาไทยในไฟล์ Excel เป็น ? | สูง | ใส่ BOM ใน CSV, ใช้ SheetJS สำหรับ .xlsx |
| Dashboard query ช้าเมื่อข้อมูลมาก | กลาง | เพิ่ม Index + Redis cache |

---

## 📁 โครงสร้าง Project (เพิ่มเติมจาก Sprint 1-2)

```
slipscan/backend/src/
├── controllers/
│   ├── dashboardController.js   ← 🆕
│   └── exportController.js      ← 🆕
├── services/
│   ├── dashboardService.js      ← 🆕
│   ├── exportService.js         ← 🆕
│   └── searchService.js         ← 🆕
├── routes/
│   ├── dashboard.js             ← 🆕
│   └── export.js                ← 🆕

slipscan/frontend/src/
├── pages/
│   ├── Dashboard.jsx            ← 🆕
│   ├── SlipList.jsx             ← 🆕
│   └── SlipDetail.jsx           ← 🆕
├── components/
│   ├── charts/
│   │   ├── RevenueTrendChart.jsx ← 🆕
│   │   └── BankRankingChart.jsx  ← 🆕
│   ├── SlipCard.jsx             ← 🆕
│   ├── SearchBar.jsx            ← 🆕
│   └── ExportButton.jsx         ← 🆕
```

---

## 📊 Sprint Velocity ที่คาดหวัง

```
Day 1  ████████████░░░  Dashboard API                 (85% done)
Day 2  ████████░░░░░░░  Dashboard UI (Setup)          (55% done)
Day 3  ████████████░░░  Dashboard UI (Charts)         (85% done)
Day 4  ████████████░░░  Search + Filter + Pagination  (80% done)
Day 5  ████████████░░░  Export CSV + Excel            (85% done)
Day 6  ████████████░░░  Slip Detail + UI Polish       (80% done)
Day 7  ████████████░░░  Integration Test + Perf       (85% done)
```

**Sprint Review:** Demo การดู Dashboard รายได้, ค้นหาสลิป, และ Export ไฟล์ Excel
