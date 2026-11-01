<div style="width: 100%; box-sizing: border-box; padding: 15mm 20mm 0 20mm; text-align: center; font-family: 'Geist', 'Liberation Sans', sans-serif; font-size: 14px; line-height: 18px; color: #111C18;">
    @if ($show)
        <div style="font-weight: bold;">{{ $letterhead->name }}</div>
        @if ($letterhead->address)<div>{{ $letterhead->address }}</div>@endif
        @if ($letterhead->ico)<div>IČO: {{ $letterhead->ico }}</div>@endif
    @endif
</div>
