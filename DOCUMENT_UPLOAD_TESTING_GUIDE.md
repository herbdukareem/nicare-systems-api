# Document Upload Testing Guide - Referral Submission

## Overview
This guide helps you test the complete document upload flow for referral submissions in the NiCare system.

---

## ✅ Complete Implementation Checklist

### Frontend (ReferralSubmissionPage.vue)
- ✅ Step 4 added for document upload
- ✅ Document requirements fetched from API
- ✅ File upload inputs with validation
- ✅ File size validation (max per document requirement)
- ✅ File type validation (allowed extensions)
- ✅ Required/Optional document handling
- ✅ FormData submission with multipart/form-data
- ✅ Documents appended as `documents[document_type]`

### Backend (ReferralController.php)
- ✅ FileUploadService injected
- ✅ Document validation rule: `'documents.*' => ['nullable', 'file', 'max:10240']`
- ✅ Database transaction wrapping
- ✅ File upload to local storage via FileUploadService
- ✅ ReferralDocument records created
- ✅ Documents relationship loaded in response
- ✅ case_record_ids validation fixed (bundle vs direct)

### API Resource (ReferralResource.php)
- ✅ Documents included in API response
- ✅ Document metadata (file_size_human, url, etc.)
- ✅ DocumentRequirement relationship included
- ✅ Uploader relationship included

### Display (ReferralManagementPage.vue)
- ✅ Documents section in referral details dialog
- ✅ Document table with all metadata
- ✅ Download button
- ✅ View button (opens in new tab)
- ✅ Validation status indicator
- ✅ Required document indicator
- ✅ Uploader name and upload date
- ✅ "No documents" message when empty

---

## 🧪 Testing Steps

### 1. Prepare Test Data

**Ensure Document Requirements Exist:**
```bash
php artisan db:seed --class=DocumentRequirementsSeeder
```

**Check available document types:**
```sql
SELECT * FROM document_requirements WHERE request_type = 'referral' AND status = 1;
```

Expected document types:
- `referral_letter` (Required)
- `medical_report` (Required)
- `lab_results` (Optional)
- `consent_form` (Required)
- `imaging_results` (Optional)

---

### 2. Test Referral Submission with Documents

**Navigate to:** Claims Module > Referral Submission

**Step 1: Patient & Facility**
- Select Referring Facility
- Select Enrollee
- Select Receiving Facility

**Step 2: Clinical Information**
- Fill all required clinical fields
- Select severity level
- Choose service selection type (Bundle or Direct)

**Step 3: Referring Person**
- Fill referring person details
- Fill contact person (optional)

**Step 4: Document Upload** ← NEW STEP
- Upload required documents (referral_letter, medical_report, consent_form)
- Upload optional documents (lab_results, imaging_results)
- Verify file size validation (try uploading >10MB file)
- Verify file type validation (try uploading .exe file)

**Step 5: Review & Submit**
- Review all information
- Verify uploaded documents are listed
- Click Submit

---

### 3. Verify Backend Processing

**Check API Response:**
```json
{
  "success": true,
  "message": "Referral created successfully",
  "data": {
    "id": 1,
    "referral_code": "REF-ABC123",
    "utn": "UTN-XYZ789",
    "documents": [
      {
        "id": 1,
        "document_type": "referral_letter",
        "original_filename": "referral_letter.pdf",
        "file_size_human": "2.5 MB",
        "url": "http://localhost:8000/storage/pas/REF-ABC123/referral_letter/2025-12-13_14-30-45_abc123.pdf",
        "is_required": true,
        "is_validated": false,
        "uploader": {
          "id": 1,
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    ]
  }
}
```

**Check Database:**
```sql
-- Check referral was created
SELECT * FROM referrals WHERE referral_code = 'REF-ABC123';

-- Check documents were saved
SELECT * FROM referral_documents WHERE referral_id = 1;
```

**Check File Storage:**
```
storage/app/public/pas/REF-ABC123/
├── referral_letter/
│   └── 2025-12-13_14-30-45_abc123.pdf
├── medical_report/
│   └── 2025-12-13_14-31-12_def456.pdf
└── consent_form/
    └── 2025-12-13_14-32-10_jkl012.pdf
```

---

### 4. Test Document Display

**Navigate to:** PAS Module > Referral Management

**Steps:**
1. Find the created referral in the list
2. Click "View Details" button
3. Scroll to "Uploaded Documents" section
4. Verify document table shows:
   - ✅ Document type name
   - ✅ Original filename
   - ✅ File size (human-readable)
   - ✅ Uploader name
   - ✅ Upload date
   - ✅ Validation status (Pending/Validated)
   - ✅ Required indicator
5. Click "Download" button → File should download
6. Click "View" button → File should open in new tab

---

## 🔍 Edge Cases to Test

### Test Case 1: Bundle Service (Empty case_record_ids)
- Select service_selection_type: "Bundle"
- Select a service bundle
- Leave case_record_ids empty
- **Expected:** Submission succeeds ✅

### Test Case 2: Direct Service (Required case_record_ids)
- Select service_selection_type: "Direct"
- Leave case_record_ids empty
- **Expected:** Validation error ❌

### Test Case 3: Missing Required Documents
- Skip uploading required documents
- Try to proceed to Step 5
- **Expected:** Validation error on Step 4 ❌

### Test Case 4: File Size Exceeds Limit
- Upload file > 10MB (or document requirement max_file_size_mb)
- **Expected:** Error message shown ❌

### Test Case 5: Invalid File Type
- Upload .exe or .zip file for PDF-only document
- **Expected:** Error message shown ❌

### Test Case 6: Referral Without Documents
- Submit referral without uploading any documents
- View referral details
- **Expected:** "No documents uploaded" message shown ℹ️

---

## 📊 Success Criteria

✅ Documents upload successfully  
✅ Files stored in correct directory structure  
✅ Database records created in `referral_documents` table  
✅ Documents visible in referral details page  
✅ Download button works  
✅ View button opens file in new tab  
✅ Validation status displayed correctly  
✅ Required documents enforced  
✅ File size/type validation works  
✅ Transaction rollback on error  

---

## 🐛 Troubleshooting

**Issue: Files not uploading**
- Check `storage/app/public` directory exists
- Run: `php artisan storage:link`
- Check file permissions

**Issue: Documents not showing in details**
- Verify `documents` relationship loaded in controller
- Check API response includes documents array
- Verify frontend is accessing `selectedReferral.documents`

**Issue: Download/View buttons not working**
- Check file exists in storage
- Verify `url` accessor in ReferralDocument model
- Check `asset('storage/...')` path is correct

**Issue: Validation errors**
- Check document requirements exist in database
- Verify file size is within limits
- Verify file type is allowed

---

## 🎯 Next Steps

After successful testing:
1. Test with real PDF files
2. Test with multiple file types (PDF, JPG, PNG)
3. Test concurrent uploads
4. Test with large files (near 10MB limit)
5. Implement document validation workflow
6. Add document deletion functionality
7. Add document replacement functionality


