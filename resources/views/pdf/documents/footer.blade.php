<div style="width: 100%; box-sizing: border-box; padding: 0 20mm 12mm 20mm; text-align: center; font-family: 'Liberation Sans', Arial, sans-serif; font-size: 12px; line-height: 16px; color: #1a1a1a;">
    @if ($show)
        @foreach ($lines as $line)
            <div>{{ $line }}</div>
        @endforeach
    @endif
    <div style="color: #666666;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>
</div>
