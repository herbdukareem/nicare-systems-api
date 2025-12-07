# NiCare Navigation Guide - Complete Reference

## 🗺️ How to Navigate the Application

### Module Switcher
The application has 4 main modules accessible via the module switcher in the sidebar:
1. **Core & Admin** - General dashboard and user management
2. **Pre-Authorization (PAS)** - Referrals, PA codes, and related management
3. **Claims** - Claim submission and review
4. **Claims Automation** - Admissions, bundle management, and claims processing

---

## 📊 DASHBOARD PAGES

### 1. Main Dashboard
**URL**: `/dashboard`
**Access**: All authenticated users (except desk officers)
**Purpose**: Overview of enrollees and system statistics
**Features**:
- Enrollee statistics
- System overview
- Quick actions

### 2. Desk Officer (DO) Dashboard
**URL**: `/do-dashboard`
**Access**: Desk officers only
**Purpose**: Specialized dashboard for desk officers
**Features**:
- Assigned facilities overview
- Key metrics
- Quick actions for claims

---

## 📋 CLAIMS MODULE

### Referral Submission
**URL**: `/claims/referrals`
**Route Name**: `claims-referrals`
**Purpose**: Submit new referrals
**Features**:
- Facility selection (referring & receiving)
- Severity level classification (Routine, Urgent, Emergency)
- Clinical notes capture
- Form validation

### Claim Submission
**URL**: `/claims/submissions`
**Route Name**: `claims-submissions`
**Purpose**: Submit new claims
**Features**:
- Multi-step wizard (3 steps)
- Step 1: Claim header information
- Step 2: Add claim line items
- Step 3: Review & submit
- Automatic claim number generation

### Claims Review & Approval
**URL**: `/claims/review`
**Route Name**: `claims-review`
**Purpose**: Review and approve submitted claims
**Features**:
- View all submitted claims
- Search & filter functionality
- Validation alerts display
- Approval/rejection workflow
- Comments & amount tracking
- Status color coding

### Claims History
**URL**: `/claims/history`
**Route Name**: `claims-history`
**Purpose**: View claims history and reports
**Status**: Coming Soon

---

## 🤖 CLAIMS AUTOMATION MODULE

### Admission Management
**URL**: `/claims/automation/admissions`
**Route Name**: `claims-automation-admissions`
**Purpose**: Create and manage patient admissions
**Features**:
- Create new admissions
- Edit admission details
- View admission status (Active, Discharged, Pending)
- Search & filter admissions
- CRUD operations

### Admission Details
**URL**: `/claims/automation/admissions/:id`
**Route Name**: `claims-automation-admission-detail`
**Purpose**: View admission details and linked claims
**Features**:
- Admission information display
- Linked claims table
- Summary statistics
- Create claim button
- Discharge patient button

### Claims Processing
**URL**: `/claims/automation/process`
**Route Name**: `claims-automation-process`
**Purpose**: Process claims with bundle classification
**Features**:
- Multi-step workflow (3 steps)
- Step 1: Bundle classification
- Step 2: FFS top-up management
- Step 3: Validation summary
- Amount calculations

### Process Specific Claim
**URL**: `/claims/automation/process/:id`
**Route Name**: `claims-automation-process-claim`
**Purpose**: Process and build specific claim sections
**Features**:
- Pre-selected claim
- Bundle classification
- FFS management
- Validation

### Bundle Management
**URL**: `/claims/automation/bundles`
**Route Name**: `claims-automation-bundles`
**Purpose**: Manage service bundles and configurations
**Features**:
- Create new bundles
- Edit bundle details
- Delete bundles
- ICD-10 code mapping
- Pricing management
- Status management (Active, Inactive)
- Search & filter

---

## 🏥 PRE-AUTHORIZATION (PAS) MODULE

### PAS Management
**URL**: `/pas`
**Purpose**: Main PAS management page
**Features**: Overview and quick actions

### Manage Cases/Programmes
**URL**: `/pas/programmes`
**Purpose**: Manage case categories and programmes
**Features**: CRUD operations for case management

### Manage Drugs
**URL**: `/pas/drugs`
**Purpose**: Manage drugs in the system
**Features**:
- View all drugs
- Add new drugs
- Edit drug details
- Delete drugs
- Search & filter

### Drug Details
**URL**: `/drugs/:drugId`
**Route Name**: `drug-detail`
**Purpose**: View detailed drug information
**Features**: Drug information and details

### Manage Labs
**URL**: `/pas/labs`
**Purpose**: Manage laboratory services
**Features**:
- View all labs
- Add new labs
- Edit lab details
- Delete labs
- Search & filter

### Tariff Item Management
**URL**: `/tariff-items`
**Route Name**: `tariff-items-management`
**Purpose**: Manage tariff items and pricing structure
**Features**:
- Create tariff items
- Edit tariff items
- Delete tariff items
- View pricing
- Import/export functionality
- Search & filter

### Case Categories
**URL**: `/case-categories`
**Purpose**: Manage case categories
**Features**: CRUD operations for case categories

### Service Categories
**URL**: `/service-categories`
**Purpose**: Manage service categories
**Features**: CRUD operations for service categories

### DO Facility Assignments
**URL**: `/do-facilities`
**Purpose**: Assign facilities to desk officers
**Features**: Manage DO facility assignments

### PAS Feedback Management
**URL**: `/feedback`
**Route Name**: `feedback-management`
**Purpose**: Manage referral and PA code feedback for claims vetting
**Features**:
- View feedback items
- Create feedback
- Edit feedback
- Assign feedback to officers
- Track feedback status

### Document Requirements
**URL**: `/document-requirements`
**Route Name**: `document-requirements-management`
**Purpose**: Manage document requirements for referrals and PA codes
**Features**: CRUD operations for document requirements

---

## 👥 ENROLLMENT MODULE

### Enrollees List
**URL**: `/enrollees`
**Route Name**: `enrollees`
**Purpose**: Manage and view all enrollee information
**Features**:
- View all enrollees
- Search & filter
- View enrollee details
- Manage enrollee information

### Enrollee Profile
**URL**: `/enrollees/:id`
**Route Name**: `enrollee-profile`
**Purpose**: View and manage individual enrollee details
**Features**:
- Enrollee information
- Medical history
- Claims history
- Linked admissions

### Pending Enrollees
**URL**: `/enrollees/pending`
**Route Name**: `enrollee-profile`
**Purpose**: View pending enrollee approvals
**Features**:
- List of pending enrollees
- Approve/reject enrollees
- View pending details

### Change of Facility
**URL**: `/enrollment/change-facility`
**Route Name**: `enrollment-change-facility`
**Purpose**: Manage facility changes for enrollees
**Status**: Coming Soon

### ID Card Printing
**URL**: `/enrollment/id-cards`
**Route Name**: `enrollment-id-cards`
**Purpose**: Print and manage ID cards
**Status**: Coming Soon

### Enrollment Phases
**URL**: `/enrollment/phases`
**Route Name**: `enrollment-phases`
**Purpose**: Manage enrollment phases
**Status**: Coming Soon

---

## 🏢 FACILITIES MANAGEMENT

### Facilities List
**URL**: `/facilities`
**Route Name**: `facilities`
**Purpose**: Manage healthcare facilities and providers
**Features**:
- View all facilities
- Add new facilities
- Edit facility details
- Delete facilities
- Search & filter

---

## ⚙️ SETTINGS & ADMINISTRATION

### User Management
**URL**: `/settings/users`
**Route Name**: `settings-users`
**Purpose**: Manage system users
**Features**:
- View all users
- Create new users
- Edit user details
- Delete users
- Manage user roles

### Roles & Permissions
**URL**: `/settings/roles`
**Route Name**: `settings-roles`
**Purpose**: Manage user roles and permissions
**Features**:
- View all roles
- Create new roles
- Edit role permissions
- Delete roles

### Benefactors
**URL**: `/settings/benefactors`
**Route Name**: `settings-benefactors`
**Purpose**: Manage benefactors/insurance providers
**Features**: CRUD operations for benefactors

### Departments
**URL**: `/settings/departments`
**Route Name**: `settings-departments`
**Purpose**: Manage organizational departments
**Status**: Coming Soon

### Designations
**URL**: `/settings/designations`
**Route Name**: `settings-designations`
**Purpose**: Manage job designations
**Status**: Coming Soon

---

## 📱 DEVICE MANAGEMENT

### Manage Device
**URL**: `/devices/manage`
**Route Name**: `devices-manage`
**Purpose**: Device management and configuration
**Status**: Coming Soon

### Enrollment Configuration
**URL**: `/devices/config`
**Route Name**: `devices-config`
**Purpose**: Configure enrollment settings
**Status**: Coming Soon

---

## 💰 CAPITATION MANAGEMENT

### Generate Capitation
**URL**: `/capitation/generate`
**Route Name**: `capitation-generate`
**Purpose**: Generate capitation payments
**Status**: Coming Soon

### Review Capitation
**URL**: `/capitation/review`
**Route Name**: `capitation-review`
**Purpose**: Review capitation calculations
**Status**: Coming Soon

### Capitation Approval
**URL**: `/capitation/approval`
**Route Name**: `capitation-approval`
**Purpose**: Approve capitation payments
**Status**: Coming Soon

### Capitation Payments/Invoices
**URL**: `/capitation/payments`
**Route Name**: `capitation-payments`
**Purpose**: Manage payments and invoices
**Status**: Coming Soon

---

## 📋 TASK MANAGEMENT

### Task Management
**URL**: `/task-management`
**Route Name**: `task-management`
**Purpose**: Manage projects, tasks, and team collaboration
**Features**:
- Kanban board view
- Task list view
- Project management
- Task calendar
- Create/edit/delete tasks

---

## 🔐 AUTHENTICATION

### Login
**URL**: `/login`
**Route Name**: `login`
**Purpose**: User authentication
**Features**: Login form with credentials

---

## 📊 QUICK ACCESS REFERENCE

| Feature | URL | Module |
|---------|-----|--------|
| Submit Referral | `/claims/referrals` | Claims |
| Submit Claim | `/claims/submissions` | Claims |
| Review Claims | `/claims/review` | Claims |
| Manage Admissions | `/claims/automation/admissions` | Automation |
| Process Claims | `/claims/automation/process` | Automation |
| Manage Bundles | `/claims/automation/bundles` | Automation |
| Manage Drugs | `/pas/drugs` | PAS |
| Manage Labs | `/pas/labs` | PAS |
| Tariff Items | `/tariff-items` | PAS |
| Enrollees | `/enrollees` | Enrollment |
| Facilities | `/facilities` | Admin |
| Users | `/settings/users` | Settings |
| Roles | `/settings/roles` | Settings |

---

## 🎯 COMMON WORKFLOWS

### Submit a Claim
1. Go to `/claims/submissions`
2. Fill in claim header information
3. Add claim line items
4. Review and submit

### Manage Admissions
1. Go to `/claims/automation/admissions`
2. Click "New Admission" to create
3. Fill in admission details
4. View linked claims

### Process a Claim
1. Go to `/claims/automation/process`
2. Select bundle classification
3. Add FFS top-ups if needed
4. Review and submit

### Manage Bundles
1. Go to `/claims/automation/bundles`
2. Click "New Bundle" to create
3. Fill in bundle details
4. Set pricing and ICD-10 codes
5. Save

---

**Last Updated**: 2025-12-04
**Version**: 1.0

