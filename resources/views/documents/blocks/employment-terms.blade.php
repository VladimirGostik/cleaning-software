<table style="width: 100%; border-collapse: collapse;"><tbody>
@foreach ($rows as $row)
<tr><td style="width: 40%; padding-top: 4px; padding-bottom: 4px;"><p>{{ $row['label'] }}</p></td><td style="padding-top: 4px; padding-bottom: 4px;"><p>{{ $row['value'] }}</p></td></tr>
@endforeach
</tbody></table>
