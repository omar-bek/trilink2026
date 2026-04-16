تمام، هذي خطة التنفيذ مرتبة حسب الأولوية بناءً على نتائج المراجعة:

Update Todos

Phase 1 — CRITICAL SECURITY (اليوم)

1.1 إزالة .env من Git + إضافته لـ .gitignore + تغيير APP_KEY و JWT_SECRET

1.2 ضبط SESSION_ENCRYPT=true و APP_DEBUG=false في .env.example

1.3 إصلاح CSP — إزالة unsafe-inline و unsafe-eval من config/security.php

Phase 2 — CRITICAL BUGS (هذا الأسبوع)

2.1 إصلاح soft-deleted user queries في PaymentService + BidService + EscrowService

2.2 استبدال float بـ bcmath في كل العمليات المالية (EscrowService, PaymentService, BidRequest)

2.3 إضافة CANCELLED status منفصل عن CLOSED في RfqStatus enum

2.4 إصلاح milestone matching من str_contains إلى exact match في EscrowService

Phase 3 — AUTHORIZATION (أسبوع 2)

3.1 إنشاء Policy classes: CompanyPolicy, ContractPolicy, BidPolicy, PaymentPolicy, RfqPolicy

3.2 ربط Policies بالـ Controllers (authorizeResource أو authorize calls)

3.3 تقسيم AppServiceProvider إلى AuthServiceProvider + EventServiceProvider + ObserverServiceProvider

Phase 4 — CODE QUALITY (أسبوع 2-3)

4.1 توحيد enum comparison — إزالة المقارنة المزدوجة (string + enum) في RfqService وغيره

4.2 استبدال find() + null check بـ findOrFail() في 12+ Service method

4.3 استبدال magic strings بـ enum constants (Company model وغيره)

4.4 تصفية الإشعارات حسب الدور (finance approvers فقط بدل كل المستخدمين)

Phase 5 — FRONTEND POLISH (أسبوع 3)

5.1 إنشاء <x-icon> Blade component بدل inline SVG المكرر

5.2 مراجعة كل {!! !!} (31 موقع) والتأكد من أمانها أو استبدالها بـ {{ }}

Phase 6 — PERFORMANCE (أسبوع 3-4)

6.1 إضافة indexes مفقودة: companies.name, users.first_name, users.last_name

6.2 إصلاح N+1 في latestActiveIcvScore() — eager load أو cache

6.3 إضافة composer audit + PHPStan في CI pipeline

Phase 7 — DEVOPS (أسبوع 4+)

7.1 إعداد Docker + docker-compose للـ production

7.2 تحويل Queue + Cache من database إلى Redis

7.3 إعداد Laravel Horizon لمراقبة الـ queue
هذي الخطة الكاملة مقسمة على 7 مراحل:

خطة التنفيذ - TriLink Audit Fixes
Phase 1 — CRITICAL SECURITY (اليوم)
#	المهمة	الملفات
1.1	إزالة .env من Git + تغيير APP_KEY و JWT_SECRET	.gitignore, .env
1.2	ضبط SESSION_ENCRYPT=true و APP_DEBUG=false	.env.example
1.3	إزالة unsafe-inline و unsafe-eval من CSP	config/security.php
Phase 2 — CRITICAL BUGS (هذا الأسبوع)
#	المهمة	الملفات
2.1	إصلاح soft-deleted user queries	PaymentService, BidService, EscrowService
2.2	استبدال float بـ bcmath للمبالغ المالية	EscrowService, PaymentService, StoreBidRequest
2.3	إضافة CANCELLED status منفصل عن CLOSED	RfqStatus enum + RfqService
2.4	إصلاح milestone matching (exact بدل str_contains)	EscrowService:361
Phase 3 — AUTHORIZATION (أسبوع 2)
#	المهمة	الملفات
3.1	إنشاء Policy classes لكل Model رئيسي	app/Policies/ (5 ملفات جديدة)
3.2	ربط Policies بالـ Controllers	Controllers
3.3	تقسيم AppServiceProvider	app/Providers/
Phase 4 — CODE QUALITY (أسبوع 2-3)
#	المهمة	الملفات
4.1	توحيد enum comparison (إزالة المقارنة المزدوجة)	RfqService وغيره
4.2	استبدال find() + null بـ findOrFail()	12+ Service methods
4.3	استبدال magic strings بـ enum constants	Company model وغيره
4.4	تصفية الإشعارات حسب الدور	PaymentService, BidService
Phase 5 — FRONTEND POLISH (أسبوع 3)
#	المهمة	الملفات
5.1	إنشاء <x-icon> component بدل inline SVG	resources/views/components/
5.2	مراجعة كل {!! !!} (31 موقع) للـ XSS	19 Blade files
Phase 6 — PERFORMANCE (أسبوع 3-4)
#	المهمة	الملفات
6.1	إضافة indexes: companies.name, users.first_name/last_name	migration جديد
6.2	إصلاح N+1 في latestActiveIcvScore()	Company model
6.3	إضافة composer audit + PHPStan في CI	.github/workflows/ci.yml
Phase 7 — DEVOPS (أسبوع 4+)
#	المهمة	الملفات
7.1	Docker + docker-compose للـ production	Dockerfile, docker-compose.yml
7.2	تحويل Queue + Cache من database إلى Redis	config/queue.php, config/cache.php
7.3	Laravel Horizon لمراقبة الـ queue	horizon config
