<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $contract->title }} {{ $contract->number ?? __('app.contract_pdf_title_fallback') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 30px; }
        h1 { font-size: 22px; color: #1a1a1a; margin-bottom: 4px; }
        .meta { color: #555; font-size: 11px; margin-bottom: 24px; }
        .body-text { white-space: pre-wrap; line-height: 1.5; margin: 20px 0; }
        table { width: 100%; }
        .signature-box { width: 45%; display: inline-block; margin-top: 60px; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ $contract->title }}</h1>
    <div class="meta">
        @if($contract->number)
            {{ __('app.contract_pdf_reference') }}: <strong>{{ $contract->number }}</strong> &nbsp;|&nbsp;
        @endif
        {{ __('app.contract_pdf_valid_from') }}: {{ $contract->valid_from->format('d.m.Y') }}
        @if($contract->end_date)
            &nbsp;|&nbsp; {{ __('app.contract_pdf_valid_to') }}: {{ $contract->end_date->format('d.m.Y') }}
        @endif
        @if($contract->signed_at)
            &nbsp;|&nbsp; {{ __('app.contract_pdf_signed_at') }}: {{ $contract->signed_at->format('d.m.Y') }}
        @endif
    </div>

    <table style="width:100%; margin-bottom:20px;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:20px;">
                <strong>{{ __('app.contract_pdf_supplier') }}</strong><br>
                <strong style="font-size:14px;">{{ $tenant->name }}</strong><br>
                @if($tenant->address_line)
                    {{ $tenant->address_line }}<br>
                @endif
                @if($tenant->city || $tenant->postal_code)
                    {{ implode(' ', array_filter([$tenant->postal_code, $tenant->city])) }}<br>
                @endif
                @if($tenant->ico)
                    IČO: {{ $tenant->ico }}<br>
                @endif
                @if($tenant->dic)
                    DIČ: {{ $tenant->dic }}<br>
                @endif
            </td>
            <td style="width:50%; vertical-align:top;">
                <strong>{{ __('app.contract_pdf_party') }}</strong><br>
                @if($contract->contractable instanceof \App\Models\CleaningObject)
                    <strong>{{ $contract->contractable->client?->name }}</strong><br>
                    {{ $contract->contractable->name }}<br>
                    @if($contract->contractable->street)
                        {{ $contract->contractable->street }}<br>
                    @endif
                    @if($contract->contractable->city || $contract->contractable->postal_code)
                        {{ implode(' ', array_filter([$contract->contractable->postal_code, $contract->contractable->city])) }}<br>
                    @endif
                @elseif($contract->contractable instanceof \App\Models\TenantMembership)
                    <strong>{{ trim(($contract->contractable->first_name ?? '').' '.($contract->contractable->last_name ?? '')) ?: $contract->contractable->user?->name }}</strong><br>
                    {{ $contract->contractable->user?->email }}<br>
                @endif
            </td>
        </tr>
    </table>

    <div class="body-text">{!! nl2br(e($contract->body)) !!}</div>

    @if($contract->employmentContract)
        <h3>{{ __('app.contract_pdf_employment_heading') }}</h3>
        <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_employment_type') }}</td>
                <td style="padding:4px 0;">{{ $contract->employmentContract->employment_type->label() }}</td>
            </tr>
            @if($contract->employmentContract->position)
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_position') }}</td>
                <td style="padding:4px 0;">{{ $contract->employmentContract->position }}</td>
            </tr>
            @endif
            @if($contract->employmentContract->monthly_salary)
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_monthly_salary') }}</td>
                <td style="padding:4px 0;">{{ number_format((float) $contract->employmentContract->monthly_salary, 2, ',', ' ') }} EUR</td>
            </tr>
            @endif
            @if($contract->employmentContract->hourly_rate)
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_hourly_rate') }}</td>
                <td style="padding:4px 0;">{{ number_format((float) $contract->employmentContract->hourly_rate, 2, ',', ' ') }} EUR</td>
            </tr>
            @endif
            @if($contract->employmentContract->weekly_hours)
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_weekly_hours') }}</td>
                <td style="padding:4px 0;">{{ number_format((float) $contract->employmentContract->weekly_hours, 2, ',', ' ') }}</td>
            </tr>
            @endif
            @if($contract->employmentContract->probation_end_date)
            <tr>
                <td style="padding:4px 8px 4px 0;">{{ __('app.contract_pdf_probation_until') }}</td>
                <td style="padding:4px 0;">{{ $contract->employmentContract->probation_end_date->format('d.m.Y') }}</td>
            </tr>
            @endif
        </table>
    @endif

    @if($contract->terminated_at)
        <p style="font-size:11px; color:#a33;">
            {{ __('app.contract_pdf_terminated_at') }}: {{ $contract->terminated_at->format('d.m.Y') }}
            @if($contract->termination_reason)
                — {{ $contract->termination_reason }}
            @endif
        </p>
    @endif

    <table style="width:100%; margin-top:40px;">
        <tr>
            <td class="signature-box">
                <div class="signature-line">{{ __('app.contract_pdf_signature_supplier') }}</div>
            </td>
            <td class="signature-box">
                <div class="signature-line">{{ __('app.contract_pdf_signature_party') }}</div>
            </td>
        </tr>
    </table>

    <p style="font-size:10px; color:#999; margin-top:30px;">{{ $contract->status->label() }}</p>
</body>
</html>
