# Module Structure & Navigation Guide

## 🎯 Application Architecture

The NiCare application is organized into 4 main modules, each with its own sidebar menu and functionality.

---

## 📊 MODULE OVERVIEW

```
┌─────────────────────────────────────────────────────────────┐
│                    NiCare Application                        │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Module Switcher (Top Sidebar)                       │   │
│  │  ┌─────────────┬──────────────┬────────┬──────────┐  │   │
│  │  │ Core&Admin  │ PAS (Pre-Auth)│ Claims │Automation│  │   │
│  │  └─────────────┴──────────────┴────────┴──────────┘  │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Module-Specific Sidebar (Left)                      │   │
│  │  Shows menu items for selected module                │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Main Content Area                                   │   │
│  │  Displays selected page/component                    │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 🏠 MODULE 1: CORE & ADMIN

**Access**: All authenticated users
**Default Route**: `/dashboard`

### Sidebar Menu Items:
```
Dashboard
├── Enrollee Dashboard (/dashboard)
└── DO Dashboard (/do-dashboard)

User Management
├── Users (/settings/users)
└── Roles & Permissions (/settings/roles)
```

### Key Pages:
- **Enrollee Dashboard** - System overview and statistics
- **DO Dashboard** - Desk officer specialized dashboard
- **User Management** - Create/edit/delete users
- **Roles & Permissions** - Manage user roles

### Management Pages:
- Users Management (`/settings/users`)
- Roles & Permissions (`/settings/roles`)
- Benefactors (`/settings/benefactors`)

---

## 🏥 MODULE 2: PRE-AUTHORIZATION (PAS)

**Access**: Admin, reviewers, approvers
**Default Route**: `/pas`

### Sidebar Menu Items:
```
Pre-Authorization System (PAS)
├── PAS Management (/pas)
├── Manage Cases (/pas/programmes)
├── Manage Drugs (/pas/drugs)
├── Manage Labs (/pas/labs)
├── Manage Tariff Items (/tariff-items)
├── Case Categories (/case-categories)
├── Service Categories (/service-categories)
└── DO Facility Assignments (/do-facilities)

PAS Feedback
└── Feedback Management (/feedback)
```

### Key Pages:
- **PAS Management** - Main PAS overview
- **Manage Cases** - Case/programme management
- **Manage Drugs** - Drug inventory management
- **Manage Labs** - Laboratory services management
- **Tariff Items** - Pricing structure management
- **Case Categories** - Case classification
- **Service Categories** - Service classification
- **DO Facility Assignments** - Assign facilities to desk officers
- **Feedback Management** - Manage referral/PA feedback

### Management Pages (CRUD Operations):
- Drugs Management (`/pas/drugs`)
- Labs Management (`/pas/labs`)
- Tariff Items (`/tariff-items`)
- Case Categories (`/case-categories`)
- Service Categories (`/service-categories`)
- DO Facility Assignments (`/do-facilities`)
- Feedback Management (`/feedback`)
- Document Requirements (`/document-requirements`)

---

## 📋 MODULE 3: CLAIMS

**Access**: Receiving facilities, desk officers
**Default Route**: `/claims/submissions`

### Sidebar Menu Items:
```
Claims
├── Submit Referral (/claims/referrals)
├── Submit Claim (/claims/submissions)
├── Review Claims (/claims/review)
└── Claims History (/claims/history)
```

### Key Pages:
- **Submit Referral** - Create new referrals
- **Submit Claim** - Submit claims for services
- **Review Claims** - Approve/reject claims
- **Claims History** - View claims history

### Workflows:
1. **Referral Submission**
   - Fill referral form
   - Select facilities
   - Submit for approval

2. **Claim Submission**
   - Multi-step wizard
   - Add claim details
   - Add line items
   - Review and submit

3. **Claims Review**
   - View submitted claims
   - Validate claims
   - Approve or reject
   - Add comments

---

## 🤖 MODULE 4: CLAIMS AUTOMATION

**Access**: Automation specialists, admins
**Default Route**: `/claims/automation/admissions`

### Sidebar Menu Items:
```
Claims Automation
├── Admission Management (/claims/automation/admissions)
├── Claims Processing (/claims/automation/process)
└── Bundle Management (/claims/automation/bundles)
```

### Key Pages:
- **Admission Management** - Create/manage patient admissions
- **Admission Details** - View admission and linked claims
- **Claims Processing** - Process claims with bundle classification
- **Bundle Management** - Manage service bundles

### Management Pages (CRUD Operations):
- Admissions Management (`/claims/automation/admissions`)
- Bundle Management (`/claims/automation/bundles`)

### Workflows:
1. **Admission Management**
   - Create new admission
   - Track admission status
   - View linked claims
   - Discharge patient

2. **Bundle Management**
   - Create service bundles
   - Set pricing
   - Map ICD-10 codes
   - Manage status

3. **Claims Processing**
   - Select bundle
   - Add FFS top-ups
   - Validate amounts
   - Process claim

---

## 🔄 MODULE SWITCHING

### How to Switch Modules:
1. Look for module switcher in top sidebar
2. Click on desired module:
   - **Core & Admin** - General administration
   - **Pre-Authorization (PAS)** - Referrals and PA codes
   - **Claims** - Claim submission and review
   - **Claims Automation** - Admissions and bundles
3. Sidebar updates to show module-specific menu
4. Navigate to desired page

### Module Switching Routes:
```javascript
// Switching to different modules
Core & Admin    → /dashboard
PAS             → /pas
Claims          → /claims/submissions
Automation      → /claims/automation/admissions
```

---

## 📊 COMPLETE NAVIGATION MAP

```
NiCare Application
│
├── Core & Admin Module
│   ├── Dashboard (/dashboard)
│   ├── DO Dashboard (/do-dashboard)
│   ├── Users (/settings/users)
│   ├── Roles (/settings/roles)
│   ├── Benefactors (/settings/benefactors)
│   ├── Facilities (/facilities)
│   └── Enrollees (/enrollees)
│
├── PAS Module
│   ├── PAS Management (/pas)
│   ├── Manage Cases (/pas/programmes)
│   ├── Manage Drugs (/pas/drugs)
│   ├── Manage Labs (/pas/labs)
│   ├── Tariff Items (/tariff-items)
│   ├── Case Categories (/case-categories)
│   ├── Service Categories (/service-categories)
│   ├── DO Facility Assignments (/do-facilities)
│   ├── Feedback Management (/feedback)
│   └── Document Requirements (/document-requirements)
│
├── Claims Module
│   ├── Submit Referral (/claims/referrals)
│   ├── Submit Claim (/claims/submissions)
│   ├── Review Claims (/claims/review)
│   └── Claims History (/claims/history)
│
└── Claims Automation Module
    ├── Admission Management (/claims/automation/admissions)
    ├── Admission Details (/claims/automation/admissions/:id)
    ├── Claims Processing (/claims/automation/process)
    ├── Process Claim (/claims/automation/process/:id)
    └── Bundle Management (/claims/automation/bundles)
```

---

## 🎯 QUICK ACCESS BY ROLE

### Admin
- Access: All modules
- Primary: Core & Admin, PAS
- Secondary: Claims, Automation

### Desk Officer
- Access: Claims, Automation
- Primary: Claims (Submit/Review)
- Secondary: Automation (Admissions)

### Receiving Facility Staff
- Access: Claims
- Primary: Submit Claim, Submit Referral
- Secondary: Review Claims

### Automation Specialist
- Access: Automation, PAS
- Primary: Automation (Admissions, Bundles)
- Secondary: PAS (Tariff Items)

---

## 📱 RESPONSIVE DESIGN

### Desktop View
- Full sidebar visible
- All menu items displayed
- Optimal for management tasks

### Tablet View
- Collapsible sidebar
- Touch-friendly buttons
- Optimized layout

### Mobile View
- Hamburger menu
- Simplified navigation
- Mobile-optimized forms

---

## 🔐 AUTHENTICATION & AUTHORIZATION

### Login Flow
1. Navigate to `/login`
2. Enter credentials
3. System authenticates
4. Redirects to appropriate dashboard:
   - Desk Officers → `/do-dashboard`
   - Others → `/dashboard`

### Role-Based Access
- Each page has required roles
- Unauthorized access redirects to dashboard
- Menu items hidden for unauthorized users

---

## 💡 NAVIGATION TIPS

### Finding Pages
1. Use module switcher to select module
2. Look for page in sidebar menu
3. Or use direct URL if known
4. Use search in management pages

### Breadcrumbs
- Shows current page location
- Click to navigate back
- Helps with orientation

### Back Navigation
- Browser back button works
- Some pages have back buttons
- Module switcher resets to default

### Bookmarking
- Direct URLs are bookmarkable
- Useful for frequently used pages
- Requires authentication

---

## 🚀 COMMON NAVIGATION PATTERNS

### Pattern 1: Management CRUD
1. Go to management page (e.g., `/tariff-items`)
2. View list of items
3. Click "Add" to create
4. Click edit icon to update
5. Click delete icon to remove

### Pattern 2: Workflow Submission
1. Go to submission page (e.g., `/claims/submissions`)
2. Fill multi-step form
3. Review information
4. Submit
5. Redirect to review page

### Pattern 3: Review & Approval
1. Go to review page (e.g., `/claims/review`)
2. View submitted items
3. Click to open details
4. Approve or reject
5. Add comments if needed

---

**Last Updated**: 2025-12-04
**Version**: 1.0

