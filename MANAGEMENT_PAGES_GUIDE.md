# Management Pages & CRUD Operations Guide

## 📋 Overview

This guide covers all management pages where you can perform CRUD (Create, Read, Update, Delete) operations on system models.

---

## 🏥 CLAIMS AUTOMATION MANAGEMENT

### 1. Admission Management
**URL**: `/claims/automation/admissions`
**Purpose**: Manage patient admissions

#### CRUD Operations:
- **Create**: Click "New Admission" button
  - Fill: admission_number, patient_name, admission_date, status
  - Submit form
  
- **Read**: View admissions in data table
  - Search by admission number or patient name
  - Filter by status (Active, Discharged, Pending)
  
- **Update**: Click pencil icon on admission row
  - Edit admission details
  - Save changes
  
- **Delete**: Admissions can be discharged (soft delete)
  - Click discharge button when status is Active

#### Features:
- Search functionality
- Status filtering
- View admission details
- Discharge patient
- Linked claims view

---

### 2. Bundle Management
**URL**: `/claims/automation/bundles`
**Purpose**: Manage service bundles and pricing

#### CRUD Operations:
- **Create**: Click "New Bundle" button
  - Fill: bundle_name, icd10_code, description, fixed_price, status
  - Submit form
  
- **Read**: View bundles in data table
  - Search by bundle name
  - Filter by status (Active, Inactive)
  
- **Update**: Click edit icon on bundle row
  - Edit bundle details
  - Update pricing
  - Save changes
  
- **Delete**: Click delete icon on bundle row
  - Confirm deletion

#### Features:
- Search functionality
- Status filtering
- ICD-10 code mapping
- Pricing management
- View bundle details

---

## 🏥 PAS MODULE MANAGEMENT

### 3. Manage Drugs
**URL**: `/pas/drugs`
**Purpose**: Manage drugs in the system

#### CRUD Operations:
- **Create**: Click "Add Drug" button
  - Fill: drug_name, drug_code, description, category
  - Submit form
  
- **Read**: View drugs in data table
  - Search by drug name or code
  - Filter by category
  
- **Update**: Click edit icon on drug row
  - Edit drug details
  - Save changes
  
- **Delete**: Click delete icon on drug row
  - Confirm deletion

#### Features:
- Search functionality
- Category filtering
- Drug details view
- Bulk import/export

---

### 4. Manage Labs
**URL**: `/pas/labs`
**Purpose**: Manage laboratory services

#### CRUD Operations:
- **Create**: Click "Add Lab" button
  - Fill: lab_name, lab_code, description, category
  - Submit form
  
- **Read**: View labs in data table
  - Search by lab name or code
  - Filter by category
  
- **Update**: Click edit icon on lab row
  - Edit lab details
  - Save changes
  
- **Delete**: Click delete icon on lab row
  - Confirm deletion

#### Features:
- Search functionality
- Category filtering
- Lab details view
- Bulk import/export

---

### 5. Tariff Item Management
**URL**: `/tariff-items`
**Purpose**: Manage tariff items and pricing structure

#### CRUD Operations:
- **Create**: Click "Add Tariff Item" button
  - Fill: item_code, description, unit_price, category
  - Submit form
  
- **Read**: View tariff items in data table
  - Search by item code or description
  - Filter by category
  
- **Update**: Click edit icon on tariff item row
  - Edit pricing
  - Update details
  - Save changes
  
- **Delete**: Click delete icon on tariff item row
  - Confirm deletion

#### Features:
- Search functionality
- Category filtering
- Pricing management
- Bulk import/export
- Download template
- Export to Excel

#### Import/Export:
- **Download Template**: Get Excel template for bulk import
- **Import**: Upload Excel file with tariff items
- **Export**: Export current tariff items to Excel

---

### 6. Case Categories
**URL**: `/case-categories`
**Purpose**: Manage case categories

#### CRUD Operations:
- **Create**: Click "Add Category" button
  - Fill: category_name, description, code
  - Submit form
  
- **Read**: View categories in data table
  - Search by category name
  
- **Update**: Click edit icon on category row
  - Edit category details
  - Save changes
  
- **Delete**: Click delete icon on category row
  - Confirm deletion

#### Features:
- Search functionality
- Category details view

---

### 7. Service Categories
**URL**: `/service-categories`
**Purpose**: Manage service categories

#### CRUD Operations:
- **Create**: Click "Add Service Category" button
  - Fill: category_name, description, code
  - Submit form
  
- **Read**: View service categories in data table
  - Search by category name
  
- **Update**: Click edit icon on service category row
  - Edit category details
  - Save changes
  
- **Delete**: Click delete icon on service category row
  - Confirm deletion

#### Features:
- Search functionality
- Service category details view

---

### 8. DO Facility Assignments
**URL**: `/do-facilities`
**Purpose**: Assign facilities to desk officers

#### CRUD Operations:
- **Create**: Click "Assign Facility" button
  - Select desk officer
  - Select facility
  - Submit form
  
- **Read**: View assignments in data table
  - Search by DO name or facility name
  
- **Update**: Click edit icon on assignment row
  - Change facility assignment
  - Save changes
  
- **Delete**: Click delete icon on assignment row
  - Remove assignment

#### Features:
- Search functionality
- Facility assignment management

---

## 👥 ENROLLMENT MANAGEMENT

### 9. Enrollees Management
**URL**: `/enrollees`
**Purpose**: Manage enrollee information

#### CRUD Operations:
- **Create**: Click "Add Enrollee" button
  - Fill: enrollee details (name, ID, facility, etc.)
  - Submit form
  
- **Read**: View enrollees in data table
  - Search by name or ID
  - Filter by status
  
- **Update**: Click on enrollee row to view profile
  - Edit enrollee details
  - Save changes
  
- **Delete**: Enrollees can be deactivated
  - Change status to inactive

#### Features:
- Search functionality
- Status filtering
- View enrollee profile
- Medical history
- Claims history

---

### 10. Pending Enrollees
**URL**: `/enrollees/pending`
**Purpose**: Manage pending enrollee approvals

#### CRUD Operations:
- **Read**: View pending enrollees
  - Search by name or ID
  
- **Update**: Approve or reject enrollee
  - Click approve button
  - Click reject button with reason

#### Features:
- Pending enrollee list
- Approve/reject functionality
- View pending details

---

## 🏢 FACILITIES MANAGEMENT

### 11. Facilities Management
**URL**: `/facilities`
**Purpose**: Manage healthcare facilities

#### CRUD Operations:
- **Create**: Click "Add Facility" button
  - Fill: facility_name, facility_code, location, contact
  - Submit form
  
- **Read**: View facilities in data table
  - Search by facility name or code
  - Filter by type
  
- **Update**: Click edit icon on facility row
  - Edit facility details
  - Save changes
  
- **Delete**: Click delete icon on facility row
  - Confirm deletion

#### Features:
- Search functionality
- Facility type filtering
- Facility details view

---

## ⚙️ SETTINGS & ADMINISTRATION

### 12. User Management
**URL**: `/settings/users`
**Purpose**: Manage system users

#### CRUD Operations:
- **Create**: Click "Add User" button
  - Fill: name, email, password, role
  - Submit form
  
- **Read**: View users in data table
  - Search by name or email
  - Filter by role
  
- **Update**: Click edit icon on user row
  - Edit user details
  - Change role
  - Save changes
  
- **Delete**: Click delete icon on user row
  - Confirm deletion

#### Features:
- Search functionality
- Role filtering
- User details view
- Password reset

---

### 13. Roles & Permissions
**URL**: `/settings/roles`
**Route Name**: `settings-roles`
**Purpose**: Manage user roles and permissions

#### CRUD Operations:
- **Create**: Click "Add Role" button
  - Fill: role_name, description
  - Select permissions
  - Submit form
  
- **Read**: View roles in data table
  - Search by role name
  
- **Update**: Click edit icon on role row
  - Edit role details
  - Update permissions
  - Save changes
  
- **Delete**: Click delete icon on role row
  - Confirm deletion

#### Features:
- Search functionality
- Permission management
- Role details view

---

### 14. Benefactors Management
**URL**: `/settings/benefactors`
**Purpose**: Manage benefactors/insurance providers

#### CRUD Operations:
- **Create**: Click "Add Benefactor" button
  - Fill: benefactor_name, code, contact
  - Submit form
  
- **Read**: View benefactors in data table
  - Search by name or code
  
- **Update**: Click edit icon on benefactor row
  - Edit benefactor details
  - Save changes
  
- **Delete**: Click delete icon on benefactor row
  - Confirm deletion

#### Features:
- Search functionality
- Benefactor details view

---

## 📋 FEEDBACK MANAGEMENT

### 15. Feedback Management
**URL**: `/feedback`
**Purpose**: Manage referral and PA code feedback

#### CRUD Operations:
- **Create**: Click "Create Feedback" button
  - Fill: feedback details
  - Submit form
  
- **Read**: View feedback items in data table
  - Search by feedback ID
  - Filter by status
  
- **Update**: Click edit icon on feedback row
  - Edit feedback details
  - Save changes
  
- **Delete**: Click delete icon on feedback row
  - Confirm deletion

#### Features:
- Search functionality
- Status filtering
- Assign feedback to officers
- View feedback details

---

## 📄 DOCUMENT REQUIREMENTS

### 16. Document Requirements Management
**URL**: `/document-requirements`
**Purpose**: Manage document requirements

#### CRUD Operations:
- **Create**: Click "Add Requirement" button
  - Fill: requirement_name, description, document_type
  - Submit form
  
- **Read**: View requirements in data table
  - Search by requirement name
  
- **Update**: Click edit icon on requirement row
  - Edit requirement details
  - Save changes
  
- **Delete**: Click delete icon on requirement row
  - Confirm deletion

#### Features:
- Search functionality
- Document type filtering

---

## 🎯 COMMON CRUD PATTERNS

### Create Operation
1. Click "Add [Item]" or "New [Item]" button
2. Fill in required fields (marked with *)
3. Click "Save" or "Submit" button
4. Success message appears
5. Item added to list

### Read Operation
1. Navigate to management page
2. View items in data table
3. Use search to find specific items
4. Use filters to narrow results
5. Click on item to view details

### Update Operation
1. Find item in data table
2. Click edit/pencil icon
3. Modify fields
4. Click "Save" or "Update" button
5. Success message appears
6. Changes reflected in list

### Delete Operation
1. Find item in data table
2. Click delete/trash icon
3. Confirm deletion in dialog
4. Item removed from list
5. Success message appears

---

## 💡 TIPS & BEST PRACTICES

### Search Tips
- Use partial names to find items
- Search is case-insensitive
- Use filters to narrow results
- Combine search and filters for best results

### Bulk Operations
- Some pages support bulk import/export
- Download template before importing
- Follow template format exactly
- Check for errors after import

### Validation
- All required fields must be filled
- Some fields have format requirements
- Error messages indicate what's wrong
- Fix errors and resubmit

### Performance
- Large lists may be paginated
- Use search to find items quickly
- Filters reduce data loaded
- Bulk operations are faster than individual

---

**Last Updated**: 2025-12-04
**Version**: 1.0

