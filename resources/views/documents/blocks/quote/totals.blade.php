@if ($included['lines'] !== [] || $groups !== [])
<table style="width: 100%; border-collapse: collapse; margin: 0 0 {{ $clause === null || $groups !== [] ? '3.5mm' : '0' }} 0; page-break-inside: avoid;"><tbody><tr>
<td style="width: 50%; vertical-align: top; padding: 2mm 6mm 0 0;">@if ($included['lines'] !== [])<p style="margin: 0 0 2mm 0; font-size: 8pt; letter-spacing: 0.22em; font-weight: 600; color: #FF5A1F; text-transform: uppercase;">{{ $included['label'] }}</p>@foreach ($included['lines'] as $line)<p style="margin: 0 0 1mm 0; font-size: 10.5pt;"><span style="font-size: 7pt; color: #FF5A1F;">●</span>&nbsp;&nbsp;{{ $line }}</p>@endforeach @endif</td>
<td style="width: 50%; vertical-align: top; padding: 0;">@if ($groups !== [])<table style="width: 100%; border-collapse: collapse;"><tbody>
@foreach ($groups as $group)
@if ($group['net'] !== null)<tr><td style="padding: 0.5mm 0; font-size: 9pt; color: #5A6661;">{{ $labels['excl_vat'] }}</td><td style="padding: 0.5mm 0; text-align: right; font-size: 9pt; color: #5A6661; white-space: nowrap; font-variant-numeric: tabular-nums;">{{ $group['net'] }} &nbsp;+&nbsp; {{ $labels['vat'] }} {{ $group['vat'] }}</td></tr>@endif
<tr><td style="padding: {{ $loop->first ? '1.2mm' : '3mm' }} 0 {{ $loop->last ? '1.2mm' : '2mm' }} 0; font-size: 11pt; font-weight: 600;{{ $loop->last ? '' : ' border-bottom: 1px solid #111C18;' }}">{{ $group['label'] }}</td><td style="padding: {{ $loop->first ? '1.2mm' : '3mm' }} 0 {{ $loop->last ? '1.2mm' : '2mm' }} 0; text-align: right; font-size: 11pt; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums;{{ $loop->last ? '' : ' border-bottom: 1px solid #111C18;' }}">{{ $group['total'] }}@if ($group['suffix']) <span style="font-size: 10pt; font-weight: 400; color: #5A6661;">/ {{ $group['suffix'] }}</span>@endif</td></tr>
@endforeach
</tbody></table>@if ($clause !== null)<p style="margin: 1.5mm 0 0 0; font-size: 8.5pt; color: #5A6661; text-align: right;">{{ $clause }}</p>@endif @endif</td>
</tr></tbody></table>
@endif
@if ($clause !== null && $groups === [])<p style="margin: 1.5mm 0 4mm 0; font-size: 8.5pt; color: #5A6661; text-align: left;">{{ $clause }}</p>@endif
