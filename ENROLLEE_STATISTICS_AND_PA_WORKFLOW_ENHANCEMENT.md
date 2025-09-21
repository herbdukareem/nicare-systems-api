# Enrollee Statistics & PA Code Workflow Enhancement

## Features Implemented

### 1. ✅ **Enrollee Medical Statistics on Select Enrollee Page**

Added comprehensive medical statistics display when an enrollee is selected, showing:

#### **Recent Activity Section**
- Real-time loading indicator
- Refresh button for manual updates
- "No recent activity found" state when applicable

#### **Medical Summary Cards**
- **Total Referrals**: Shows count of all referrals for the enrollee
- **PA Codes Used**: Shows count of used PA codes
- **Last Visit**: Shows relative time of most recent activity (referral or PA code)

#### **Visual Design**
- Color-coded cards (blue for referrals, green for PA codes, orange for last visit)
- Icons for each statistic type
- Responsive grid layout
- Integrated into the existing enrollee selection card

#### **Smart Date Formatting**
- "Today", "Yesterday" for recent visits
- "X days ago", "X weeks ago" for recent activity
- Month/year format for older visits
- "N/A" when no activity found

### 2. ✅ **Enhanced PA Code Workflow with Approved Referrals Dropdown**

#### **Key Features**:
- **Dropdown selection** of last 2 approved referrals for the selected enrollee
- **Service selection preserved** - users can still select additional services
- **Detailed referral information** displayed in dropdown and selection card
- **Validation** ensures an approved referral is selected before proceeding

#### **Dropdown Features**:
- Shows referral code and diagnosis as display text
- Facility routing information (From → To)
- Approval date and status indicators
- "No approved referrals found" state
- Auto-loads when enrollee is selected

#### **Selected Referral Details Card**:
- Referral code and approval date
- Diagnosis and severity level
- Status indicator
- Clean, organized layout

## Technical Implementation

### **EnrolleeSelector.vue Enhancements**

#### **New Reactive Data**:
```javascript
const medicalStats = ref(null);
const loadingMedicalStats = ref(false);
```

#### **Medical Statistics Loading**:
```javascript
const loadEnrolleeMedicalStats = async () => {
  const [referralsResponse, paCodesResponse] = await Promise.all([
    pasAPI.getReferrals({ search: selectedEnrollee.value.enrollee_id }),
    pasAPI.getPACodes({ search: selectedEnrollee.value.enrollee_id })
  ]);

  const totalReferrals = referrals.length;
  const paCodesUsed = paCodes.filter(pa => pa.status === 'used').length;
  const lastVisit = /* most recent activity date */;

  medicalStats.value = { total_referrals, pa_codes_used, last_visit };
};
```

#### **Smart Date Formatting**:
```javascript
const formatLastVisit = (dateString) => {
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays === 1) return 'Today';
  if (diffDays === 2) return 'Yesterday';
  if (diffDays <= 7) return `${diffDays - 1} days ago`;
  // ... more formatting logic
};
```

### **RequestReview.vue PA Code Enhancements**

#### **New Reactive Data**:
```javascript
const selectedApprovedReferral = ref(null);
const approvedReferrals = ref([]);
const loadingReferrals = ref(false);
```

#### **Approved Referrals Loading**:
```javascript
const loadApprovedReferrals = async () => {
  const response = await pasAPI.getReferrals({
    search: props.enrollee.enrollee_id,
    status: 'approved',
    limit: 10
  });
  
  approvedReferrals.value = response.data.data
    .filter(referral => referral.status === 'approved')
    .slice(0, 2) // Last 2 approved referrals
    .map(referral => ({
      ...referral,
      display_text: `${referral.referral_code} - ${referral.preliminary_diagnosis}`
    }));
};
```

#### **Enhanced Submit Logic**:
```javascript
if (props.requestType === 'pa_code') {
  if (!selectedApprovedReferral.value) {
    error('Please select an approved referral for PA Code generation');
    return;
  }
  
  requestData = {
    referral_id: selectedApprovedReferral.value.id,
    services: props.services.map(service => ({ /* service data */ })),
    service_description: props.services.map(s => s.service_description).join(', '),
    approved_amount: totalCost.value,
    // ... other PA code fields
  };
}
```

## User Experience Improvements

### **Before**
- ❌ No medical history visible when selecting enrollees
- ❌ PA Code workflow didn't show approved referrals
- ❌ No context about enrollee's previous activity
- ❌ Limited validation for PA code requirements

### **After**
- ✅ **Rich medical statistics** showing enrollee's activity history
- ✅ **Approved referrals dropdown** with detailed information
- ✅ **Service selection preserved** for additional services
- ✅ **Smart date formatting** for easy understanding
- ✅ **Real-time loading** with proper loading states
- ✅ **Comprehensive validation** ensuring proper PA code workflow
- ✅ **Visual indicators** for approval status and activity

## Workflow Changes

### **PA Code Generation Process**:
1. **Select Facility** → Choose healthcare facility
2. **Select Enrollee** → Choose patient (shows medical statistics)
3. **View Profile** → Review enrollee details with activity summary
4. **Choose PA Code Request** → Select PA Code as request type
5. **Select Approved Referral** → Choose from dropdown of last 2 approved referrals
6. **Select Services** → Add additional services if needed (preserved functionality)
7. **Review & Submit** → Generate PA code linked to selected referral

### **Medical Statistics Display**:
- **Automatic loading** when enrollee is selected
- **Refresh capability** for real-time updates
- **Contextual information** helping staff make informed decisions
- **Activity timeline** showing recent medical interactions

## Files Modified
- `resources/js/components/pas/components/EnrolleeSelector.vue` - Added medical statistics
- `resources/js/components/pas/components/RequestReview.vue` - Added approved referrals dropdown

## API Integration
- **Existing endpoints used**: `/v1/pas/referrals` and `/v1/pas/pa-codes`
- **Efficient parallel loading** of referrals and PA codes for statistics
- **Filtered queries** for approved referrals only
- **Proper error handling** for all API calls

## Testing Recommendations

1. **Test medical statistics**:
   - Select enrollee with existing referrals/PA codes
   - Verify statistics load correctly
   - Test refresh functionality
   - Check date formatting for various time periods

2. **Test PA code workflow**:
   - Select enrollee with approved referrals
   - Verify dropdown shows correct referrals
   - Test service selection still works
   - Verify validation prevents submission without referral selection

3. **Test edge cases**:
   - Enrollee with no approved referrals
   - Enrollee with no medical history
   - Network errors during loading
   - Multiple rapid enrollee selections

The implementation provides a much richer user experience while maintaining all existing functionality and adding the requested approved referrals dropdown for PA code generation.
