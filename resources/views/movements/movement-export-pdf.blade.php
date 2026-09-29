<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <title>Movement Application Form</title>

    <style>
        @page {
            margin: 28px 34px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .company {
            font-size: 17px;
            font-weight: bold;
            color: #111827;
        }

        .document-title {
            margin-top: 18px;
            padding: 10px 12px;
            background: #111827;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.4px;
        }

        .section-title {
            margin-top: 16px;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            border-bottom: 2px solid #111827;
            padding-bottom: 4px;
        }

        .detail-table td {
            border: 1px solid #d1d5db;
            padding: 8px 9px;
            vertical-align: top;
        }

        .label {
            width: 28%;
            background: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }

        .value {
            color: #111827;
        }

        .muted {
            color: #6b7280;
        }

        .description {
            min-height: 72px;
            white-space: pre-line;
        }

        .status-active {
            display: inline-block;
            padding: 3px 8px;
            background: #dcfce7;
            color: #166534;
            border-radius: 4px;
            font-weight: bold;
        }

        .status-inactive {
            display: inline-block;
            padding: 3px 8px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 4px;
            font-weight: bold;
        }

        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #d1d5db;
            font-size: 9px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>

<body>

@php
    $user = $movement->user;
    $personal = $user?->personal;
    $contact = $user?->contact;
    $employment = $user?->employment;
    $applicant_name = trim(($personal?->first_name ?? '') . ' ' . ($personal?->last_name ?? '')) ?: $user?->email;
@endphp

<table class="header-table">
    <tr>
        <td style="width: 58px;">
            <img src="https://www.petro-excel.com.my/wp-content/uploads/2018/09/Oil-Drop-Out-line-e1736841035299.png" width="46">
        </td>
        <td>
            <div class="company">Petro-Excel Sdn Bhd</div>
            <div class="muted">Employee Movement Application</div>
        </td>
        <td style="text-align:right; font-size:9px; color:#6b7280;">
            Generated At<br>
            {{ now()->format('Y-m-d h:i:s A') }}
        </td>
    </tr>
</table>

<div class="document-title">MOVEMENT APPLICATION FORM</div>

<div class="section-title">Applicant Information</div>
<table class="detail-table">
    <tr>
        <td class="label">Applicant Name</td>
        <td class="value">{{ $applicant_name }}</td>
        <td class="label">Email</td>
        <td class="value">{{ $user?->email ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Company Email</td>
        <td class="value">{{ $contact?->company_email ?: '-' }}</td>
        <td class="label">Phone Number</td>
        <td class="value">{{ $contact?->phone_number ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Department</td>
        <td class="value">{{ $employment?->department?->name ?: '-' }}</td>
        <td class="label">Position</td>
        <td class="value">{{ $employment?->position?->name ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Office</td>
        <td class="value">{{ $employment?->office?->name ?: '-' }}</td>
        <td class="label">Submitted Date</td>
        <td class="value">{{ $movement->created_at ? $movement->created_at->format('Y-m-d h:i:s A') : '-' }}</td>
    </tr>
</table>

<div class="section-title">Movement Details</div>
<table class="detail-table">
    <tr>
        <td class="label">Movement Type</td>
        <td class="value">{{ $movement->movement_type?->name ?: '-' }}</td>
        <td class="label">Location</td>
        <td class="value">{{ $movement->location ?: '-' }}</td>
    </tr>
    <tr>
        <td class="label">Start Date</td>
        <td class="value">{{ $movement->start_date ? \Carbon\Carbon::parse($movement->start_date)->format('Y-m-d') : '-' }}</td>
        <td class="label">End Date</td>
        <td class="value">{{ $movement->end_date ? \Carbon\Carbon::parse($movement->end_date)->format('Y-m-d') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Status</td>
        <td class="value">
            @if($movement->is_active)
                <span class="status-active">Active</span>
            @else
                <span class="status-inactive">Inactive</span>
            @endif
        </td>
        <td class="label">Reference UUID</td>
        <td class="value">{{ $movement->uuid }}</td>
    </tr>
    <tr>
        <td class="label">Description</td>
        <td class="value description" colspan="3">{{ $movement->description ?: '-' }}</td>
    </tr>
    @if($movement->attachment_path)
        <tr>
            <td class="label">Attachment</td>
            <td class="value" colspan="3">{{ asset(\Illuminate\Support\Facades\Storage::url($movement->attachment_path)) }}</td>
        </tr>
    @endif
</table>

<div class="footer">
    This document was generated by PE Portal. Petro-Excel Sdn Bhd, Lot 1236 & 1237, Senadin Venture Light Industrial Park, Jalan Lutong - Kuala Baram, 98000 Miri, Sarawak.
</div>

</body>
</html>
