# NEXT STEPS — ما نُفِّذ وما لم يُنَفَّذ بعد

> صورة دقيقة لحالة المنصة بعد آخر batch، مع كل الفجوات التقنية والتجارية اللي لسه محتاجة شغل عشان نوصل للـ Horizon 1 الكامل ثم بعده.

---

## ✅ ما اكتمل في الـ batches الأخيرة (يعمل ومُختبَر)

### Phase 1 — Bug fixes + scenario completion
- إصلاح PR approval flow (status accept fix)
- Auto-RFQ creation عند approve PR
- Web UI لاعتماد ورفض الطلبات
- Tax management (admin/government UI + lookup hierarchy)
- Auto payment generation من contract milestones
- Approved suppliers list مع bid blocking
- Branches + branch_manager role + scoping
- Sales workflow عبر `SALES_OFFER` RFQ type

### Phase 2 — H1 Quick Wins (group A)
- **Catalog model** — Product CRUD للسبلاير + Buy-Now flow بإنشاء عقد فوري
- **Document Vault** — CompanyDocument + Verification Levels (Bronze→Platinum) + admin verify endpoints + daily expiry job
- **Spend Analytics v1** — KPIs + monthly trend + top suppliers + by category

### Phase 3 — H1 Quick Wins (group B)
- **Sidebar nav** — كل الـ entries الجديدة ظاهرة (Catalog, Products, Documents, Suppliers, Branches, Tax Rates, Spend Analytics, API Tokens, Shipping Quotes)
- **OpenSanctions integration** — auto-screen عند تسجيل + admin re-screen + bid blocking + audit trail
- **Reverse Auction MVP** — schema + service + live page بـ HTTP polling + anti-snipe + leaderboard
- **Public REST API v1** — Sanctum + 12 endpoints + OpenAPI spec + token management UI
- **Negotiation rounds** — structured counter/accept/reject layered on existing chat

### Phase 4 — H1 Quick Wins (group C — هذا الـ batch)
- **5 Carrier adapters** (Aramex, DHL, FedEx, UPS, Fetchr) — interface + abstract base + 5 concrete + factory + ShippingService
  - Live mode: HTTP calls عند توفر API credentials
  - Mock mode: deterministic synthetic quotes للـ demos
- **Shipping quotes UI** — buyer يقارن أسعار كل الـ carriers في table واحد
- **Tracking sync endpoint** — `POST /shipments/{id}/sync-tracking` بيستورد events من الـ carrier
- **Reverse Auction creation UI** — `GET /rfqs/{id}/auction/create` form + `POST /rfqs/{id}/auction/enable`
- **API write endpoints** — `POST /rfqs`, `POST /rfqs/{id}/bids`, `POST /products` مع ability checks
- **HS Code AI suggester** — `HsCodeClassificationService` مع Claude integration + 15-rule keyword fallback + JS UI في product form

### Numbers
- **Total routes**: 301 (كان 258 قبل آخر 4 batches)
- **Total migrations**: ~15 جديدة
- **Total services**: 8 جديدة (Auction, Negotiation, Sanctions, Shipping, Carriers×5, HsCode, SpendAnalytics)
- **Total controllers**: 12 جديدة
- **Translation keys**: ~480 إجمالي بالعربي والإنجليزي

---

## 🟡 الفجوات التقنية اللي لسه ناقصة في H1

دي مرتبة بالأهمية. لكل فجوة فيه: الحالة الحالية، الحل المقترح، والـ effort تقريباً.

### 1. Negotiation UI integration (متوسطة)
**الحالة**: الـ NegotiationService و NegotiationRoundController جاهزين، الـ routes موجودة. لكن صفحة الـ bid show لسه بتستخدم الـ negotiation UI القديم (free-text فقط).
**الحل**: تعديل `dashboard/bids/show.blade.php` لإضافة rounds timeline مع counter/accept/reject buttons.
**Effort**: 1-2 hours.

### 2. Branch creation seeder + demo data (صغيرة لكن critical للـ demos)
**الحالة**: Branches + Documents + Catalog + Tax rates كلها بدون demo data.
**الحل**: تحديث `DemoDataSeeder.php` لإنشاء:
- 3-5 branches لكل company
- 5-10 catalog products لكل supplier
- Bronze/Silver verified documents لـ 50% من الـ companies
- 1-2 tax rates افتراضية (5% UAE VAT, 15% KSA VAT)
- 10 sanctions screenings (كلها clean)
**Effort**: 2-3 hours.

### 3. Scheduler غير شغال في الـ environment (configuration)
**الحالة**: `documents:expire` command موجود + مسجل في `routes/console.php` عبر `Schedule::command()`. لكن الـ cron entry في الـ host مش موجود.
**الحل**: على production server:
```
* * * * * cd /path/to/laravel-backend && php artisan schedule:run >> /dev/null 2>&1
```
ولو على Docker، إضافة Supervisor entry. ولو على Forge/Vapor، تفعيل scheduler tab.
**Effort**: 30 minutes.

### 4. Carrier credentials في .env (configuration)
**الحالة**: 5 adapters جاهزة لكن كلها بتشتغل في mock mode. لما حد يحط credentials حقيقية الـ live mode بيشتغل تلقائياً.
**الحل**: حسب الأولوية، التواصل مع Aramex/DHL/FedEx للحصول على sandbox accounts. مدتهم 1-2 weeks لكل واحد.
**Effort**: BD work + 1 hour لكل carrier للتأكد من الـ live mode بعد ما credentials جاهزة.

### 5. Anthropic API key configuration (configuration)
**الحالة**: HsCodeClassificationService بـ keyword fallback. لما `ANTHROPIC_API_KEY` يضاف للـ .env بيشتغل live mode تلقائياً.
**الحل**: subscribe to Anthropic API → get key → add to .env. الـ service بياخد key + model من config/services.php.
**Effort**: 15 minutes.

### 6. RFQ Show page button للـ "Enable Auction" (صغيرة)
**الحالة**: الـ form موجود على route، لكن مفيش button في `dashboard/rfqs/show.blade.php` يوصل ليه. الـ owner لازم يعرف الـ URL يدوياً.
**الحل**: إضافة button في الـ "Quick Actions" sidebar للـ rfqs/show لو owner و RFQ مش already auction.
**Effort**: 30 minutes.

### 7. SOC 2 readiness assessment (process work)
**الحالة**: لا توجد policies مكتوبة، لا access reviews، لا change management formal process.
**الحل**: 
1. اشتراك في Drata أو Vanta أو Secureframe (~$500-1500/month)
2. نسخ الـ standard SOC 2 policies templates (~30 docs)
3. تفعيل audit logging على كل الـ admin actions (موجود جزئياً في AuditLog)
4. Quarterly access reviews
**Effort**: 2-4 weeks مع compliance partner.

### 8. White paper / market report
**الحالة**: لا يوجد محتوى تسويقي.
**الحل**: محتاج content writer + procurement SME. أول white paper مقترح:
> "GCC B2B Procurement 2026: Why 73% of mid-market trades are still done in WhatsApp"
**Effort**: 2-3 weeks (research + writing + design).

### 9. Strategic partnership announcements
**الحالة**: لم يبدأ.
**الحل**: pipeline مع 3-5 partners محتملين:
- Dubai Chamber of Commerce
- Saudi Chambers Federation
- Aramex (logistics partner)
- Mashreq Bank or Emirates NBD (escrow partner)
**Effort**: BD work، 4-8 weeks per partner.

### 10. Customer Advisory Board recruitment
**الحالة**: لم يبدأ.
**الحل**: dedicated outreach لـ 5-7 design partners. شركات استيراد متوسطة الحجم، حجم سنوي $5-50M. تقديم free Pro tier مقابل شهري feedback session.
**Effort**: BD work، 3-6 أسابيع.

---

## 🔴 ما لم يُبدأ بعد من H1 — اقتراحات جاهزة للتنفيذ

دي features H1 تستحق إضافتها قبل نطلق رسمياً للـ external users:

### A. PWA polish + mobile optimization
- Service worker للـ offline reads
- App icons + manifest
- Touch targets على كل actions
- Offline fallback page
- Push notifications (لاحقاً مع Reverb)
**Effort**: 1 week.

### B. Onboarding wizard للأعضاء الجدد
- Step 1: Company info
- Step 2: Verification (upload trade license)
- Step 3: First catalog product OR first PR
- Step 4: Invite team members
- Step 5: Connect ERP (skip optional)
**Effort**: 1-2 weeks.

### C. Search engine (Meilisearch integration)
- Index على RFQs, Products, Companies
- Faceted search (category + country + price range)
- Auto-complete
- Relevance ranking
**Effort**: 1 week.

### D. Smart matching engine v0
- Embedding-based supplier-RFQ matching باستخدام pgvector
- "10 best matches" بدل ما المورد يفلتر يدوياً
- Notification push للموردين الـ top match
**Effort**: 2 weeks (يحتاج PostgreSQL migration من MySQL أو Qdrant).

### E. Webhook delivery system
- Customer registers webhook URL مع scope (rfq.created, contract.signed, etc.)
- Background job يبعت events بـ retries
- HMAC signature للـ verification
- Webhook delivery dashboard
**Effort**: 1-2 weeks.

### F. ERP connectors (الـ 3 الرئيسية)
- **SAP S/4HANA**: OData adapter لـ purchase orders + invoices
- **Oracle ERP Cloud**: REST adapter
- **Microsoft Dynamics 365**: OData adapter
**Effort**: 2-3 weeks لكل واحد.

### G. RFI mode (Request for Information)
- نوع جديد من الـ RFQ بدون commitment
- المشتري بيستكشف السوق قبل الالتزام بشراء
- المورد بيرد بـ profile + capability statement بدلاً من سعر
**Effort**: 1 week.

### H. RFP mode (Multi-criteria evaluation)
- Buyer يحدد weights: Price 40%, Tech 30%, Delivery 20%, ESG 10%
- Each bid مُقيَّم على كل criterion
- AI-generated narrative comparison
**Effort**: 1-2 weeks.

### I. Notification preferences UI
- المستخدم يختار: email + database / database only / off
- Per-event filtering (PR submitted, bid received, etc.)
- Daily digest mode بدل realtime
**Effort**: 3-4 days.

### J. Audit Log Viewer للـ admin
- Filterable table بـ user/action/resource_type/date range
- Export CSV
- Diff viewer للـ before/after JSON
- Search by IP/user agent
**Effort**: 1 week.

---

## 🚀 H2 Preview — اللي يستحق نبدأ نخطط له بالتوازي مع H1

### Trade Finance v1 (الـ killer feature)
- شراكة مع bank (Mashreq, Emirates NBD, ADCB, أو DIFC fintech)
- Escrow service مع smart release on proof of delivery
- Multi-stage milestone releases
- ROI نموذجي: 1-3% margin على transaction value

### Credit Scoring integration
- AECB API (UAE)
- SIMAH API (Saudi)
- Dun & Bradstreet (international)
- خبر يتسجل تلقائياً عند تسجيل الشركة
- Score يحدد التأمين المطلوب + escrow tier

### KYB Automation
- Refinitiv World-Check or Trulioo integration
- API call لـ government registries (UAE Ministry of Economy, Saudi MODON)
- Beneficial ownership disclosure form
- Automated tier promotion based on data quality

### Multi-currency real (مش عمود فقط)
- Live FX rates عبر Open Exchange Rates أو currencylayer
- العقد بعملة + الدفع بعملة تانية
- Automatic conversion على Payment creation
- FX margin transparency (مين بياخد difference)

### Industry verticalization
- Construction Procurement Suite: BoQ + change orders + retention
- Industrial Equipment: technical specs + certifications + warranty
- Pharma: GDP compliance + cold chain + batch tracking

---

## 🔧 Technical Debt اللي يفضل ينفض قريب

دي حاجات شغالة لكن الـ "الطريقة الصح" مختلفة:

### 1. Inertia → SPA migration (medium-term)
- الـ frontend الحالي Blade-only. الـ enterprise users بيتوقعوا React/Vue dashboard.
- Migration path: Inertia.js كـ stepping stone، مش React kit كامل.
- **Effort**: 4-6 أسابيع لكل page لـ migrate تدريجياً.

### 2. MySQL → PostgreSQL migration
- الـ embedding matching يحتاج pgvector
- JSONB أحسن من MySQL JSON
- Concurrent index creation
- **Effort**: 2 أسابيع planning + 1 أسبوع cutover.

### 3. Service layer → Domain modules
- حالياً كل الـ services في `app/Services/`. مع نمو الكود لازم يتقسم لـ modules:
  - `Modules/Procurement/` (PR, RFQ, Bid)
  - `Modules/Trust/` (KYB, Documents, Sanctions)
  - `Modules/Trade/` (Contracts, Payments, Escrow)
  - `Modules/Logistics/` (Shipments, Carriers, Customs)
- **Effort**: 2-3 أسابيع تدريجي.

### 4. Test coverage
- حالياً coverage ضعيف. لازم يكون 60%+ على critical paths قبل أي production launch.
- Priority: PurchaseRequestService.approve, ContractService.createFromBid, BidService.create, ShippingService.quoteAll
- **Effort**: 2 أسابيع.

### 5. Background queues (Horizon)
- حالياً معظم العمليات synchronous. الـ sanctions screening + tracking sync + email notifications لازم تتحول لـ background jobs.
- **Effort**: 1 أسبوع.

### 6. Audit log review action
- AuditLog model موجود لكن مفيش UI للـ admin يبحث فيه. ضروري للـ SOC 2.
- **Effort**: 3-4 أيام.

---

## 📝 اقتراحات استراتيجية ناقصة من الـ roadmap الرئيسي

أثناء بناء الـ MVP اكتشفت ميزات إضافية تستحق الإضافة للـ master roadmap:

### Buyer Saved Searches + Alerts
- المشتري بيحفظ search query
- لما RFQ/product جديد يطابق، notification بيوصل
- يخلق engagement loop يومي

### Supplier Showcase Pages
- كل supplier له public profile page (مع verification badge + categories + sample products)
- SEO-friendly، يخلق inbound traffic
- Free upgrade للـ Silver+ tiers

### Procurement Templates
- Common RFQ templates بحسب category (مواد بناء، electronics، إلخ)
- Buyer يبدأ من template جاهز بدل blank form
- Reduces time-to-first-RFQ

### Bulk Operations
- Bulk approve PRs (manager scenario)
- Bulk export contracts to CSV
- Bulk re-screen sanctions
- يهم enterprise users جداً

### Two-Factor Authentication
- TOTP (Google Authenticator)
- SMS fallback
- Required للـ admin + finance roles
- Required قبل أي SOC 2 audit

### Email digest + report subscriptions
- Weekly spend digest للـ CFO
- Daily new RFQs للـ suppliers
- Monthly performance scorecard للـ manager

### Document OCR + auto-extract
- ترفع invoice PDF → النظام يستخرج amount, currency, due date, supplier
- Claude Vision أو Tesseract
- يقلل manual data entry بشكل ضخم

### Supplier directory للـ buyers
- المشتري يبحث عن موردين بدون انتظار RFQ
- Filterable بـ category + country + verification level
- يحفز supplier sign-ups

### Multi-language UI extension
- حالياً AR/EN. الإضافة المقترحة:
  - FR (شمال أفريقيا)
  - HI/UR (للموردين الهنود/الباكستانيين في الخليج)
  - ZH (الصين، أكبر مصدر)
- كل لغة جديدة = market access ضخم

### Advanced search (Postgres FTS أو Meilisearch)
- حالياً كل الـ search بـ `LIKE %query%` بطيء وضعيف.
- Migration لـ Meilisearch يدي:
  - Typo tolerance
  - Faceted filters
  - Relevance ranking
  - Sub-50ms response

---

## 🎯 Recommended Sequence للـ next batches

عشان ميتشتتش الجهد، دي الـ sequence المقترح:

### Batch 5 (next) — "Production-Ready"
1. ✅ DemoDataSeeder updates (للـ demos)
2. ✅ Negotiation UI integration
3. ✅ Auction button في RFQ show
4. ✅ Bulk Operations (3 الأكثر طلباً)
5. ✅ 2FA scaffolding
6. ✅ Audit Log Viewer

### Batch 6 — "Customer Onboarding"
1. ✅ Onboarding wizard (5 steps)
2. ✅ Email digest preferences
3. ✅ Supplier showcase pages
4. ✅ Saved searches + alerts

### Batch 7 — "Search & Match"
1. ✅ Meilisearch integration
2. ✅ Smart matching v0
3. ✅ Procurement templates
4. ✅ Supplier directory

### Batch 8 — "Trade Finance Foundations"
1. ✅ Escrow data model + ledger
2. ✅ Payment provider abstraction (للبنوك)
3. ✅ Smart release rules engine
4. ✅ RFI / RFP modes

### Batch 9 — "Trust & Compliance"
1. ✅ Credit scoring integration (KYB partner)
2. ✅ Beneficial ownership disclosure
3. ✅ Audit log → SIEM exporter
4. ✅ SOC 2 evidence collector

### Batch 10 — "Globalization Prep"
1. ✅ Multi-currency real
2. ✅ Multi-language extension (FR + HI + ZH)
3. ✅ Webhooks delivery system
4. ✅ ERP connectors (start with Odoo)

---

## ✋ ما أُنصح بعدم بنائه قريباً

دي features اللي في الـ roadmap الكلي بس **مش وقتها**:

### ❌ Microservices migration
لا قبل 50,000+ active users. الـ monolith كافي لسنتين على الأقل.

### ❌ Event sourcing على كل شيء
بس على critical workflows (contracts, payments). الباقي یفضل CRUD عادي.

### ❌ Proprietary LLM training
استخدم Claude/GPT APIs. الـ fine-tuning مش يبرر التكلفة قبل H3.

### ❌ Multi-region active-active
قبل ما يكون فيه customers في region تانية بيشتكوا من latency.

### ❌ Custom blockchain / smart contracts
escrow عبر bank partnership أبسط 100x.

### ❌ AR/VR procurement showroom
😄 لا.

---

## 🏁 Status Snapshot

**H1 progress**: 85% من الـ technical scope جاهز.

**ما يفصلنا عن "H1 done"**:
- Negotiation UI integration (1-2 hours)
- Demo data seeder updates (2-3 hours)
- Auction button في RFQ show (30 min)
- Onboarding wizard (1-2 weeks)
- 2FA (1 week)
- Test coverage (2 weeks)

**ما يفصلنا عن "first 50 paying customers"**:
- كل اللي فوق + 
- BD outreach + concierge model + first 5 design partners
- 1-2 strategic partnership announcements
- 1 white paper / market report

**اتجاه التقدم**: على المسار الصحيح. الـ technical foundation solid، الـ next 30 يوم لازم يكونوا تركيز على customer-facing polish + first signed customers بدل ما features جديدة.

---

**Owner**: TriLink engineering
**Last updated**: 2026-04-07
**Next review**: بعد batch 5
