# RequestReview Function Reference Fix

## Issue Identified
**Error**: `Uncaught (in promise) ReferenceError: loadApprovedReferrals is not defined`

**Root Cause**: The `onMounted` lifecycle hook in RequestReview.vue was calling `loadApprovedReferrals()` function, but this function was removed when we moved the approved referral selection logic to the parent component (CreateReferralPAPage.vue).

## Code Location
**File**: `resources/js/components/pas/components/RequestReview.vue`
**Line**: 548 in the `onMounted` lifecycle hook

```javascript
// Lifecycle
onMounted(() => {
  if (props.requestType === 'referral') {
    loadReferringFacilities();
  } else if (props.requestType === 'pa_code' && props.enrollee) {
    loadApprovedReferrals(); // ← This function no longer exists
  }
});
```

## Solution Applied

### **Removed Function Call**
Updated the `onMounted` lifecycle hook to remove the reference to the non-existent function:

```javascript
// Lifecycle
onMounted(() => {
  if (props.requestType === 'referral') {
    loadReferringFacilities();
  }
  // Note: Approved referral loading is now handled in the parent component (CreateReferralPAPage)
});
```

## Architecture Changes Context

### **Before (Problematic)**:
- RequestReview component handled both referral form AND approved referral selection
- `loadApprovedReferrals()` function existed in RequestReview
- Mixed responsibilities in single component

### **After (Fixed)**:
- **RequestReview**: Handles only the review and submission logic
- **ApprovedReferralSelector**: Dedicated component for approved referral selection
- **CreateReferralPAPage**: Orchestrates the workflow and data flow between components

## Component Responsibilities

### **RequestReview.vue**:
- ✅ Display request summary (facility, enrollee, services)
- ✅ Show selected approved referral details (read-only)
- ✅ Handle clinical form for referrals
- ✅ Submit request with proper validation
- ❌ No longer loads or manages approved referrals

### **ApprovedReferralSelector.vue**:
- ✅ Load approved referrals for selected enrollee
- ✅ Display referrals in selectable format
- ✅ Handle referral selection logic
- ✅ Emit selected referral to parent

### **CreateReferralPAPage.vue**:
- ✅ Manage overall workflow state
- ✅ Pass selected approved referral as prop to RequestReview
- ✅ Coordinate data flow between components

## Data Flow

### **PA Code Workflow**:
1. **Step 5**: ApprovedReferralSelector loads and manages referral selection
2. **Step 6**: Services selection (optional)
3. **Step 7**: RequestReview receives selected referral as prop and displays summary

### **Props Flow**:
```
CreateReferralPAPage
├── selectedApprovedReferral (reactive data)
├── ApprovedReferralSelector (Step 5)
│   └── v-model="selectedApprovedReferral"
└── RequestReview (Step 7)
    └── :selected-approved-referral="selectedApprovedReferral"
```

## Expected Behavior After Fix

### **No More Errors**:
- ✅ No more "loadApprovedReferrals is not defined" error
- ✅ Clean component separation
- ✅ Proper data flow through props

### **Workflow Functions Correctly**:
- ✅ Approved referral selection works in Step 5
- ✅ Selected referral displays in Step 7 review
- ✅ Submit button validation works correctly

## Files Modified
- `resources/js/components/pas/components/RequestReview.vue` - Removed function call from onMounted

## Testing Steps

1. **Navigate to PA Code workflow**
2. **Complete Steps 1-4** (Facility, Enrollee, Profile, Request Type)
3. **Step 5**: Select approved referral - should work without errors
4. **Step 6**: Select services (optional)
5. **Step 7**: Review and submit - should display selected referral and work correctly

The fix ensures clean component separation and eliminates the function reference error while maintaining all functionality.
