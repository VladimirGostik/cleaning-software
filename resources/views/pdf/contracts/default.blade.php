<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $contract->title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 0; }
        .page { padding: 40px 48px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 2px solid #333; padding-bottom: 16px; }
        .header h1 { font-size: 22px; margin: 0 0 4px; }
        .header .ref { color: #555; font-size: 11px; }
        .parties { display: flex; gap: 32px; margin-bottom: 24px; }
        .party { flex: 1; }
        .party h3 { font-size: 12px; text-transform: uppercase; color: #555; margin: 0 0 6px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .meta-table td { padding: 4px 8px; border: 1px solid #ddd; }
        .meta-table td:first-child { font-weight: bold; width: 35%; background: #f5f5f5; }
        .body-section { margin-bottom: 24px; }
        .body-section h3 { font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; }
        .body-text { line-height: 1.7; white-space: pre-wrap; }
        .employment-section { margin-top: 24px; padding: 16px; background: #f9f9f9; border: 1px solid #ddd; }
        .signature-block { margin-top: 48px; display: flex; gap: 48px; }
        .signature { flex: 1; border-top: 1px solid #333; padding-top: 8px; font-size: 11px; color: #555; }
        .footer { margin-top: 32px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 10px; color: #777; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <h1>{{ $contract->title }}</h1>
            @if($contract->reference_number)
                <div class="ref">Ref: {{ $contract->reference_number }}</div>
            @endif
            <div class="ref">{{ $contract->category->label() }} · {{ $contract->term_type->label() }}</div>
        </div>
        <div style="text-align:right; font-size: 11px; color: #555;">
            <div>Platnosť od: {{ $contract->valid_from->format('d.m.Y') }}</div>
            @if($contract->end_date)
                <div>Platnosť do: {{ $contract->end_date->format('d.m.Y') }}</div>
            @endif
            @if($contract->signed_at)
                <div>Podpísaná: {{ $contract->signed_at->format('d.m.Y') }}</div>
            @endif
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h3>Dodávateľ</h3>
            <strong>{{ $tenant->name }}</strong>
        </div>
        <div class="party">
            <h3>Zmluvná strana</h3>
            @if($contract->contractable_type === 'cleaning_object')
                <strong>{{ $contract->contractable->name ?? '' }}</strong>
                <div>{{ $contract->contractable->street ?? '' }}, {{ $contract->contractable->city ?? '' }}</div>
            @elseif($contract->contractable_type === 'tenant_membership')
                <strong>{{ $contract->contractable->user->name ?? '' }}</strong>
                <div>{{ $contract->contractable->user->email ?? '' }}</div>
            @endif
        </div>
    </div>

    <div class="body-section">
        <h3>Obsah zmluvy</h3>
        <div class="body-text">{!! nl2br(e($contract->body)) !!}</div>
    </div>

    @if($contract->employmentContract)
        <div class="employment-section">
            <strong>Pracovné podmienky</strong><br>
            Typ: {{ $contract->employmentContract->employment_type->label() }}
            @if($contract->employmentContract->position)
                | Pozícia: {{ $contract->employmentContract->position }}
            @endif
            @if($contract->employmentContract->monthly_salary)
                | Mesačná mzda: {{ $contract->employmentContract->monthly_salary }} €
            @endif
            @if($contract->employmentContract->hourly_rate)
                | Hodinová sadzba: {{ $contract->employmentContract->hourly_rate }} €
            @endif
            @if($contract->employmentContract->weekly_hours)
                | Týždenné hodiny: {{ $contract->employmentContract->weekly_hours }}
            @endif
            @if($contract->employmentContract->probation_end_date)
                | Skúšobná doba do: {{ $contract->employmentContract->probation_end_date->format('d.m.Y') }}
            @endif
        </div>
    @endif

    @if($contract->terminated_at)
        <div style="margin-top: 16px; padding: 10px; background: #fff3f3; border: 1px solid #fcc; font-size: 11px;">
            Zmluva ukončená: {{ $contract->terminated_at->format('d.m.Y') }}
            @if($contract->termination_reason) — {{ $contract->termination_reason }} @endif
        </div>
    @endif

    <div class="signature-block">
        <div class="signature">Dodávateľ</div>
        <div class="signature">Zmluvná strana</div>
    </div>

    <div class="footer">
        <span>
            @if($contract->reference_number) Ref: {{ $contract->reference_number }} · @endif
            Platnosť od: {{ $contract->valid_from->format('d.m.Y') }}
            @if($contract->end_date) do {{ $contract->end_date->format('d.m.Y') }} @endif
        </span>
        <span>{{ $contract->status->label() }}</span>
    </div>
</div>
</body>
</html>
