<div style="width: 100%; box-sizing: border-box; padding: 0 20mm 12mm 20mm; text-align: center; font-family: 'Geist', 'Liberation Sans', sans-serif; font-size: 12px; line-height: 16px; color: #111C18;">
    @if ($show)
        @foreach ($lines as $line)
            <div>{{ $line }}</div>
        @endforeach
    @endif
    <div style="color: #5A6661;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>
</div>
