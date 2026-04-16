خطة تنفيذ التحسينات — Trilink
خطة مقسمة على 4 مراحل حسب الأولوية والمخاطر. كل مهمة لها: الملف، التقدير الزمني، ومعيار القبول.

🔴 المرحلة 1 — أمان حرج (اليوم/الغد) — ~6 ساعات
الهدف: إغلاق الثغرات قبل أي شيء آخر.

#	المهمة	الملف	الوقت	معيار القبول
1.1	إصلاح Authorization في StoreBidRequest — التحقق أن المورد مؤهل للـ RFQ	app/Http/Requests/Bid/StoreBidRequest.php + app/Policies/RfqPolicy.php	1h	Test يثبت أن مورد من شركة أخرى يرجع 403
1.2	إضافة throttle:6,1 على routes تسجيل الدخول و OTP و password-reset	routes/web.php, routes/api.php	30m	اختبار: 7 محاولات → 429
1.3	مراجعة كل store/update/destroy للتأكد من استدعاء $this->authorize(...)	app/Http/Controllers/Web/* + Api/*	2h	Checklist موثّقة
1.4	تقوية فحص رفع الملفات: mimes حقيقي + حجم + فحص PDF header	app/Http/Controllers/Web/CompanyDocumentController.php	1h	Test يرفض PDF مزوّر
1.5	تأكيد APP_DEBUG=false + APP_ENV=production في .env.example وتوثيق deployment	.env.example	30m	README فيه warning
1.6	تغليف إنشاء العرض + مرفقاته في DB::transaction	app/Http/Controllers/Web/BidController.php + app/Services/BidService.php	1h	Test: فشل رفع مرفق → لا bid يتبقّى
تسليم المرحلة 1: PR واحد باسم security/phase-1-hardening.

🟠 المرحلة 2 — أداء وN+1 (هذا الأسبوع) — ~2 يوم
#	المهمة	الملف	الوقت
2.1	إصلاح Company::isInsured() — استخدام العلاقة المحمّلة بدل query جديدة	app/Models/Company.php:398-404	30m
2.2	نفس الشيء لـ Company::hasValidTradeLicense()	app/Models/Company.php:310-315	30m
2.3	إضافة with(['insurances','companyDocuments','icvCertificates']) في ContractController قبل أي loop	app/Http/Controllers/Web/ContractController.php:540-544	1h
2.4	نقل فلترة المدفوعات إلى SQL بدل PHP	ContractController.php:238-240	1h
2.5	إعادة كتابة top-suppliers aggregation باستخدام junction table contract_parties + groupBy SQL	ContractController.php:128-139	3h
2.6	مراجعة الـ migration المفتوحة حاليًا 2026_04_27_100000_add_search_performance_indexes.php والتأكد من تغطية: contracts(status,date), bids(rfq_id,status), rfqs(buyer_company_id,status)	migration الحالية	2h
2.7	تثبيت Laravel Telescope في local + Debugbar لتحديد أي N+1 متبقية	composer	1h
2.8	تفعيل OPcache preload في Dockerfile للإنتاج	Dockerfile	1h
تسليم المرحلة 2: PR perf/phase-2-n-plus-one.

🟡 المرحلة 3 — بنية وجودة كود (هذا الشهر) — ~2 أسبوع
3.A تقسيم الـ God Controller (3 أيام)
قسّم ContractController.php (3043 سطر) إلى:


Web/Contract/
  ├── ShowController.php        ← show, pdf, diff, timeline
  ├── SigningController.php     ← sign, verify password, signature recording
  ├── AmendmentController.php   ← amendments, approvals
  ├── AnalyticsController.php   ← velocity, top suppliers, dashboards
  └── (shared) ContractPresenter.php  ← JSON parties/signatures → view model
المشترك يروح لـ App\Support\Contracts\ContractPresenter أو App\Http\Resources\ContractResource
Web و Api يستخدمان نفس الـ Presenter
3.B Value Objects للأعمدة JSON (2 يوم)
بدل string keys سحرية على parties / signatures / payment_schedule:


// app/ValueObjects/ContractParty.php
final readonly class ContractParty {
    public function __construct(
        public int $companyId,
        public PartyRole $role,
        public ?int $signedBy = null,
    ) {}
    public static function fromArray(array $d): self { ... }
    public function toArray(): array { ... }
}
ثم Cast على الـ Model:


protected $casts = [
    'parties' => AsCollection::of(ContractParty::class),
];
3.C API Versioning (نصف يوم)
نقل routes/api.php محتواه تحت prefix v1
إبقاء route قديمة لـ deprecation مع header warning لمدة 60 يوم
توثيق في README
3.D State Machine للـ Contract/Rfq/Bid (3 أيام)
تثبيت spatie/laravel-model-states
تعريف states: Draft → Published → Awarded → Signed → Active → Completed / Cancelled
كل transition يتحقق من الصلاحيات وينتج Event
استبدال كل مقارنات status النصية بـ $contract->status instanceof Signed
3.E استبدال milestone matching الهش (يوم)
إضافة عمود milestone_type (enum) على payment_schedule items
استبدال str_contains(strtolower(...)) في ContractController.php:623-625 بمقارنة enum
تسليم المرحلة 3: 5 PRs صغيرة متسلسلة، كل واحد <500 سطر diff.

🟢 المرحلة 4 — Enterprise Grade (الشهر الثاني) — ~3 أسابيع
4.A Observability & Production Hardening (أسبوع)
 Sentry للأخطاء
 Laravel Horizon للـ queues + dashboard محمي
 Failed-job alerts على Slack/Email
 Health-check endpoint /health (DB + Redis + Queue)
 Log rotation في Dockerfile
 Deploy script: php artisan config:cache && route:cache && view:cache && event:cache
4.B Testing to 150+ (أسبوع)
أولوية بحسب المخاطر المالية:

Payment flows (اختبار كل حالة: partial, refund, dispute) — 25 test
Escrow release conditions — 20 test
Contract signing + versioning — 20 test
Bid submission + awarding — 15 test
E-invoice dispatch + retry — 15 test
Tax invoice generation (بالعربية + RTL PDF) — 10 test
Authorization matrix (buyer vs supplier vs admin vs government) — 30 test
E2E Playwright: 10 سيناريو critical path
4.C UAE Compliance Deep-Dive (أسبوع)
 اختبار RTL Arabic rendering في tax-invoices/pdf.blade.php — screenshot test
 ZATCA-style phase-2 XML signing في EInvoiceDispatcher — تأكيد توقيع الشهادة
 UAE Pass SSO integration (إن كان مطلوب)
 Audit log لكل عملية مالية (who/what/when/ip)
 Data retention policy + soft-deletes audit
 Pen-test خارجي (استخدم شركة محلية: CPX, DarkMatter, Help AG)
خريطة زمنية مختصرة

الأسبوع 1:  [🔴 Phase 1 Security] [🟠 Phase 2 Performance]
الأسبوع 2:  [🟡 Phase 3A: Split ContractController]
الأسبوع 3:  [🟡 Phase 3B: Value Objects] [🟡 3C: API v1]
الأسبوع 4:  [🟡 Phase 3D: State Machines] [🟡 3E: milestones]
الأسبوع 5:  [🟢 Phase 4A: Observability]
الأسبوع 6:  [🟢 Phase 4B: Testing 150+]
الأسبوع 7:  [🟢 Phase 4C: UAE Compliance + Pen-test]
قواعد تنفيذ
PR واحد لكل مهمة فرعية — لا تجمّع أكثر من موضوع في PR واحد.
كل PR لازم فيه Test يثبت الإصلاح.
لا تبدأ Phase 3 قبل إغلاق 1 و 2 بالكامل — الأمان والأداء أهم من البنية.
Feature flags لأي تغيير قد يكسر UI (مثل API v1): استخدم env variable للتحويل التدريجي.
Rollback plan لكل PR إنتاجي.
مؤشرات النجاح (KPIs)
بعد Phase 1: 0 ثغرة Critical/High في pentest داخلي
بعد Phase 2: p95 response time < 300ms على dashboard العقود (الآن غالبًا >1s)
بعد Phase 3: ContractController < 400 سطر، API له /v1/ prefix، test coverage > 60%
بعد Phase 4: Sentry error rate < 0.1%، 150+ test، Horizon stable، جاهز للبيع لعميل حكومي إماراتي
تريد أبدأ التنفيذ مباشرة من Phase 1.1 (إصلاح ثغرة StoreBidRequest) أم تفضل نراجع الخطة سوا ونعدّل أولويات؟