# NGSCHA Claims Management System (RCMS) - Implementation Guide

## 🎯 **System Overview**

The NGSCHA Claims Management System (RCMS) is a comprehensive workflow-based system for processing healthcare claims with role-based access control and multi-stage approval processes.

### **Proposed Workflow**
1. **Desk Officer (HIM)** - Prepares and submits claims through RCMS portal
2. **Doctor** - Validates entries: prescriptions, diagnosis, investigations  
3. **Pharmacist** - Reviews and validates drugs/medications
4. **Claim Reviewer** - Vets claims submissions and tariff validation
5. **Claim Confirmer** - Confirms reviewed claims
6. **Claim Approver** - Final approval and payment authorization

## 🏗️ **Database Architecture**

### **Core Tables Created**

#### **1. Claims Table** (`claims`)
- **Primary claim record** with enrollee details, facility info, PA codes
- **Workflow status tracking** through all approval stages
- **Financial tracking** (claimed, approved, paid amounts)
- **Audit trail** with timestamps and user tracking for each stage

#### **2. Claim Diagnoses** (`claim_diagnoses`)
- **ICD-10 coded diagnoses** (primary and secondary)
- **Doctor validation** with comments and timestamps
- **Illness descriptions** from facility

#### **3. Claim Treatments** (`claim_treatments`)
- **Line-itemized services**: professional, hospital stay, medications, labs, etc.
- **Dual validation**: Doctor validation + Pharmacist validation (for medications)
- **Tariff validation** with approved benefit fees
- **Quantity and pricing** with automatic total calculations

#### **4. Claim Attachments** (`claim_attachments`)
- **Document management** with facility stamp validation
- **File metadata** (size, type, MIME type)
- **Document classification** (lab results, prescriptions, discharge notes, etc.)
- **Validation tracking** by authorized personnel

#### **5. Claim Audit Logs** (`claim_audit_logs`)
- **Comprehensive audit trail** for all claim operations
- **Field-level change tracking** with old/new values
- **User action logging** with IP addresses and user agents
- **Reason and comment tracking** for all decisions

## 👥 **Role-Based Access Control (RBAC)**

### **Roles Created**
1. **`desk_officer`** - Create, edit, submit claims
2. **`doctor`** - Validate diagnoses and treatments
3. **`pharmacist`** - Validate medications and drug prescriptions
4. **`claim_reviewer`** - Review claims and validate tariffs
5. **`claim_confirmer`** - Confirm reviewed claims
6. **`claim_approver`** - Final approval and payment authorization
7. **`claims_admin`** - Full system administration

### **Permission System**
- **Granular permissions** for each action (create, view, edit, approve, reject)
- **Role-specific workflows** with automatic routing
- **Middleware protection** for all sensitive operations

## 🔄 **Workflow Implementation**

### **Status Flow**
```
draft → submitted → doctor_review → pharmacist_review → claim_review → claim_confirmed → claim_approved → paid
```

### **Conditional Routing**
- **Medications present**: Routes through pharmacist review
- **No medications**: Skips pharmacist, goes directly to claim review
- **Rejection at any stage**: Returns to appropriate status

### **Validation Requirements**
- **All diagnoses** must be doctor-validated before approval
- **All treatments** must be doctor-validated
- **All medications** must be pharmacist-validated
- **Tariff validation** ensures amounts don't exceed approved rates

## 🛠️ **API Endpoints Created**

### **General Claims Management**
```
GET    /api/v1/claims              - List claims (role-filtered)
POST   /api/v1/claims              - Create new claim
GET    /api/v1/claims/{id}         - View claim details
PUT    /api/v1/claims/{id}         - Update claim (draft only)
POST   /api/v1/claims/{id}/submit  - Submit claim for review
```

### **Doctor Review Endpoints**
```
GET    /api/v1/claims/doctor/pending                    - Claims pending doctor review
GET    /api/v1/claims/doctor/reviewed                   - Claims reviewed by doctor
POST   /api/v1/claims/doctor/diagnoses/{id}/validate    - Validate diagnosis
POST   /api/v1/claims/doctor/treatments/{id}/validate   - Validate treatment
POST   /api/v1/claims/doctor/{id}/approve               - Approve claim
POST   /api/v1/claims/doctor/{id}/reject                - Reject claim
GET    /api/v1/claims/doctor/statistics                 - Doctor dashboard stats
```

### **Pharmacist Review Endpoints**
```
GET    /api/v1/claims/pharmacist/pending                     - Claims pending pharmacist review
GET    /api/v1/claims/pharmacist/reviewed                    - Claims reviewed by pharmacist
GET    /api/v1/claims/pharmacist/{id}/medications            - Get claim medications
POST   /api/v1/claims/pharmacist/medications/{id}/validate   - Validate medication
POST   /api/v1/claims/pharmacist/{id}/approve                - Approve claim
POST   /api/v1/claims/pharmacist/{id}/reject                 - Reject claim
GET    /api/v1/claims/pharmacist/statistics                  - Pharmacist dashboard stats
```

### **Claims Review Endpoints**
```
GET    /api/v1/claims/review/pending        - Claims pending review
POST   /api/v1/claims/review/{id}/review    - Review claim (with tariff adjustments)
GET    /api/v1/claims/confirm/pending       - Claims pending confirmation  
POST   /api/v1/claims/confirm/{id}/confirm  - Confirm claim
GET    /api/v1/claims/approve/pending       - Claims pending final approval
POST   /api/v1/claims/approve/{id}/approve  - Final approval with payment authorization
```

## 📊 **Key Features Implemented**

### **1. Data Validation & Integrity**
- **PA Code validation** against NiCare database
- **Service code validation** against approved schedules
- **Date validation** within PA validity periods
- **Attachment requirements** with facility stamp verification

### **2. Financial Controls**
- **Automatic total calculations** based on quantity × unit price
- **Tariff validation** against approved benefit fees
- **Amount tracking** (claimed vs approved vs paid)
- **Pharmacist quantity adjustments** with recalculation

### **3. Audit & Compliance**
- **Complete audit trail** for all actions
- **Field-level change tracking** with before/after values
- **User accountability** with timestamps and IP logging
- **Reason tracking** for all approvals/rejections

### **4. Role-Based Security**
- **Middleware protection** on all sensitive endpoints
- **Role-specific data filtering** (users only see relevant claims)
- **Permission-based UI rendering** (frontend will show only allowed actions)
- **Session management** with role validation

## 🔧 **Technical Implementation**

### **Models Created**
- `Claim` - Main claim model with relationships and business logic
- `ClaimDiagnosis` - Diagnosis validation and ICD-10 tracking
- `ClaimTreatment` - Treatment validation with dual approval
- `ClaimAttachment` - Document management with validation
- `ClaimAuditLog` - Comprehensive audit logging

### **Controllers Created**
- `ClaimsController` - General claim CRUD operations
- `DoctorReviewController` - Doctor-specific workflow actions
- `PharmacistReviewController` - Pharmacist-specific workflow actions  
- `ClaimsReviewController` - Final review stages (reviewer, confirmer, approver)

### **Middleware & Security**
- `ClaimsRoleMiddleware` - Role-based access control
- Route protection with role requirements
- Input validation and sanitization
- SQL injection prevention through Eloquent ORM

## 📋 **Sample Users Created**

The system includes sample users for testing each role:

| Role | Username | Email | Password |
|------|----------|-------|----------|
| Desk Officer | `desk_officer` | desk.officer@ngscha.gov.ng | password123 |
| Doctor | `doctor` | doctor@ngscha.gov.ng | password123 |
| Pharmacist | `pharmacist` | pharmacist@ngscha.gov.ng | password123 |
| Claim Reviewer | `claim_reviewer` | claim.reviewer@ngscha.gov.ng | password123 |
| Claim Confirmer | `claim_confirmer` | claim.confirmer@ngscha.gov.ng | password123 |
| Claim Approver | `claim_approver` | claim.approver@ngscha.gov.ng | password123 |
| Claims Admin | `claims_admin` | claims.admin@ngscha.gov.ng | password123 |

## 🚀 **Next Steps**

### **Immediate Tasks**
1. **Run migrations** to create database structure
2. **Seed sample users** for testing
3. **Build frontend components** for each role
4. **Implement file upload** for attachments
5. **Add reporting dashboard** for analytics

### **Frontend Development Needed**
- **Role-specific dashboards** with pending items
- **Claim creation forms** with validation
- **Review interfaces** for each approval stage
- **Document upload/viewing** components
- **Audit trail display** for transparency

### **Integration Points**
- **NiCare database** for enrollee validation
- **PA Code system** for pre-authorization checks
- **Service schedule** for tariff validation
- **Payment system** for final disbursement

## ✅ **System Benefits**

1. **Streamlined Workflow** - Clear approval stages with automatic routing
2. **Enhanced Security** - Role-based access with comprehensive audit trails
3. **Data Integrity** - Validation at every stage with business rule enforcement
4. **Transparency** - Complete audit trail for all decisions and changes
5. **Efficiency** - Automated calculations and workflow routing
6. **Compliance** - Built-in controls for regulatory requirements
7. **Scalability** - Modular design allows for easy expansion

The Claims Management System provides a robust foundation for processing healthcare claims with proper controls, audit trails, and role-based workflows that ensure accuracy, security, and compliance with healthcare regulations.
