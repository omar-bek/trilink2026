# خطة الوصول بمنصة TriLink إلى المستوى العالمي
## Strategic Roadmap to Global B2B Platform Leadership

> وثيقة استراتيجية تفصيلية — تغطي 4 مراحل (Horizons)، تشمل المنتج، الـ GTM، الفريق، التقنية، الامتثال، والمالية.

---

## 0. ملخص تنفيذي (Executive Summary)

**الوضع الحالي**: منصة B2B procurement محلية تعمل في الإمارات/الخليج، تغطي workflow أساسي (Company → PR → RFQ → Bid → Contract → Payment) مع أدوار متعددة (buyer, supplier, logistics, clearance, finance, sales).

**الرؤية**: خلال 5 مراحل، نتحول من "Regional procurement tool" إلى "Global B2B trade infrastructure" — منصة تتنافس مع SAP Ariba, Coupa, Tradeshift, Jaggaer في فئتها، مع تميز محلي خاص بأسواق MENA و Africa.

**الميزة التنافسية المستهدفة**:
1. **Trust by design** — KYB متعمق مع integration حكومي إقليمي يفوق المنافسين الدوليين
2. **Cross-border friction killer** — escrow + trade finance + customs integration في tool واحد
3. **AI-native procurement** — مش feature ملصوقة، بل embedded في كل decision point
4. **Vertical-first** — السيطرة على 2-3 verticals (Construction Materials, Industrial Equipment, Pharma) قبل التوسع

**المراحل**:
| Horizon | الفترة | الحالة المستهدفة | الهدف الكمي |
|---|---|---|---|
| **H1: Foundation** | المرحلة الأولى | منصة تجارية شغالة بكامل الـ workflow | 50 شركة، 500 transaction |
| **H2: Regional Leader** | المرحلة الثانية | المنصة الأولى في MENA لـ B2B procurement | 1,000 شركة، GMV $50M |
| **H3: International** | المرحلة الثالثة | تواجد فعلي في 3 قارات (MENA, Europe, Asia) | 10,000 شركة، GMV $500M |
| **H4: Global Player** | المرحلة الرابعة | category leader معترف به عالمياً | 100,000+ شركة، GMV $5B+ |

---

## 1. الوضع الحالي (Honest State Assessment)

### ما عندنا ✅
- Multi-role architecture (12 roles + branch_manager) مع fine-grained permissions
- Workflow كامل: PR → approval → auto-RFQ → bidding → comparison → contract → signing → auto-payments
- Tax management مرن (TaxRate model مع category/country precedence)
- Approved suppliers locking (exclusivity)
- Branches مع scoping على branch_manager
- Sales workflow عبر SALES_OFFER RFQs
- Audit logs، notifications، disputes، shipments، categories
- Bilingual (AR/EN) مع RTL

### ما نقصنا ❌ (الفجوات اللي تفصلنا عن المستوى العالمي)
1. **Trust layer ضعيف** — مفيش KYB حقيقي، مفيش credit scoring، مفيش sanctions screening
2. **Discovery primitive** — supplier index بيتصفح يدوي، مفيش matching engine
3. **Pricing intelligence معدومة** — لا should-cost، لا benchmarking، لا historical analysis
4. **Trade finance غائبة** — لا escrow، لا factoring، لا BNPL
5. **Logistics integration صفر** — مفيش ربط مع carriers أو customs portals
6. **ESG/Sustainability غائبة** — لا scoring، لا carbon tracking
7. **API ecosystem محدود** — مفيش public API documented، مفيش webhooks للـ external systems، مفيش ERP connectors
8. **Multi-currency basic** — العملة column بس، مفيش FX rates أو conversion
9. **Analytics ضعيفة** — مفيش spend dashboard، مفيش supplier scorecards
10. **No catalog model** — كل حاجة RFQ، مفيش "buy now" experience

### ما يجعلنا نسبياً أقوى من المنافسين الإقليميين
- نسخة مدفوعة بـ Laravel مرنة (vs مواقع legacy في الخليج)
- AR/EN من اليوم الأول
- نموذج adapt-friendly مع fine-grained permissions
- بنية تحت SaaS تسمح بالتوسع بسرعة

---

## 2. الفلسفة الاستراتيجية (Strategic Pillars)

### Pillar 1: Trust as Product
الثقة مش feature، هي القلب. كل قرار منتج يجب أن يجاوب: "هل ده يخلي مشتري عمره ما عرف المورد ده يثق فيه؟"

### Pillar 2: Zero-Friction Cross-Border Trade
كل feature يجب أن يقلل احتكاك التجارة الدولية: العملة، اللغة، الجمارك، الدفع، الثقافة، القانون.

### Pillar 3: AI-Native, Not AI-Bolted
الـ AI مش chatbot على الجانب. الـ AI داخل كل قرار: matching، pricing، negotiation، fraud detection، contract analysis.

### Pillar 4: Vertical Depth Before Horizontal Breadth
احتكر 2-3 صناعات عميق قبل التوسع. كل vertical له workflow و terminology و compliance خاص بيه.

### Pillar 5: Network Effects Are the Moat
الـ network لما يكبر، يكون defensible. كل قرار منتج يسأل: "هل ده يخلي اللي فيه يدعو حد جديد؟"

---

## 3. الأركان الأربعة (4 Horizons)

---

## H1 — Foundation (المرحلة الأولى)
### الهدف: منتج تجاري كامل، أول 50 عميل ملتزم، product-market fit واضح في الخليج

### Scope تفصيلي

#### A. إكمال الـ MVP الحالي
- ✅ Phase 1-3 (تم فعلاً): bug fixes, tax, branches, sales
- **Catalog Model**: ضيف موديل `Product` و `ProductVariant` ليرفع الموردين منتجاتهم بأسعار ثابتة
- **Buy-Now flow**: بدل RFQ لكل حاجة، المشتري يقدر يشتري من الـ catalog مباشرة
- **Reverse Auction (e-Auction)**: 30-60 دقيقة dynamic bidding مع WebSocket
- **Negotiation rounds**: تحويل [negotiation_messages](app/Models/) من free-form إلى structured rounds

#### B. Trust Layer v1
- **KYB Manual + API**: integration مع UAE Ministry of Economy API (or scraping fallback) لتأكيد trade license
- **Document Vault**: مع expiry tracking لكل document
- **Sanctions Screening v1**: integration مع OpenSanctions.org (open-source) لـ basic screening
- **Verification Levels**: Bronze (registered) → Silver (KYB verified) → Gold (audited)

#### C. Spend Analytics v1
- Spend by category, vendor, branch, time
- Top 10 suppliers
- Maverick spend detection
- Supplier scorecards (OTD, quality if measured, response time)

#### D. Mobile-Responsive Web (PWA)
- لسه مش app native — بس responsive ممتاز للـ procurement managers في الـ field

#### E. Public REST API + Webhooks
- OAuth 2.0
- Documented في OpenAPI/Swagger
- Webhook events لكل state change (PR.approved, RFQ.opened, Bid.accepted, Contract.signed, Payment.processed)

### الفريق المطلوب
| Role | عدد | الأولوية |
|---|---|---|
| Backend Engineer (Laravel) | 2 | حرجة |
| Frontend Engineer (Vue/React) | 1 | حرجة |
| Product Manager | 1 | حرجة |
| Customer Success / BD | 1-2 | حرجة |
| QA Engineer | 1 | عالية |
| DevOps (part-time) | 0.5 | عالية |

### Go-to-Market H1
- **Beachhead market**: شركات استيراد/تصدير متوسطة في الإمارات والسعودية في 2-3 categories (مواد البناء، المعدات الصناعية، المنتجات الكيميائية)
- **Pricing**: Freemium لأول 6 أشهر للحصول على feedback
- **Channels**: 
  - Direct sales / cold outreach عبر LinkedIn
  - Partnerships مع غرف التجارة (Dubai Chamber, Sharjah Chamber)
  - Procurement training events
- **Concierge model**: BD team بتدخل RFQs يدوياً للعملاء الأوائل لخلق early liquidity

### Success Metrics H1
- 50 شركة active (مش بس مسجلة)
- 500 transactions
- $5M GMV
- NPS > 40
- 30% MAU/Total ratio (engagement حقيقي)

---

## H2 — Regional Leader (المرحلة الثانية)
### الهدف: المنصة #1 لـ B2B procurement في MENA

### Scope تفصيلي

#### A. Trust Layer v2 — الجدية تبدأ هنا
- **Credit Scoring**: integration مع AECB (الإمارات), SIMAH (السعودية), Creditinfo, Dun & Bradstreet
- **Beneficial Ownership disclosure** — متطلب AML
- **Insurance Verification**: integration مع شركات التأمين الإقليمية
- **Audited financials upload + verification** للـ Gold tier
- **Supplier badges**: ISO 9001, ISO 14001, Halal, FDA, CE — all verified

#### B. Trade Finance v1 — أول killer feature
- **Escrow Service** بشراكة مع بنك إقليمي (Mashreq, Emirates NBD, ADCB) أو licensed PSP
- **Smart Release**: تلقائي بعد proof of delivery (ربط مع shipment tracking)
- **Multi-stage releases**: 30% advance, 50% on shipment, 20% on delivery

#### C. Logistics Integration
- **Carrier APIs**: Aramex, DHL, FedEx, UPS (الـ small parcels)
- **Freight Forwarder integration**: Maersk, MSC, CMA CGM للـ FCL/LCL
- **Customs**: Dubai Trade portal, Saudi Fasah, Egyptian NAFEZA
- **HS Code Auto-suggestion** عبر AI من item descriptions
- **Carbon footprint estimation** لكل shipment

#### D. Smart Matching Engine
- Embedding-based matching: vector store مع pgvector أو Qdrant
- كل RFQ → embedding، كل supplier → embedding (بناءً على history + categories + geography)
- Cosine similarity + business rules → "10 best matches" with explainability
- **Smart Notifications**: pushed إلى الموردين المطابقين، مش الكل

#### E. Multi-Currency Real
- Live FX rates عبر integration (Open Exchange Rates, currencylayer)
- العقود ممكن تكون بعملة، الدفع بعملة ثانية
- Conversion fees transparent
- Optional FX hedging (forwards) بشراكة مع broker

#### F. Reverse Auctions (Production-Grade)
- 15-60 دقيقة dynamic bidding
- Real-time WebSocket
- Anti-snipe rules (auction extends 2 minutes if last bid in last 2 minutes)
- Confidential bidding mode (الموردين ميشوفوش بعض الأسعار)

#### G. RFP Mode (Multi-Criteria Evaluation)
- Beyond RFQ: weighted scoring matrix
- Buyer defines: Price 40%, Technical 30%, Delivery 20%, ESG 10%
- Each bid scored on each criterion
- Final score auto-calculated, ranking with explanation

#### H. Spend Intelligence v2
- Custom dashboards per buyer organization
- Forecasting (predict next 90 days spend)
- Should-cost models per category
- Benchmarking: "Your average price for category X is 8% above market median"

### الفريق المطلوب
- Backend: 4-5 engineers
- Frontend: 2-3 engineers
- Mobile (PWA → React Native): 1
- ML/AI engineer: 1
- DevOps + SRE: 1
- Data engineer: 1
- Product managers: 2 (one for trust/finance, one for sourcing)
- BD/Sales: 5-7 (2 per major market: UAE, KSA, Egypt)
- Customer success: 3-4
- Compliance officer: 1 (for trade finance + KYB)
- Legal counsel (part-time): 0.5

### Go-to-Market H2
- **Geo expansion**: KSA, Egypt, Jordan, Kuwait, Oman, Bahrain, Qatar
- **Vertical depth**: 3-4 verticals owned
- **Pricing model**: 
  - Buyer subscription: tiered ($500-5000/month)
  - Supplier free + transaction fee (0.5-1.5% on completed contracts)
  - Trade finance margin (1-3% on financed transactions)
- **Strategic partnerships**:
  - Banks (escrow, financing)
  - Logistics providers (preferred rates for users)
  - Government (UAE Ministry of Economy, Saudi MODON)
  - Industry associations
- **Marketing**:
  - Content (procurement insights blog, market reports)
  - Events (own conferences, sponsor major industry events)
  - PR (target Gulf Business, Forbes ME, Wamda)

### Compliance Milestones H2
- **VARA / SCA license** للـ trade finance activities (UAE)
- **SAMA approval** للخدمات المالية (السعودية)
- **AML/CFT program** مع compliance officer مفرغ
- **Data residency**: servers في UAE + KSA لـ data sovereignty
- **ISO 27001** certification
- **SOC 2 Type I** audit

### Success Metrics H2
- 1,000 active companies
- $50M GMV
- $5M ARR
- 80% retention year-over-year
- 60% of transactions on platform-managed escrow

---

## H3 — International (المرحلة الثالثة)
### الهدف: منصة معترف بها في 3 قارات، profitable

### Scope تفصيلي

#### A. Geographic Expansion
- **Africa (top priority)**: Egypt → Morocco → South Africa → Nigeria → Kenya
- **Asia**: India → Pakistan → Indonesia → Vietnam
- **Europe (selected)**: Türkiye → Cyprus → Malta (gateway to EU)
- لكل سوق: local entity, local language, local payment, local compliance

#### B. Trust Layer v3 — Enterprise-Grade
- **Real-time sanctions screening** على كل transaction (مش بس على الـ onboarding)
- **Adverse Media monitoring** عبر Refinitiv World-Check or LexisNexis
- **PEP screening** ongoing
- **Watchlists ESG**: dirty list (forced labor, environmental violations)
- **Decentralized Identity (optional)**: integration مع GLEIF (Global Legal Entity Identifier) — ده standard للـ enterprise globally

#### C. Trade Finance v2 — Full Stack
- **Invoice Factoring**: المنصة (أو bank partner) تشتري الـ approved invoice من المورد بـ discount
- **Reverse Factoring (Supply Chain Finance)**: المشتري يدفع للمنصة في الميعاد، المنصة دفعت للمورد بدري
- **BNPL B2B**: المشتري ياخد البضاعة دلوقتي، يدفع في 60-90 يوم، المنصة بتاخد credit risk
- **Letter of Credit (LC)**: digital LC مع banks integration (SWIFT MT700)
- **Trade Credit Insurance**: integration مع Atradius, Coface, Euler Hermes

#### D. Logistics v2
- **Multimodal**: ocean + air + road + rail combined
- **Real-time GPS tracking** لكل shipment
- **Document automation**: BL, COO, packing list, invoice generated in standard format
- **Customs brokerage marketplace**: المخلصين بيقدموا خدماتهم زي الموردين

#### E. AI Procurement Copilot
- **Conversational interface**: "Find me 3 suppliers for stainless steel pipes 304 grade, ISO 9001, can deliver to Jeddah in 4 weeks"
- **Negotiation assistant**: يقترح counter-offers based on market data و supplier history
- **Contract redlining**: AI بيقرا العقد ويحدد risky clauses
- **Spend anomaly detection**: "ده الـ supplier ده رفع أسعاره 15% بدون سبب واضح. هل تعرف؟"
- **Predictive analytics**: "بناءً على pattern الشراء، هتحتاج reorder خلال 18 يوم"

#### F. ERP Connectors
- **Native integrations**: SAP S/4HANA, Oracle ERP Cloud, Microsoft D365, NetSuite, Odoo
- **Punchout catalogs**: cXML, OCI standards
- **EDI gateway**: AS2/X12 (850, 855, 856, 810)

#### G. Vertical Solutions
- **Construction Procurement Suite**: BoQ management, change orders, retention payments, progress billing
- **Industrial Equipment Module**: technical specs, certifications, warranty tracking
- **Pharma Procurement Module**: GDP compliance, cold chain, batch tracking, expiry management

#### H. Marketplace App Store (المنصة كـ platform)
- Third-party developers يبنوا تطبيقات
- Revenue share مع developers
- Examples: insurance providers, cargo inspection, freight quotation tools

### الفريق المطلوب
- Engineering: 25-40 (5-7 squads مستقلة)
- Product: 6-8 PMs
- Design: 3-5
- Data/ML: 5-8
- Sales: 30-50 (regional hubs)
- Customer success: 15-25
- Compliance/Legal: 5-8
- Finance/Operations: 10-15
- Marketing: 8-12
- HR: 3-5

### Go-to-Market H3
- **Enterprise sales motion**: dedicated AE for $1M+ deals
- **Channel partners**: consultancies (Deloitte, PwC, Accenture procurement practices)
- **Strategic alliances**: SAP, Oracle (be in their app stores)
- **Industry events**: ProcureCon, Gartner Procurement, ISM Conference

### Compliance Milestones H3
- **Multi-jurisdiction trade finance licenses**: UK FCA, EU MiFID II passporting, Singapore MAS
- **SOC 2 Type II**
- **ISO 27001 + ISO 27017 (cloud)**
- **GDPR full compliance** + **CCPA** + **LGPD (Brazil)** + **PDPL (Saudi)**
- **PCI DSS** if processing cards directly
- **Sustainability reporting**: prepare for CSRD compliance

### Success Metrics H3
- 10,000 active companies
- $500M GMV
- $50M ARR
- Profitable in 60% of markets
- 5-7 verticals with > 100 companies each

---

## H4 — Global Player (المرحلة الرابعة)
### الهدف: category leader معترف به مع SAP Ariba, Coupa, Jaggaer, Tradeshift

### Scope تفصيلي

#### A. Tech Foundation Maturity
- **Multi-region active-active**: latency < 100ms في أي قارة
- **Data residency**: customer can choose region
- **99.95% SLA** with credits
- **Zero-downtime deployments**, blue/green
- **Chaos engineering** practice

#### B. AI as Competitive Moat
- **Proprietary procurement LLM** fine-tuned على بيانات المنصة
- **Multi-modal**: image recognition للـ products, OCR للـ contracts/invoices
- **Voice interface** للـ procurement managers in field
- **Autonomous procurement**: للـ routine repeat purchases (with guardrails)

#### C. Embedded Finance Stack Complete
- License or charter as a financial institution في region واحدة على الأقل
- **Multi-currency wallets** للشركات
- **Cross-border instant payments** عبر stablecoin rails (USDC, EURC) للـ markets اللي بتدعمها
- **Working capital optimization** كـ service

#### D. Sustainability & ESG Leadership
- **Scope 3 emissions tracking** لكل supply chain
- **Carbon offsets marketplace** integrated
- **CSRD/ESRS reporting** للـ buyers (required for EU operations)
- **Modern slavery audit trail**
- **Circular economy features** (returns, refurbishment, recycling)

#### E. Marketplace Ecosystem
- **App store** mature مع 100+ third-party apps
- **Developer platform** مع SDKs بكل اللغات الرئيسية
- **API revenue stream** (per-call billing for premium APIs)

#### F. Industry Standards Leadership
- **شراكة في تطوير standards** عبر GS1, ISO, GLEIF
- **Open-source contributions** (cXML extensions, OpenLineage for procurement)

### Success Metrics H4
- 100,000+ active companies
- $5B+ GMV
- $500M+ ARR
- Recognized in Gartner Magic Quadrant for Procurement
- Listed (IPO) أو acquired بـ premium valuation

---

## 4. الـ Tech Architecture Evolution

### H1 Architecture (الحالي + توسعات بسيطة)
- Laravel monolith
- MySQL primary
- Redis cache
- Single region (UAE)
- Vue/Inertia frontend
- Cron-based jobs

### H2 Architecture
- Laravel monolith لسه (لا تكسر اللي شغال)
- Read replicas للـ MySQL
- Elasticsearch/Meilisearch للـ search
- Queue workers (Horizon)
- Object storage (S3-compatible)
- WebSocket server (Soketi/Reverb) للـ auctions
- pgvector أو Qdrant للـ embedding matching
- 2 regions (UAE primary, KSA secondary)
- CDN (Cloudflare)
- Datadog للـ APM

### H3 Architecture
- **Modular monolith** → start splitting hot modules into services:
  - Trust/KYB service
  - Search/Matching service
  - Trade Finance service
  - Logistics integration service
  - Notification service
  - Analytics service
- Event bus (Kafka or NATS)
- Multi-region (UAE, EU, India)
- Data lake (S3 + Parquet) للـ analytics
- ML platform (Vertex AI أو SageMaker)
- Service mesh (Istio)

### H4 Architecture
- Microservices حقيقية (لكن بـ distributed monolith awareness)
- Event sourcing للـ critical workflows
- CQRS where it makes sense
- Multi-region active-active with conflict resolution
- Edge compute للـ user-facing read APIs
- Privacy-preserving compute للـ data residency

### Key Tech Decisions Now (تأثيرها بعدين)

| القرار | الاختيار الموصى به | السبب |
|---|---|---|
| Database | PostgreSQL > MySQL | JSONB، pgvector، concurrent index, mature partitioning |
| Search | Meilisearch ثم Elasticsearch | Meili أبسط للبداية، Elastic للـ scale |
| Queue | Laravel Horizon → Kafka | Horizon ممتاز للـ early stages |
| Storage | S3-compatible (R2/Wasabi/MinIO) | لا تحبس نفسك في AWS |
| Deployment | Docker + K8s من H2 | باكر كوبر فيها H1 |
| CI/CD | GitHub Actions → GitLab/Jenkins بعدين | بسيط أهم من perfect |
| Monitoring | Sentry + Datadog/Grafana | observability من اليوم الأول |
| Frontend | لو ممكن تنتقل من Inertia → SPA | flexibility أكتر للـ enterprise UX |

---

## 5. Compliance & Legal Roadmap

### H1 — UAE Foundation
- Mainland UAE LLC أو Free Zone (DIFC حسن للـ fintech ambitions)
- VAT registration
- Data Protection (UAE PDPL)
- Basic ToS, Privacy Policy, DPA
- Trademark registration (UAE + key markets)

### H2 — Regional + Trade Finance
- **DIFC fintech license** أو ADGM equivalent
- **SCA approval** لو هتقدم financial services
- **AML officer** مفرغ
- **OFAC compliance program**
- **MLAT awareness** للـ data sharing
- Local entities في KSA, Egypt
- Tax registrations في كل market

### H3 — International Expansion
- **UK FCA** authorization (gateway للـ EU + Africa)
- **Singapore MAS** Major Payment Institution license (gateway للـ Asia)
- **GDPR** full compliance + DPO
- **CSRD readiness**
- **SOC 2 Type II**
- **ISO 27001**

### H4 — Global
- **Banking partnership أو charter**
- **Multi-jurisdiction trade finance**
- **Crypto/stablecoin licenses حيث applicable**
- **CSRD/ESRS reporting capability**
- **ESG audit trail meeting EU Taxonomy**

---

## 6. النموذج المالي الإجمالي (Rough Financial Model)

### Revenue Streams (متعددة)
1. **Buyer SaaS subscriptions** — recurring, predictable
   - Starter: $500/month
   - Pro: $2,000/month
   - Enterprise: $10,000-50,000/month
2. **Transaction fees** — % من GMV
   - 0.5-1.5% on contract value
3. **Trade finance margin** — أكبر مصدر ربح طويل المدى
   - 1-3% on financed amount
4. **API/Integration fees** — للـ developers و enterprise
5. **Premium services** — KYB reports, market intelligence reports
6. **Marketplace fees** (H4) — من third-party apps

### Unit Economics المستهدفة
| Metric | H1 | H2 | H3 | H4 |
|---|---|---|---|---|
| ARPU (annual) | $5K | $15K | $35K | $80K |
| CAC | $3K | $8K | $15K | $20K |
| LTV | $15K | $60K | $200K | $500K |
| LTV:CAC | 5x | 7.5x | 13x | 25x |
| Gross margin | 60% | 70% | 75% | 80% |
| Payback period | 18m | 12m | 9m | 6m |

### Funding Strategy (تقديرية)
| Round | الفترة | المبلغ | الـ valuation المستهدفة |
|---|---|---|---|
| Pre-seed | الآن | $500K-1M | $5-8M |
| Seed | بعد H1 | $3-5M | $15-25M |
| Series A | بداية H2 | $15-25M | $80-150M |
| Series B | منتصف H3 | $50-100M | $400M-1B |
| Series C+ | H4 | $200M+ | $2-5B |

### Key Investors to Target
- **Pre-seed/Seed**: regional VCs (Wamda, Beco, Shorooq, Global Ventures)
- **Series A**: Sequoia ME, Saudi PIF, MIC, e&Capital
- **Series B+**: Tiger, Coatue, General Atlantic, SoftBank Vision Fund
- **Strategic**: SAP Ventures, Salesforce Ventures, Maersk Growth, DP World ventures

---

## 7. Hiring Plan

### H1 (10-15 people)
Founding team + 5 engineers + 1 PM + 2 BD + 1 CS + 1 ops

### H2 (50-70 people)
- Engineering: 18-25
- Product/Design: 5-7
- BD/Sales: 12-18
- CS: 6-10
- Compliance/Legal: 2-3
- Marketing: 3-5
- Operations: 4-6
- HR/Finance: 2-4

### H3 (200-300 people)
- Multi-region offices (UAE HQ + Riyadh + Cairo + London + Singapore)
- Engineering: 60-90
- Product: 15-25
- Sales: 50-80
- CS/Support: 30-50
- Compliance: 10-15
- Operations/Finance: 20-30
- Marketing: 15-25

### H4 (1000+ people)
- Global org chart
- Country managers
- Vertical-specific teams

### Critical Early Hires (in priority order)
1. **CTO with B2B SaaS scale experience**
2. **Head of Compliance** (financial services background)
3. **Head of Trust & Safety**
4. **VP Sales** with enterprise B2B background
5. **Head of Product** with procurement domain expertise
6. **Head of Trade Finance** (banker)
7. **Head of Customer Success**

---

## 8. Risk Matrix

| Risk | الاحتمال | الأثر | Mitigation |
|---|---|---|---|
| Big competitor (SAP/Coupa) enters MENA seriously | متوسط | عالي جداً | Speed to market, vertical depth, regional network effects, government partnerships |
| Regulatory shift (sudden license requirement) | عالي | عالي | Compliance-first culture, legal counsel from day 1, multi-jurisdiction footprint |
| Trade finance bad debt | متوسط | عالي | Credit scoring rigor, insurance, conservative limits, gradual expansion |
| Data breach / security incident | متوسط | كارثي | Security investment from day 1, regular pentests, bug bounty, SOC 2 |
| Key supplier/buyer churn | عالي | متوسط | NPS tracking, customer success investment, multi-touchpoint engagement |
| Technical debt يبطئ النمو | عالي | متوسط | Allocate 20% of engineering capacity to refactoring/infra |
| Geopolitical (sanctions, trade wars) | متوسط | عالي | Diversified geographic exposure, sanctions screening capability |
| Talent acquisition في الـ region | عالي | متوسط | Remote-first option, competitive comp, equity, employer branding |
| Currency volatility | متوسط | متوسط | USD-pegged for now, hedging at scale |
| Litigation من supplier dropped | منخفض | متوسط | Clear ToS, dispute resolution clause, mediation-first |

---

## 9. Critical Decisions Needed Now

دي قرارات لو اتأخرت، الـ roadmap بيتعطل. ادخلها مع المؤسسين/المستثمرين قبل البداية:

1. **Build vs Buy للـ KYB**: نبني internally أو نستخدم Refinitiv/Trulioo؟
   - **اقتراح**: نبدأ بـ partner، نبني internal بعد scale
2. **License strategy**: نتقدم لـ DIFC من الآن أو نأجل؟
   - **اقتراح**: ابدأ الـ application في H1، ضروري للـ trade finance في H2
3. **Trade finance: principal أو marketplace?**
   - **اقتراح**: marketplace في البداية (نوصل buyers/banks مباشرة), principal لاحقاً مع license
4. **Vertical focus**: أي 2-3 verticals نختار؟
   - **اقتراح**: مواد البناء (huge market in GCC, government spending)، المعدات الصناعية، المنتجات الكيميائية
5. **Open source strategy**: نفتح parts من الـ code أم نخلي proprietary؟
   - **اقتراح**: SDK + integration helpers مفتوحة، core proprietary
6. **Acquisition vs build للـ logistics integration**: ممكن نشتري broker صغير؟
   - **اقتراح**: strategic partnership أولاً
7. **AI strategy**: ندرب models خاصة أو نستخدم Claude/GPT APIs؟
   - **اقتراح**: APIs في البداية، fine-tuning في H3+
8. **Data residency**: نلتزم من اليوم الأول أو لما يطلب عميل؟
   - **اقتراح**: architect لها من اليوم الأول، deploy حسب الطلب

---

## 10. Quick Wins للـ 90 يوم القادمة

عشان تظهر momentum بدون انتظار roadmap طويل:

1. **Catalog model + Buy-Now flow** — يفتح UX جديد
2. **Reverse Auction MVP** — wow factor للـ demos
3. **OpenSanctions integration** — trust win مجاني
4. **Spend dashboard v1** — بيخلي CFO يحس بالقيمة
5. **Public API documentation (Swagger)** — يفتح integrations
6. **5 Carrier API integrations** — Aramex, DHL, FedEx, UPS, Fetchr
7. **SOC 2 readiness assessment** — ابدأ الـ paperwork
8. **First white paper / market report** — content marketing kickoff
9. **Strategic partnership announcement** (chamber of commerce, bank, or carrier)
10. **Customer advisory board** من 5-7 design partners

---

## 11. KPIs ولوحة قياس النمو

### North Star Metric
**Active Trading Companies** — شركات عملت على الأقل 1 transaction خلال آخر 30 يوم

### Leading Indicators (يومية/أسبوعية)
- Sign-ups
- KYB completion rate
- First RFQ time (signup → first RFQ)
- First Bid time (signup → first bid)
- First Contract time

### Health Metrics (أسبوعية)
- DAU/MAU ratio
- RFQ → Contract conversion rate
- Contract → Payment success rate
- Average bids per RFQ
- Time to first response (supplier side)

### Growth Metrics (شهرية)
- New active companies
- GMV growth %
- Net Revenue Retention (NRR)
- Gross Revenue Retention (GRR)
- Logo retention

### Trust Metrics (شهرية)
- Disputes / total contracts %
- Dispute resolution time
- Failed payment %
- Sanctions hits
- Verification level distribution

### Financial Metrics (شهرية)
- ARR
- MRR growth
- CAC
- LTV
- Burn rate
- Runway

---

## 12. Roadmap على شكل Gantt مبسط

```
                          H1                H2                  H3                  H4
                          ↓                 ↓                   ↓                   ↓
Trust Layer        ████████░░░░░░    ██████████████      ██████████████      ██████████████
Catalog/Buy Now    ░░██████████░░    ██████████████      ██████████████      ██████████████
Reverse Auction    ░░░░░░██████░░    ██████████████      ██████████████      ██████████████
Smart Matching     ░░░░░░░░░░░░░░    ░░██████████░░      ██████████████      ██████████████
Trade Finance      ░░░░░░░░░░░░░░    ░░░░██████████      ██████████████      ██████████████
Logistics Integ.   ░░░░░░░░░░░░░░    ░░██████████░░      ██████████████      ██████████████
Multi-Currency     ░░░░░░░░░░░░░░    ████████░░░░░░      ██████████████      ██████████████
Spend Analytics    ░░░░░░░░██████    ██████████████      ██████████████      ██████████████
Public API         ░░░░░░░░██████    ██████████████      ██████████████      ██████████████
ERP Connectors     ░░░░░░░░░░░░░░    ░░░░░░░░██████      ██████████████      ██████████████
AI Copilot         ░░░░░░░░░░░░░░    ░░░░░░░░░░░░░░      ░░██████████░░      ██████████████
Verticals (3-5)    ░░░░░░░░░░░░░░    ░░░░░░██████░░      ██████████████      ██████████████
ESG/Sustainability ░░░░░░░░░░░░░░    ░░░░░░░░░░░░░░      ░░░░██████████      ██████████████
Geo Expansion      ░░░░░░░░░░░░░░    ████████████░░      ██████████████      ██████████████
Compliance         ████████████░░    ██████████████      ██████████████      ██████████████
```

---

## 13. ملاحظة استراتيجية ختامية

في السوق ده، الفائز مش بالضرورة اللي عنده أحسن feature. الفائز هو:
- اللي بيتحرك أسرع
- اللي يبني الـ trust الأقوى
- اللي ينجح في الـ network effects
- اللي يبقى عنده الـ patience لـ multi-year game

**3 قواعد ذهبية**:

1. **لا تبنِ ما يستطيع المستخدم شراؤه**: ابدأ بـ partners و APIs، ابني الـ in-house بعد ما تثبت الفئة.
2. **التركيز يفوق الطموح**: في H1، ركز على feature واحد يفوق فيه أي منافس إقليمي. في H2، اثنين. في H3، ثلاثة.
3. **الـ network effects تتغذى من الـ supply side**: كلما زاد عدد الموردين الموثوقين، زادت قيمة المنصة للمشترين. ركز إنفاقك على onboarding الموردين الكبار في كل vertical.

---

**Owner**: TriLink Founding Team
**Last Updated**: 2026-04-07
**Next Review**: After H1 milestones
**Status**: Living document — update quarterly
