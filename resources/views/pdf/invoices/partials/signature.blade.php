{{-- Supplier signature + stamp image, frozen at issue (or live for drafts/previews) --}}
@if(($signatureDataUri ?? null))
<div class="signature-block">
    <div class="signature-label" style="font-size:9px; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">{{ __('app.invoice_pdf_signature') }}</div>
    <img src="{{ $signatureDataUri }}" alt="" style="max-height:70px; max-width:220px;" />
</div>
@endif
