# خطة التنفيذ المرحلية — TriLink Execution Plan
## Strategic Build Order: From Current State to Regional Leader

> وثيقة عملية. كل phase له deliverable واضح، لا features speculative. الترتيب من الأعلى ROI للأدنى. نُحدِّث هذا المستند بعد كل sprint بالحالة الفعلية.

**الإصدار:** 1.0
**التاريخ:** 2026-04-07
**النطاق:** الأسابيع الـ 14 القادمة (3.5 شهر، ~7 sprints)
**المالك:** TriLink Engineering + Product

---

## 0. الفلسفة الموجِّهة

قبل أي phase، ثلاث قواعد لازم نتفق عليها:

### القاعدة 1: الـ Foundations قبل الـ Features
ميصحش نبني AI matching على search معدوم. ميصحش نبني trade finance على audit log فاضي. ميصحش نبني marketplace على search بـ `LIKE %x%`.

### القاعدة 2: الـ Trust Loop قبل الـ Scale
أي feature بيزود الـ supply (موردين) لازم تكون مسبوقة بـ feature بيزود الـ trust (KYB، reviews، disputes). مش العكس.

### القاعدة 3: الـ User Value الأسبوعي
كل phase لازم يطلع feature يقدر المستخدم يستخدمه يوم 1 من release — مش "ابني infrastructure 6 شهور و بعدين شوف". كل phase تحت ليه deliverable ظاهر.

### القاعدة 4 (الأهم): كل feature لازم يجاوب سؤال واحد
> **"هل هذا الـ feature بيـ generate المزيد من الـ trust signals بين الـ buyers و الـ suppliers؟"**

لو الإجابة لا، الـ feature ده مش priority — بصرف النظر عن قد إيه الـ idea cool.

---

## 1. تقييم الوضع الحالي (صريح)

### ✅ نقاط القوة الفعلية
- Workflow كامل end-to-end (PR → RFQ → Bid → Contract → Payment → Shipment)
- Multi-role architecture (12 دور) مع permissions مرنة
- Bilingual AR/EN + RTL native
- Branches + branch_manager scoping (enterprise-grade)
- Tax management متقدم (precedence rules)
- Supplier flow كامل (Browse → Submit → My Bids → Contracts)
- Feedback/reviews system بيشتغل على real DB
- Payment schedules + milestones
- `Contract::realProgress()` pattern (3-source layered fallback)

### ❌ الفجوات الحقيقية على مستوى الكود

| # | الفجوة | الأثر | الفداحة |
|---|---|---|---|
| 1 | **`RfqController::supplierIndex` match score مزيف** (`70 + ($id * 13) % 31`) | matching engine كل المنصة بـني عليه | 🔴 حرج |
| 2 | **Sidebar badges بـ 6-8 SQL queries في كل request بدون cache** | -70% DB load possible | 🔴 حرج |
| 3 | **Search معدوم** — `LIKE %query%` على full table | ميشتغلش بعد 50K rows | 🔴 حرج |
| 4 | **Notifications synchronous** — `Notification::send()` في الـ HTTP request | request timeout مع 50 manager | 🟠 عالي |
| 5 | **مفيش Webhooks** — أي state change مش بيوصل لـ external systems | blocker لـ ERP integration | 🟠 عالي |
| 6 | **`info_request` JSON column = junk drawer** (bank details, admin notes...) | data integrity + queryability | 🟡 متوسط |
| 7 | **Real-time مفعّل في 5% بس من الـ flows** | UX سيء في negotiations + auctions | 🟡 متوسط |
| 8 | **`DocumentExpiringSoonNotification` موجود لكن مش scheduled** | الـ feature موجودة لكن ميتة | 🟡 متوسط |
| 9 | **Audit log مش بيتسجل automatically** على state changes | compliance + SOC 2 blocker | 🟡 متوسط |
| 10 | **مفيش CI/CD أو staging environment** | كل deploy = خطر | 🟠 عالي |

### ❌ الفجوات على مستوى الـ Product
1. **مفيش Discovery حقيقي** — الموردين مش بيوصلوا للمشترين تلقائياً
2. **مفيش Catalog model** — كل حاجة RFQ، مفيش "Buy Now"
3. **مفيش Trade Finance حقيقي** — قلب الـ B2B platforms المتقدمة
4. **Trust layer ناقص** — sanctions screening API stub، KYB manual فقط
5. **مفيش Public API / ERP connectors** — blocker للـ enterprise sales
6. **Spend Analytics سطحية** — `PerformanceController` بسيط جداً
7. **مفيش Mobile/PWA** — procurement managers في الـ field

---

## 2. الـ Phases (مرتبة حسب القيمة/الجهد)

```
Sprint  1   2   3   4   5   6   7   8   9  10  11  12  13  14
        ├───┴───┤
        │ Ph 0  │  Stabilization (أساسيات)
                ├───┴───┴───┤
                │   Phase 1  │  Discovery & Search
                            ├───┴───┴───┴───┤
                            │    Phase 2     │  Trust Layer
                                            ├───┴───┴───┴───┴───┤
                                            │       Phase 3      │  Trade Finance MVP
```

> **القرار:** Phase 4 (Catalog) يبدأ بعد Phase 3 يخلص. Phase 5+ (AI، Logistics، API) بعد ما يكون عندنا 100+ active companies.

---

## 🟢 Phase 0 — Stabilization (أسبوعين، Sprints 1-2)

**الهدف:** الـ code اللي عندنا يطلع enterprise-grade. مفيش features جديدة، بس كل اللي موجود يتقن.

**Why first:** بدون ده، أي feature جديد هيقعد يضرب في bugs قديمة. الـ ROI الفوري على الـ existing user base.

### Sprint 1 — Performance & Search Foundations (أسبوع)

| # | Task | Effort | Owner | Done When |
|---|---|---|---|---|
| 0.1 | **Cache sidebar badges** بـ Redis tag-based invalidation. كل badge يعيش 60 ثانية | يوم | BE | DB queries -70% للـ sidebar |
| 0.2 | **Move all `Notification::send` calls** inside `dispatch()->onQueue('notifications')` + setup Horizon | يومين | BE | request time من 2s لـ 200ms |
| 0.3 | **Replace `LIKE %q%` searches** بـ Postgres trigram indexes (`pg_trgm`) أو MySQL FULLTEXT | يوم | BE | search ينفع لـ 100K rows |
| 0.4 | **Real category-based RFQ matching** — استبدال `($id * 13) % 31` | 3 أيام | BE | match scores معبرة |
| 0.5 | **N+1 query audit** — install `barryvdh/laravel-debugbar` في staging، fix كل query > 5 calls | يوم | BE | zero N+1 في الـ index pages |

**Deliverable:** نفس المنصة، 5x أسرع، search حقيقي.

### Sprint 2 — Data Quality & Polish (أسبوع)

| # | Task | Effort | Owner | Done When |
|---|---|---|---|---|
| 0.6 | **Convert `info_request` JSON** إلى typed `company_metadata` table مع keys مفهرسة | يوم | BE | bank_details + admin_notes في tables منفصلة |
| 0.7 | **Schedule `ExpireCompanyDocuments`** في `routes/console.php` daily + UI dashboard | يوم | FS | Documents Dashboard بيشتغل |
| 0.8 | **Bulk actions:** "Approve All Selected" في PR pending list + bid list | يومين | FS | manager يقدر يعتمد 10 PR في click واحد |
| 0.9 | **CSV export** لكل index page (PR, RFQ, Bid, Contract, Payment) | يوم | FS | كل صفحة index فيها زر Export |
| 0.10 | **Empty states** متقنة — كل صفحة فاضية تقول "you have no X yet" + CTA | نص يوم | FS | polish |
| 0.11 | **Audit Logs Observer** — `LogsActivity` trait على Contract, Bid, PR, Payment models | يوم | BE | كل state change بيتسجل |
| 0.12 | **CI/CD setup** — GitHub Actions: phpstan + tests + auto-deploy preview branches | يومين | DevOps | quality gate شغّال |
| 0.13 | **Translate الـ 86 keys الجديدة** بمراجع لغوي محترف | حسب المراجع | Editor | Arabic copy احترافي |

**Deliverable Phase 0:** المنصة بتشتغل بدون bugs ظاهرة + Search سريع + Audit logging كامل + CI/CD.

### Acceptance Criteria — Phase 0
- [ ] جلسة dashboard كاملة < 2 ثانية لكل صفحة
- [ ] بحث في 10K RFQ بيرجع نتائج < 200ms
- [ ] Match scores في dashboard supplier تتطابق مع category overlap حقيقي
- [ ] كل notification بتروح في < 100ms (مش 2s)
- [ ] Coverage في tests > 50% للـ controllers
- [ ] أول deploy via GitHub Actions ناجح

---

## 🟡 Phase 1 — Discovery & Search (3-4 أسابيع، Sprints 3-6)

**الهدف:** أي مستخدم يقدر يكتشف الـ supply / demand اللي موجودة على المنصة. ده الـ blocker الأكبر للـ network effect.

**Why next:** بعد ما الـ foundations سليمة، الـ priority الأولى هي إن الموردين يلاقوا الـ RFQs و الـ buyers يلاقوا الموردين. بدون ده، الـ منصة فاضية حتى لو فيها 1000 user.

### Sprint 3 — Supplier Directory & Smart Matching (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 1.1 | **Supplier Directory page** `/dashboard/suppliers/directory` | 3 أيام | Buyers يقدروا يتصفحوا 100 supplier بـ filters |
| 1.2 | **Filters:** category, country, verification level, rating, certifications | يومين | filters تشتغل بـ Postgres GIN indexes |
| 1.3 | **`Rfq::matchScoreFor($supplier)` method** — single source of truth | يوم | controllers + tests تستخدمه |
| 1.4 | **Match score rules:** category 40, country 20, history 15, verification 10, active 10, certs 5 | يوم | scores 0-100 deterministic |

### Sprint 4 — Saved Searches & Smart Notifications (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 1.5 | **`saved_searches` table** + UI "Save this search" button | يومين | users يقدروا يحفظوا filters |
| 1.6 | **Daily digest job** يجمع matching results + يبعت email | يومين | suppliers بيستلموا "3 new RFQs match you" daily |
| 1.7 | **Match threshold slider** (50-100%) في notification preferences | يوم | users يـ tune الـ noise |
| 1.8 | **Smart pull instead of push:** "Recommended for You" section في supplier dashboard | يوم | بيـ replace الـ "Latest RFQs" stub |

### Sprint 5 — Category Taxonomy Standardization (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 1.9 | **Import UNSPSC taxonomy** (4 levels, ~150K codes) في `categories` table | يومين | كل category مربوطة بـ UNSPSC code |
| 1.10 | **Hierarchical category browser** (segment → family → class → commodity) | يومين | browser بيشبه Amazon's category tree |
| 1.11 | **Migration script** للـ existing RFQs/Products لـ UNSPSC | يوم | كل entity له category code معياري |

### Sprint 6 — Search Polish & Public Discovery (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 1.12 | **Meilisearch integration** (أو Postgres FTS لو الـ server صغير) | 3 أيام | full-text search على RFQs + Products + Companies |
| 1.13 | **Search history** — كل user يشوف آخر 10 searches | يوم | quick access لـ frequent queries |
| 1.14 | **Public landing page** للـ "Browse Suppliers" بدون login | يوم | SEO + lead generation |

### Deliverable Phase 1
**Buyer جديد يلاقي 10 موردين مناسبين في 30 ثانية. Supplier يستلم 1-3 RFQs ذات صلة في اليوم بدل 50 RFQ random.**

### Acceptance Criteria — Phase 1
- [ ] Supplier directory بـ filters يرجع 50 results في < 300ms
- [ ] Match score يتغير لما تـ change supplier categories
- [ ] Daily digest emails بتروح فعلاً (verified بـ test account)
- [ ] UNSPSC taxonomy واضحة في الـ category dropdowns
- [ ] Search "stainless steel" يرجع 20 RFQ + 5 product + 3 supplier ذو صلة

---

## 🟠 Phase 2 — Trust Layer Completion (4-6 أسابيع، Sprints 7-10)

**الهدف:** قبل ما نطلب من شركة دفع $50K للمنصة، لازم نقدر نقولها بثقة "الـ supplier ده نظيف."

**Why next:** بدون trust حقيقي، اللي عندنا في Phase 1 (discovery) بيـ surface موردين مش verified — وده يضر السمعة. لازم الـ trust layer يكون ready قبل ما الـ network ينمو.

### Sprint 7 — Sanctions Screening Real Integration (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 2.1 | **OpenSanctions.org integration** عبر `App\Services\Sanctions\OpenSanctionsProvider` | 3 أيام | كل registration بيـ trigger screening |
| 2.2 | **Hook في `RegisterController::register`** — fire `ScreenCompany($company)` job | يوم | sanctions check تلقائي |
| 2.3 | **Admin alert + auto-set `verification_level = pending_review`** | يوم | matches بتمنع approval |
| 2.4 | **Daily re-screen cron** — sanctions lists بتتحدث | يوم | الشركات الموجودة بتتفحص |

### Sprint 8 — KYB Workflow Completion (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 2.5 | **Verification levels workflow:** Bronze → Silver → Gold | يومين | clear path للـ companies للترقية |
| 2.6 | **Admin verification queue** صفحة في `/dashboard/admin/verification` | يومين | admin يراجع docs و يوافق |
| 2.7 | **`beneficial_owners` table** + form في settings للـ Gold tier | يوم | UAE PDPL compliance |
| 2.8 | **Tier badges على supplier profile + bid card + RFQ list** | يوم | trust signals visible |

### Sprint 9 — Documents & Compliance Dashboard (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 2.9 | **Documents Dashboard** `/dashboard/compliance/documents` | يومين | كل docs مع expiry status |
| 2.10 | **Status pills:** Valid (>30 days)، Expiring Soon (<30)، Expired | يوم | clear visual states |
| 2.11 | **Renew workflow** — re-upload + admin re-verify | يوم | full lifecycle |
| 2.12 | **`company_certifications` JSON wired** في Bid show + Supplier profile | يوم | verified certs visible |

### Sprint 10 — Insurance + Credit (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 2.13 | **`company_insurances` table** (type, policy, coverage, expires_at, doc) | يوم | data model |
| 2.14 | **Manual insurance upload** + admin verification | يومين | حالياً manual، API integration in Phase 3+ |
| 2.15 | **"Insured" badge** على supplier profile | نص يوم | trust signal |
| 2.16 | **Credit Scoring Provider Interface** + mock implementation | يومين | adapter pattern للـ AECB/SIMAH integration لاحقاً |

### Deliverable Phase 2
**شركة Gold-tier لها profile زي LinkedIn for B2B: rating + reviews + sanctions clear + insured + verified docs + beneficial owners disclosed.**

### Acceptance Criteria — Phase 2
- [ ] Supplier جديد بيـ register → في 5 دقائق فيه sanctions screening result
- [ ] Verification queue UI تعرض pending companies مع كل المستندات
- [ ] Document expiry notifications بتروح 30 يوم قبل
- [ ] Gold tier badge يظهر فقط للشركات اللي عندها audited financials
- [ ] Beneficial owners form لازم تتعملى قبل Phase 3 trade finance flow

---

## 🔴 Phase 3 — Trade Finance MVP (8-12 أسبوع، Sprints 11-14+)

**الهدف:** الـ killer feature. ده اللي بيخلي المنصة "must have" مش "nice to have".

**Why now:** بعد ما عندنا trust layer، الـ buyers مستعدين يحطوا فلوس في escrow. ده الـ feature اللي بيـ justify الـ enterprise pricing ($5K-50K/month).

### القرار الأساسي قبل ما نبدأ
**Marketplace أم Principal؟**
- ✅ **Marketplace (الـ MVP):** الـ منصة بتربط الـ buyer بـ bank partner، لكن الـ funds مش بتعدي علينا. ده ميحتاجش license جديدة و سريع launch.
- ❌ **Principal:** نأخذ DIFC license و نبقى financial entity. لاحقاً.

### Sprint 11 — Escrow Foundations (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 3.1 | **Bank partnership signed** — Mashreq NeoBiz أو Emirates NBD Trade | external | API access |
| 3.2 | **`escrow_accounts` table** + `escrow_releases` table | يوم | schema |
| 3.3 | **`App\Services\Escrow\BankPartnerAdapter` interface** + first implementation | يومين | adapter pattern |
| 3.4 | **Webhook handler** للـ bank partner deposits | يومين | bank يـ notify لما funds يصلوا |

### Sprint 12 — Escrow Workflow (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 3.5 | **"Activate Escrow" button** على contract show page (post-sign) | يوم | clear CTA |
| 3.6 | **Modal flow** لـ deposit (buyer يحط مبلغ، bank بيـ confirm) | يومين | UX clean |
| 3.7 | **Auto-release on shipment delivery** — listener على `ShipmentDelivered` event | يومين | automation شغّالة |
| 3.8 | **Manual release UI** للـ buyer (override) | يوم | flexibility للـ edge cases |

### Sprint 13 — Smart Payment Release (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 3.9 | **`release_condition` field** على كل milestone في `payment_schedule` JSON | يوم | data model |
| 3.10 | **Conditions:** `manual`, `on_signature`, `on_delivery`, `on_inspection_pass` | يوم | ENUM-like in JSON |
| 3.11 | **Cron job (every 10 min)** يـ check pending milestones و يـ auto-release | يومين | smart release شغّال |
| 3.12 | **Audit trail** لكل release في `escrow_releases` | يوم | compliance |

### Sprint 14 — Multi-currency + Polish (أسبوع)

| # | Task | Effort | Done When |
|---|---|---|---|
| 3.13 | **`exchange_rates` table** + daily cron من Open Exchange Rates | يوم | live rates available |
| 3.14 | **Money formatter** بيـ convert لما display currency != contract currency | يوم | UI consistency |
| 3.15 | **Dual-currency input** في contract creation | يوم | enter في currency 1، display conversion |
| 3.16 | **Escrow dashboard** للـ buyer + supplier (held / released / total) | يومين | transparency |

### Deliverable Phase 3
**Buyer يدفع مرة واحدة في escrow → فلوس بتـ release تلقائي لما الـ shipment يوصل verified — مفيش "did you get my payment?" rounds.**

### Acceptance Criteria — Phase 3
- [ ] Buyer يقدر يـ activate escrow في 60 ثانية بعد contract sign
- [ ] Bank partner webhook بيوصل خلال 5 دقائق من الـ deposit
- [ ] Auto-release بيشتغل لما `Shipment::status = delivered`
- [ ] Escrow balance visible في contract show + supplier dashboard
- [ ] Audit log بـ كل escrow event (deposit, release, refund)

---

## 3. ما بعد الـ 14 أسبوع (Phases 4-8 مختصرة)

دي phases بنخطط لها بس مش بنبدأها قبل ما الـ Phases 0-3 يخلصوا.

### 🟣 Phase 4 — Catalog & Buy-Now (3-5 أسابيع)
- `products` + `product_variants` tables
- Buyer catalog browse
- Cart + multi-supplier checkout
- Quick reorder
- (optional) cXML punchout

**Trigger to start:** بعد ما يكون عندنا 100+ active suppliers مع repeat customers

### 🤖 Phase 5 — AI Layer (6-8 أسابيع)
- Document OCR (invoices, BLs)
- Negotiation Assistant
- Contract Risk Analysis
- Predictive Analytics
- Procurement Copilot (chat interface)

**Trigger to start:** بعد ما يكون عندنا 1000+ contracts كـ training data

### 📦 Phase 6 — Logistics Integration (4-6 أسابيع)
- Carrier APIs (Aramex, DHL, FedEx)
- HS Code auto-suggest
- Carbon footprint tracking
- Customs documents automation
- Dubai Trade portal integration

**Trigger to start:** أول enterprise customer يطلبه

### 🔌 Phase 7 — Public API + ERP Connectors (4-8 أسابيع)
- REST API v1 + OpenAPI docs
- Webhooks system
- First ERP connector (Odoo)
- SSO (SAML/OIDC)
- SCIM provisioning

**Trigger to start:** أول integration request من enterprise customer

### 🌱 Phase 8 — ESG & Sustainability (متوازي مع 6-7)
- ESG scorecards
- Carbon emissions full tracking
- Modern slavery audit trail
- Conflict minerals reporting

**Trigger to start:** أول EU customer يطلبه (CSRD compliance)

---

## 4. Cross-Cutting Concerns (طول الـ Phases)

دي مش phases منفصلة — دي مهام بـ run في الـ background طول الـ 14 أسبوع.

### Performance & Scale
- [ ] Read replicas للـ DB قبل 500 active companies
- [ ] Redis للـ session + cache + queue
- [ ] CDN للـ static + uploaded images
- [ ] Database indexes audit (كل WHERE/JOIN فيه index)

### Observability
- [ ] **Sentry** للـ error tracking — Phase 0
- [ ] **Datadog/Grafana Cloud** للـ APM — Phase 1
- [ ] **Structured logging** مع `request_id` — Phase 0
- [ ] **Custom metrics** (counters + histograms) — Phase 1

### Security
- [ ] **Penetration test** قبل launch — Phase 2
- [ ] **OWASP ZAP scan** monthly automated — Phase 1
- [ ] **Bug bounty** على HackerOne — Phase 3
- [ ] **Backup strategy:** daily DB + S3 + tested restore quarterly — Phase 0
- [ ] **Disaster recovery:** RTO 4 hours, RPO 1 hour — Phase 1
- [ ] **Secrets management** (AWS Secrets Manager / Doppler) — Phase 0

### Data Quality
- [ ] **Master data management:** vendor cleansing pipeline — Phase 1
- [ ] **GDPR/PDPL compliance:** "Right to be forgotten" workflow — Phase 2
- [ ] **Data export:** كل user يقدر يحمل بياناته — Phase 2

### Developer Experience
- [ ] **CI/CD pipeline** (GitHub Actions) — Phase 0
- [ ] **Staging environment** يطابق production — Phase 0
- [ ] **Feature flags** (Laravel Pennant) — Phase 1
- [ ] **Test coverage** 70% services + controllers, 90% business logic — Phase 0+

---

## 5. القرارات الحرجة قبل البداية

دي قرارات لو اتأخرت، الـ roadmap بيتعطل. لازم تتاخد قبل Sprint 1:

| # | القرار | Default Recommendation |
|---|---|---|
| 1 | **Database engine:** MySQL أم Postgres؟ | **Postgres** — للـ JSONB، pgvector، concurrent indexes، partitioning |
| 2 | **Search engine:** Meilisearch أم Postgres FTS؟ | **Postgres FTS** للـ MVP، Meilisearch لو > 100K rows |
| 3 | **Queue driver:** database أم Redis؟ | **Redis** + Horizon من Phase 0 |
| 4 | **Hosting:** AWS، DigitalOcean، Hetzner؟ | **Hetzner** للـ MVP (cheap)، AWS/GCP لاحقاً |
| 5 | **Bank partner للـ Escrow:** Mashreq أم Emirates NBD؟ | اتفاوض مع الاتنين، اختار اللي بـ better APIs |
| 6 | **Sanctions provider:** OpenSanctions أم Refinitiv؟ | **OpenSanctions** للـ MVP (free)، Refinitiv للـ enterprise |
| 7 | **AI provider:** Claude أم GPT؟ | **Claude (Sonnet 4.6 1M context)** للـ default — long contracts |
| 8 | **DIFC license application timing** | ابدأ في Sprint 1 — process بياخد 6-9 شهور |
| 9 | **Verticals focus:** أي 2-3؟ | **Construction Materials + Industrial Equipment + Electronics** (GCC focus) |
| 10 | **Translation review** للـ 86 keys الجديدة | اشتغل مع مراجع لغوي قبل Sprint 2 |

---

## 6. فريق العمل المقترح للـ 14 أسبوع

### Minimum viable team
| Role | عدد | الوقت | الأولوية |
|---|---|---|---|
| **Backend Engineer (Laravel senior)** | 2 | full-time | حرج |
| **Frontend Engineer (Blade + Alpine + Tailwind)** | 1 | full-time | حرج |
| **Product Manager** | 1 | full-time | حرج |
| **QA Engineer** | 1 | full-time | عالي (من Phase 1) |
| **DevOps** | 0.5 | part-time | عالي (Phase 0) |
| **Compliance Officer** | 0.5 | part-time | عالي (Phase 2-3) |
| **UX Designer** | 0.5 | part-time | متوسط |
| **Translation reviewer (AR)** | 0.25 | as-needed | متوسط |

**Total:** 5-6 FTE للـ 14 أسبوع

---

## 7. النموذج المالي المختصر

### تكلفة 14 أسبوع (تقديرية)
| Item | شهر واحد | 14 أسبوع |
|---|---|---|
| Salaries (5 FTE × $4K avg) | $20K | $70K |
| Infrastructure (Hetzner + S3 + CDN + Redis) | $300 | $1K |
| Third-party services (Sentry, OpenSanctions, OpenExchangeRates, Mailgun) | $400 | $1.4K |
| Bank partner setup fees + legal | one-time | $5K |
| Penetration test (Phase 2) | one-time | $7K |
| **Total** | $20.7K | **$84.4K** |

### Revenue trajectory المستهدفة
| Milestone | الفترة | Active Companies | MRR |
|---|---|---|---|
| End of Phase 0 | أسبوع 2 | 5 (closed beta) | $0 (free) |
| End of Phase 1 | أسبوع 6 | 25 | $5K |
| End of Phase 2 | أسبوع 10 | 50 | $15K |
| End of Phase 3 | أسبوع 14 | 75 | $30K |
| End of Q2 2026 | أسبوع 26 | 150 | $80K |

---

## 8. مؤشرات النجاح (KPIs)

### North Star Metric
**Active Trading Companies** = شركات عملت على الأقل 1 transaction خلال آخر 30 يوم

### Phase 0 Success
- [ ] Page load avg < 2s
- [ ] DB query avg < 50ms
- [ ] Zero N+1 in production
- [ ] CI pipeline green
- [ ] Audit log capturing 100% of state changes

### Phase 1 Success
- [ ] Average bids per RFQ: 5+ (currently ?)
- [ ] Time from RFQ creation to first bid: < 48 hours
- [ ] Saved searches: 30% of active users
- [ ] Daily digest open rate: > 25%

### Phase 2 Success
- [ ] 100% of new registrations sanctions-screened within 5 minutes
- [ ] Verification queue avg response time: < 24 hours
- [ ] % of suppliers with verified documents: > 70%
- [ ] Document expiry notifications: 0 missed

### Phase 3 Success
- [ ] Escrow adoption rate: > 40% of contracts
- [ ] Auto-release success rate: > 90%
- [ ] Avg payment cycle time: from 30 days to 7 days
- [ ] Zero escrow funds lost / disputed

---

## 9. المخاطر و الـ Mitigations

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Phase 3 bank partnership delayed | عالي | كارثي | اتفاوض مع 2 banks بالتوازي من Phase 0 |
| Postgres migration breaks existing data | متوسط | عالي | full backup + tested rollback قبل migration |
| Search re-architecture بياخد 2x الوقت | عالي | متوسط | timebox سبرنت، fallback لـ Postgres FTS |
| Translation reviewer غير متاح | متوسط | منخفض | shortlist 3 freelancers قبل Sprint 2 |
| Sanctions screening API rate limits | متوسط | متوسط | cache + degrade gracefully |
| Compliance officer hiring بياخد وقت | عالي | عالي | hire as contractor للـ Phase 2-3 |
| Escrow legal complexity في UAE | عالي | عالي | engage legal counsel من Sprint 1 |

---

## 10. التحديثات و الـ Reviews

### Cadence
- **Daily standup:** 15 دقيقة
- **Sprint review:** آخر يوم في الـ sprint (cadence: weekly)
- **Phase retrospective:** آخر يوم في الـ phase
- **Roadmap review:** كل 4 أسابيع، update هذه الوثيقة

### Status Tracking
بعد كل sprint، حدّث الجدول التالي:

| Phase | Status | Started | Completed | Notes |
|---|---|---|---|---|
| Phase 0 | ⚪ Not started | — | — | — |
| Phase 1 | ⚪ Not started | — | — | — |
| Phase 2 | ⚪ Not started | — | — | — |
| Phase 3 | ⚪ Not started | — | — | — |

**Status legend:** ⚪ Not started · 🟡 In progress · 🟢 Complete · 🔴 Blocked

---

## 11. المرفقات / الـ References

- **GLOBAL_PLATFORM_ROADMAP.md** — الرؤية الإستراتيجية على 4 horizons
- **NEXT_STEPS.md** — الـ status snapshot الأخير
- **القاعدة 4 الذهبية:** كل feature لازم يجاوب: "هل بيـ generate trust signals بين buyers و suppliers؟"

---

## 12. آخر كلمة

أنتم مش في سباق features ضد SAP. أنتم في سباق **trust + speed + locality** ضدهم.

### 3 mistakes لازم تتجنبوها:

1. **مش تبني features قبل ما يكون الـ existing شغّال 100%.** الـ codebase الحالي فيه 5+ features stub. إكمالها أهم من إضافة 5 جديدة.

2. **مش تقعد تـ chase enterprise customers قبل ما يكون عندكم 10 mid-market customers سعداء.** الـ enterprise procurement يحرق cash بدون deals. ركزوا على 50-200 شركة في الـ mid-market UAE/SA الأول.

3. **مش تبني AI / blockchain قبل ما يكون عندكم network effect.** الـ buzzwords دي بتـ raise money بس. خليها لـ Phase 5+.

### 3 things لازم تـ optimize for from day 1:

1. **Time to first transaction** — من signup للـ first PR/Bid/Contract — لازم < 24 ساعة
2. **NPS بـ 30 يوم** — لو NPS < 30، الـ منتج فيه مشكلة جوهرية
3. **Multi-stakeholder retention** — الـ buyer ميغادرش لو 3+ من suppliers الـ key بتاعوه على المنصة

---

**Status:** Living document — update after every sprint
**Next review:** End of Sprint 1
**Owner:** TriLink Engineering + Product
