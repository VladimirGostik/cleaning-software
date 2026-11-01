<table style="width: 100%; border-collapse: collapse;"><tbody>
@foreach ($rows as $row)
<tr><td style="text-align: right; padding-top: 2px; padding-bottom: 2px;">@if ($row['bold'])<p><strong>{{ $row['label'] }}</strong></p>@else<p>{{ $row['label'] }}</p>@endif</td><td style="width: 25%; text-align: right; padding-top: 2px; padding-bottom: 2px;">@if ($row['bold'])<p><strong>{{ $row['value'] }}</strong></p>@else<p>{{ $row['value'] }}</p>@endif</td></tr>
@endforeach
</tbody></table>
