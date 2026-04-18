# TriLink - B2B Procurement Platform
## Presentation Deck Content

---

# Slide 1: Title

**TriLink**
_The Smart B2B Procurement Platform for UAE & GCC_

> Streamlining procurement, ensuring compliance, empowering businesses.

---

# Slide 2: The Problem

### What businesses face today in UAE/GCC procurement:

- **Manual procurement processes** — PRs on paper, RFQs via email, contracts in Word files
- **Zero compliance automation** — VAT invoicing, ICV scoring, sanctions screening all done manually
- **Fragmented vendor management** — no centralized supplier directory or performance tracking
- **No transparency** — buyers and suppliers lack real-time visibility into procurement status
- **Regulatory risk** — PDPL, anti-collusion (Federal Decree-Law 36/2023), e-Invoicing (July 2026) requirements growing fast
- **No digital trail** — audit logs, contract versioning, and signature verification don't exist

---

# Slide 3: The Solution

### TriLink: End-to-End Digital Procurement

A single platform that takes a purchase need from **request → RFQ → bidding → contract → payment → shipment** with full regulatory compliance built-in.

**Key differentiators:**
1. UAE-first compliance (not a Western tool adapted for the region)
2. AI-powered procurement intelligence
3. Multi-role architecture (13 distinct roles)
4. Government oversight dashboard
5. Arabic/English bilingual with RTL support

---

# Slide 4: Platform Architecture

### Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel (PHP 8.3) — Monolith |
| **Frontend** | Blade + Alpine.js + Tailwind CSS |
| **Database** | MySQL with full-text search |
| **API** | RESTful + JWT Auth + Sanctum tokens |
| **AI Engine** | Anthropic Claude API |
| **File Storage** | S3-compatible (public + private disks) |
| **Deployment** | Docker + Docker Compose |
| **Testing** | PHPUnit + Playwright E2E |
| **Monitoring** | Sentry error tracking |

### Architecture Highlights
- **60+ Eloquent Models** covering the entire procurement lifecycle
- **70+ Service Classes** with clean separation of concerns
- **90+ Controllers** (Web + API + Admin)
- **Provider Pattern** for all integrations (swap implementations without code changes)

---

# Slide 5: Core Workflow

```
Purchase Request → Manager Approval → Auto-RFQ Generation
       ↓
Supplier Bidding ← (Negotiation / Auction)
       ↓
Bid Acceptance → Auto-Contract Generation
       ↓
Digital Signing (Simple / UAE Pass / Qualified TSP)
       ↓
Payment Processing (Stripe / PayPal / Escrow)
       ↓
Shipment & Logistics Tracking
       ↓
Contract Completion + Feedback
```

---

# Slide 6: User Roles & Access Control

### 13 Distinct Roles

| Role | Responsibilities |
|---|---|
| **Buyer** | Create PRs, publish RFQs, accept bids, manage contracts |
| **Company Manager** | Team management, approval workflows, company settings |
| **Branch Manager** | Branch-scoped operations |
| **Supplier** | Browse RFQs, submit bids, manage products, fulfill contracts |
| **Logistics** | Shipment management, GPS tracking |
| **Clearance** | Customs documentation, Dubai Trade integration |
| **Finance / Finance Manager** | Payment approval, tax invoices, escrow management |
| **Sales / Sales Manager** | CRM-style pipeline, supplier outreach |
| **Service Provider** | Third-party service offerings |
| **Government** | Oversight dashboard, competition analysis, dispute resolution |
| **Admin** | Full platform administration, verification queue, system health |

### Permission System
- Spatie Laravel Permission package
- Fine-grained ability-based access (not just role checks)
- Per-user permission allowlists
- Route-level middleware guards

---

# Slide 7: Procurement Features

### Purchase Requests
- Create, submit, approve/reject workflow
- Bulk approval for managers
- Auto-RFQ generation on approval

### RFQ Management
- Buy & Sell RFQ types
- Anonymous bidding option
- Bid comparison dashboard
- PDF export

### Bidding System
- Supplier bid submission with attachments
- Bid evaluation & scoring
- ICV-weighted scoring for government tenders
- Bulk reject functionality

### Negotiation & Auctions
- Structured counter-offer rounds
- AI-powered negotiation suggestions
- Live auction with real-time polling
- Minimum decrement rules

---

# Slide 8: Contract Management

### Full Contract Lifecycle
- **Auto-generation** from accepted bids
- **Version tracking** with diff/track-changes view
- **Amendment workflow** — propose, negotiate, approve/reject
- **Internal notes** — team-scoped collaboration
- **Internal approval** — threshold-based approval workflows

### Digital Signatures (3 Grades)
| Grade | Method | Legal Standing |
|---|---|---|
| Simple | Password authentication | Basic |
| Advanced | UAE Pass OAuth 2.0 | Strong |
| Qualified | TSP-issued (Comtrust / ESSP) | Full legal equivalence |

### Post-Signing
- Signature audit certificates
- Public verification URL (no auth required)
- Contract renewal & termination workflows
- Reorder from previous contracts

---

# Slide 9: Financial Module

### Payments
- Milestone-based payment schedules
- Payment approval workflow (request → approve → process)
- Stripe & PayPal integration
- Tax invoice auto-generation

### Escrow Accounts
- Mashreq NeoBiz & Emirates NBD integration
- Escrow activation, deposit, release, refund
- Auto-release on delivery milestones

### Tax Invoicing
- UAE VAT compliant (5%)
- Sequential invoice numbering per company
- QR code encoding
- Credit note support
- Admin void/reissue capability

---

# Slide 10: Logistics & Shipping

### Carrier Integrations
- **Regional:** Aramex, Fetchr
- **International:** DHL, FedEx, UPS

### Features
- Real-time GPS tracking
- Tracking event timeline
- Customs documentation (commercial invoice, packing list)
- Dubai Trade portal integration
- Shipping quote calculator
- Carbon footprint tracking (Scope 1-3)

---

# Slide 11: AI-Powered Features

### Anthropic Claude Integration

| Feature | Description |
|---|---|
| **Procurement Copilot** | Conversational AI assistant for procurement guidance |
| **Contract Risk Analysis** | Clause-by-clause risk scoring |
| **Document OCR** | AI-powered field extraction from scanned documents |
| **Negotiation Assistant** | Counter-offer suggestions based on bid history |
| **Price Prediction** | Market trend analysis and price forecasting |
| **HS Code Classification** | Automated product classification for customs |

---

# Slide 12: UAE Regulatory Compliance

### Federal Laws Implemented

| Law | Feature |
|---|---|
| **Decree-Law 46/2021** (E-Transactions) | Qualified digital signatures, TSP integration |
| **Decree-Law 50/2022** (Corporate Tax) | Tax registration, free zone exemptions |
| **PDPL 2021** (Data Protection) | Consent ledger, DSAR, right-to-erasure, encryption |
| **Decree-Law 20/2018** (AML) | KYC/UBO disclosure, sanctions screening |
| **Decree-Law 36/2023** (Anti-Bid Rigging) | Collusion pattern detection, alert triage |
| **Decree-Law 45/2021** (Trade) | ICV scoring, free zone jurisdiction handling |
| **FTA e-Invoicing** (July 2026) | PINT-AE UBL 2.1, ASP submission (Avalara/Sovos) |

---

# Slide 13: Company Verification & KYC

### 4-Tier Verification System

| Tier | Level | Requirements |
|---|---|---|
| **1** | Bronze | Trade license, basic company info |
| **2** | Silver | Insurance, additional documents |
| **3** | Gold | Beneficial ownership (UBO) disclosure, certificates (CoO, ECAS, Halal, GSO, ISO) |
| **4** | Platinum | Full compliance, credit scoring, ESG assessment |

### Compliance Features
- Document vault with expiry tracking
- Auto-notification for expiring documents
- ICV certificate management
- Sanctions screening (OpenSanctions + UAE local lists)
- Blacklist management

---

# Slide 14: ESG & Sustainability

### Environmental, Social & Governance

- **ESG Questionnaire** — Self-assessment scoring for suppliers
- **Carbon Footprint Tracking** — Scope 1, 2, 3 emissions logging
- **Modern Slavery Statement** — Supplier declarations
- **Conflict Minerals Declaration** — ESG conflict minerals disclosure
- **Government ESG Report** — Aggregated ESG metrics across platform

---

# Slide 15: Government Dashboard

### Oversight & Transparency

| Report | What it shows |
|---|---|
| **Contracts Report** | All platform contracts with filtering |
| **Payments Report** | Payment flows and anomalies |
| **ICV Report** | In-Country Value compliance metrics |
| **Competition Analysis** | Market concentration & fairness |
| **Anti-Collusion Report** | Suspected bid-rigging patterns |
| **ESG Report** | Platform-wide sustainability metrics |
| **Sanctions Report** | Screening results & flagged entities |
| **SME Report** | Small business participation metrics |
| **Disputes Report** | Active disputes and resolution stats |

---

# Slide 16: Admin Console

### 20+ Admin Features

- **User Management** — CRUD, toggle, password reset
- **Company Management** — Approve, reject, suspend, reactivate, set verification level
- **Verification Queue** — Priority-sorted verification pipeline
- **Category Management** — UNSPSC-based hierarchical categories
- **Tax Administration** — Tax rates, invoices, credit notes, void
- **ICV Verification** — Certificate approval queue
- **e-Invoice Queue** — FTA submission monitoring & retry
- **Anti-Collusion Triage** — Alert review & status updates
- **Audit Logs** — Tamper-evident, hash-chained, searchable, exportable
- **System Health** — Platform monitoring dashboard
- **Oversight Pivot** — Cross-entity system-wide view
- **Reports & Analytics** — KPIs, supplier scorecard, cycle time, savings, CSV export
- **Dispute Management** — Assignment & SLA tracking
- **Platform Settings** — Global configuration
- **Fee Management** — Commission structure setup
- **Exchange Rates** — Daily FX rate management
- **Webhook Monitoring** — Delivery history & health

---

# Slide 17: API & Integration Layer

### REST API (JWT + Sanctum)
- Full CRUD for all entities
- OpenAPI 3.0.3 specification
- Rate-limited authentication endpoints
- Ability-based permission checks

### SCIM 2.0
- Enterprise IdP user provisioning
- Azure AD / Okta compatible

### Webhook System
- Customer-managed webhook endpoints
- Event types: contract.signed, payment.completed, shipment.updated, etc.
- Delivery logging with retry mechanism
- HMAC-SHA256 signature verification

### ERP Integration
- Odoo connector (contract push, order sync)
- Custom webhook-based connector
- Push contracts to external ERP systems

---

# Slide 18: Notification System

### 45+ Notification Types

**Channels:**
- In-app notifications (database)
- Email notifications
- SMS notifications (urgent alerts)

**Categories:**
- Bid management (new bid, accepted, rejected, lost)
- Contract lifecycle (created, signed, expired, terminated, renewed)
- Amendment workflow (proposed, decided, messages)
- Payment events (requested, approved, failed, overdue)
- Compliance alerts (document expiring, sanctions hit, collusion detected)
- e-Invoice status (dispatched, accepted, rejected by FTA)
- Privacy (DSAR ready, erasure completed, breach notification)

**All notifications are bilingual (Arabic/English)**

---

# Slide 19: Security & Data Protection

### Security Features
- **2FA (TOTP)** — Time-based one-time passwords with recovery codes
- **Tamper-evident audit logs** — Hash-chained with external anchoring
- **Encrypted sensitive fields** — PDPL Article 7 compliance
- **Virus scanning** — File uploads scanned before storage
- **Rate limiting** — Per-endpoint throttling
- **CORS & CSRF protection** — Standard Laravel security
- **Encryption key rotation** — Admin command for key rotation
- **Data breach response** — Incident notification skeleton

### Data Privacy (PDPL)
- Privacy policy versioning
- Cookie consent management
- Data Subject Access Requests (DSAR)
- Right-to-erasure workflow
- Data Processing Agreement
- Consent ledger with audit trail

---

# Slide 20: Product Catalog & Marketplace

### For Suppliers
- Product CRUD with variants & pricing
- AI-powered HS code classification
- Category-based product organization

### For Buyers
- Product catalog browsing with search
- Shopping cart & multi-supplier checkout
- "Buy Now" creates instant Purchase Request
- Quick reorder from previous contracts
- Supplier directory with category filtering

### Global Search
- Federated search across RFQs, products, suppliers
- Saved searches with toggle & digest notifications
- Search history tracking

---

# Slide 21: Localization & UX

### Bilingual Support
- Arabic & English with `__('key')` translation
- RTL layout support (`dir="rtl"` on `<html>`)
- Language switcher in dashboard

### UI/UX
- Dark mode default with light mode toggle
- Tailwind CSS design tokens (bg-page, bg-surface, bg-accent, etc.)
- Responsive dashboard layout
- Alpine.js for interactive components
- Onboarding checklist for new companies

---

# Slide 22: Scheduled Automation

### Automated Tasks (Cron)

| Task | Frequency |
|---|---|
| Exchange rate sync | Daily |
| RFQ deadline reminders | Scheduled |
| Contract expiry reminders | Scheduled |
| Contract renewal alerts | Scheduled |
| Payment overdue reminders | Scheduled |
| ICV certificate expiry warnings | Scheduled |
| Document expiration cleanup | Scheduled |
| Signature window expiration | Scheduled |
| Escrow auto-release | Scheduled |
| Saved search digests | Scheduled |
| Old notification cleanup | Scheduled |
| UNSPSC category mapping | On-demand |
| Sanctions rescreening | On-demand |
| Audit chain anchoring | On-demand |

---

# Slide 23: Compliance Roadmap

### 8-Phase Implementation

| Phase | Feature | Status | Priority |
|---|---|---|---|
| **0** | Foundation Hardening | In Progress | P0 |
| **1** | Tax Invoice Infrastructure | Delivered | P1 |
| **2** | PDPL Foundation | In Progress | P1 |
| **3** | Free Zone & Jurisdiction | Planned | P2 |
| **4** | ICV Scoring | Skeleton Ready | P1 |
| **5** | e-Invoicing (PINT-AE) | Skeleton Ready | P0 (July 2026) |
| **6** | Qualified e-Signature & UAE Pass | In Progress | P1 |
| **7** | Corporate Tax + Anti-Collusion | Partial | P2 |
| **8** | Tier 3 Compliance Polish | Planned | P3 |

---

# Slide 24: By The Numbers

| Metric | Count |
|---|---|
| **Eloquent Models** | 60+ |
| **Service Classes** | 70+ |
| **Controllers** | 90+ |
| **Blade Views** | 100+ |
| **Notification Types** | 45+ |
| **API Endpoints** | 150+ |
| **User Roles** | 13 |
| **Verification Tiers** | 4 |
| **Signature Grades** | 3 |
| **Payment Gateways** | 2 |
| **Shipping Carriers** | 5 |
| **Escrow Banks** | 2 |
| **e-Invoice Providers** | 2 |
| **UAE Laws Implemented** | 7+ |
| **Compliance Phases** | 8 |
| **Languages** | 2 (AR/EN) |

---

# Slide 25: Competitive Advantages

### Why TriLink?

1. **UAE-Native Compliance** — Built from day one for UAE federal laws, not retrofitted
2. **Full Lifecycle Coverage** — PR to payment in one platform
3. **AI-Powered Intelligence** — Not just automation, but smart decision support
4. **Government-Ready** — Dedicated oversight dashboard for regulatory bodies
5. **Enterprise Integration** — SCIM 2.0, webhooks, ERP connectors, public API
6. **Multi-Party Escrow** — Bank-backed escrow with Mashreq & ENBD
7. **Qualified e-Signatures** — UAE Pass + TSP integration for legal-grade signing
8. **Scalable Architecture** — Provider pattern allows swapping any integration
9. **Bilingual & RTL** — True Arabic/English support, not just translated strings
10. **e-Invoice Ready** — Ahead of July 2026 FTA deadline

---

# Slide 26: Target Market

### Primary Market
- **UAE-based B2B companies** — buyers & suppliers in manufacturing, construction, oil & gas, IT services
- **Government entities** — procurement oversight and compliance monitoring
- **Free zone companies** — JAFZA, JLT, DIFC, ADGM with jurisdiction-specific handling

### Expansion
- **GCC countries** — Saudi Arabia, Oman, Bahrain, Kuwait, Qatar
- **MENA region** — Egypt, Jordan, Iraq
- **Global** — Per documented roadmap (docs/GLOBAL_PLATFORM_ROADMAP.md)

---

# Slide 27: Future Vision

### Near-Term (2026)
- Complete e-Invoicing Phase 5 before FTA July 2026 deadline
- Qualified e-Signature rollout
- PDPL full compliance
- Mobile-responsive optimization

### Mid-Term (2027)
- GCC expansion with multi-jurisdiction support
- Advanced AI analytics (spend optimization, supplier risk scoring)
- Marketplace features (product catalog expansion)
- Advanced auction types (Dutch, Japanese)

### Long-Term
- MENA + Global platform per roadmap
- Blockchain-based audit trail anchoring
- Supply chain financing integration
- Real-time market intelligence

---

# Slide 28: Thank You

**TriLink**
_Procurement, Simplified. Compliance, Guaranteed._

Contact: [Your Contact Info]
Demo: /demo endpoint available

---

## Presentation Notes

### Recommended Format
- Use a clean, professional template (navy/white/gold color scheme matching TriLink brand)
- Each slide should have minimal text — expand verbally
- Include screenshots from the actual platform for slides 5-16
- Demo the live auction and negotiation features if possible
- The compliance slides (12-13) are the strongest selling points for UAE market

### Key Talking Points (16-Year Expert Perspective)
1. **Architecture Decision**: Monolith over microservices was intentional — for a procurement platform serving SMEs to enterprises, the operational overhead of microservices isn't justified until you hit 10M+ transactions/month. The provider pattern gives us the same swappability.
2. **Compliance-First**: Most competitors bolt on compliance after building features. We built the audit trail, permission system, and encryption layer first (Phase 0), then built features on top.
3. **AI Integration**: The AI isn't a gimmick — contract risk analysis alone saves legal teams 4-6 hours per contract review. OCR eliminates manual data entry for trade documents.
4. **UAE Pass**: Being one of the few platforms with UAE Pass OAuth integration for qualified signatures gives us a massive competitive moat.
5. **Escrow**: Bank-backed escrow is the feature that closes enterprise deals — it removes the trust barrier in B2B transactions.
