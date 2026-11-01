<table style="width: 100%; border-collapse: collapse;"><tbody><tr>
@foreach ($parties as $party)
<td style="width: 50%; vertical-align: top; padding-right: 16px;"><p><strong>{{ $party['heading'] }}</strong></p>@foreach ($party['lines'] as $line)<p>@if ($line['bold'])<strong>{{ $line['text'] }}</strong>@else{{ $line['text'] }}@endif</p>@endforeach</td>
@endforeach
</tr></tbody></table>
