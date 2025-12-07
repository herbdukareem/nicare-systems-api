# Navigation Quick Reference Card

## 🚀 START HERE

### First Time Users
1. Login at `/login`
2. You'll be redirected to your dashboard
3. Use module switcher to select your module
4. Browse sidebar menu for available pages

### Module Switcher Location
- Top left of sidebar
- Shows 4 options: Core & Admin, PAS, Claims, Automation
- Click to switch modules

---

## 📍 DIRECT URLS - COPY & PASTE

### DASHBOARDS
```
Main Dashboard:           /dashboard
Desk Officer Dashboard:   /do-dashboard
```

### CLAIMS MODULE
```
Submit Referral:          /claims/referrals
Submit Claim:             /claims/submissions
Review Claims:            /claims/review
Claims History:           /claims/history
```

### CLAIMS AUTOMATION
```
Admission Management:     /claims/automation/admissions
Admission Details:        /claims/automation/admissions/:id
Claims Processing:        /claims/automation/process
Process Claim:            /claims/automation/process/:id
Bundle Management:        /claims/automation/bundles
```

### PAS MODULE
```
PAS Management:           /pas
Manage Cases:             /pas/programmes
Manage Drugs:             /pas/drugs
Manage Labs:              /pas/labs
Tariff Items:             /tariff-items
Case Categories:          /case-categories
Service Categories:       /service-categories
DO Facility Assignments:  /do-facilities
Feedback Management:      /feedback
Document Requirements:    /document-requirements
```

### ENROLLMENT
```
Enrollees List:           /enrollees
Enrollee Profile:         /enrollees/:id
Pending Enrollees:        /enrollees/pending
```

### SETTINGS & ADMIN
```
Facilities:               /facilities
Users:                    /settings/users
Roles & Permissions:      /settings/roles
Benefactors:              /settings/benefactors
```

### TASK MANAGEMENT
```
Task Management:          /task-management
```

---

## 🎯 MANAGEMENT PAGES (CRUD OPERATIONS)

| Model | URL | Create | Read | Update | Delete |
|-------|-----|--------|------|--------|--------|
| Admissions | `/claims/automation/admissions` | ✅ | ✅ | ✅ | ✅ |
| Bundles | `/claims/automation/bundles` | ✅ | ✅ | ✅ | ✅ |
| Drugs | `/pas/drugs` | ✅ | ✅ | ✅ | ✅ |
| Labs | `/pas/labs` | ✅ | ✅ | ✅ | ✅ |
| Tariff Items | `/tariff-items` | ✅ | ✅ | ✅ | ✅ |
| Case Categories | `/case-categories` | ✅ | ✅ | ✅ | ✅ |
| Service Categories | `/service-categories` | ✅ | ✅ | ✅ | ✅ |
| DO Facilities | `/do-facilities` | ✅ | ✅ | ✅ | ✅ |
| Feedback | `/feedback` | ✅ | ✅ | ✅ | ✅ |
| Users | `/settings/users` | ✅ | ✅ | ✅ | ✅ |
| Roles | `/settings/roles` | ✅ | ✅ | ✅ | ✅ |
| Benefactors | `/settings/benefactors` | ✅ | ✅ | ✅ | ✅ |
| Facilities | `/facilities` | ✅ | ✅ | ✅ | ✅ |
| Enrollees | `/enrollees` | ✅ | ✅ | ✅ | ✅ |

---

## 🔄 COMMON WORKFLOWS

### Workflow 1: Submit a Claim
```
1. Go to: /claims/submissions
2. Fill claim header (admission, date, amount)
3. Add line items (services)
4. Review information
5. Submit
6. Redirects to: /claims/review
```

### Workflow 2: Manage Admissions
```
1. Go to: /claims/automation/admissions
2. Click "New Admission"
3. Fill admission details
4. Save
5. View in list
6. Click to view details: /claims/automation/admissions/:id
```

### Workflow 3: Create Bundle
```
1. Go to: /claims/automation/bundles
2. Click "New Bundle"
3. Fill bundle details (name, ICD-10, price)
4. Save
5. View in list
```

### Workflow 4: Manage Tariff Items
```
1. Go to: /tariff-items
2. Click "Add Tariff Item"
3. Fill item details (code, price)
4. Save
5. Or import from Excel
```

### Workflow 5: Review & Approve Claims
```
1. Go to: /claims/review
2. View submitted claims
3. Click to open claim
4. Review details
5. Approve or reject
6. Add comments
7. Submit decision
```

---

## 🎨 UI ELEMENTS GUIDE

### Buttons
- **Blue Button** - Primary action (Save, Submit, Create)
- **Gray Button** - Secondary action (Cancel, Reset)
- **Red Button** - Dangerous action (Delete)
- **Green Button** - Success action (Approve)
- **Orange Button** - Warning action (Reject)

### Icons
- **Pencil Icon** - Edit
- **Trash Icon** - Delete
- **Eye Icon** - View/Details
- **Plus Icon** - Add/Create
- **Search Icon** - Search field
- **Filter Icon** - Filter options

### Status Colors
- **Green** - Active, Approved, Success
- **Orange** - Pending, Warning
- **Red** - Rejected, Error, Inactive
- **Blue** - Draft, Info
- **Gray** - Discharged, Inactive

---

## 🔍 SEARCH & FILTER TIPS

### Search
- Available on most management pages
- Search by name, code, or ID
- Case-insensitive
- Partial matches work

### Filters
- Filter by status (Active, Inactive, etc.)
- Filter by category
- Filter by date range
- Combine multiple filters

### Sorting
- Click column header to sort
- Click again to reverse sort
- Available on data tables

---

## 📱 KEYBOARD SHORTCUTS

| Shortcut | Action |
|----------|--------|
| `Ctrl+S` | Save form |
| `Esc` | Close dialog |
| `Enter` | Submit form |
| `Tab` | Next field |
| `Shift+Tab` | Previous field |

---

## ⚠️ COMMON ISSUES & SOLUTIONS

### Issue: Page not found
**Solution**: Check URL spelling, ensure you're logged in

### Issue: Can't see menu item
**Solution**: Check your role/permissions, switch module

### Issue: Form won't submit
**Solution**: Check required fields (marked with *), fix validation errors

### Issue: Data not saving
**Solution**: Check internet connection, try again, check for error messages

### Issue: Can't find item
**Solution**: Use search, check filters, scroll through pages

---

## 🆘 GETTING HELP

### Documentation
- **Navigation Guide**: `NAVIGATION_GUIDE.md`
- **Management Pages**: `MANAGEMENT_PAGES_GUIDE.md`
- **Module Structure**: `MODULE_STRUCTURE_GUIDE.md`
- **API Documentation**: `API_DOCUMENTATION.md`

### Support
- Check browser console for errors (F12)
- Look for error messages on page
- Check network tab for API errors
- Contact system administrator

---

## 📋 CHECKLIST: BEFORE YOU START

- [ ] You are logged in
- [ ] You can see the module switcher
- [ ] You can see the sidebar menu
- [ ] You know which module you need
- [ ] You know which page you need
- [ ] You have the required permissions

---

## 🎯 ROLE-BASED QUICK ACCESS

### Admin
```
Primary: /dashboard → /settings/users → /settings/roles
Secondary: /pas → /tariff-items
```

### Desk Officer
```
Primary: /do-dashboard → /claims/submissions
Secondary: /claims/automation/admissions
```

### Receiving Facility
```
Primary: /claims/submissions → /claims/referrals
Secondary: /claims/review
```

### Automation Specialist
```
Primary: /claims/automation/admissions → /claims/automation/bundles
Secondary: /tariff-items
```

---

## 🚀 QUICK ACTIONS

### Create New Item
1. Go to management page
2. Click "Add [Item]" or "New [Item]"
3. Fill form
4. Click Save

### Edit Item
1. Find item in list
2. Click pencil icon
3. Modify fields
4. Click Save

### Delete Item
1. Find item in list
2. Click trash icon
3. Confirm deletion

### View Details
1. Find item in list
2. Click eye icon or item name
3. View details page

### Search Item
1. Go to management page
2. Use search field
3. Type search term
4. Results update automatically

---

## 📞 CONTACT & SUPPORT

**System Administrator**: Contact your admin for access issues
**Technical Support**: Check documentation or contact IT
**Feature Requests**: Submit through feedback system

---

**Last Updated**: 2025-12-04
**Version**: 1.0
**Quick Reference**: Use this card for quick navigation lookups

