# تقرير التطوير — منصة Trilink للمشتريات
### التغييرات من بداية المشروع حتى آخر تحديث

| المقياس | القيمة |
|---|---|
| إجمالي الملفات المعدَّلة | 750 |
| إجمالي الأسطر المضافة | 161,367+ |
| إصدار Laravel | 11.31 |
| إصدار PHP | 8.3 |
| عدد الـ Migrations | 92 |
| عدد الخدمات (Services) | 95+ |
| عدد الاختبارات | 30 ملف اختبار (8,243 سطر) |
| تغطية i18n | عربي / إنجليزي (كامل) |

---

## ملخص التحديث الأخير

| المقياس | القيمة |
|---|---|
| الملفات المعدَّلة | 501 |
| أسطر مضافة | +25,165 |
| أسطر محذوفة (إعادة هيكلة) | −11,241 |
| صافي النمو | +13,924 |

---

### 1. البنية التحتية والأمان (Infrastructure & Security)

**النشر والتشغيل**

- حاوية **Docker** إنتاجية كاملة: `Dockerfile` متعدّد المراحل (multi-stage build)، `docker-compose.yml`، تهيئة `nginx`، مُشرف `supervisord` للعمّال (queue workers + scheduler)، `opcache` مفعَّل، و`php.ini` مُحكم.
- خط أنابيب **CI/CD** على GitHub Actions: فحص `Pint` (PSR-12)، `PHPUnit` على PHP 8.3، `composer audit` للثغرات الأمنية، و`migrate --force` لاختبار التهجيرات.
- سكربت نشر آلي (`deploy.sh`) مع قفل `flock` يمنع النشر المتوازي، و`php artisan down --retry=60` لنشر بدون انقطاع.

**أدوات التقوية (Hardening Commands)**

| الأمر | الغرض |
|---|---|
| `php artisan keys:rotate` | تدوير مفاتيح التشفير (`APP_KEY`) مع إعادة تشفير الأعمدة الحساسة تدريجياً. |
| `php artisan sanctions:rescreen` | إعادة الفحص الدوري لجميع الشركات ضد قوائم العقوبات (OFAC / UN / EU). |
| `php artisan audit:anchor-chain` | تثبيت نقطة مرجعية (anchor) في سلسلة تجزئة سجلات التدقيق لضمان عدم التلاعب. |
| `php artisan audit:verify-chain` | التحقق اليومي من سلامة السلسلة والتنبيه على أي كسر. |

---

### 2. قاعدة البيانات والتهجيرات (Database & Migrations)

**أكثر من 30 تهجير جديد** — كلها مكتوبة بنمط دفاعي (`Schema::hasColumn` guard) بحيث يمكن إعادة تشغيلها على قواعد dev ملوَّثة دون تدخل يدوي.

**جداول الامتثال (Compliance)**

- `beneficial_owners` — المستفيدون الحقيقيون (متطلب AML/CFT).
- `company_insurances` — وثائق التأمين التجاري.
- `credit_scores` — التصنيف الائتماني للشركات.
- `icv_certificates` + ربط مباشر بـ `rfqs` — نسبة القيمة المضافة داخل الدولة.
- `sanctions_screenings`, `collusion_alerts`, `blacklisted_companies` — فحص مستمر للمخاطر.
- `certificate_uploads` — شهادات طرف ثالث (CoO, ECAS, Halal, GSO).

**جداول الماليات والعقود**

- `tax_invoices`, `tax_credit_notes`, `invoice_number_sequences` — ترقيم متسلسل لا ينكسر.
- `e_invoice_submissions` — قناة الفوترة الإلكترونية عبر ASP (Peppol / FTA).
- `contract_approvals`, `contract_amendments`, `contract_versions`, `contract_parties`.
- `platform_fees` — رسوم المنصة القابلة للتهيئة لكل نوع.

**جداول خصوصية البيانات (PDPL)**

- `consents` — سجل موافقات المستخدمين.
- `privacy_requests` — طلبات الحذف / التصدير (DSR).
- `privacy_policy_versions` — لقطات ثابتة من السياسة لا تقبل التعديل.

**أعمدة مُضافة للشركات والمستخدمين**

- **Companies:** `electronic_seal`, `digital_signature_key`, `free_zone_authority`, `legal_jurisdiction`, `corporate_tax_applicable`, `trc_issue_date`, `trc_expiry_date`, `is_qfzp`.
- **Users:** `notification_preferences`, `locale`, `password_changed_at`, `two_factor_secret` (مشفَّر).

**الأداء والأمان على مستوى قاعدة البيانات**

- فهارس مركّبة (composite indexes) على أعمدة البحث الشائعة: `(company_id, status)`, `(rfq_id, status)`, `(due_date, status)`.
- **التشفير في الراحة (Encryption at Rest)** للأعمدة الحساسة: `AuditLog.{before, after, ip_address, user_agent}`, `User.two_factor_secret`, `CompanyBankDetail.{iban, swift, holder_name}` باستخدام Laravel envelope encryption.

---

### 3. الخدمات (Services)

**95+ خدمة** موزَّعة على 12 مجال وظيفي. كل خدمة مُحقَنة عبر constructor promotion مع `readonly` properties، وتخضع لاختبار وحدة مستقل.

**الشحن والجمارك (Shipping)**

- أدابتر موحَّد لخمس شركات: `Aramex`, `DHL`, `FedEx`, `Fetchr`, `UPS` — عبر `ShippingProviderInterface`.
- `CustomsFeeCalculator` + `DutyEstimator` مرتبط بجدول HS codes.

**المدفوعات (Payments)**

- بوابات: `StripeGateway`, `PayPalGateway`, `MashreqBankGateway` (الإمارات المحلية).
- طبقة تجريد `PaymentGatewayInterface` لاختيار المسار ديناميكياً (`PaymentRail` enum).
- خدمات الإمارات المخصَّصة: `FxLockService`, `WhtService`, `CorporateTaxService`, `DualApprovalService`, `ChequeService` (للشيكات المؤجَّلة PDC)، `CreditNoteAutoGenerator`, `LateFeeAccrualService` (بسقف 12% سنوياً وفق المادة 76 من القانون المدني الإماراتي).

**التوقيع الإلكتروني (Signing)**

- تكامل PKI بمعايير eIDAS / ETSI.
- مزوّدو ختم زمني (TSP) متعدّدون مع fallback تلقائي: `DigicertTsp`, `GlobalsignTsp`, `AmbiorixTsp`.
- `SignatureGradeResolver` يرفض التوقيع إذا كانت قوّته أدنى من الحد الأدنى القانوني للعقد.

**الفوترة الإلكترونية (E-Invoicing)**

- `PintAeMapper` — توليد XML بصيغة PINT-AE للـ FTA.
- أدابتر ASP (Accredited Service Provider) متعدد: `AvalaraAsp`, `SovosAsp`, `MockAsp`.
- `SubmitEInvoiceJob` على queue مع إعادة محاولة exponential backoff.

**الذكاء الاصطناعي (AI)**

- `AnthropicClient` (Claude) — نقطة وصول موحَّدة مع prompt caching.
- `ProcurementCopilot` — مساعد محادثة للمشترين.
- `OcrService` — قراءة الفواتير والبطاقات التجارية.
- `RiskAnalysisService` — تسجيل المخاطر قبل قبول العطاء.

**الامتثال (Compliance)**

- `SanctionsService`, `CollusionDetectionService`, `IcvEvaluator`, `EsgScorer`, `CarbonFootprintCalculator`.

**الخصوصية (PDPL)**

- `DataExportService` — ZIP كامل لكل البيانات الشخصية.
- `DataErasureService` — حذف موجَّه مع الحفاظ على السجلات القانونية المطلوبة.
- `ConsentLedger` — تتبُّع زمني لكل موافقة.

**التكامل مع أنظمة ERP**

- `OdooConnector` (XML-RPC) + `CustomErpConnector` (Webhook-based).
- مزامنة ثنائية الاتجاه لأوامر الشراء والفواتير.

---

### 4. واجهات المستخدم (Views)

**الأدمن (11 صفحة)**

مكافحة التواطؤ · القائمة السوداء · طلبات التصنيفات · شهادات الشركات · المنازعات · أسعار الصرف · رسوم المنصة · التقارير · صحة النظام · الفواتير الضريبية (PDF) · Webhooks.

**الداشبورد**

العطاءات · العقود · المستندات · الجهات الحكومية (10 صفحات فرعية) · طلبات الشراء (5 صفحات) · طلبات عروض الأسعار (RFQs) · بروفايل الشركة · غرفة التفاوض.

**صفحات عامة**

عن المنصة · تواصل · سياسة ملفات الارتباط · الشروط والأحكام.

**صفحات الأخطاء**

`403`, `404`, `419`, `429`, `500` — كلها مترجمة ومتجاوبة مع RTL.

**المكوّنات المشتركة (Blade Components)**

`AdminNavbar`, `FlashMessages`, `Sidebar`, `SkeletonLoading`, `Icons`, `NegotiationRounds`, `StatusBadge`.

**التقنيات الأمامية**

- Vite + Tailwind CSS + Alpine.js.
- دعم **RTL** تلقائي عبر `dir="rtl"` عند اللغة العربية.
- وضع **مظلم (Dark Mode)** مع حفظ التفضيل في `localStorage`.
- محرف عربي (Arabic shaper) للـ PDF عبر `ArabicShaper`.

---

### 5. الإشعارات (Notifications)

**46 نوع إشعار** — جميعها `ShouldQueue` للأداء، ومحليَّة الاستجابة (`LocalizesNotification` trait) بحيث يتلقى كل مستخدم الإشعار بلغته المفضَّلة بغضّ النظر عن لغة العامل (queue worker).

المجالات المغطَّاة: العطاءات · العقود · التواقيع · المدفوعات · الضمانات البنكية · الفوترة الإلكترونية · الضمان (Escrow) · المنازعات · تجديد المستندات · الخصوصية · ICV · انتهاء كلمة المرور · اختراق البيانات.

---

### 6. الاختبارات (Testing)

**30 ملف اختبار — 8,243 سطر** على PHPUnit 11 بقاعدة SQLite في الذاكرة.

- **Security Hardening** — OWASP Top 10 + CSRF + mass assignment.
- **Tax Invoices & E-Invoicing** — توليد PINT-AE، ترقيم متسلسل، credit notes.
- **PDPL Privacy** — تصدير البيانات، حذفها، سجلّ الموافقات.
- **Free Zones & Jurisdictions** — موجِّه البنود القانونية.
- **ICV** — حساب الدرجة المركَّبة للعطاء.
- **Qualified Signatures (Phase 6)** — رفض التوقيع دون eIDAS.
- **Corporate Tax & Collusion** — قواعد QFZP واكتشاف الأنماط المشبوهة.
- **Audit, Payments, Shipping** — سلامة سلسلة التدقيق، rails المدفوعات، تتبُّع الشحنات.
- **Supplier Flows & Dashboard** — رحلات end-to-end.

---

### 7. الترجمة والدعم العربي (i18n)

- ملفات `lang/ar.json` و `lang/en.json` بتغطية كاملة لكل مفتاح في الكود والواجهات.
- **التاريخ الهجري** عبر خدمة `HijriDate` — عرض مزدوج (ميلادي / هجري) في الشهادات والفواتير.
- **مُشكِّل الحروف العربية (`ArabicShaper`)** لتوليد PDF عربية صحيحة عبر DomPDF.
- احترام التقويم الإماراتي: أسبوع عمل السبت–الأحد + الإجازات الرسمية الفدرالية عبر `SettlementCalendarService`.

---

## التفاصيل الكاملة لكل الأقسام

---

## 1. نظام المصادقة والأمان (Authentication & Security)

| الملف / الموديول | الوصف |
|---|---|
| `AuthController` (API + Web) | تسجيل الدخول، التسجيل، تسجيل الخروج، تجديد التوكن (JWT) |
| `RegisterController` | تسجيل الشركات الجديدة مع رفع المستندات المطلوبة |
| `TwoFactorController` + `TwoFactorService` | نظام المصادقة الثنائية (2FA) للحسابات |
| `PasswordResetController` | استعادة كلمة المرور عبر البريد الإلكتروني |
| `JwtAuthenticate` Middleware | التحقق من صلاحية التوكن في API |
| `SecurityHeaders` Middleware | إضافة رؤوس أمان HTTP تلقائياً |
| `AdminIpAllowlist` Middleware | تقييد وصول الأدمن بعناوين IP محددة |
| `PasswordExpiration` Middleware | إجبار تغيير كلمة المرور بعد فترة محددة |
| `CheckOwnership` Middleware | التحقق من ملكية الموارد قبل الوصول |
| `EnsureCompanyApproved` Middleware | منع الشركات غير المعتمدة من الوصول |
| `EnsureUserHasRole` Middleware | التحقق من صلاحيات الأدوار |

---

## 2. إدارة الشركات (Company Management)

| الملف / الموديول | الوصف |
|---|---|
| `CompanyController` (Admin) | عرض، تعديل، اعتماد، رفض، تعليق الشركات |
| `CompanyProfileController` | صفحة البروفايل الموحدة (أدمن + مدير الشركة) |
| `CompanyService` | منطق الأعمال لإدارة الشركات |
| `CompanyDocumentController` | إدارة مستندات الشركة (رخصة تجارية، شهادة ضريبية) |
| `CompanyInsuranceController` | إدارة وثائق التأمين |
| `CompanyUserController` | إدارة فريق العمل داخل الشركة |
| `BeneficialOwnerController` | إدارة المستفيدين الحقيقيين (compliance) |
| `BranchController` | إدارة فروع الشركات |
| `CompanySupplierController` | إدارة قائمة الموردين المعتمدين |
| `VerificationQueueController` | طابور التحقق من الشركات الجديدة |
| `CompanyInfoRequest` Model | نظام طلب المعلومات/المستندات الناقصة من الشركات |
| `BlacklistController` | القائمة السوداء للشركات المحظورة |
| `VerificationLevel` Enum | مستويات التحقق (Unverified, Bronze, Silver, Gold, Platinum) |

---

## 3. طلبات الشراء (Purchase Requests)

| الملف / الموديول | الوصف |
|---|---|
| `PurchaseRequestController` (API + Web) | إنشاء وإدارة طلبات الشراء |
| `PurchaseRequestService` | منطق الأعمال لمعالجة الطلبات |
| `PurchaseRequest` Model | نموذج طلب الشراء مع العلاقات |

---

## 4. طلبات عروض الأسعار (RFQs)

| الملف / الموديول | الوصف |
|---|---|
| `RfqController` (API + Web) | إنشاء ونشر وإدارة طلبات عروض الأسعار |
| `RfqService` | منطق الأعمال للـ RFQ |
| `RfqType` Enum | أنواع الـ RFQ (مفتوح، مغلق، مزايدة عكسية) |
| `AuctionController` | نظام المزادات الإلكترونية |
| `AuctionService` | منطق الأعمال للمزادات |

---

## 5. العطاءات والعروض (Bids)

| الملف / الموديول | الوصف |
|---|---|
| `BidController` (API + Web) | تقديم وإدارة العطاءات |
| `BidService` | منطق الأعمال لمعالجة العطاءات |
| `BidPolicy` | صلاحيات العطاءات (من يقدر يقدم/يعدل/يسحب) |
| `Livewire/BidComparison` | مقارنة العطاءات في الوقت الحقيقي |

---

## 6. العقود (Contracts)

| الملف / الموديول | الوصف |
|---|---|
| `ContractController` (API + Web) | إنشاء وإدارة العقود |
| `ContractService` | منطق الأعمال للعقود |
| `SigningController` | التوقيع الإلكتروني للعقود |
| `PkiService` | البنية التحتية للمفاتيح العامة (PKI) |
| `AmendmentController` | التعديلات على العقود |
| `ContractAmendment` + `ContractAmendmentMessage` | نظام مراسلات تعديل العقود |
| `ContractApproval` Model | نظام الموافقات متعدد المستويات |
| `ContractInternalNote` Model | الملاحظات الداخلية على العقود |
| `ContractParty` Model | أطراف العقد |
| `ContractObserver` | مراقبة أحداث العقود تلقائياً |
| `ContractRiskAnalysisService` | تحليل مخاطر العقود |
| `SignatureGrade` Enum | درجات التوقيع الإلكتروني |
| عرض العقد PDF | توليد ملفات PDF للعقود |
| شهادة التدقيق | شهادة تدقيق العقود (Audit Certificate) |

---

## 7. المدفوعات (Payments)

| الملف / الموديول | الوصف |
|---|---|
| `PaymentController` (API + Web) | إدارة المدفوعات |
| `PaymentService` | منطق الأعمال للمدفوعات |
| `StripeGateway` | التكامل مع Stripe |
| `PayPalGateway` | التكامل مع PayPal |
| `EscrowController` + `EscrowService` | نظام الضمان المالي (Escrow) |
| `EscrowAccount` + `EscrowRelease` Models | حسابات وعمليات الإفراج عن الضمان |
| `FeeController` | إدارة رسوم المنصة |
| `MashreqNeoBizPartner` | التكامل مع بنك المشرق |
| `BankPartnerFactory` | مصنع مزودي الخدمات البنكية |

---

## 8. الفواتير الضريبية والإلكترونية (Tax & E-Invoicing)

| الملف / الموديول | الوصف |
|---|---|
| `TaxInvoiceController` + `TaxInvoiceService` | إنشاء وإدارة الفواتير الضريبية |
| `EInvoiceController` + `EInvoiceDispatcher` | إرسال الفواتير الإلكترونية |
| `TaxInvoiceQrEncoder` | ترميز QR للفواتير حسب معايير هيئة الضرائب |
| `PintAeMapper` | تحويل الفواتير لمعيار PINT AE |
| `InvoiceNumberAllocator` | تخصيص أرقام الفواتير التسلسلية |
| `TaxRateController` | إدارة معدلات الضرائب |
| `TaxCreditNote` Model | إشعارات الإئتمان الضريبي |
| `CorporateTaxStatus` Enum | حالات ضريبة الشركات |
| فواتير PDF | توليد فواتير PDF مع QR Code |

---

## 9. الشحن واللوجستيات (Shipping & Logistics)

| الملف / الموديول | الوصف |
|---|---|
| `ShipmentController` (API + Web) | إدارة الشحنات |
| `ShipmentService` + `ShippingService` | منطق الأعمال للشحن |
| `ShippingQuoteController` | طلب عروض أسعار الشحن |
| `LogisticsController` | إدارة اللوجستيات |
| `AramexCarrier` | تكامل مع أرامكس |
| `DhlCarrier` | تكامل مع DHL |
| `FedExCarrier` | تكامل مع FedEx |
| `FetchrCarrier` | تكامل مع Fetchr |
| `UpsCarrier` | تكامل مع UPS |
| `DutyCalculatorService` | حساب الرسوم الجمركية |
| `CustomsDocumentService` | إعداد مستندات الجمارك |
| `HsCodeClassificationService` | تصنيف رموز النظام المنسق (HS Codes) |
| `DubaiTradeAdapter` | التكامل مع منصة دبي التجارية |
| `Livewire/LiveTrackingMap` | خريطة تتبع الشحنات مباشر |

---

## 10. التفاوض (Negotiations)

| الملف / الموديول | الوصف |
|---|---|
| `NegotiationController` + `NegotiationRoundController` | إدارة جولات التفاوض |
| `NegotiationService` | منطق الأعمال للتفاوض |
| `NegotiationAssistantService` | مساعد التفاوض بالذكاء الاصطناعي |
| `NegotiationMessage` Model | رسائل التفاوض |

---

## 11. المنازعات (Disputes)

| الملف / الموديول | الوصف |
|---|---|
| `DisputeController` + `DisputeManagementController` | إدارة المنازعات |
| `DisputeService` | منطق الأعمال لحل المنازعات |

---

## 12. كتالوج المنتجات (Product Catalog)

| الملف / الموديول | الوصف |
|---|---|
| `ProductController` | إدارة المنتجات |
| `CategoryController` (Admin + Web) | إدارة التصنيفات (شجرة UNSPSC) |
| `CategoryService` + `CategoryRoutingService` | منطق الأعمال للتصنيفات |
| `CartController` + `CartService` | سلة المشتريات |
| `Product` + `ProductVariant` Models | المنتجات ومتغيراتها |
| `CompanyCategoryRequestController` | طلب إضافة تصنيفات جديدة |

---

## 13. التحليلات والتقارير (Analytics & Reports)

| الملف / الموديول | الوصف |
|---|---|
| `AnalyticsController` (API + Web) | لوحة التحليلات |
| `SpendAnalyticsController` + `SpendAnalyticsService` | تحليلات الإنفاق |
| `ReportsController` | التقارير الإدارية |
| `PerformanceController` | تقارير أداء الموردين |
| `PredictiveAnalyticsService` | التحليلات التنبؤية |

---

## 14. الذكاء الاصطناعي (AI Features)

| الملف / الموديول | الوصف |
|---|---|
| `AIController` | واجهة خدمات الذكاء الاصطناعي |
| `ProcurementCopilotService` | مساعد المشتريات الذكي |
| `AnthropicClient` | التكامل مع Claude API |
| `DocumentOcrService` | استخراج النصوص من المستندات (OCR) |
| `ContractRiskAnalysisService` | تحليل مخاطر العقود بالذكاء الاصطناعي |

---

## 15. الامتثال والحوكمة (Compliance & Governance)

| الملف / الموديول | الوصف |
|---|---|
| `SanctionsScreeningService` | فحص العقوبات الدولية |
| `AntiCollusionService` | كشف التواطؤ في العطاءات |
| `CreditScoringService` | التصنيف الائتماني للشركات |
| `IcvCertificateController` | إدارة شهادات القيمة المحلية المضافة (ICV) |
| `IcvScoringService` | حساب درجات ICV |
| `EsgController` + `EsgScoringService` | معايير البيئة والمجتمع والحوكمة (ESG) |
| `OversightController` | الرقابة والإشراف |
| `GovernmentController` | واجهة الجهات الحكومية |
| `ConflictMineralsDeclaration` Model | إفصاحات المعادن المتنازع عليها |
| `ModernSlaveryStatement` Model | بيانات مكافحة العبودية الحديثة |
| `CarbonFootprint` Model | البصمة الكربونية |

---

## 16. الخصوصية وحماية البيانات (Privacy & Data Protection)

| الملف / الموديول | الوصف |
|---|---|
| `PrivacyController` | إدارة طلبات الخصوصية |
| `DataExportService` | تصدير بيانات المستخدم (GDPR) |
| `DataErasureService` | حذف بيانات المستخدم (حق النسيان) |
| `ConsentLedger` | سجل الموافقات |
| `PrivacyPolicyVersion` Model | إصدارات سياسة الخصوصية |
| `PrivacyRequest` Model | طلبات الخصوصية |

---

## 17. التكاملات الخارجية (Integrations)

| الملف / الموديول | الوصف |
|---|---|
| `IntegrationsController` | إدارة التكاملات |
| `WebhookManagementController` | إدارة الـ Webhooks |
| `WebhookDispatcherService` | إرسال الأحداث للأنظمة الخارجية |
| `ApiTokenController` | إدارة مفاتيح API |
| `ScimController` | تكامل SCIM لإدارة المستخدمين |
| `ErpConnectorFactory` + `OdooConnector` | تكامل مع أنظمة ERP (Odoo) |
| `UaePassProvider` | تكامل مع UAE Pass |
| `ExchangeRateController` + `SyncExchangeRates` | أسعار صرف العملات |

---

## 18. الإشعارات (Notifications) — 46 نوع إشعار

| المجال | الإشعارات |
|---|---|
| **العطاءات** | عطاء جديد، عطاء مقبول، عطاء مرفوض، عطاء خاسر |
| **العقود** | عقد جديد، طلب توقيع، تم التوقيع، رفض التوقيع، انتهاء صلاحية التوقيع، إنهاء العقد، عقد قارب على الانتهاء، تذكير تجديد، تعديل مقترح، قرار تعديل، رسالة تعديل |
| **المدفوعات** | طلب دفع، حالة الدفع، فشل الدفع، تأخر الدفع |
| **RFQ** | نشر RFQ، دعوة RFQ، إغلاق RFQ، إلغاء RFQ، تذكير موعد نهائي |
| **الشركات** | تسجيل شركة، طلب معلومات، اكتمال المعلومات |
| **طلبات الشراء** | تقديم طلب، اعتماد، رفض |
| **الشحن** | تحديث حالة الشحنة |
| **الأمان** | كشف عقوبات، اشتباه تواطؤ، خرق بيانات |
| **الخصوصية** | تصدير بيانات جاهز، اكتمال حذف البيانات، رفض حذف البيانات |
| **أخرى** | شهادات ICV قاربت على الانتهاء، فاتورة إلكترونية، ضمان مالي، ملخص بحث محفوظ |

---

## 19. المهام المجدولة (Scheduled Commands) — 19 أمر

| الأمر | الوصف |
|---|---|
| `SyncExchangeRates` | مزامنة أسعار صرف العملات |
| `SendRfqDeadlineReminders` | تذكير بمواعيد انتهاء الـ RFQ |
| `SendContractExpiryReminders` | تذكير بانتهاء العقود |
| `SendContractRenewalAlerts` | تنبيه تجديد العقود |
| `SendPaymentOverdueReminders` | تذكير بالمدفوعات المتأخرة |
| `ExpireCompanyDocuments` | إنتهاء صلاحية مستندات الشركات |
| `ExpireSignatureWindows` | إنتهاء فترات التوقيع |
| `SweepEscrowReleases` | معالجة عمليات الإفراج عن الضمان |
| `RescreenCompanies` | إعادة فحص العقوبات |
| `NotifyExpiringIcvCertificates` | تنبيه انتهاء شهادات ICV |
| `SendSavedSearchDigests` | إرسال ملخصات البحث المحفوظ |
| `AnchorAuditChain` | تثبيت سلسلة التدقيق |
| `VerifyAuditChain` | التحقق من سلامة سلسلة التدقيق |
| `ArchiveAuditLogs` | أرشفة سجلات التدقيق |
| `CleanupOldNotifications` | تنظيف الإشعارات القديمة |
| `MapCategoriesToUnspsc` | ربط التصنيفات بنظام UNSPSC |
| `RotateEncryptionKey` | تدوير مفاتيح التشفير |
| `ReportDataBreach` | الإبلاغ عن خروقات البيانات |
| `RescreenAllCompanies` | إعادة فحص جميع الشركات |

---

## 20. واجهات المستخدم (Views) — 120+ صفحة

### لوحة تحكم الأدمن (Admin Dashboard)
- إدارة الشركات (قائمة، عرض، تعديل)
- إدارة المستخدمين (قائمة، إنشاء، تعديل)
- إدارة التصنيفات
- طابور التحقق
- سجلات التدقيق
- الفواتير الضريبية (مع PDF)
- معدلات الضرائب
- أسعار الصرف
- المنازعات
- مكافحة التواطؤ
- الفواتير الإلكترونية
- القائمة السوداء
- شهادات ICV
- رسوم المنصة
- صحة النظام
- التقارير
- الإعدادات
- الـ Webhooks
- الرقابة والإشراف
- طلبات التصنيفات
- شهادات الشركات

### لوحة تحكم الشركة (Company Dashboard)
- الرئيسية + التحليلات
- البروفايل الموحد
- طلبات الشراء
- RFQs + المزادات
- العطاءات
- العقود + التوقيع
- التفاوض
- المدفوعات + الضمان
- الفواتير الضريبية
- الشحنات
- الموردين
- المنتجات + الكتالوج
- السلة
- المنازعات
- المستندات + التأمين
- شهادات ICV
- الفروع + المستفيدين
- فريق العمل
- الإعدادات + البروفايل
- البحث (عام + محفوظ)
- الإشعارات
- التكاملات + API Tokens
- ESG
- الخصوصية
- الأداء

### صفحات عامة (Public Pages)
- الصفحة الرئيسية (Landing Page)
- عن المنصة
- تواصل معنا
- سياسة الخصوصية
- الشروط والأحكام
- اتفاقية معالجة البيانات (DPA)
- سياسة ملفات الارتباط
- دليل الموردين
- صفحة تجريبية (Demo)
- التحقق من العقود
- صفحات الأخطاء (403, 404, 419, 429, 500)

### المصادقة (Auth Pages)
- تسجيل الدخول
- التسجيل
- نسيت كلمة المرور
- إعادة تعيين كلمة المرور
- تحدي المصادقة الثنائية
- نجاح التسجيل

---

## 21. البنية التحتية (Infrastructure)

| الملف | الوصف |
|---|---|
| `Dockerfile` + `.dockerignore` | حاوية Docker للنشر |
| `.github/workflows/ci.yml` | خط أنابيب CI/CD مع GitHub Actions |
| `config/security.php` | إعدادات الأمان |
| `config/signing.php` | إعدادات التوقيع الإلكتروني |
| `config/einvoice.php` | إعدادات الفوترة الإلكترونية |
| `config/audit.php` | إعدادات سجلات التدقيق |
| `config/procurement.php` | إعدادات المشتريات |
| `config/data_residency.php` | إعدادات إقامة البيانات |
| `config/uae_pass.php` | إعدادات UAE Pass |
| 65+ migration | هيكل قاعدة البيانات |
| 6 seeders | بيانات تجريبية وأساسية |

---

## 22. الاختبارات (Tests)

| النوع | العدد |
|---|---|
| Feature Tests | 20 ملف |
| E2E Tests | 14 ملف |

---

## ملخص تنفيذي

تم بناء منصة مشتريات B2B متكاملة تغطي دورة المشتريات الكاملة من طلب الشراء إلى الدفع، مع التركيز على:

1. **الامتثال للتشريعات الإماراتية** — ICV، ضريبة الشركات، المناطق الحرة، UAE Pass، الفوترة الإلكترونية
2. **الأمان والخصوصية** — تشفير البيانات، 2FA، فحص العقوبات، GDPR، سلسلة تدقيق غير قابلة للتلاعب
3. **التكاملات** — شركات شحن (5)، بوابات دفع (2)، بنوك، ERP، SCIM
4. **الذكاء الاصطناعي** — مساعد مشتريات، تحليل مخاطر العقود، OCR، تحليلات تنبؤية
5. **الحوكمة** — ESG، مكافحة التواطؤ، البصمة الكربونية، المعادن المتنازع عليها
