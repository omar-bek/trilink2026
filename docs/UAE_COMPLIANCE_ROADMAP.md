# خريطة طريق امتثال TriLink للقوانين الإماراتية

> **الجمهور**: فريق المنتج، الفريق الهندسي، Legal counsel، مدير الامتثال
> **الحالة**: مسوّدة جاهزة للتنفيذ
> **آخر تحديث**: 2026-04-08
> **مراجعة كل**: نهاية كل مرحلة (PR review checkpoint)

---

## 1. ملخص تنفيذي

منصة TriLink بُنيت بأساس قانوني قوي — عقودها التلقائية تستشهد بقوانين إماراتية محددة (Federal Decree-Law 50/2022, 46/2021, 20/2018, 45/2021)، عندها KYC + UBO + Sanctions Screening حقيقي، و i18n عربي/إنجليزي كامل. الفجوات الموجودة ليست في الفهم القانوني — بل في **عمق التنفيذ التقني** لبعض المتطلبات الإماراتية الخاصة.

الهدف من هذه الخريطة: نقل المنصة من حالة "جاهزة للـ B2B الخاص" إلى حالة **"جاهزة للقطاع الحكومي والـ Enterprise compliance audits"**، مع البقاء على المسار الصحيح لاستحقاق الفاتورة الإلكترونية الإجبارية في يوليو 2026.

### حالة المنصة الآن مقابل الهدف

| البُعد | الحالة الآن | بعد الخريطة |
|---|---|---|
| العقود + الأساس القانوني | 🟢 95% | 🟢 100% |
| KYC + UBO + Sanctions | 🟢 85% | 🟢 95% |
| VAT + Tax Invoice | 🟡 40% | 🟢 95% |
| e-Invoicing (FTA Phase 1) | 🔴 0% | 🟢 80% (skeleton ready) |
| PDPL Implementation | 🟡 25% | 🟢 90% |
| التوقيع الإلكتروني (Qualified) | 🟡 50% | 🟢 90% |
| سجل التدقيق tamper-evident | 🟡 50% | 🟢 95% |
| Free Zone vs Mainland | 🔴 0% | 🟢 90% |
| ICV / In-Country Value | 🔴 0% | 🟢 90% |
| Anti-collusion controls | 🟡 30% | 🟢 75% |
| Corporate Tax (9%) | 🔴 0% | 🟢 80% |

---

## 2. كيف نستخدم هذا المستند

### مبادئ التنفيذ

1. **كل مرحلة = PR واحد قابل للدمج بشكل مستقل.** لا توجد مرحلة تعتمد على مرحلة لاحقة.
2. **كل مرحلة فيها rollback plan واضح.** لو في مشكلة في الإنتاج، نقدر نرجع للحالة السابقة بـ migration واحد.
3. **كل مرحلة فيها acceptance criteria قابلة للقياس.** "تم" = كل النقاط متشيكة، مش "خلصت كتابة الكود".
4. **كل مرحلة فيها review checkpoint.** قبل الانتقال للي بعدها، نقعد ربع ساعة، نراجع، نتفق.
5. **الـ Backwards compatibility إلزامية.** أي bid/contract موجود قبل الـ migration لازم يفضل يفتح ويعرض صح.

### دورة العمل المقترحة

```
المرحلة → فرع جديد (feature/phase-N-slug) → تنفيذ → اختبار محلي
        → PR → review checkpoint → merge → نشر staging
        → smoke test → نشر إنتاج → ✓ المرحلة التالية
```

### حالات المرحلة

- 🔵 **Planned** — في الخريطة، لم تبدأ
- 🟡 **In progress** — جاري التنفيذ
- 🟠 **In review** — PR مفتوح في انتظار الـ checkpoint
- 🟢 **Done** — مدمج وفي إنتاج

### تقييم الحجم (بدلاً من تقدير الزمن)

الـ effort sizing بـ T-shirt sizes — أكثر صدقاً من تقدير الأيام:
- **XS** — تغيير محدود في ملف أو اتنين
- **S** — migration + service + 2-3 blade files
- **M** — migration + service + controller + 5+ blade files + new schema relationships
- **L** — multiple migrations + new services + UI section overhaul + integration testing
- **XL** — external system integration + abstraction layer + multiple new schemas

---

## 3. نظرة عامة على الخريطة

| # | المرحلة | الحجم | المخرجات الرئيسية | تعتمد على |
|---|---|---|---|---|
| 0 | Foundation Hardening | XS | إصلاحات أمنية فورية: HMAC fallback removal، audit hash chain، retention skeleton | — |
| 1 | Tax Invoice Infrastructure | M | جدول `tax_invoices`، sequential numbering، PDF generator، Tax Credit Notes | — |
| 2 | PDPL Foundation | M | Privacy policy، Cookie consent، DSAR، right-to-erasure، breach notification skeleton | — |
| 3 | Free Zone & Jurisdiction Awareness | S | `is_free_zone`, `legal_jurisdiction`, DIFC/ADGM contract clauses | — |
| 4 | ICV (In-Country Value) Scoring | M | جدول `icv_certificates`, ICV-aware bid evaluation, weighted scoring | — |
| 5 | e-Invoicing Skeleton | L | `e_invoice_submissions`, AspProviderInterface, PintAeMapper, Mock provider | المرحلة 1 |
| 6 | Qualified e-Signature & UAE Pass | L | `signature_grade` enum, UAE Pass integration, TSP abstraction | المرحلة 0 |
| 7 | Corporate Tax + Anti-Collusion | M | CT registration tracking, AntiCollusionService, Free zone CT 0% logic | المرحلة 3 |
| 8 | Tier 3 Polish | M | ECAS uploads, CoO uploads, NESA hardening، WPS hooks | — |

**القيود الزمنية الخارجية الوحيدة:**
- 🔴 **يوليو 2026** — FTA e-Invoicing Phase 1 (شركات > AED 100M revenue) — المرحلة 5 لازم تكون متعمولة قبل ده
- 🟡 **يناير 2027** — FTA e-Invoicing Phase 2 (شركات > AED 50M)
- 🟡 **PDPL grace period** — انتهت نظرياً (PDPL سارية من 2022) لكن enforcement تدريجي

---

## 4. المرحلة 0 — Foundation Hardening 🛠️

**الهدف**: إصلاحات أمنية فورية صغيرة الحجم لكن خطورتها مرتفعة. كلها XS وممكن تتعمل في PR واحد.

**ليه دلوقتي**: 3 إصلاحات فورية (المفاتيح: tamper-evidence, signature integrity, retention policy stub) — لو عمل enforcement أو audit جا، دي أول حاجات هيتفقدوها.

### النطاق

**في النطاق:**
1. شيل `hash_hmac` fallback من [PkiService::createDigitalSignature()](app/Services/PkiService.php) — يفشل بـ exception لو مفيش private key
2. تحويل `audit_logs.hash` إلى **hash chain حقيقي** بـ `previous_hash` reference
3. Migration: إضافة `previous_hash` column على audit_logs
4. Artisan command `audit:verify-chain` للتحقق من سلامة الـ chain
5. Settings entry لـ `audit_log_retention_days` (default: 2555 يوم = 7 سنوات)
6. Artisan command `audit:archive` (skeleton — مش فعال لكن في الـ kernel)

**خارج النطاق:**
- WORM external storage (S3 Object Lock) → مرحلة لاحقة
- Backup encryption rotation → مرحلة 8

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_09_100000_add_previous_hash_to_audit_logs.php` |
| Edit | `app/Models/AuditLog.php` (booted method) |
| Edit | `app/Services/PkiService.php` (شيل HMAC fallback) |
| New | `app/Console/Commands/VerifyAuditChainCommand.php` |
| New | `app/Console/Commands/ArchiveAuditLogsCommand.php` (skeleton) |
| Edit | `bootstrap/app.php` أو `app/Console/Kernel.php` (تسجيل الـ commands) |
| Edit | `config/audit.php` (جديد) |

### Database changes
```sql
ALTER TABLE audit_logs
  ADD COLUMN previous_hash VARCHAR(64) NULL AFTER hash,
  ADD INDEX idx_previous_hash (previous_hash);
```

### Acceptance criteria
- [ ] PR مرفق فيه screenshot لـ `php artisan audit:verify-chain` وهو passing على بيانات seeded
- [ ] أي audit log جديد بيتسجل بـ `hash = sha256(previous_hash || canonical_json(self))`
- [ ] محاولة استدعاء `PkiService::createDigitalSignature(null privateKey)` ترجع `RuntimeException`
- [ ] Test: عمل seed لـ 100 audit row، تعديل واحد في النص، تشغيل verify → الـ chain يكشف التلاعب
- [ ] Documentation في PR تشرح كيف الـ chain يشتغل
- [ ] الـ existing audit logs قبل الـ migration كل واحدة `previous_hash = NULL` (لا يكسر شيئاً)

### Review checkpoint
1. هل الـ HMAC fallback اتشال أم بقى كـ feature flag؟
2. هل الـ verify command يقدر يحسب الـ chain لـ 1M+ row في وقت معقول؟ (هل محتاج batched approach)
3. هل الـ retention period 7 سنوات هو الصح للـ business model أم في نوع records محتاج 5 سنوات بس؟

### Rollback plan
الـ migration رفعت `previous_hash` كـ nullable. لو فيه مشكلة، rollback الـ migration بيحذف العمود بدون تأثير على البيانات الأساسية.

### الحجم: **XS**

---

## 5. المرحلة 1 — Tax Invoice Infrastructure 🧾

**الهدف**: تشغيل إصدار فواتير ضريبية رسمية بـ sequential numbering يطابق متطلبات Federal Decree-Law 8/2017 و Cabinet Decision 52/2017 Article 59.

**ليه دلوقتي**: المنصة دلوقتي بتسجل `payments` فيها amount + vat_amount لكن ده مش tax invoice. أي مشتري VAT-registered طلب فاتورة لإثبات Input Tax مش هياخدها. بدون ده، الفواتير اللي بتطلعها المنصة قانونياً غير صالحة لاسترداد الضريبة.

### النطاق

**في النطاق:**
1. جدول جديد `tax_invoices` بـ sequential `invoice_number` فريد بـ row-level locking
2. جدول `tax_credit_notes` للإلغاء/الرد
3. `TaxInvoiceService::issueFor(Payment)` — يولّد invoice number atomically، PDF، hash
4. PDF template جديد `resources/views/dashboard/tax-invoices/pdf.blade.php` — bilingual layout يطابق FTA template
5. Routes للـ download + view + admin reissue
6. Admin screen لـ "Tax Invoices" tab تحت Finance section
7. Auto-issue: في Payment lifecycle، لما الـ status يتحول لـ `paid`، Job يصدر الفاتورة تلقائياً
8. كل tax invoice يسجل event في audit log + escrow trail

**خارج النطاق:**
- Peppol XML generation → مرحلة 5
- Real-time submission to FTA → مرحلة 5
- Multi-language credit note PDF → مرحلة 8

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_10_100000_create_tax_invoices_table.php` |
| Migration | `database/migrations/2026_04_10_100100_create_tax_credit_notes_table.php` |
| Migration | `database/migrations/2026_04_10_100200_create_invoice_number_sequences_table.php` |
| New Model | `app/Models/TaxInvoice.php` |
| New Model | `app/Models/TaxCreditNote.php` |
| New Model | `app/Models/InvoiceNumberSequence.php` |
| New Service | `app/Services/Tax/TaxInvoiceService.php` |
| New Service | `app/Services/Tax/InvoiceNumberAllocator.php` (atomic, row-locked) |
| New Job | `app/Jobs/IssueTaxInvoiceJob.php` |
| New Controller | `app/Http/Controllers/Web/TaxInvoiceController.php` |
| New Blade | `resources/views/dashboard/tax-invoices/index.blade.php` |
| New Blade | `resources/views/dashboard/tax-invoices/show.blade.php` |
| New Blade | `resources/views/dashboard/tax-invoices/pdf.blade.php` |
| New Blade | `resources/views/dashboard/tax-invoices/credit-note-pdf.blade.php` |
| Edit | `app/Services/PaymentService.php` (hook to dispatch IssueTaxInvoiceJob) |
| Edit | `routes/web.php` (5 routes جديدة) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 60 مفتاح جديد) |

### Database schema
```sql
CREATE TABLE invoice_number_sequences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    series VARCHAR(8) NOT NULL DEFAULT 'INV',  -- INV / CN
    year SMALLINT NOT NULL,
    next_value INT NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_company_series_year (company_id, series, year),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE tax_invoices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(32) NOT NULL UNIQUE,  -- e.g., INV-2026-000123
    contract_id BIGINT NULL,
    payment_id BIGINT NULL,
    issue_date DATE NOT NULL,
    supply_date DATE NOT NULL,
    -- Supplier (issuer)
    supplier_company_id BIGINT NOT NULL,
    supplier_trn VARCHAR(32) NULL,
    supplier_name VARCHAR(255) NOT NULL,  -- snapshot
    supplier_address TEXT NULL,           -- snapshot
    -- Buyer (recipient)
    buyer_company_id BIGINT NOT NULL,
    buyer_trn VARCHAR(32) NULL,
    buyer_name VARCHAR(255) NOT NULL,     -- snapshot
    buyer_address TEXT NULL,
    -- Amounts
    line_items JSON NOT NULL,             -- [{description, qty, unit_price, discount, tax_rate, taxable_amount, tax_amount}]
    subtotal_excl_tax DECIMAL(15,2) NOT NULL,
    total_discount DECIMAL(15,2) DEFAULT 0,
    total_tax DECIMAL(15,2) NOT NULL,
    total_inclusive DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'AED',
    -- Document
    pdf_path VARCHAR(255) NULL,
    pdf_sha256 VARCHAR(64) NULL,
    -- Lifecycle
    status VARCHAR(16) NOT NULL DEFAULT 'issued',  -- issued | voided
    voided_at TIMESTAMP NULL,
    voided_by BIGINT NULL,
    void_reason TEXT NULL,
    issued_by BIGINT NULL,
    issued_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP, updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX (contract_id), INDEX (payment_id),
    INDEX (supplier_company_id), INDEX (buyer_company_id),
    INDEX (issue_date)
);

CREATE TABLE tax_credit_notes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    credit_note_number VARCHAR(32) NOT NULL UNIQUE,  -- e.g., CN-2026-000045
    original_invoice_id BIGINT NOT NULL,
    issue_date DATE NOT NULL,
    reason VARCHAR(64) NOT NULL,  -- refund / correction / cancellation / dispute_settlement
    line_items JSON NOT NULL,
    subtotal_excl_tax DECIMAL(15,2) NOT NULL,
    total_tax DECIMAL(15,2) NOT NULL,
    total_inclusive DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    notes TEXT NULL,
    pdf_path VARCHAR(255) NULL,
    pdf_sha256 VARCHAR(64) NULL,
    issued_by BIGINT NULL,
    issued_at TIMESTAMP NOT NULL,
    created_at, updated_at, deleted_at,
    FOREIGN KEY (original_invoice_id) REFERENCES tax_invoices(id),
    INDEX (issue_date)
);
```

### Sequential numbering — race condition safety
الـ `InvoiceNumberAllocator` يستخدم `SELECT ... FOR UPDATE` على الـ sequence row:

```php
DB::transaction(function () use ($companyId, $series) {
    $row = InvoiceNumberSequence::where([
        'company_id' => $companyId,
        'series'     => $series,
        'year'       => now()->year,
    ])->lockForUpdate()->firstOrCreate(['next_value' => 1]);

    $number = sprintf('%s-%d-%06d', $series, now()->year, $row->next_value);
    $row->increment('next_value');
    return $number;
});
```
لا يسمح بـ duplicates حتى تحت 1000+ concurrent invoice issues.

### Acceptance criteria
- [ ] إصدار فاتورة على Payment يولّد invoice number فريد بشكل atomic (test مع 100 concurrent jobs)
- [ ] الـ PDF يحتوي على كل عناصر FTA tax invoice (12 نقطة) — ✓ على كل واحدة
- [ ] الـ PDF bilingual (Arabic + English side-by-side أو safwt صفحة عربي ثم صفحة إنجليزي)
- [ ] الـ PDF موقّع بـ SHA-256 hash مخزّن على الـ DB
- [ ] إصدار credit note يربط بالـ original invoice
- [ ] لا يمكن حذف tax_invoice — فقط void مع reason
- [ ] الـ admin tab "Tax Invoices" بيعرض list + filter بالـ year, status, supplier
- [ ] PaymentService::markPaid() بيشغّل Job لإصدار الفاتورة تلقائياً

### Review checkpoint
1. هل الـ FTA template الفعلي اللي بتستخدمه متاح؟ (Comparing with their reference template at tax.gov.ae)
2. هل في `simplified tax invoice` use case (Article 60 — للـ B2C تحت AED 10,000)؟ ولا B2B بس؟
3. هل الـ PDF يحتاج QR code؟ (FTA Phase 1 يطلب QR code فيه: seller TRN, invoice number, timestamp, total, tax)
4. هل الـ company address snapshot نستخدم الـ current أم الـ at-time-of-issue؟

### Rollback plan
الـ migration بترجع 3 جداول بالكامل. الـ Payment lifecycle مش متغير في الـ schema، بس فيه trigger event جديد لـ Job. الـ Job بيكون في queue منفصل، فلو حصل issue، نقدر نوقف الـ queue من غير ما الـ payments نفسها تتأثر.

### الحجم: **M**

---

## 6. المرحلة 2 — PDPL Foundation 🔒

**الهدف**: تطبيق المتطلبات الأساسية لـ Federal Decree-Law 45/2021 (Personal Data Protection Law) — privacy notice، consent management، DSAR، right to erasure، breach notification skeleton، data residency declaration.

**ليه دلوقتي**: PDPL سارية من 2022. الـ enforcement بدأ تدريجي بس الغرامات تبدأ من **AED 50,000** وتصل لـ **AED 5M**. لو في data breach اليوم، التزام الـ 72-hour notification موجود من اليوم الأول.

### النطاق

**في النطاق:**
1. صفحة `/privacy` public بـ Privacy Policy bilingual
2. صفحة `/data-processing-agreement` (DPA) للـ enterprise customers
3. Cookie consent banner (PDPL مش GDPR لكن لازم explicit consent)
4. Settings page → "Privacy" tab فيها:
   - Download my data (DSAR)
   - Delete my account (right to erasure)
   - Consent log (شو وافقت عليه ومتى)
   - Withdraw consent for marketing
5. جدول `consents` للـ tracking
6. جدول `privacy_requests` (DSAR + erasure queue للـ admin)
7. `DataExportService::buildArchive(User)` يولّد ZIP فيه JSON + uploaded files
8. `DataErasureService::scheduleErasure(User, $delayDays = 30)` — soft schedule مع cooling period
9. `app/Notifications/DataBreachNotification.php` skeleton + admin command `php artisan privacy:report-breach`
10. `config/data_residency.php` — يصرّح بـ hosting region + adequacy basis
11. تشفير على مستوى الـ application للأعمدة الحساسة: `tax_number`, `id_number`, `bank_account_number`

**خارج النطاق:**
- Real cross-border SCC negotiation (ده شغل legal مش engineering)
- Encryption key rotation policy (مرحلة 8)
- DPO appointment (administrative)

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_11_100000_create_consents_table.php` |
| Migration | `database/migrations/2026_04_11_100100_create_privacy_requests_table.php` |
| Migration | `database/migrations/2026_04_11_100200_encrypt_sensitive_columns.php` (uses Laravel Encrypted casts) |
| New Models | `Consent.php`, `PrivacyRequest.php` |
| New Service | `app/Services/Privacy/DataExportService.php` |
| New Service | `app/Services/Privacy/DataErasureService.php` |
| New Service | `app/Services/Privacy/ConsentLedger.php` |
| New Job | `app/Jobs/ExecutePrivacyErasureJob.php` |
| New Controller | `app/Http/Controllers/Web/PrivacyController.php` |
| New Blades | `privacy/policy.blade.php`, `privacy/dpa.blade.php`, `dashboard/privacy/index.blade.php` |
| New Component | `<x-privacy.cookie-banner />` (renders in app layout) |
| New Notification | `app/Notifications/DataBreachNotification.php` |
| New Command | `app/Console/Commands/ReportDataBreachCommand.php` |
| New Config | `config/data_residency.php` |
| Edit | `app/Models/Company.php`, `app/Models/BeneficialOwner.php`, `app/Models/CompanyBankDetail.php` (encrypted casts) |
| Edit | `routes/web.php` (~ 8 routes) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 80 مفتاح ترجمة جديد + Privacy Policy text)

### Database schema
```sql
CREATE TABLE consents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    consent_type VARCHAR(64) NOT NULL,  -- privacy_policy, marketing, cookies_analytics, dpa
    version VARCHAR(16) NOT NULL,
    granted_at TIMESTAMP NULL,
    withdrawn_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at, updated_at,
    INDEX (user_id, consent_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE privacy_requests (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    request_type VARCHAR(32) NOT NULL,  -- data_export | erasure | rectification
    status VARCHAR(32) NOT NULL DEFAULT 'pending',  -- pending | in_review | approved | rejected | completed | withdrawn
    requested_at TIMESTAMP NOT NULL,
    scheduled_for TIMESTAMP NULL,  -- 30 days from request for erasure
    completed_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    fulfillment_metadata JSON NULL,  -- e.g., file path of exported archive
    handled_by BIGINT NULL,
    created_at, updated_at,
    INDEX (user_id, status),
    INDEX (scheduled_for)
);
```

### Encryption strategy
استخدام Laravel's `encrypted` cast على الأعمدة الحساسة:
```php
// app/Models/BeneficialOwner.php
protected function casts(): array {
    return [
        'id_number' => 'encrypted',
        'date_of_birth' => 'encrypted:date',
        'source_of_wealth' => 'encrypted',
    ];
}
```
الـ Laravel encryption بيستخدم AES-256-CBC + APP_KEY. الـ keys management باق على الـ infrastructure layer (AWS KMS أو Azure Key Vault للـ production).

### Acceptance criteria
- [ ] صفحة `/privacy` public + bilingual + لها unique URL ثابت
- [ ] Cookie banner بيظهر للزائر الجديد مرة واحدة، الموافقة بتسجل في `consents`
- [ ] User يقدر يضغط "Download my data" → خلال 30 ثانية، يحصل على ZIP file
- [ ] User يقدر يضغط "Delete my account" → request بتدخل queue، 30 يوم cooling، email confirmation كل 7 أيام
- [ ] Admin يقدر يفتح "Privacy Requests" tab يشوف المطلوبات pending
- [ ] الأعمدة الحساسة encrypted في الـ DB (verify بـ raw query)
- [ ] `php artisan privacy:report-breach --severity=high --affected=120 --description="..."` يفعّل DataBreachNotification لكل admin
- [ ] `config/data_residency.php` فيه `region`, `adequacy_basis`, `scc_signed_at`
- [ ] PrivacyController routes كلها محمية بـ auth + rate limiting

### Review checkpoint
1. هل الـ Privacy Policy text محتاج مراجعة legal؟ (نعم، definitely — مش engineering decision)
2. هل المستخدم يقدر يحذف حسابه إذا كان عنده active contracts/disputes؟ (الإجابة: لأ — block + قول له ليه)
3. هل الـ admin يقدر يحذف user data بدون إذن المستخدم؟ (compliance: only on legal order — track in audit log)
4. الـ data residency: AWS Bahrain أم Azure UAE أم on-prem؟

### Rollback plan
كل الـ migrations reversible. الـ encryption casts بترجع plain text لو شيلت الـ cast (طول ما الـ APP_KEY ما اتغيرتش). الـ middleware للـ cookie banner يتشال من الـ layout.

### الحجم: **M**

---

## 7. المرحلة 3 — Free Zone & Jurisdiction Awareness 🏛️

**الهدف**: تمييز الشركات الـ Free Zone عن الـ Mainland، وتطبيق القانون الصحيح في العقود تلقائياً (Federal vs DIFC vs ADGM).

**ليه دلوقتي**: شركات DIFC و ADGM عندهم common-law system منفصل عن القانون الفيدرالي. أي عقد بين شركتين DIFC تلقائياً بـ federal civil law clauses هو عقد ضعيف قانونياً ومش enforceable في DIFC Courts بدون إعادة كتابة.

### النطاق

**في النطاق:**
1. إضافة `is_free_zone`, `free_zone_authority`, `legal_jurisdiction`, `is_designated_zone` على `companies`
2. Enum: `LegalJurisdiction { FEDERAL, DIFC, ADGM }`
3. Enum: `FreeZoneAuthority { DAFZA, JAFZA, DMCC, ADGM, DIFC, KIZAD, SAIF, RAKEZ, FUJAIRAH_FREE_ZONE, MASDAR, ... }`
4. ContractService::buildContractTerms يصبح dispatcher على `legal_jurisdiction`:
   - `FEDERAL` → الـ clauses الحالية (Federal Decree-Law 50/2022 + DIAC arbitration)
   - `DIFC` → DIFC Contract Law clauses + DIFC Courts jurisdiction + DIFC-LCIA replaced by DIAC
   - `ADGM` → ADGM Application Regulations + ADGM Courts jurisdiction
5. VAT logic awareness:
   - لو الـ supplier و buyer كلاهما في Designated Zones (DAFZA, JAFZA, KIZAD, إلخ) → VAT 0% on goods (services still 5%)
   - لو الـ buyer Designated Zone و الـ supplier mainland → reverse charge mechanism applies
   - الـ submit-bid form يعرض warning hint عند تطابق هذه الحالات
6. Registration form (`auth/register.blade.php`) يطلب اختيار Free Zone أم Mainland + اختيار الـ authority لو Free Zone
7. Admin verification queue يضيف validation step: "هل الـ trade license من فعلاً الـ authority اللي اختارها؟"
8. Public supplier directory يعرض free zone badge

**خارج النطاق:**
- Free zone-specific document upload (e.g. FZE/FZ-LLC differences) → مرحلة 8
- Cross-zone trade restrictions enforcement → مرحلة 7

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_12_100000_add_free_zone_to_companies.php` |
| New Enum | `app/Enums/LegalJurisdiction.php` |
| New Enum | `app/Enums/FreeZoneAuthority.php` |
| Edit | `app/Models/Company.php` (fillable + casts) |
| Edit | `app/Services/ContractService.php` (jurisdiction-aware buildContractTerms) |
| Edit | `app/Services/ContractService.php` (build*Terms variants for DIFC, ADGM) |
| Edit | `app/Http/Controllers/Web/Auth/RegisterController.php` |
| Edit | `app/Http/Controllers/Web/Admin/CompanyController.php` (verification UI) |
| Edit | `resources/views/auth/register.blade.php` |
| Edit | `resources/views/dashboard/admin/companies/edit.blade.php` |
| Edit | `resources/views/public/suppliers.blade.php` (badge) |
| Edit | `resources/views/dashboard/rfqs/submit-bid.blade.php` (designated zone hint) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 100 مفتاح: free zone names, jurisdictions, contract clauses for DIFC/ADGM)

### Database schema
```sql
ALTER TABLE companies
  ADD COLUMN is_free_zone BOOLEAN NOT NULL DEFAULT 0 AFTER country,
  ADD COLUMN free_zone_authority VARCHAR(64) NULL AFTER is_free_zone,
  ADD COLUMN is_designated_zone BOOLEAN NOT NULL DEFAULT 0 AFTER free_zone_authority,
  ADD COLUMN legal_jurisdiction VARCHAR(16) NOT NULL DEFAULT 'federal' AFTER is_designated_zone,
  ADD INDEX idx_free_zone (is_free_zone),
  ADD INDEX idx_jurisdiction (legal_jurisdiction);
```

### Acceptance criteria
- [ ] أي شركة جديدة لازم تختار FZ vs Mainland في الـ registration
- [ ] الشركات الموجودة قبل الـ migration كلها `is_free_zone = false, jurisdiction = federal`
- [ ] عقد بين 2 شركات DIFC يحتوي على DIFC Courts jurisdiction (مش UAE federal courts)
- [ ] عقد بين 2 شركات Designated Zone (e.g., DAFZA-DAFZA) يحتوي على VAT 0% clause
- [ ] الـ submit-bid form يعرض banner لما الـ buyer في designated zone و الـ supplier في mainland: "Reverse charge mechanism may apply"
- [ ] Public supplier listing يعرض FZ badge مع اسم الـ authority
- [ ] DIFC + ADGM contract templates مراجعتها legal counsel

### Review checkpoint
1. هل الـ DIFC و ADGM clauses الجديدة محتاجة legal counsel review؟ (نعم — definitely)
2. هل الـ Mainland → Free Zone supply VAT logic صح؟ (في 4 cases، confirm with FTA reference)
3. هل في FZ authorities ناقصة من الـ list؟

### Rollback plan
الـ migration reversible. لو الـ ContractService dispatch failed، يفول-باك للـ federal default. الـ existing contracts لا تتأثر (الـ jurisdiction column nullable في الـ contracts table، fallback = federal).

### الحجم: **S**

---

## 8. المرحلة 4 — ICV (In-Country Value) Scoring 🇦🇪

**الهدف**: دعم الـ ICV scoring اللي اعتمدها MoIAT والشركات الحكومية الإماراتية الكبيرة (ADNOC, Mubadala, EGA, EWEC, ETIHAD, Masdar, EMSTEEL).

**ليه دلوقتي**: ده الـ gating factor للقطاع الحكومي. بدون ICV support، المنصة عندها سقف واضح: B2B خاص فقط. مع ICV، تنفتح أبواب لشركات بتشتري بـ مليارات الدراهم سنوياً.

### النطاق

**في النطاق:**
1. جدول `icv_certificates` لكل شركة + linked PDF document
2. Admin verification queue للـ certificates
3. حقل `icv_weight_percentage` على الـ RFQ — الـ buyer يقدر يحدد الوزن (0-50%)
4. Bid evaluation يحسب composite score:
   ```
   total_score = (1 - icv_weight) × price_score + icv_weight × icv_score
   price_score = 100 × (lowest_bid / this_bid)
   icv_score = bid.company.latest_active_icv.score
   ```
5. `compare-bids` view يضيف عمود "ICV Score" + ranking column
6. صفحة supplier profile تعرض الـ ICV badge + score + expiry
7. Notification للـ supplier لما الـ ICV cert قارب على الانتهاء (60 يوم)
8. Filter في الـ Suppliers directory: "Min ICV ≥ 30%"

**خارج النطاق:**
- Auto-calculation of ICV from supplier's spend data → مرحلة 8 (يحتاج عام كامل من الـ data)
- Integration with MoIAT API (لو موجود) → مرحلة 8
- ADNOC ICV vs Mubadala ICV (each issuer has slightly different methodology) → نتعامل معاهم كـ separate certificates

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_13_100000_create_icv_certificates_table.php` |
| Migration | `database/migrations/2026_04_13_100100_add_icv_to_rfqs.php` |
| New Model | `app/Models/IcvCertificate.php` |
| New Service | `app/Services/Procurement/IcvScoringService.php` |
| New Controller | `app/Http/Controllers/Web/IcvCertificateController.php` |
| Edit | `app/Models/Company.php` (relationship + helper) |
| Edit | `app/Models/Rfq.php` (icv_weight_percentage) |
| Edit | `app/Http/Controllers/Web/RfqController.php` (compareBids) |
| New Blade | `resources/views/dashboard/icv-certificates/index.blade.php` |
| New Blade | `resources/views/dashboard/icv-certificates/upload.blade.php` |
| Edit | `resources/views/dashboard/rfqs/compare-bids.blade.php` |
| Edit | `resources/views/dashboard/rfqs/create.blade.php` (ICV weight slider) |
| Edit | `resources/views/dashboard/admin/companies/edit.blade.php` (verify ICV) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 50 مفتاح ترجمة)

### Database schema
```sql
CREATE TABLE icv_certificates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT NOT NULL,
    certificate_number VARCHAR(64) NOT NULL,
    issuer VARCHAR(64) NOT NULL,  -- MoIAT | ADNOC | Mubadala | EGA | EWEC | ETIHAD | Other
    score DECIMAL(5,2) NOT NULL,  -- 0.00 to 100.00 (e.g. 38.45)
    issued_date DATE NOT NULL,
    expires_date DATE NOT NULL,
    file_path VARCHAR(255) NULL,
    file_sha256 VARCHAR(64) NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',  -- pending | verified | rejected | expired
    rejection_reason TEXT NULL,
    verified_by BIGINT NULL,
    verified_at TIMESTAMP NULL,
    created_at, updated_at, deleted_at,
    UNIQUE KEY uniq_company_issuer_year (company_id, issuer, certificate_number),
    INDEX (company_id, status),
    INDEX (expires_date)
);

ALTER TABLE rfqs
  ADD COLUMN icv_weight_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER currency,
  ADD COLUMN icv_minimum_score DECIMAL(5,2) NULL AFTER icv_weight_percentage;
```

### Bid scoring formula
```php
public function calculateCompositeScore(Bid $bid, Collection $allBidsOnRfq): float {
    $rfq = $bid->rfq;
    $weight = (float) ($rfq->icv_weight_percentage ?? 0) / 100;

    if ($weight === 0.0) {
        return 100.0; // ICV not weighted, return neutral
    }

    // Price score: lowest bid gets 100, others scale inversely
    $lowest = $allBidsOnRfq->min('price');
    $priceScore = $lowest > 0 ? round(($lowest / $bid->price) * 100, 2) : 0;

    // ICV score: most recent verified active certificate
    $icv = $bid->company->icvCertificates()
        ->where('status', 'verified')
        ->where('expires_date', '>=', now())
        ->orderByDesc('issued_date')
        ->first();

    $icvScore = $icv ? (float) $icv->score : 0.0;

    return round((1 - $weight) * $priceScore + $weight * $icvScore, 2);
}
```

### Acceptance criteria
- [ ] Supplier يقدر يرفع ICV certificate من supplier dashboard
- [ ] Admin يقدر يـ verify/reject + يكتب reason
- [ ] Buyer يقدر يضع ICV weight slider في الـ RFQ create form
- [ ] Compare-bids يعرض column "ICV Score" + composite score + ranks reordered
- [ ] Supplier directory يعرض ICV badge + min score filter
- [ ] الـ certificate المنتهي يتعرض كـ "Expired" + لا يدخل في scoring
- [ ] Notification للـ supplier 60 يوم قبل الانتهاء + 30 يوم + 7 أيام
- [ ] Audit log: كل verify/reject/expire event

### Review checkpoint
1. هل الـ ICV score formula الصح؟ (راجع mع MoIAT methodology PDF)
2. هل في حالات حيث الـ RFQ يحتاج minimum ICV score مع اختيار disqualify automatic للموردين تحت الحد؟
3. هل الـ rejection_reason يحتاج preset list أم free text؟

### Rollback plan
Migration reversible. الـ scoring يتعطل لو weight = 0 (default). الـ existing RFQs و bids لا تتأثر.

### الحجم: **M**

---

## 9. المرحلة 5 — e-Invoicing Skeleton 📡

**الهدف**: بناء الـ abstraction layer + mock implementation لـ Peppol PINT-AE حتى تكون المنصة جاهزة لـ FTA Phase 1 في يوليو 2026 بـ مجرد كتابة provider واحد جديد، مش refactor كامل.

**ليه دلوقتي**: لأن لما الموعد يقرب، كل المنصات هتتسابق على نفس الـ ASP providers. الانتظار = مخاطرة. الـ skeleton الآن = تأمين على الجدول الزمني.

### النطاق

**في النطاق:**
1. جدول `e_invoice_submissions` لكل tax_invoice
2. `AspProviderInterface` — abstraction لـ Accredited Service Providers
3. `MockAspProvider` — يولد UBL XML سليم محلياً + acknowledgment fake
4. `PintAeMapper::toUbl(TaxInvoice)` — يحول الـ TaxInvoice لـ UBL 2.1 XML بـ PINT-AE schema
5. `EInvoiceDispatchJob` — async job يبعت للـ ASP و يخزن الـ acknowledgment
6. لما الـ TaxInvoice تتصدر (المرحلة 1)، تلقائياً Job يتطلق للـ submission
7. Admin tab "e-Invoice Submissions" بيعرض status + retry button
8. Webhook endpoint `/api/webhooks/e-invoice/asp/{provider}` لاستقبال async acknowledgments
9. Config switch `EINVOICE_ENABLED` (default off حتى الـ go-live)

**خارج النطاق:**
- Real ASP integration (Avalara/Sovos/Pagero) → نتعامل معها لما يكون عقد ASP موقّع
- B2C simplified e-invoice → out of scope
- B2G dedicated channel (gov entities) → out of scope حالياً

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_14_100000_create_e_invoice_submissions_table.php` |
| New Model | `app/Models/EInvoiceSubmission.php` |
| New Interface | `app/Services/EInvoice/AspProviderInterface.php` |
| New | `app/Services/EInvoice/MockAspProvider.php` |
| New | `app/Services/EInvoice/AvalaraAspProvider.php` (skeleton, throws if config missing) |
| New | `app/Services/EInvoice/SovosAspProvider.php` (skeleton) |
| New | `app/Services/EInvoice/PintAeMapper.php` |
| New | `app/Services/EInvoice/EInvoiceDispatcher.php` |
| New | `app/Jobs/SubmitEInvoiceJob.php` |
| New | `app/Http/Controllers/Api/EInvoiceWebhookController.php` |
| New Blade | `resources/views/dashboard/admin/e-invoice/index.blade.php` |
| Edit | `app/Services/Tax/TaxInvoiceService.php` (dispatch job after issue) |
| Edit | `routes/api.php` (webhook routes) |
| New Config | `config/einvoice.php` |
| Edit | `lang/en.json` و `lang/ar.json` (~ 30 مفتاح ترجمة)

### Database schema
```sql
CREATE TABLE e_invoice_submissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tax_invoice_id BIGINT NOT NULL,
    asp_provider VARCHAR(32) NOT NULL,  -- mock | avalara | sovos | pagero | tradeshift | comarch
    asp_environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',  -- sandbox | production
    status VARCHAR(16) NOT NULL DEFAULT 'queued',  -- queued | submitted | accepted | rejected | failed
    payload_xml LONGTEXT NULL,
    payload_sha256 VARCHAR(64) NULL,
    asp_submission_id VARCHAR(255) NULL,
    asp_acknowledgment_id VARCHAR(255) NULL,
    fta_clearance_id VARCHAR(255) NULL,
    asp_response_raw JSON NULL,
    error_message TEXT NULL,
    submitted_at TIMESTAMP NULL,
    acknowledged_at TIMESTAMP NULL,
    retries SMALLINT DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    created_at, updated_at,
    INDEX (tax_invoice_id),
    INDEX (status, next_retry_at),
    FOREIGN KEY (tax_invoice_id) REFERENCES tax_invoices(id) ON DELETE CASCADE
);
```

### Acceptance criteria
- [ ] إصدار TaxInvoice (من المرحلة 1) يدفع SubmitEInvoiceJob للـ queue
- [ ] MockAspProvider يولد UBL XML valid (تـ schema validate ضد UBL 2.1)
- [ ] الـ mock يرجع acknowledgment fake بعد 2-5 ثواني
- [ ] الـ submission record بيتحدث لـ accepted
- [ ] Webhook endpoint بيتقبل callbacks من الـ provider (للـ async)
- [ ] Admin يقدر يـ retry failed submissions
- [ ] Config flag `EINVOICE_ENABLED=false` بيوقف الـ pipeline بدون errors
- [ ] Avalara و Sovos providers throw `NotImplementedException` مع رسالة واضحة "Configure provider before enabling"

### Review checkpoint
1. ASP provider اللي هنشتغل معاه: نختار كده بسرعة أم نشوف pricing comparison أول؟
2. هل الـ XML payload نخزّنه في DB أو في object storage (S3)؟ (S3 بيحفظ DB size، DB أسهل للـ query)
3. هل في GCC e-invoicing standards مختلفة (السعودية ZATCA, البحرين, إلخ) محتاجين نخطط لها هنا أم separate phase؟

### Rollback plan
كل الـ schema reversible. الـ feature gated خلف config flag. لو في issue، تعطيل الـ flag يوقف كل الـ submissions بدون أي رفت في الـ TaxInvoice الأساسي.

### الحجم: **L**

---

## 10. المرحلة 6 — Qualified e-Signature & UAE Pass 🔐

**الهدف**: ترقية الـ e-signature implementation من Simple إلى **Advanced** + **Qualified** حسب Federal Decree-Law 46/2021. تكامل مع UAE Pass للأفراد و TSP معتمد للشركات.

**ليه دلوقتي**: لو في عقد حكومي أو عقد > AED 500K، التوقيع الحالي مش enforceable في المحكمة بدون evidence إضافي. UAE Pass هو الحل الأرخص (مجاني للأفراد) والأسهل.

### النطاق

**في النطاق:**
1. Enum: `SignatureGrade { SIMPLE, ADVANCED, QUALIFIED }`
2. ContractService يحدد الـ required grade تلقائياً based on:
   - Total value (> 500K → advanced minimum)
   - Counterparty (government entity → qualified required)
   - Category (real estate/insurance → qualified required)
3. UAE Pass OAuth integration:
   - Redirect flow: contract show → "Sign with UAE Pass" → UAE Pass login → callback → signature recorded
   - Signed payload includes UAE Pass user identifier + government-verified Emirates ID
4. TSP abstraction `TrustServiceProviderInterface`:
   - `Comtrust`, `ESSP`, `DigitalCert` (skeletons)
   - Each accepts contract hash + signer cert, returns CAdES/PAdES signature
5. Schema additions to `contract_signatures`: `signature_grade`, `tsp_provider`, `tsp_certificate_id`, `uae_pass_user_id`, `signature_payload`, `signature_format` (CMS/CAdES/PAdES)
6. Verification page `/contracts/{id}/verify` — public endpoint anyone can use to validate signature integrity (لو محكمة طلبت)
7. Long-Term Validation (LTV) — embed timestamp authority response so signature stays valid even after cert expiry

**خارج النطاق:**
- Comtrust/ESSP commercial agreements (legal/business)
- HSM (Hardware Security Module) integration for very high-value contracts → مرحلة 8
- Multi-party signature ordering (already exists in `signatures` JSON)

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_15_100000_add_signature_grade_to_contracts.php` |
| New Enum | `app/Enums/SignatureGrade.php` |
| New Interface | `app/Services/Signing/TrustServiceProviderInterface.php` |
| New | `app/Services/Signing/UaePassProvider.php` (real OAuth integration) |
| New | `app/Services/Signing/ComtrustTspProvider.php` (skeleton) |
| New | `app/Services/Signing/EsspTspProvider.php` (skeleton) |
| New | `app/Services/Signing/SignatureGradeResolver.php` (decides required grade per contract) |
| Edit | `app/Services/PkiService.php` (delete HMAC fallback if not done in Phase 0) |
| Edit | `app/Services/ContractService.php` (sign() method becomes grade-aware) |
| Edit | `app/Http/Controllers/Web/ContractController.php` (UAE Pass redirect/callback) |
| New Controller | `app/Http/Controllers/Public/SignatureVerifyController.php` |
| New Routes | `/contracts/{id}/sign/uae-pass`, `/contracts/{id}/sign/uae-pass/callback`, `/contracts/{id}/verify` |
| Edit | `resources/views/dashboard/contracts/show.blade.php` (signature CTAs) |
| New Blade | `resources/views/public/contracts/verify.blade.php` (public signature verification) |
| New Config | `config/uae_pass.php` |
| Edit | `lang/en.json` و `lang/ar.json` (~ 60 مفتاح)

### Acceptance criteria
- [ ] العقد > 500K AED بيتطلب grade=advanced كحد أدنى
- [ ] العقد مع government buyer بيتطلب grade=qualified
- [ ] الـ supplier/buyer يقدر يضغط "Sign with UAE Pass" — redirect صحيح
- [ ] بعد العودة من UAE Pass، الـ signature record بيتسجل بـ uae_pass_user_id + verified إماراتي
- [ ] صفحة `/contracts/{id}/verify` public — أي حد عنده الـ link يقدر يتأكد من سلامة التوقيع (مش محتاج login)
- [ ] الـ signature payload فيه timestamp من timestamp authority
- [ ] LTV: لو الـ cert اللي اتوقع بيها انتهت، الـ signature لسه bytes verifiable بسبب الـ embedded timestamp + crl/ocsp response
- [ ] PkiService::HMAC fallback مشال (إذا لم يكن في Phase 0)

### Review checkpoint
1. UAE Pass production credentials: مين هياخدها؟ تحتاج company registration مع TDRA
2. هل في cases حيث grade=qualified مطلوب لكن TSP عقد لسه ما اتعمل؟ نعرض warning أم نمنع التوقيع؟
3. PAdES vs CAdES vs XAdES: أيهما الأنسب؟ (الجواب: PAdES للـ PDFs لأنه embedded في الملف)

### Rollback plan
الـ Migration nullable. التوقيع الحالي لسه يشتغل لو الـ UAE Pass providers ما اتـ configure-وش. الـ feature flag `UAE_PASS_ENABLED=false` يعطل redirect ويخلي السلوك القديم default.

### الحجم: **L**

---

## 11. المرحلة 7 — Corporate Tax + Anti-Collusion 💼

**الهدف**: 
- (أ) دعم Corporate Tax (9% على الأرباح فوق AED 375,000) registration tracking + invoice annotations.
- (ب) Anti-collusion detection patterns لحماية المنصة من Federal Decree-Law 36/2023 (Competition Law).

### النطاق

**في النطاق:**
1. إضافة `corporate_tax_number`, `corporate_tax_status` على `companies`
2. Tracking لـ Qualifying Free Zone Person (QFZP) — يستفيد من 0% CT
3. Annotation على tax invoices: "Supplier is QFZP" / "Supplier is exempt below threshold"
4. `AntiCollusionService::analyzeRfq(Rfq)` يفحص:
   - Multiple bids من نفس IP address
   - Multiple bids من شركات بنفس beneficial owner
   - Multiple bids من شركات بنفس phone number/email domain
   - Negotiation messages بين موردين على نفس RFQ open
5. Admin alert لما الـ pattern detection يحدد suspected collusion
6. Audit log لكل anti-collusion check
7. كل anti-collusion event يضاف لـ audit_logs

**خارج النطاق:**
- Real CT calculation (CT بيتم annual، مش per-transaction)
- CT return generation
- Withholding tax (currently 0% in UAE, may change)

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `database/migrations/2026_04_16_100000_add_corporate_tax_to_companies.php` |
| New Service | `app/Services/Procurement/AntiCollusionService.php` |
| New Job | `app/Jobs/AnalyzeRfqForCollusionJob.php` (runs after RFQ closes) |
| New Notification | `app/Notifications/SuspectedCollusionNotification.php` |
| Edit | `app/Models/Company.php` |
| Edit | `app/Services/Tax/TaxInvoiceService.php` (annotation) |
| Edit | `app/Http/Controllers/Web/Admin/CompanyController.php` (CT verification) |
| New Blade | `resources/views/dashboard/admin/anti-collusion/index.blade.php` |
| Edit | `resources/views/dashboard/admin/companies/edit.blade.php` (CT fields) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 40 مفتاح)

### Database schema
```sql
ALTER TABLE companies
  ADD COLUMN corporate_tax_number VARCHAR(32) NULL AFTER tax_number,
  ADD COLUMN corporate_tax_status VARCHAR(32) NOT NULL DEFAULT 'unknown' AFTER corporate_tax_number,
    -- registered | exempt_below_threshold | qfzp | not_registered | unknown
  ADD COLUMN corporate_tax_registered_at DATE NULL AFTER corporate_tax_status,
  ADD INDEX idx_corporate_tax_status (corporate_tax_status);
```

### Anti-collusion patterns
```php
public function analyzeRfq(Rfq $rfq): Collection {
    $patterns = collect();
    $bids = $rfq->bids()->with(['company.beneficialOwners', 'provider'])->get();

    // Pattern 1: Shared login IPs
    $ipGroups = $bids->groupBy(fn ($b) => $b->provider?->last_login_ip);
    foreach ($ipGroups as $ip => $group) {
        if ($ip && $group->count() > 1) {
            $patterns->push([
                'type' => 'shared_ip',
                'severity' => 'high',
                'evidence' => ['ip' => $ip, 'bid_ids' => $group->pluck('id')],
            ]);
        }
    }

    // Pattern 2: Shared beneficial owners
    $boGroups = $bids->flatMap(fn ($b) =>
        $b->company->beneficialOwners->map(fn ($bo) => [
            'bid_id' => $b->id, 'id_number' => $bo->id_number
        ])
    )->groupBy('id_number');
    foreach ($boGroups as $idNum => $group) {
        if ($idNum && $group->unique('bid_id')->count() > 1) {
            $patterns->push([
                'type' => 'shared_beneficial_owner',
                'severity' => 'critical',
                'evidence' => ['id_number_hash' => sha1($idNum), 'bid_ids' => $group->pluck('bid_id')],
            ]);
        }
    }

    // Pattern 3: Email domain overlap (excluding generic providers)
    // Pattern 4: Negotiation messages between bidders
    // Pattern 5: Submission timing clustering (within 10 min window)
    // ...

    return $patterns;
}
```

### Acceptance criteria
- [ ] Company edit form يظهر CT registration fields
- [ ] Tax invoice PDF يضيف annotation عند QFZP supplier
- [ ] AnalyzeRfqForCollusionJob يشتغل بعد قفل الـ RFQ
- [ ] لو في pattern critical-severity، notification للـ admin team
- [ ] Admin tab "Anti-Collusion Alerts" يعرض كل الـ patterns الـ outstanding
- [ ] الـ admin يقدر يضع label "False Positive" أو "Investigating" أو "Confirmed - Action Taken"
- [ ] كل event مسجل في audit log
- [ ] BO id_number hashed في الـ evidence (مش plain text — PDPL)

### Review checkpoint
1. الـ severity thresholds: ما هي الـ rules اللي auto-block bid vs alert فقط؟
2. هل في false positive risk عالي على shared IP (موردين شغالين من نفس co-working space)؟
3. هل نضيف appeal mechanism للموردين اللي اتسموا collusion؟

### Rollback plan
الـ jobs في queue منفصل، نقدر نوقفه بدون رفت على الـ RFQs نفسها. الـ schema additive (nullable).

### الحجم: **M**

---

## 12. المرحلة 8 — Tier 3 Polish 🎯

**الهدف**: استكمال المتطلبات الـ tier 3 — certificates upload (CoO, ECAS, Halal), NESA hardening، WPS integration hooks، Encryption key rotation، WORM external storage.

**ليه دلوقتي**: المنصة لازم تكون قادرة تحمل enterprise audit بكل التفاصيل — ده بيقفل الفجوات اللي ممكن enterprise procurement team تسأل عنها لكن مش blocking.

### النطاق

**في النطاق:**
1. **Document upload extensions**:
   - Certificate of Origin (CoO) per shipment
   - ECAS (Emirates Conformity Assessment Scheme) per product
   - Halal certification per product/category
   - GSO (GCC Standardization Organization) certificate per product
2. **NESA hardening** (Information Assurance Standards):
   - Security headers (HSTS, CSP, X-Frame-Options) middleware
   - Rate limiting على كل sensitive endpoints
   - Brute-force protection على login (already exists in fortify? verify)
   - IP allow-listing للـ admin routes
3. **External WORM storage** (S3 Object Lock):
   - Audit log archival job (extends Phase 0)
   - Daily Merkle root anchored to AWS QLDB أو OpenTimestamps
4. **Encryption key rotation policy**:
   - APP_KEY rotation procedure documented
   - Re-encrypt sensitive columns command
5. **WPS integration hooks**:
   - Webhook endpoint to receive WPS payment confirmations (لو في مستخدمي labor force tracking)
6. **Customs duty calculation**:
   - GCC origin → 0% intra-GCC duty
   - Non-GCC → 5% common external tariff (HS-code aware exceptions)

### المخرجات

| النوع | الملف |
|---|---|
| Migration | `2026_04_17_100000_create_certificate_uploads_table.php` |
| Migration | `2026_04_17_100100_add_security_settings_to_companies.php` |
| New Model | `app/Models/CertificateUpload.php` |
| New Service | `app/Services/Compliance/CertificateService.php` |
| New Service | `app/Services/Customs/DutyCalculatorService.php` |
| New Middleware | `app/Http/Middleware/SecurityHeaders.php` |
| New Middleware | `app/Http/Middleware/AdminIpAllowlist.php` |
| New Command | `app/Console/Commands/RotateEncryptionKeyCommand.php` |
| New Command | `app/Console/Commands/AnchorAuditChainCommand.php` |
| Edit | `app/Console/Commands/ArchiveAuditLogsCommand.php` (now active, S3 Object Lock) |
| New Provider | `app/Providers/SecurityServiceProvider.php` |
| Edit | `bootstrap/app.php` (register middleware) |
| Edit | `lang/en.json` و `lang/ar.json` (~ 50 مفتاح)

### Acceptance criteria
- [ ] Supplier يقدر يرفع CoO/ECAS/Halal certs مع expiry dates
- [ ] Buyer يقدر يفلتر الموردين بالـ certifications المطلوبة
- [ ] Admin verification queue للـ certs الجديدة
- [ ] Security headers active على كل HTTP responses
- [ ] Login route عليه rate limit 5/min per IP
- [ ] Admin routes ممكن تتقيد بـ IP allowlist (config-driven)
- [ ] `php artisan audit:anchor-chain` يطلع Merkle root + يخزنه في S3 Object Lock + OpenTimestamps
- [ ] `php artisan audit:archive --before=2024-01-01` ينقل القديم لـ S3 Object Lock مع 7-year retention
- [ ] Customs duty calculator يرجع الصح لـ test cases (intra-GCC, non-GCC, exempted HS codes)

### Review checkpoint
1. هل في NESA audit جاي مع government contract؟
2. AWS region للـ Object Lock storage: Bahrain أم UAE Dubai؟
3. WPS integration: هل نحتاجها فعلاً (المنصة دي procurement مش HR)؟

### Rollback plan
كل المكونات additive. الـ middleware يتعطل بـ env flag. الـ key rotation يتم في maintenance window.

### الحجم: **M**

---

## 13. الـ Cross-Cutting Concerns

كل المراحل بتشترك في الـ concerns دي. لازم تكون mind-y عند تنفيذ كل مرحلة:

### Translation Keys
- كل phase بيضيف ~30-100 مفتاح ترجمة
- الـ keys لازم تكون موجودة في en.json و ar.json قبل merge
- استخدم `php artisan lang:sync` (لو موجود) أو افحص يدوياً
- نص العقود القانوني (term_*) لازم review من legal counsel للـ AR translations

### Tests
- كل phase ينهي بـ feature tests للـ happy path + 2-3 edge cases
- لازم Test الـ migration up + down لكل phase
- AntiCollusion + Audit chain محتاجين extra-careful tests (security implications)

### Audit Log Coverage
كل المراحل لازم تضيف entries في audit_logs لـ:
- إصدار/إلغاء tax invoice
- إصدار credit note
- DSAR submitted
- DSAR fulfilled
- Account erasure scheduled
- ICV cert verified/rejected
- Free zone status changed
- Anti-collusion alert raised
- Signature verification accessed (public endpoint)

### i18n / RTL
- كل blade فيه `text-end` للـ RTL، `start-*` بدل `left-*`
- الـ PDFs لازم تدعم Arabic font (DejaVu Sans, Amiri, أو Cairo)
- الـ contract clauses لازم تكون متطابقة في الـ EN و AR (مش paraphrased)

### Backwards Compatibility
- كل migration nullable
- كل service فيها fallback للـ legacy data
- الـ ContractService بقى يدير 4-5 paths ممكن (legacy contract, new VAT-aware, free zone DIFC, free zone ADGM, government)

---

## 14. سجل المخاطر

| # | المخاطرة | الاحتمالية | الأثر | المرحلة | التخفيف |
|---|---|---|---|---|---|
| 1 | تأخر الـ Tax Invoice schema يلغّي إمكانية إصدار فواتير قانونية | متوسط | عالي | 1 | الـ Phase 1 priority واضحة، schema موافق عليها قبل التنفيذ |
| 2 | PDPL audit يحصل قبل Phase 2 ينتهي | منخفض | عالي جداً (5M AED) | 2 | بدء Phase 2 على الفور بعد Phase 1 + privacy policy public حتى لو الـ DSAR backend لسه قيد التنفيذ |
| 3 | FTA Phase 1 e-invoicing يبدأ ولسه ASP اختيار ما اتمش | متوسط | حرج | 5 | Phase 5 skeleton بيخلي الـ provider plug-and-play |
| 4 | UAE Pass production credentials registration بياخد وقت | عالي | متوسط | 6 | ابدأ تسجيل UAE Pass dev account من الآن (2-4 أسابيع) |
| 5 | DIFC/ADGM clauses غلط تنفقد contracts قانونياً | منخفض | عالي | 3 | Legal counsel review قبل merge |
| 6 | ICV scoring formula يطلع غلط lawsuit من supplier خسر | منخفض | متوسط | 4 | المحاكاة على real bid data + buyer override option |
| 7 | Anti-collusion false positives تسبب complaints | متوسط | متوسط | 7 | "Investigating" status + appeals workflow |
| 8 | Encryption key loss after rotation = lost data | منخفض | حرج | 8 | KMS بـ versioned keys + DR plan documented |
| 9 | Audit chain hash mismatch بيجبر replay من scratch | منخفض | عالي | 0 | كل event بـ id-based replay، مش timestamp-based |
| 10 | Free zone vs mainland classification غلط يخسر شركة الاستفادة الضريبية | منخفض | متوسط | 3 | Admin verification step قبل activation |

---

## 15. مسرد المصطلحات (Glossary)

| المصطلح | المعنى |
|---|---|
| **FTA** | Federal Tax Authority — الجهة الإماراتية الرسمية للضرائب |
| **TRN** | Tax Registration Number — رقم تسجيل ضريبي 15-digit، إجباري للفاتورة الضريبية |
| **VAT** | Value Added Tax — ضريبة القيمة المضافة 5% |
| **CT** | Corporate Tax — ضريبة الشركات 9% فوق AED 375K |
| **PDPL** | Personal Data Protection Law — Federal Decree-Law 45/2021 |
| **DSAR** | Data Subject Access Request — حق المستخدم في طلب نسخة من بياناته |
| **DPA** | Data Processing Agreement |
| **AML** | Anti-Money Laundering |
| **CFT** | Combating the Financing of Terrorism |
| **UBO** | Ultimate Beneficial Owner — Cabinet Resolution 58/2020 |
| **STR/SAR** | Suspicious Transaction/Activity Report (goAML system) |
| **PEP** | Politically Exposed Person |
| **DIFC** | Dubai International Financial Centre — common-law jurisdiction |
| **ADGM** | Abu Dhabi Global Market — common-law jurisdiction |
| **DMCC** | Dubai Multi Commodities Centre — Free Zone |
| **JAFZA** | Jebel Ali Free Zone Authority |
| **DAFZA** | Dubai Airport Free Zone Authority |
| **ICV** | In-Country Value — government procurement scoring methodology |
| **MoIAT** | Ministry of Industry and Advanced Technology |
| **DIAC** | Dubai International Arbitration Centre |
| **TSP** | Trust Service Provider — accredited e-signature provider |
| **TDRA** | Telecommunications and Digital Government Regulatory Authority |
| **NESA** | National Electronic Security Authority — UAE infosec standards |
| **CBUAE** | Central Bank of the UAE |
| **SVF** | Stored Value Facility — CBUAE-licensed e-money holder |
| **RPSP** | Retail Payment Service Provider — CBUAE license type |
| **WPS** | Wages Protection System — payroll compliance for hiring entities |
| **CoO** | Certificate of Origin |
| **ECAS** | Emirates Conformity Assessment Scheme |
| **HS Code** | Harmonized System code — customs classification |
| **GCC CCT** | GCC Common Customs Tariff |
| **Incoterms 2020** | International Chamber of Commerce trade terms |
| **PINT-AE** | Peppol International Invoicing — UAE profile |
| **UBL** | Universal Business Language — XML standard for invoices |
| **PAdES** | PDF Advanced Electronic Signature |
| **CAdES** | CMS Advanced Electronic Signature |
| **LTV** | Long-Term Validation (signature retention) |
| **QLDB** | Amazon Quantum Ledger Database (for tamper-evident records) |

---

## 16. الخاتمة

المنصة دي عندها أساس قوي وفي position ممتاز للنمو. الفجوات الحالية كلها قابلة للإغلاق في 8 مراحل منظمة، كل منها قابلة للمراجعة بشكل مستقل.

**التوصية النهائية**:
1. ابدأ بالـ **Phase 0** فوراً (XS — أمان فوري)
2. **Phase 1** (Tax Invoice) بعدها مباشرة — كل يوم بدون tax invoices = invoices غير صالحة
3. **Phases 2 & 3** بالتوازي (مختلف teams لو متاح)
4. **Phase 4 (ICV)** قبل أي government go-live
5. **Phase 5 (e-Invoicing)** قبل مارس 2026 بالأكثر (3 أشهر سابقة لـ FTA Phase 1)
6. **Phases 6-8** بعد ما الـ critical infrastructure تكون مدمجة

كل phase merge = trilink أقرب لـ "production-ready for government and enterprise UAE customers".

---

**نهاية المستند.** للمراجعات والتعديلات، استخدم Git history على الـ ملف ده + comments في PRs المتعلقة.
