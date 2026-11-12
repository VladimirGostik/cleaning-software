<table style="width: 100%; border-collapse: collapse; margin: 3mm 0 0 0;"><tbody><tr>
@foreach ([$provider, $buyer] as $party)
<td style="width: 50%; vertical-align: top; padding: 0 {{ $loop->first ? '6mm' : '0' }} 0 0; line-height: 1.4; color: #5A6661;"><p style="margin: 0 0 2.5mm 0; font-size: 8pt; letter-spacing: 0.22em; font-weight: 600; color: #FF5A1F; text-transform: uppercase;">{{ $party['label'] }}</p>@if (filled($party['name']))<p style="margin: 0 0 0.5mm 0; font-size: 11.5pt; font-weight: 600; color: #111C18;">{{ $party['name'] }}</p>@endif @foreach ($party['lines'] as $line)<p style="margin: 0;">{{ $line }}</p>@endforeach</td>
@endforeach
</tr></tbody></table><p style="margin: 2.5mm 0 0 0; font-size: 9pt; color: #5A6661;">@foreach ($dates as $date){{ $date['label'] }}: <strong style="font-weight: 500; color: #111C18;">{{ $date['value'] }}</strong>@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach</p>
