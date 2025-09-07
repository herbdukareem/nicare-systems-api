<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollee Profile - {{ $enrollee->enrollee_id }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #2563eb;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 200px;
            font-weight: bold;
            padding: 8px 0;
            background-color: #f8f9fa;
        }
        .info-value {
            display: table-cell;
            padding: 8px 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>NGSCHA Enrollee Profile</h1>
        <p>Enrollee ID: {{ $enrollee->enrollee_id }}</p>
        <p>Generated on: {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <div class="section">
        <h2>Personal Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Full Name:</div>
                <div class="info-value">{{ $enrollee->first_name }} {{ $enrollee->middle_name }} {{ $enrollee->last_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value">{{ $enrollee->phone }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $enrollee->email ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">NIN:</div>
                <div class="info-value">{{ $enrollee->nin ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Birth:</div>
                <div class="info-value">{{ $enrollee->date_of_birth ? $enrollee->date_of_birth->format('F j, Y') : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Age:</div>
                <div class="info-value">{{ $enrollee->date_of_birth ? $enrollee->date_of_birth->age . ' years' : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender:</div>
                <div class="info-value">{{ $enrollee->gender }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Marital Status:</div>
                <div class="info-value">{{ $enrollee->marital_status ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value">{{ $enrollee->address ?: 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Enrollment Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Enrollee Type:</div>
                <div class="info-value">{{ $enrollee->enrolleeType ? $enrollee->enrolleeType->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Enrollee Category:</div>
                <div class="info-value">{{ $enrollee->enrollee_category ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Facility:</div>
                <div class="info-value">{{ $enrollee->facility ? $enrollee->facility->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">LGA:</div>
                <div class="info-value">{{ $enrollee->lga ? $enrollee->lga->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Ward:</div>
                <div class="info-value">{{ $enrollee->ward ? $enrollee->ward->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Village:</div>
                <div class="info-value">{{ $enrollee->village ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="status-badge status-{{ strtolower($enrollee->status ? $enrollee->status->value : 'pending') }}">
                        {{ $enrollee->status ? $enrollee->status->value : 'pending' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Premium & Funding Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Premium ID:</div>
                <div class="info-value">{{ $enrollee->premium_id ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Funding Type:</div>
                <div class="info-value">{{ $enrollee->fundingType ? $enrollee->fundingType->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Benefactor:</div>
                <div class="info-value">{{ $enrollee->benefactor ? $enrollee->benefactor->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Capitation Start Date:</div>
                <div class="info-value">{{ $enrollee->capitation_start_date ? $enrollee->capitation_start_date->format('F j, Y') : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Approval Date:</div>
                <div class="info-value">{{ $enrollee->approval_date ? $enrollee->approval_date->format('F j, Y \a\t g:i A') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    @if($enrollee->employmentDetail)
    <div class="section">
        <h2>Employment Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Employer:</div>
                <div class="info-value">{{ $enrollee->employmentDetail->employer ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Position:</div>
                <div class="info-value">{{ $enrollee->employmentDetail->position ?: 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Salary:</div>
                <div class="info-value">{{ $enrollee->employmentDetail->salary ? '₦' . number_format($enrollee->employmentDetail->salary, 2) : 'N/A' }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class="section">
        <h2>System Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Created By:</div>
                <div class="info-value">{{ $enrollee->creator ? $enrollee->creator->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Created Date:</div>
                <div class="info-value">{{ $enrollee->created_at ? $enrollee->created_at->format('F j, Y \a\t g:i A') : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Approved By:</div>
                <div class="info-value">{{ $enrollee->approver ? $enrollee->approver->name : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Last Updated:</div>
                <div class="info-value">{{ $enrollee->updated_at ? $enrollee->updated_at->format('F j, Y \a\t g:i A') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>This document was generated by the NGSCHA Enrolment System</p>
        <p>Confidential - For Official Use Only</p>
    </div>
</body>
</html>