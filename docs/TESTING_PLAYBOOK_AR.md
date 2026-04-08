# خطة اختبار منصة TriLink — مشي يدوي كامل

> ملف اختبار يدوي شامل للمنصة بعد تشغيل `ComprehensiveSeeder`. كل سيناريو
> يحدّد: المستخدم اللي تسجل به، الرابط، الخطوات، والنتيجة المتوقعة.
>
> **كل المستخدمين عندهم نفس كلمة السر:** `password`

---

## 0) تحضير قاعدة البيانات

```bash
php artisan migrate:fresh --seed
php artisan serve
```

افتح: `http://localhost:8000`

---

## 1) جدول الحسابات الجاهزة

| الدور | الإيميل | الشركة | تستخدمه لـ |
|---|---|---|---|
| `admin` | `admin@trilink.test` | — | الموافقة على الشركات، الفئات، إعدادات المنصة، ضرائب، مراجعة وثائق |
| `government` | `gov@trilink.test` | — | تحكيم النزاعات المصعّدة، تقارير المنصة |
| `company_manager` (مشتري) | `manager@al-ahram.test` | Al-Ahram Group | إدارة الفريق، اعتماد PRs، توقيع العقود، الفروع، الموردين |
| `buyer` | `buyer@al-ahram.test` | Al-Ahram Group | إنشاء PRs، تقييم العروض، قبول العطاءات |
| `branch_manager` | `branch.dubai@al-ahram.test` | Al-Ahram Group | تشغيل فرع دبي |
| `finance` | `finance@al-ahram.test` | Al-Ahram Group | متابعة الدفعات |
| `finance_manager` | `finance.mgr@al-ahram.test` | Al-Ahram Group | اعتماد الدفعات الكبيرة |
| `sales` | `sales@al-ahram.test` | Al-Ahram Group | عروض البيع (Sales Offers) |
| `sales_manager` | `sales.mgr@al-ahram.test` | Al-Ahram Group | إدارة فريق المبيعات |
| `company_manager` (مورّد) | `manager.mohammed@emirates-ind.test` | Emirates Industrial Co. | إدارة فريق المورّد، تحميل وثائق التحقق |
| `supplier` | `mohammed@emirates-ind.test` | Emirates Industrial Co. | تقديم العروض، التفاوض، تنفيذ العقود |
| `supplier` آخر | `rashid@dbtech.test` | Dubai Tech Solutions | اختبار حالات إضافية للعروض والمزاد |
| `logistics` | `driver@fastline.test` | FastLine Logistics | تحديث حالة الشحنات والتتبع |
| `clearance` | `agent@cargocheck.test` | CargoCheck Customs | التخليص الجمركي |
| `service_provider` | `engineer@buildtech.test` | BuildTech Services | عروض الخدمات |
| `company_manager` (شركة قيد المراجعة) | `owner@future-inv.test` | Future Investments LLC (PENDING) | اختبار صفحة "في انتظار الموافقة" |

> ⚠️ ملاحظة: حساب `contact@redflag.test` تابع لشركة `RedFlag Trading FZE` المعلّقة
> بسبب مطابقة قائمة العقوبات. يُستخدم لاختبار سلوك الحساب الموقوف.

---

## 2) المسار العام (E2E الكامل) — الذهبي

> هذا المسار يجرّب الدورة الكاملة من تسجيل شركة جديدة حتى تقييم المورّد بعد
> اكتمال العقد. خصص ساعة لتشغيله من البداية للنهاية.

### 2.1 تسجيل شركة جديدة (مشتري) ومراجعة الإدارة
1. اخرج من أي جلسة. ادخل على `/register`.
2. اختر النوع "Buyer"، عبّي بيانات الشركة، تقدّم.
3. النظام يسجّل دخولك تلقائيًا ويحوّلك لـ `/register/success`. لاحظ أنه لا
   يسمح لك بدخول الـ Dashboard لأن حالتك `pending`.
4. سجّل خروج، ادخل بحساب `admin@trilink.test`.
5. روح `/dashboard/admin/companies`، فلتر على Pending. شركتك الجديدة لازم تظهر،
   بالإضافة إلى **Future Investments LLC** اللي زرعها السيدر.
6. افتح **Future Investments LLC**. جرّب:
   - **Request Info** → اطلب رقم ضريبي ووثيقة رخصة. يفترض يطلع للمستخدم نموذج
     في صفحة "Pending" يحمّل فيه المعلومات.
   - **Approve** على شركتك الجديدة (مش Future) → الحالة تتحول `active` ويقدر
     المستخدم بتاعها يدخل الـ Dashboard.

### 2.2 إنشاء طلب شراء وتدويره لـ RFQ
سجّل دخول بـ `buyer@al-ahram.test`:
1. روح `/dashboard/purchase-requests`. لازم تشوف 5 PRs محضّرة (حالة لكل واحد):
   Draft, Submitted, Pending Approval, Approved, Rejected.
2. اضغط **Create** وعبّي طلب جديد:
   - عنوان: "Test PR — Manual Run"
   - فئة: Industrial Equipment
   - عنصر: Hydraulic Pump × 5، سعر تقديري 12000
   - تاريخ تسليم بعد 30 يوم
3. اضغط **Submit for Approval**. الحالة تصير `pending_approval`.

اخرج وادخل بـ `manager@al-ahram.test`:
4. روح PRs، شوف الـ PR اللي رفعه أحمد. اضغط **Approve**.
5. لازم النظام يولّد RFQ تلقائيًا. روح `/dashboard/rfqs` وتأكد ظهر RFQ جديد
   مرتبط بالـ PR.

### 2.3 تقديم عرض من جانب المورّد
سجّل دخول بـ `mohammed@emirates-ind.test`:
1. روح `/dashboard/rfqs`. لازم تشوف RFQs المتاحة (مش كلها — لأن الـ supplier
   ما يشوف إلا اللي شركته داخل `target_company_ids`).
2. افتح RFQ "Copper Wire 16mm — Electrical". اضغط **Submit Bid**.
3. عبّي:
   - السعر: 92,000
   - مدة التسليم: 14 يوم
   - شروط الدفع: 30/50/20
4. حفظ. الحالة `submitted`.
5. **اختبار قيد المورّد المربوط:** Emirates Industrial Co. مربوطة بـ Al-Ahram
   كـ "captive supplier" (سيدر `CompanySupplier`). يفترض إن النظام يسمح
   بالعروض على هذا الـ RFQ لأن الشركة هي اللي ربطته. لو عندك RFQ لشركة ثانية
   لازم النظام يرفض أو يسمح حسب القاعدة. (تأكد من السلوك الفعلي ضد
   `BidService::create()`).

### 2.4 مقارنة العروض وقبول واحد
اخرج وادخل بـ `buyer@al-ahram.test`:
1. روح RFQ "Copper Wire 16mm". اضغط **Compare Bids**. لازم تشوف جدول مقارنة
   ثلاثة عروض كاملة (Submitted, Under Review, Rejected) من الـ seeder + عرضك
   الجديد.
2. ادخل أحد العروض اللي حالته `submitted`. اضغط **Accept**.
3. النظام لازم:
   - يحوّل حالة العرض لـ `accepted`.
   - يولّد عقد جديد تلقائيًا في `/dashboard/contracts`.
4. روح العقد الجديد. لاحظ أن الحالة `pending_signatures`.

### 2.5 توقيع العقد من الطرفين
1. كـ `manager@al-ahram.test` (عنده صلاحية `contracts.sign`)، افتح العقد →
   اضغط **Sign**. توقيع الجهة المشترية يتسجّل.
2. اخرج، ادخل بـ `mohammed@emirates-ind.test`. افتح نفس العقد → اضغط **Sign**.
3. لما يوقّع الطرفان، الحالة تتحول لـ `signed` ثم `active`.

### 2.6 الدفعات
1. كـ `buyer@al-ahram.test` ادخل العقد، تحت **Payment Schedule** اضغط
   "Create Payment" لمعلَم "Advance Payment".
2. الحالة `pending_approval`.
3. اخرج، ادخل بـ `manager@al-ahram.test` (أو `finance.mgr@al-ahram.test`)
   اعتمد الدفعة → **Approve** ثم **Process**.
4. الحالة تتغيّر إلى `processing` ثم `completed` (حسب تكامل بوابة الدفع
   الموهومة).

### 2.7 الشحنة
1. كـ `manager@al-ahram.test` افتح العقد ولاحظ زر "Schedule Shipment".
2. أنشئ شحنة جديدة (logistics: FastLine).
3. اخرج، ادخل بـ `driver@fastline.test`.
4. روح `/dashboard/shipments`. افتح الشحنة الجديدة، اضغط **Track** وحدّث
   الحالة من `in_production` إلى `ready_for_pickup` ثم `in_transit`.
5. ادخل بـ `agent@cargocheck.test` وحدّث الحالة إلى `in_clearance`.
6. ارجع لـ `driver@fastline.test` وحدّث إلى `delivered`.

### 2.8 رفع تقرير التقدم من المورّد
1. كـ `mohammed@emirates-ind.test` ادخل العقد النشط.
2. تحت **Progress Updates** ضع نسبة 75% مع ملاحظة، وارفع وثيقة (PDF/صورة).
3. كـ `buyer@al-ahram.test` تأكد إن الـ progress bar اتحدث وإن الوثيقة ظاهرة
   تحت **Supplier Documents**.

### 2.9 تقييم المورّد بعد إتمام العقد
1. حدّث حالة العقد يدويًا أو عن طريق الإتمام الطبيعي إلى `completed`.
2. كـ `buyer@al-ahram.test` افتح العقد → **Leave Feedback**. ضع 5 نجوم
   وتعليق.
3. روح `/dashboard/companies/{supplier_id}` لازم تشوف التقييم الجديد ضمن
   متوسط تقييم المورّد.

---

## 3) سيناريوهات بدور Admin (`admin@trilink.test`)

### 3.1 إدارة الشركات
- `/dashboard/admin/companies` → افتح كل تبويبات الفلترة (active, pending,
  inactive). تأكد من وجود **Future Investments LLC** ضمن pending،
  **RedFlag Trading FZE** ضمن inactive (مع badge "Sanctions Hit").
- افتح **RedFlag Trading FZE**. اضغط **Rescreen Sanctions**. لازم يضيف صف
  جديد في sanctions log.
- افتح أي شركة مورّد فعّالة، شوف الـ Document Vault. عندك مزيج من
  pending/verified/rejected/expired. جرّب:
  - **Verify** على وثيقة pending.
  - **Reject** على وثيقة pending مع سبب.
- جرّب **Set Verification Level** → غيّر مستوى التحقق إلى Platinum.

### 3.2 المستخدمين
- `/dashboard/admin/users`. اعرض، اعدّل، فعّل/علّق، أعِد تعيين كلمة سر،
  واحذف مستخدم تجريبي.

### 3.3 الفئات والإعدادات والضرائب
- `/dashboard/admin/categories` → أضف فئة فرعية (Sub-category) تحت Electronics.
- `/dashboard/admin/tax-rates` → السيدر زرع 3 (Standard 5%, Medical 0%, Export 0%).
  أضف ضريبة جديدة برمز unique وتأكد تظهر في القائمة.
- `/dashboard/admin/settings` → عدّل قيمة `platform.timezone` مثلًا.

### 3.4 سجل التدقيق
- `/dashboard/admin/audit` → السيدر يحط 4 سجلات. تأكد كل سجل يحوي hash، تاريخ،
  IP، ومستخدم.

---

## 4) سيناريوهات بدور Government (`gov@trilink.test`)

1. روح `/gov` (لوحة الحكومة).
2. لازم تشوف:
   - العقود والدفعات على مستوى المنصة (read-only).
   - النزاعات المصعّدة. السيدر فيه نزاع `escalated` نوعه `payment` معيّن
     لمستخدم gov.
3. افتح النزاع المصعّد → اضغط **Resolve** بحلّ مكتوب. الحالة تتحوّل
   `resolved`.
4. روح `/dashboard/disputes` تأكد إن النزاع راح من قائمة المفتوحة.
5. تأكد من القدرة على تصدير تقارير: `analytics.export`, `audit.export`,
   `data.export` (هذه الصلاحيات في قائمة gov).

---

## 5) سيناريوهات بدور Company Manager — Buyer

سجّل دخول بـ `manager@al-ahram.test`:

### 5.1 إدارة الفريق
- `/dashboard/company/users` → اعرض الفريق (≈8 مستخدمين تابعين للشركة).
- اضغط **Create User**. أنشئ مستخدم بدور `buyer`، اعطه permissions مخصصة
  (مثلاً فقط `purchase-requests.view`). تأكد إن المستخدم الجديد لما يدخل ما
  يقدر يفتح صفحات RFQ أو Contracts.
- جرّب **Toggle Status** و **Reset Password** و **Delete**.

### 5.2 إدارة الفروع
- `/dashboard/branches` → السيدر زرع فرعين (Dubai HQ, Abu Dhabi Logistics).
- أضف فرع جديد، حدّد له branch_manager.
- ادخل بمستخدم branch_manager (`branch.dubai@al-ahram.test`) وتأكد إنه يشوف
  فقط PRs/RFQs/Contracts التابعة للفرع بتاعه.

### 5.3 إدارة الموردين المربوطين
- `/dashboard/suppliers` → السيدر مربوط Emirates Industrial Co. كـ captive
  supplier. تأكد إنها ظاهرة.
- اضغط **Add Supplier**. جرّب ربط شركة مورّد جديدة.

### 5.4 وثائق الشركة (Vault)
- `/dashboard/documents` → يفترض يكون عندك وثائق متنوعة (verified,
  pending, rejected, expired). جرّب رفع وثيقة جديدة.

### 5.5 اعتماد الـ PRs
- روح `/dashboard/purchase-requests`. افتح الـ PR اللي حالته `pending_approval`
  (HVAC Retrofit) → جرّب **Approve** والـ **Reject** على آخر.

### 5.6 توقيع العقود + الدفع
- سبق في القسم 2. كرّرها على عقود ثانية إذا حابب.

### 5.7 التعديلات على العقد (Amendments)
- افتح العقد النشط CNT-2026-0001. تحت **Amendments** يفترض تشوف:
  - Pending amendment (تأخير تسليم 14 يوم).
  - Approved amendment (زيادة سعر 5,000).
- اعتمد الـ pending → الحالة تتحول `approved` وتظهر نسخة جديدة في
  Version history.

---

## 6) سيناريوهات بدور Buyer العادي (`buyer@al-ahram.test`)

- `/dashboard` → اللوحة الرئيسية، تحقق من الـ stat-cards.
- `/dashboard/purchase-requests` → إنشاء وتعديل وحذف drafts فقط.
- `/dashboard/rfqs/{id}/compare` → مقارنة العروض بالـ AI Score.
- `/dashboard/rfqs/{id}/pdf` → تنزيل حزمة الـ RFQ كـ PDF (DejaVu Sans، يدعم
  العربي).
- `/dashboard/bids/{id}` → افتح أي عرض، تأكد من ظهور `payment_schedule`
  والـ AI Score.
- `/dashboard/bids/{id}/pdf` → تنزيل العرض PDF.
- `/dashboard/negotiations/{bid_id}` → السيدر زرع جلسة تفاوض على الـ bid اللي
  حالته `under_review` فيها 4 رسائل (نص + كاونتر أوفر round 1 وround 2). جرّب
  ترسل نص جديد، ترسل counter-offer، تقبل، تنهي الجلسة.

---

## 7) سيناريوهات بدور Supplier

### 7.1 المورّد الرئيسي (`mohammed@emirates-ind.test`)
- `/dashboard/rfqs` → يشوف الـ RFQs اللي شركته داخل `target_company_ids`.
- `/dashboard/bids` → يشوف عروضه (واحدة `submitted`، الباقي حسب الحالات).
- `/dashboard/contracts` → يشوف العقود اللي شركته طرف فيها (الفلترة عبر
  `whereJsonContains('parties', ['company_id' => $cid])`).
- جرّب **Withdraw** على عرض submitted.
- جرّب رفع وثائق إنتاج وتقدّم على العقد النشط.

### 7.2 مورّد مختلف (`rashid@dbtech.test`)
- ادخل المزاد المباشر `Live Auction — IT Hardware Refresh`:
  - `/dashboard/auctions/{id}/live`
  - السيدر زرع عرضين على المزاد. ضع عرض ثالث أقل بـ 1000 (= bid_decrement)
    لازم يُقبل.
  - حاول تضع عرض أقل من السعر الاحتياطي 380,000 → لازم يُرفض.

---

## 8) سيناريوهات بدور Logistics و Clearance

### 8.1 لوجستيات (`driver@fastline.test`)
- `/dashboard/shipments` → يشوف 6 شحنات بحالات مختلفة (every ShipmentStatus).
- جرّب تحديث حالة شحنة `in_production` → `ready_for_pickup`.
- جرّب **Sync Tracking** على شحنة `in_transit`.
- `/dashboard/shipping/quotes` → احسب عرض سعر شحن.

### 8.2 تخليص (`agent@cargocheck.test`)
- يشوف الشحنات اللي وصلت `in_clearance` فقط.
- جرّب تأكيد التخليص (تتغير الحالة إلى cleared).

---

## 9) سيناريوهات بدور Service Provider (`engineer@buildtech.test`)

- يشوف RFQs النوع `service_provider` فقط.
- السيدر فيه واحد: "Service — HVAC Installation".
- يقدم عرض جديد.

---

## 10) سيناريوهات بدور Sales / Sales Manager

سجّل بـ `sales@al-ahram.test`:
- `/dashboard/products` → يقدر ينشئ منتج كاتالوج جديد (المنصة فيها 6 منتجات
  من السيدر).
- `/dashboard/rfqs` → يشوف الـ Sales Offer اللي زرعها السيدر ("Sales Offer —
  Excess Office Stock"). هذا RFQ من نوع `sales_offer` يعني الشركة هي اللي
  تبيع، والـ bidders هم المشترون.

---

## 11) كاتالوج المنتجات (Buy-Now)

كأي مستخدم له صلاحية الكتالوج:
1. روح `/dashboard/catalog`.
2. لازم تشوف 6 منتجات (Hydraulic Pump, Industrial Motor, Cement, Dell XPS,
   Sit-Stand Desk, Ultrasound).
3. افتح Dell XPS 15 → اضغط **Buy Now** بكمية 5.
4. النظام لازم ينشئ عقد مباشرة عبر `ContractService::createFromProduct()`،
   من غير مرور بـ RFQ/Bid.
5. تأكد إن العقد الجديد ظهر في `/dashboard/contracts` بحالة `pending_signatures`.

---

## 12) النزاعات

كـ `buyer@al-ahram.test`:
1. `/dashboard/disputes` → 5 نزاعات بحالات وأنواع متنوعة (open, under_review,
   escalated, resolved).
2. افتح أي نزاع `open` → اكتب تحديث، أو اضغط **Escalate** لتصعيده للحكومة.
3. كـ `gov@trilink.test` افتح النزاع المصعّد، اكتب الحل، **Resolve**.

---

## 13) الإعدادات والتخصيص الشخصي

كأي مستخدم:
- `/profile` → عدّل اسمك الأول، التليفون، صورة الشركة.
- `/profile/password` → غيّر كلمة السر.
- `/settings/notifications` → اضبط تفضيلات الإشعارات.
- `/settings/security` → فعّل MFA لو متوفر.
- `/locale/switch` → بدّل بين عربي/إنجليزي. لاحظ أن الواجهة تتحول لـ RTL.

---

## 14) الإشعارات

- بعد ما تنفّذ أي إجراء (موافقة PR، قبول عرض، توقيع عقد...) سجّل دخول
  بالمستخدم المُتأثّر وروح `/notifications`.
- لازم تشوف صفوف database notifications جايّة من الـ Notification classes
  (NewBidNotification, ContractSignedNotification, PaymentStatusNotification,
  DisputeNotification).
- جرّب **Mark Read** و **Mark All Read** و **Delete**.

---

## 15) قائمة فحص سريعة للحالات (Coverage)

| نوع | الحالات اللي السيدر بيغطيها |
|---|---|
| **PurchaseRequest** | draft, submitted, pending_approval, approved, rejected ✅ |
| **RFQ Type** | supplier, logistics, clearance, service_provider, sales_offer ✅ |
| **RFQ Status** | draft, open, closed, cancelled, + auction live ✅ |
| **Bid Status** | draft, submitted, under_review, accepted, rejected, withdrawn ✅ |
| **Contract Status** | draft, pending_signatures, signed, active, completed, terminated, cancelled ✅ |
| **Payment Status** | pending_approval, approved, processing, completed, rejected, failed, cancelled, refunded ✅ |
| **Shipment Status** | in_production, ready_for_pickup, in_transit, in_clearance, delivered, cancelled ✅ |
| **Dispute Status** | open, under_review, escalated, resolved ✅ |
| **Dispute Type** | quality, delivery, payment, contract_breach, other ✅ |
| **Verification Level** | unverified, bronze, silver, gold, platinum ✅ |
| **Document Status** | pending, verified, rejected, expired ✅ |
| **Sanctions** | clean, hit, not_screened ✅ |
| **Amendment Status** | pending_approval, approved ✅ |

---

## 16) ما لم يُغطّ بعد (Manual Tasks)

هذي الأشياء السيدر ما يمسها — إذا حبيت تختبرها لازم تنفّذها يدويًا داخل
الواجهة:

- **Database notifications**: تتولّد فقط لما تنفّذ إجراء فعلي. السيدر بيقفز
  فوقها عشان ما يوسّخ القاعدة.
- **API tokens**: تنشأ من `/dashboard/api-tokens` بدور `company_manager`.
- **Real file uploads**: السيدر يستخدم paths وهمية. ارفع PDFs حقيقية من
  واجهة Documents/Contracts عشان تختبر التخزين الفعلي.
- **MFA / Password reset emails**: تشتغل لو إعدادات `mail` صالحة في
  `.env`.
- **PDF Generation**: روح `/dashboard/contracts/{id}/pdf` و
  `/dashboard/rfqs/{id}/pdf` و `/dashboard/bids/{id}/pdf` تأكد إن DomPDF
  يولّد ملفات سليمة بالعربية.

---

## 17) إعادة التشغيل

إذا أفسدت البيانات تجريبيًا، اعدّ التشغيل:

```bash
php artisan migrate:fresh --seed
```

السيدر idempotent (يستخدم `updateOrCreate`) فتقدر كمان تشغّله بدون
`migrate:fresh`:

```bash
php artisan db:seed --class=ComprehensiveSeeder
```

---

> آخر تحديث: 2026-04-07. أي حقل أو حالة جديدة تنضاف للموديل لازم تُحدّث في
> `ComprehensiveSeeder` وفي هذا الملف.
