┌─────────────────────────────────────────────────────────────────┐
│                    ONE EPISODE OF CARE                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. REFERRAL (Approved)                                        │
│     ├─ Generates UTN                                           │
│     ├─ May include Service Bundle or Direct Services           │
│     └─ Creates PRIMARY PA CODE (implicit)                      │
│                                                                 │
│  2. ADMISSION (UTN Validated)                                  │
│     ├─ Linked to Referral                                      │
│     ├─ Linked to UTN                                           │
│     └─ Episode starts                                          │
│                                                                 │
│  3. FU-PA CODE(S) (During Admission) - OPTIONAL                │
│     ├─ Requested when additional services needed               │
│     ├─ Type: BUNDLE or FFS_TOP_UP                             │
│     ├─ Must reference the original referral                    │
│     └─ Approved before services rendered                       │
│                                                                 │
│  4. CLAIM (At Discharge)                                       │
│     ├─ ONE claim per UTN/admission                            │
│     ├─ Includes PRIMARY bundle (from referral)                 │
│     ├─ Includes FU-PA services (from approved FU-PA codes)     │
│     └─ All linked via UTN + Principal ICD-10 diagnosis         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘


📋 Episode Flow: Referral → Admission → FU-PA Code → Claims
1. Initial Referral (Episode Start)
    Patient gets a referral from referring facility to receiving facility
    Referral is approved → generates UTN (Unique Treatment Number)
    Referral may include:
    Service Bundle (e.g., "Appendectomy Package")
    Direct Services (e.g., specific procedures)
    Or no service selection (just referral for assessment)

2. Admission (Episode Continues)
    Patient arrives at receiving facility with approved referral + UTN
    Facility validates UTN and creates admission record
    Admission is linked to the referral
    One UTN = One Admission = One Episode of Care
3. FU-PA Code (Follow-Up Pre-Authorization)
    During admission, if additional services are needed beyond the original referral:
    Complications arise (e.g., infection after surgery)
    Additional procedures needed (e.g., extra imaging, lab tests)
    Extended care required (e.g., ICU admission)
    Facility requests FU-PA Code (Follow-Up PA Code) for:
    Service Bundle (single selection) - e.g., "ICU Care Package"
    Multiple Direct Services (e.g., CT Scan + Blood Culture + Specialist Consultation)
    FU-PA Code Type:
    BUNDLE - If service bundle selected
FFS_TOP_UP - If direct services selected (Fee-For-Service top-up)
4. Claims Submission (Episode End)
When patient is discharged, facility submits ONE CLAIM for the entire episode:

Claim includes:

✅ Primary Bundle (from original referral/UTN) - linked via principal ICD-10 diagnosis
✅ FU-PA Services (from approved FU-PA codes) - additional services during admission
✅ All services must have valid PA codes or be part of approved bundle


Suggested Testing:

✅ Create a referral with service bundle → Approve → Verify PA code is auto-created
✅ Create admission for approved referral
✅ Request FU-PA code with bundle service (single)
✅ Request FU-PA code with multiple direct services
✅ Verify admission_id is required and validated
✅ Verify episode-specific validation works correctly
✅ View details in FU-PA approval page
✅ Approve/reject PA codes
✅ Create claim and verify UTN is populated
✅ Verify all data is saved correctly in database


📝 NEXT STEPS (OPTIONAL)
If you want to further enhance the system, consider:

Claim Review/Approval Workflow - Agency staff interface to review and approve/reject claims
Claim Payment Processing - Payment batch generation and tracking
Claim Reports - Analytics and reporting for claims data
Claim Amendments - Allow facilities to amend rejected claims
Bulk Claim Upload - CSV/Excel import for multiple claims