<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cenová ponuka {{ $quote->number ?? 'Koncept' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 0; }
        .page { padding: 40px 48px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 2px solid #333; padding-bottom: 16px; }
        .header h1 { font-size: 22px; margin: 0 0 4px; }
        .header .ref { color: #555; font-size: 11px; }
        .parties { display: flex; gap: 32px; margin-bottom: 24px; }
        .party { flex: 1; }
        .party h3 { font-size: 11px; text-transform: uppercase; color: #555; margin: 0 0 6px; letter-spacing: 0.5px; }
        .object-section { margin-bottom: 20px; font-size: 11px; color: #555; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
        .items-table th { background: #f5f5f5; text-align: left; padding: 6px 8px; border: 1px solid #ddd; font-size: 10px; text-transform: uppercase; color: #555; }
        .items-table td { padding: 6px 8px; border: 1px solid #eee; vertical-align: top; }
        .items-table tr:nth-child(even) td { background: #fafafa; }
        .items-table .num { text-align: right; }
        .vat-recap { margin-bottom: 16px; }
        .vat-recap table { width: auto; min-width: 320px; margin-left: auto; border-collapse: collapse; font-size: 11px; }
        .vat-recap th { background: #f5f5f5; padding: 4px 10px; border: 1px solid #ddd; text-align: right; font-size: 10px; text-transform: uppercase; color: #555; }
        .vat-recap td { padding: 4px 10px; border: 1px solid #eee; text-align: right; }
        .totals-block { text-align: right; margin-bottom: 20px; }
        .totals-block table { width: auto; min-width: 240px; margin-left: auto; border-collapse: collapse; font-size: 12px; }
        .totals-block td { padding: 4px 10px; }
        .totals-block .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .note-section { font-size: 11px; color: #555; margin-top: 16px; }
        .footer { margin-top: 32px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 10px; color: #777; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div>
            <h1>Cenová ponuka</h1>
            @if($quote->number)
                <div class="ref">Číslo: <strong>{{ $quote->number }}</strong></div>
            @else
                <div class="ref">Koncept</div>
            @endif
            @if($quote->subject)
                <div class="ref" style="margin-top:4px;">{{ $quote->subject }}</div>
            @endif
        </div>
        <div style="text-align:right; font-size: 11px; color: #555;">
            <div>Dátum: {{ $quote->issue_date->format('d.m.Y') }}</div>
            <div>Platná do: <strong>{{ $quote->valid_until->format('d.m.Y') }}</strong></div>
            <div style="margin-top:4px;">{{ $quote->status->label() }}</div>
        </div>
    </div>

    {{-- Supplier + Customer --}}
    <div class="parties">
        <div class="party">
            <h3>Dodávateľ</h3>
            <strong>{{ $tenant->name }}</strong>
            @if($tenant->ico)
                <div>IČO: {{ $tenant->ico }}</div>
            @endif
            @if($tenant->dic)
                <div>DIČ: {{ $tenant->dic }}</div>
            @endif
            @if($tenant->is_vat_payer && $tenant->vat_number)
                <div>IČ DPH: {{ $tenant->vat_number }}</div>
            @endif
            @if($tenant->address_line)
                <div>{{ $tenant->address_line }}</div>
            @endif
            @if($tenant->city)
                <div>{{ $tenant->postal_code ? $tenant->postal_code . ' ' : '' }}{{ $tenant->city }}</div>
            @endif
        </div>
        <div class="party">
            <h3>Zákazník</h3>
            @if($quote->client)
                <strong>{{ $quote->client->name }}</strong>
                @if($quote->client->ico)
                    <div>IČO: {{ $quote->client->ico }}</div>
                @endif
                @if($quote->client->dic)
                    <div>DIČ: {{ $quote->client->dic }}</div>
                @endif
                @if($quote->client->vat_number)
                    <div>IČ DPH: {{ $quote->client->vat_number }}</div>
                @endif
                @if($quote->client->street)
                    <div>{{ $quote->client->street }}</div>
                @endif
                @if($quote->client->city)
                    <div>{{ $quote->client->postal_code ? $quote->client->postal_code . ' ' : '' }}{{ $quote->client->city }}</div>
                @endif
            @endif
        </div>
    </div>

    {{-- Object --}}
    @if($quote->cleaningObject)
        <div class="object-section">
            <strong>Objekt:</strong> {{ $quote->cleaningObject->name }}
            @if($quote->cleaningObject->street || $quote->cleaningObject->city)
                — {{ $quote->cleaningObject->street }}{{ $quote->cleaningObject->street && $quote->cleaningObject->city ? ', ' : '' }}{{ $quote->cleaningObject->city }}
            @endif
        </div>
    @endif

    {{-- Items table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:28%;">Názov</th>
                <th style="width:18%;">Popis / frekvencia</th>
                <th class="num" style="width:8%;">Množstvo</th>
                <th style="width:8%;">Jedn.</th>
                <th class="num" style="width:12%;">Cena/jedn.</th>
                @if($quote->is_vat_payer)
                    <th class="num" style="width:8%;">Zľava</th>
                    <th class="num" style="width:8%;">DPH %</th>
                @else
                    <th class="num" style="width:8%;">Zľava</th>
                @endif
                <th class="num" style="width:10%;">Spolu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>
                        @if($item->description){{ $item->description }}@endif
                        @if($item->description && $item->frequency) <br> @endif
                        @if($item->frequency)<em>{{ $item->frequency }}</em>@endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float)$item->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $item->unit ?? '' }}</td>
                    <td class="num">{{ number_format((float)$item->unit_price, 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                    @if($quote->is_vat_payer)
                        <td class="num">{{ $item->discount_percent > 0 ? number_format((float)$item->discount_percent, 2, '.', '') . ' %' : '—' }}</td>
                        <td class="num">{{ number_format((float)$item->vat_rate, 0) }} %</td>
                    @else
                        <td class="num">{{ $item->discount_percent > 0 ? number_format((float)$item->discount_percent, 2, '.', '') . ' %' : '—' }}</td>
                    @endif
                    <td class="num">{{ number_format((float)$item->line_total, 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- VAT recap --}}
    @if($quote->is_vat_payer && !empty($quote->vat_breakdown))
        <div class="vat-recap">
            <table>
                <thead>
                    <tr>
                        <th>Sadzba DPH</th>
                        <th>Základ dane</th>
                        <th>DPH</th>
                        <th>Spolu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quote->vat_breakdown as $line)
                        <tr>
                            <td>{{ number_format((float)$line['rate'], 0) }} %</td>
                            <td>{{ number_format((float)$line['base'], 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                            <td>{{ number_format((float)$line['vat'], 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                            <td>{{ number_format((float)$line['total'], 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(!$quote->is_vat_payer)
        <p style="font-size:10px; color:#777; margin-bottom:12px;">Dodávateľ nie je platiteľom DPH.</p>
    @endif

    {{-- Totals --}}
    <div class="totals-block">
        <table>
            @if($quote->is_vat_payer)
                <tr>
                    <td>Základ dane:</td>
                    <td>{{ number_format((float)$quote->subtotal, 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                </tr>
                <tr>
                    <td>DPH celkom:</td>
                    <td>{{ number_format((float)$quote->vat_amount, 2, '.', ' ') }} {{ $quote->currency->value }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Celkom:</td>
                <td>{{ number_format((float)$quote->total, 2, '.', ' ') }} {{ $quote->currency->value }}</td>
            </tr>
        </table>
    </div>

    {{-- Note --}}
    @if($quote->note)
        <div class="note-section">
            <strong>Poznámka:</strong> {{ $quote->note }}
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <span>
            Cenová ponuka
            @if($quote->number) č. {{ $quote->number }} @endif
            · Platná do {{ $quote->valid_until->format('d.m.Y') }}
        </span>
        <span>{{ $quote->status->label() }}</span>
    </div>

</div>
</body>
</html>
