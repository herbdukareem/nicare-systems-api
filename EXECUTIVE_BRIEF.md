# Executive Brief: NiCare Systems New Web Platform

## 1. Executive Summary

NiCare Systems `new-web` is a Laravel 12 and Vue 3 web platform for administering a health insurance scheme. The system digitizes the operational lifecycle of a scheme administrator: enrollee registration, NIN verification, facility assignment, premium and coverage management, referrals, pre-authorization, admissions, claims review, capitation, provider payments, reporting, and governance.

The platform is designed as an operational back office plus self-service enrollee portal. It provides separate workspaces for administrators, desk officers, facilities/providers, claims teams, finance teams, and enrollees. The strongest business value is centralizing scheme data and enforcing controlled workflows around eligibility, care authorization, claims adjudication, provider settlement, and auditability.

## 2. Strategic Purpose

The system supports the transition from fragmented manual health insurance administration to a controlled digital operating platform.

Primary objectives:

- Maintain a single source of truth for enrollees, facilities, benefit packages, funding types, premiums, claims, and capitation.
- Improve enrollee data quality through NIN verification, duplicate detection, integrity indexes, approval workflows, and audit trails.
- Enforce eligibility before care delivery, referral authorization, admission processing, and claims submission.
- Support revenue collection through premium plans, premium PINs, hosted checkout, and payroll-based coverage activation.
- Improve provider payment governance through claim payment batches and capitation review, approval, finalization, and payment stages.
- Provide role-based dashboards, reports, financial summaries, and audit/security monitoring.
- Enable mobile enrolment operations through queued mobile sync and per-record validation status.

## 3. Core Stakeholders

The platform is structured for multiple operational actors:

- Scheme administrators: configure users, roles, permissions, organization settings, benefit packages, funding types, locations, facilities, payment gateways, and NIN providers.
- Enrollment officers: register enrollees, import enrollee data, process mobile sync records, print slips/cards, verify NIN data, review duplicates, and manage approvals.
- Desk officers: monitor assigned facilities, referrals, PA codes, and UTN validation workflows.
- Healthcare facilities/providers: manage facility dashboards, admissions, referral submissions, PA requests, and claims submission.
- Claims and medical review teams: validate, review, approve, reject, batch-process, and track claims.
- Finance teams: oversee premium purchases, premium PINs, capitation, claim payment batches, payment processing, and financial reports.
- Enrollees: use a public/self-service portal for enrollment, premium PIN purchase, plan viewing, login, profile access, and password change.
- Management/executives: view dashboards, analytics, reports, audit logs, and financial performance indicators.

## 4. Major Functional Capabilities

### 4.1 Enrollment and Enrollee Administration

The platform manages enrollee records across registration, approval, identity verification, facility assignment, and coverage activation.

Key capabilities:

- Enrollee CRUD and profile management.
- Public self-enrollment.
- Pending approval workflows.
- NIN verification with configurable provider integration.
- NIN comparison evidence and approval merge selection.
- Duplicate detection using NIN matching and fuzzy name/date-of-birth/facility matching.
- Bulk enrollee import and import status tracking.
- Mobile sync with queued validation and retry handling.
- Bulk enrollment slip and ID card generation flows.
- Facility transfer workflow and transfer history.
- Principal/dependant relationships, next of kin, emergency contacts, and enrollee relations.

### 4.2 Facility and Provider Management

The system maintains facilities and links them to enrollees, desk officers, referrals, admissions, capitation, and claims.

Key capabilities:

- Facility registry and facility enrollee lists.
- Desk officer to facility assignment.
- Facility dashboards.
- Provider-linked admissions.
- Provider claims submission.
- Provider capitation history.
- LGA, ward, village, sector, and location setup.

### 4.3 Premium, Coverage, and Revenue Collection

Premium coverage is a central business layer. Premium plans define amount, duration, benefit package, insurance programme, and payment gateway linkage. PINs and purchases activate coverage.

Key capabilities:

- Premium plan management.
- Premium PIN generation, sale, validation, use, cancellation, and expiry handling.
- Public premium PIN purchase flow.
- Hosted online checkout for premium purchases.
- Payment verification and gateway confirmation.
- Payroll batch activation for group coverage.
- Coverage start/end calculation, waiting-period checks, and benefit package assignment.
- Audit logging for premium and PIN actions.

### 4.4 Eligibility Management

Eligibility is enforced before care-related workflows proceed.

Key rules implemented:

- Enrollee must be active.
- Coverage start date must be due.
- Coverage end date must not have expired.
- Benefit package must be assigned and active.
- Care facility must match the enrollee's assigned coverage facility where required.
- Waiting-period violations are blocked.

### 4.5 Pre-Authorization System

The PAS module governs referrals, UTN validation, facility PA codes, FU-PA requests, approvals, rejections, document requirements, and admissions.

Key capabilities:

- Referral submission and management.
- UTN validation.
- Facility PA code management.
- FU-PA code request and approval.
- Referral and PA document requirements.
- Admission management linked to referrals and claims.
- Desk officer dashboards for referrals and PA codes.

### 4.6 Claims Automation and Adjudication

Claims workflows cover facility submission through review, approval/rejection, payment batching, and history.

Key capabilities:

- Draft claim creation from referral/admission.
- Claims with line items, bundles, fee-for-service items, and top-ups.
- Claim validation checks.
- Claim submission, review, approval, rejection, and batch decisions.
- Mandatory rejection reason.
- Separation of duties: claim submitter cannot adjudicate the same claim.
- Immutable claim states after submission/review/approval/rejection.
- Payment batch creation, processing, paid marking, and receipts.
- Claim alerts, status history, attachments, audit trails, and full-detail views.

### 4.7 Capitation

Capitation supports monthly provider payment generation based on active, covered enrollees assigned to eligible facilities.

Key capabilities:

- Capitation period creation by month, year, funding type, and rate.
- Eligible provider discovery.
- Facility-level capitation computation.
- Review, approval, payment, and finalization stages.
- Separation of duties for creator/finalizer/payment confirmation.
- Capitation breakdown and provider history.
- Capitation payment records and audit trail entries.

### 4.8 Reporting, Analytics, and Dashboards

The platform includes dashboards and reporting surfaces for management, facilities, finance, claims, PAS, desk officers, and general operations.

Key capabilities:

- Main dashboard metrics.
- Enrollment and facility statistics.
- Finance dashboard.
- Facility dashboard.
- Claims dashboard.
- Premium dashboard.
- Management dashboard.
- Reports, financial reports, analytics, and extended report endpoint.
- Audit logs and security logs.

### 4.9 Workdesk and Collaboration

The platform includes internal work management tools that support operational follow-up.

Key capabilities:

- Feedback creation, assignment, management, and statistics.
- Task management with projects, categories, tasks, assignments, comments, attachments, list, kanban, calendar, and detail views.

### 4.10 Administration and Governance

Administration is role and permission driven.

Key capabilities:

- User management.
- Role and permission management.
- User role switching.
- Direct and role-based permissions.
- Departments and designations.
- Organization settings.
- NIN provider configuration.
- Payment gateway configuration.
- Security dashboard, logs, audit trail, sessions, and session revocation.

## 5. Technical Architecture

The application is built as a modern API-backed single page application.

Backend:

- Laravel 12 on PHP 8.2+.
- REST API routes under `routes/api.php`.
- Laravel Sanctum for authenticated API access.
- Laravel Passport/OAuth tables are present for broader token infrastructure.
- Spatie Permission for roles and permissions.
- Eloquent models and services for business domains.
- Queued jobs for mobile sync processing.
- Maatwebsite Excel for imports/exports.
- DomPDF for generated PDFs/slips/receipts.
- S3-compatible filesystem support.

Frontend:

- Vue 3 with Vue Router and Pinia.
- Vite build pipeline.
- Vuetify, Material Design Icons, Tailwind utility classes, Chart.js, and reusable application components.
- Role-aware route guards and permission-gated navigation.
- Separate system/admin portal and enrollee portal flows.

Architecture patterns:

- Controllers delegate most domain work to services.
- Payment checkout uses a gateway adapter pattern:
  `BillingCheckoutService -> BillingGatewayManager -> BillingPaymentGatewayInterface -> PaystackBillingGateway`.
- Paystack is the first online payment implementation, while controller-facing outputs use generic payment language such as provider, reference, authorization URL, and verification result.
- Domain-specific services exist for eligibility, premium coverage, NIN verification, enrollee imports, duplicate detection, claims automation, capitation, reporting, and mobile sync.

## 6. Security and Controls

The system includes several governance controls relevant to health insurance operations:

- Role-based access control with permissions on API routes and UI navigation.
- Separate enrollee authentication provider and enrollee portal guard logic.
- Security middleware for suspicious activity, SQL injection signatures, sensitive endpoint rate limiting, failed/successful login logging, and sensitive endpoint access logging.
- Sanitization/redaction of sensitive security log details.
- Audit trails for sensitive lifecycle actions such as NIN verification, claim decisions, capitation generation/finalization/payment, premium PIN usage, mobile sync, and enrollee updates.
- Separation-of-duty checks in claims and capitation workflows.
- Coverage and facility matching checks before care/claims workflows.

## 7. Data and Operating Model

The data model reflects a health insurance scheme rather than a generic POS/inventory system. Key entities include:

- Enrollees, enrollee relations, enrollee categories, enrollee types, enrollment phases, duplicate flags, imports, and facility transfers.
- Facilities, desk officers, LGAs, wards, villages, sectors, departments, and designations.
- Insurance programmes, benefit packages, funding types, benefactors, vulnerable groups, premiums, premium plans, premium pins, premium purchases, payroll batches, and invoices.
- Referrals, admissions, PA codes, document requirements, claims, claim lines, claim alerts, claim status history, payment batches, capitation periods, capitation details, and capitation payments.
- Users, roles, permissions, audit trails, security logs, configurations, organization settings, payment gateway settings, NIN provider settings, tasks, projects, and feedback records.

## 8. Current Product Maturity

The codebase shows a substantial functional implementation, not just a prototype. Implemented areas include database migrations, models, controllers, services, Vue pages, navigation, tests, and seeders across the main business domains.

Evidence of maturity:

- Extensive API route map covering enrollment, PAS, claims, capitation, premium, settings, reporting, security, feedback, and task management.
- Frontend route map and navigation for the same domains.
- Dedicated services for complex workflows.
- Feature tests for capitation, claim lifecycle, enrollee auth/security, enrollee integrity, NIN verification, facility transfer, mobile sync, payment gateway configuration, premium coverage, public enrollment payment, and PAS authorization.
- Unit tests for admission, bundle classification, claim processing, and payment processing services.
- Database migrations include performance/integrity work such as duplicate fields, NIN verification fields, public self-enrollment support, payment gateway checkout fields, enrollee integrity indexes, and status indexes.

## 9. Business Value

The system can improve scheme operations in five major ways:

- Operational efficiency: reduces manual handling of enrollment, claims, capitation, and provider payment workflows.
- Financial control: links premiums, PINs, coverage, payment verification, claims approvals, and capitation disbursement to auditable records.
- Data integrity: introduces NIN verification, duplicate detection, imports, indexes, and approval controls.
- Provider governance: connects facilities, referrals, admissions, claims, PA codes, capitation, and payment history.
- Executive visibility: provides dashboards, analytics, financial reports, audit logs, and management views.

## 10. Observations and Gaps

The following points should be considered before production rollout or executive presentation:

- The root repository instructions describe inventory/POS/SaaS/multi-tenancy, but `new-web` is implemented primarily as a health insurance administration platform. Any external positioning should be aligned to the actual product.
- Several navigation entries intentionally point to "Coming Soon" pages, including mobile sync UI, change of facility UI, ID card management, and device configuration. Backend support exists for some of these areas, but UI completeness varies.
- Paystack is the only registered online gateway adapter today. The adapter pattern is present, so future gateways can be added without redesigning controllers.
- The system has strong role/permission controls, but production hardening should include a full permission audit against every role and route.
- Tenant isolation was not a visible dominant concern in the inspected `new-web` structure. If this system must become a multi-tenant SaaS product, tenant scoping should be explicitly reviewed before production multi-tenant use.
- The README is still the default Laravel README and should be replaced with project-specific deployment, operations, and support documentation.

## 11. Recommended Next Steps

- Replace the default README with project-specific installation, deployment, environment, queue, storage, and operations instructions.
- Complete the remaining "Coming Soon" UI surfaces or remove them from production navigation until ready.
- Run a production-readiness security review covering route permissions, audit coverage, file upload handling, PHI/PII exposure, NIN provider logging, and payment callbacks.
- Create an executive dashboard package focused on enrollment growth, active coverage, premium revenue, claims liability, claims turnaround, capitation payable/paid, provider performance, and exception counts.
- Add gateway adapter readiness for at least one additional provider or manual confirmation flow to prove payment architecture extensibility.
- Formalize data governance policies for enrollee identity, duplicate resolution, NIN verification evidence, coverage changes, and facility transfers.
- If multi-tenancy is required, perform a tenant-isolation design review before adding more tenant-owned data.

## 12. Positioning Statement

NiCare Systems `new-web` is best positioned as an integrated health insurance scheme administration platform. It connects enrollment, identity verification, premium collection, provider management, pre-authorization, claims adjudication, capitation, payments, reporting, and governance into one role-based web system.

Its executive promise is stronger control over scheme funds, cleaner enrollee records, faster provider workflows, better accountability, and clearer visibility into operational and financial performance.
