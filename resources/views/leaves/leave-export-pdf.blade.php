<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <title>Leave Application Form</title>

<style>
    @page {
        margin: 20px 30px;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #000;
        line-height: 1.2;
        margin: 0;
        padding: 0;
    }

    * {
        box-sizing: border-box;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .font-bold {
        font-weight: bold;
    }

    .font-italic {
        font-style: italic;
    }

    .small {
        font-size: 8px;
    }

    .mb-2 {
        margin-bottom: 4px;
    }

    .mb-3 {
        margin-bottom: 6px;
    }

    .mb-4 {
        margin-bottom: 8px;
    }

    .mb-5 {
        margin-bottom: 10px;
    }

    .title {
        font-size: 21px;
        font-weight: bold;
        letter-spacing: 0.8px;
        text-align: center;
        margin-bottom: 12px;
    }

    .revision {
        font-size: 9px;
        font-weight: bold;
        text-align: right;
        line-height: 1.15;
    }

    .form-table td {
        vertical-align: bottom;
        padding: 4px 4px;
    }

    .label {
        white-space: nowrap;
        font-weight: bold;
    }

    .field {
        border-bottom: 1px solid #000;
        min-height: 15px;
        padding: 0 3px 2px 3px;
    }

    .section-line {
        border-top: 2px solid #000;
        margin: 9px 0 6px 0;
    }

    .section-title {
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .checkbox {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 1px solid #000;
        text-align: center;
        line-height: 12px;
        font-size: 9px;
        margin-right: 4px;
        vertical-align: middle;
    }

    .leave-option {
        vertical-align: top;
        width: 33.33%;
        padding: 4px 6px;
    }

    .leave-name {
        font-size: 10px;
        font-weight: bold;
    }

    .leave-note {
        font-size: 8px;
        margin-left: 19px;
        margin-top: 2px;
        line-height: 1.2;
    }

    .note {
        font-size: 8px;
        line-height: 1.25;
    }

    .office-title {
        font-size: 16px;
        font-weight: bold;
        text-align: center;
        margin-top: 9px;
        margin-bottom: 2px;
    }

    .office-table {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
    }

    .office-table td {
        border-right: 1px solid #777;
        border-bottom: 1px solid #777;
        padding: 5px 6px;
        height: 22px;
    }

    .office-table tr:last-child td {
        border-bottom: none;
    }

    .office-table td:last-child {
        border-right: none;
    }

    .office-label {
        width: 25%;
        font-weight: bold;
    }

    .office-value {
        width: 25%;
    }

    .approval-table {
        margin-top: 7px;
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;
    }

    .approval-table td {
        width: 50%;
        vertical-align: top;
        padding: 6px 8px;
        height: 80px;
    }

    .approval-table > tbody > tr > td:first-child {
        border-right: 1px solid #777;
    }

    .signature-space {
        height: 27px;
    }

    .signature-line {
        border-bottom: 1px dotted #000;
        margin-bottom: 2px;
        min-height: 12px;
    }

    .status {
        font-size: 8px;
        margin-top: 4px;
    }
</style>
</head>

<body>

@php
    $user = $leave_request->user;
    $personal = $user?->personal;
    $contact = $user?->contact;
    $employment = $user?->employment;

    $leave_entitlement = $leave_request->leaveEntitlement;
    $leave_policy = $leave_entitlement?->leavePolicy;

    $request_dates = $leave_request->leaveRequestDates ?? collect();

    $start_date = $request_dates->min('date');
    $end_date = $request_dates->max('date');

    $employee_name = trim(
        ($personal?->first_name ?? '') . ' ' .
        ($personal?->last_name ?? '')
    );

    $employee_name = $employee_name ?: ($user?->name ?? '--');

    $position = $employment?->position?->name ?? '--';
    $department = $employment?->department?->name ?? '--';

    $handover_personal = $leave_request->handoverBy?->personal;

    $handover_name = trim(
        ($handover_personal?->first_name ?? '') . ' ' .
        ($handover_personal?->last_name ?? '')
    );

    $handover_name = $handover_name ?: '--';

    $manager_personal = $leave_request->managerActionBy?->personal;

    $manager_name = trim(
        ($manager_personal?->first_name ?? '') . ' ' .
        ($manager_personal?->last_name ?? '')
    );

    $manager_name = $manager_name ?: '--';

    $director_personal = $leave_request->directorActionBy?->personal;

    $director_name = trim(
        ($director_personal?->first_name ?? '') . ' ' .
        ($director_personal?->last_name ?? '')
    );

    $director_name = $director_name ?: '--';

    $leave_name = strtolower($leave_policy?->name ?? '');

    $is_annual = str_contains($leave_name, 'annual');
    $is_medical = str_contains($leave_name, 'medical');
    $is_unpaid = str_contains($leave_name, 'unpaid') || str_contains($leave_name, 'special');
    $is_compassionate = str_contains($leave_name, 'compassionate');
    $is_maternity = str_contains($leave_name, 'maternity');
@endphp

<!-- Header -->
<table>
    <tr>
        <td style="width: 20%;"></td>

        <td style="width: 60%;">
            <div class="title">
                LEAVE APPLICATION FORM
            </div>
        </td>

        <td style="width: 20%;">
            <div class="revision">
                Rev. 1<br>
                {{ now()->format('d-M-Y') }}
            </div>
        </td>
    </tr>
</table>

<!-- Application Date -->
<table class="form-table mb-3">
    <tr>
        <td style="width: 55%;"></td>

        <td class="label" style="width: 20%;">
            Date of Application:
        </td>

        <td style="width: 25%;">
            <div class="field">
                {{ optional($leave_request->created_at)->format('d-M-Y') }}
            </div>
        </td>
    </tr>
</table>

<!-- Main Information -->
<table class="form-table">
    <tr>
        <td class="label" style="width: 14%;">
            Name:
        </td>

        <td style="width: 41%;">
            <div class="field">
                {{ $employee_name }}
            </div>
        </td>

        <td style="width: 8%;"></td>

        <td style="width: 37%;"></td>
    </tr>

    <tr>
        <td class="label">
            Designation:
        </td>

        <td>
            <div class="field">
                {{ $position }}
            </div>
        </td>

        <td class="label">
            Department:
        </td>

        <td>
            <div class="field">
                {{ $department }}
            </div>
        </td>
    </tr>

    <tr>
        <td class="label">
            No. of Days:
        </td>

        <td>
            <div class="field">
                {{ $leave_request->total_days ?? '--' }}
            </div>
        </td>

        <td class="label">
            From - To:
        </td>

        <td>
            <div class="field">
                {{ $start_date ? \Carbon\Carbon::parse($start_date)->format('d-M-Y') : '--' }}
                -
                {{ $end_date ? \Carbon\Carbon::parse($end_date)->format('d-M-Y') : '--' }}
            </div>

            <div class="text-center small">
                (both dates inclusive)
            </div>
        </td>
    </tr>

    <tr>
        <td colspan="2"></td>

        <td class="label">
            Resume Duties Date:
        </td>

        <td>
            <div class="field">
                {{ $leave_request->resume_date
                    ? $leave_request->resume_date->format('d-M-Y')
                    : '--'
                }}
            </div>
        </td>
    </tr>
</table>

<!-- Handover -->
<table class="form-table mb-2">
    <tr>
        <td style="width: 48%;" class="label">
            During my leave, my duties will be taken over by:
        </td>

        <td style="width: 7%;" class="label">
            Name:
        </td>

        <td style="width: 45%;">
            <div class="field">
                {{ $handover_name }}
            </div>
        </td>
    </tr>

    <tr>
        <td></td>

        <td class="label">
            Signature:
        </td>

        <td>
            <div class="field">
                @if($leave_request->handover_approved)
                    Approved

                    @if($leave_request->handover_action_at)
                        - {{ $leave_request->handover_action_at->format('d-M-Y') }}
                    @endif
                @endif
            </div>
        </td>
    </tr>
</table>

<div class="font-bold font-italic mb-4">
    (Note: The person who takes over the job MUST understand the scope of work he/she undertakes.)
</div>

<!-- Contact -->
<table class="form-table mb-3">
    <tr>
        <td style="width: 20%;" class="label">
            Tel No. during leave:
        </td>

        <td style="width: 30%;">
            <div class="field">
                {{ $contact?->phone_number ?? '--' }}
            </div>

            <div class="text-center small">
                (in case of emergency)
            </div>
        </td>

        <td style="width: 50%;"></td>
    </tr>
</table>

<!-- Notes / Applicant -->
<table class="mb-3">
    <tr>
        <td style="width: 7%; vertical-align: top;">
            <strong>Note:</strong>
        </td>

        <td class="note" style="width: 58%; vertical-align: top;">
            (1) In the event that I do not complete the necessary period of service
            which would entitle me to the leave applied for, I undertake to pay
            the company the salary/wages paid to me for such leave or the company
            may deduct the sum from salary/wages due to me.
            <br>
            (2) This application is subject to applicable company leave policies
            and approval requirements.
        </td>

        <td style="width: 3%;"></td>

        <td style="width: 32%; vertical-align: bottom;">
            <div class="signature-space"></div>

            <div class="signature-line">
                {{ $employee_name }}
            </div>

            <div class="text-center">
                Applicant's Name / Date
            </div>

            <div class="text-center small">
                {{ optional($leave_request->created_at)->format('d-M-Y') }}
            </div>
        </td>
    </tr>
</table>

<div class="section-line"></div>

<!-- Leave Type -->
<div class="section-title">
    Type of Leave
    <span class="small">
        (Please take note of the notice period required to process leave application)
    </span>
</div>

<table>
    <tr>
        <td class="leave-option">
            <span class="checkbox">
                {{ $is_annual ? 'X' : '' }}
            </span>

            <span class="leave-name">
                Annual Leave
            </span>

            <div class="leave-note">
                Subject to leave policy notice requirements
            </div>
        </td>

        <td class="leave-option">
            <span class="checkbox">
                {{ $is_medical ? 'X' : '' }}
            </span>

            <span class="leave-name">
                Medical Leave
            </span>

            <div class="leave-note">
                Medical document may be required
            </div>
        </td>

        <td class="leave-option">
            <span class="checkbox">
                {{ $is_unpaid ? 'X' : '' }}
            </span>

            <span class="leave-name">
                Special / Unpaid Leave
            </span>

            <div class="leave-note">
                Subject to approval
            </div>
        </td>
    </tr>

    <tr>
        <td class="leave-option">
            <span class="checkbox">
                {{ $is_compassionate ? 'X' : '' }}
            </span>

            <span class="leave-name">
                Compassionate Leave
            </span>
        </td>

        <td class="leave-option">
            <span class="checkbox">
                {{ $is_maternity ? 'X' : '' }}
            </span>

            <span class="leave-name">
                Maternity Leave
            </span>
        </td>

        <td class="leave-option">
            @if(
                !$is_annual &&
                !$is_medical &&
                !$is_unpaid &&
                !$is_compassionate &&
                !$is_maternity
            )
                <span class="checkbox">
                    X
                </span>

                <span class="leave-name">
                    {{ $leave_policy?->name ?? 'Other Leave' }}
                </span>
            @endif
        </td>
    </tr>
</table>

<!-- Office Use -->
<div class="office-title">
    FOR OFFICE USE ONLY
</div>

<table class="office-table">
    <tr>
        <td class="office-label">
            Date of Commencement:
        </td>

        <td class="office-value">
            {{ $employment?->joined_date
                ? \Carbon\Carbon::parse($employment->joined_date)->format('d-M-Y')
                : '--'
            }}
        </td>

        <td class="office-label">
            Days Entitled:
        </td>

        <td class="office-value">
            {{ $leave_entitlement?->entitled_days ?? '--' }}
        </td>
    </tr>

    <tr>
        <td class="office-label">
            Leave Entitlement:
        </td>

        <td class="office-value">
            {{ $leave_policy?->name ?? '--' }}
        </td>

        <td class="office-label">
            Leave Already Taken:
        </td>

        <td class="office-value">
            {{ $leave_entitlement?->used_days ?? '--' }}
        </td>
    </tr>

    <tr>
        <td class="office-label">
            Period of Service:
        </td>

        <td class="office-value">
            @if($employment?->joined_date)
                {{ \Carbon\Carbon::parse($employment->joined_date)->diffInYears(now()) }}
                year(s)
            @else
                --
            @endif
        </td>

        <td class="office-label">
            This Application:
        </td>

        <td class="office-value">
            {{ $leave_request->total_days ?? '--' }}
        </td>
    </tr>

    <tr>
        <td class="office-label">
            Reason:
        </td>

        <td class="office-value">
            {{ $leave_request->reason ?? '--' }}
        </td>

        <td class="office-label">
            Balance of Leave:
        </td>

        <td class="office-value">
            {{ $leave_entitlement?->balance_days ?? '--' }}
        </td>
    </tr>
</table>

<!-- Approval -->
<table class="approval-table">
    <tr>
        <td>
            <div class="font-bold">
                Verified / Approved by Manager
            </div>

            <div class="signature-space"></div>

            <div class="signature-line">
                {{ $manager_name }}
            </div>

            <table>
                <tr>
                    <td>
                        Manager
                    </td>

                    <td class="text-right">
                        @if($leave_request->manager_action_at)
                            {{ $leave_request->manager_action_at->format('d-M-Y') }}
                        @endif
                    </td>
                </tr>
            </table>

            @if(!is_null($leave_request->manager_approved))
                <div class="status">
                    Status:
                    <strong>
                        {{ $leave_request->manager_approved ? 'APPROVED' : 'NOT APPROVED' }}
                    </strong>
                </div>
            @endif
        </td>

        <td>
            <div class="font-bold">
                Leave Approved / Not Approved by
            </div>

            <div class="signature-space"></div>

            <div class="signature-line">
                {{ $director_name }}
            </div>

            <table>
                <tr>
                    <td>
                        Director / Manager
                    </td>

                    <td class="text-right">
                        @if($leave_request->director_action_at)
                            {{ $leave_request->director_action_at->format('d-M-Y') }}
                        @endif
                    </td>
                </tr>
            </table>

            @if(!is_null($leave_request->director_approved))
                <div class="status">
                    Status:
                    <strong>
                        {{ $leave_request->director_approved ? 'APPROVED' : 'NOT APPROVED' }}
                    </strong>
                </div>
            @endif
        </td>
    </tr>
</table>

</body>
</html>