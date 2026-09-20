<!-- Chrome footer template: isolated from page CSS, everything must be inline. Chrome defaults font-size to 0. -->
<table style="width:100%; font-family:'Open Sans','DejaVu Sans',Arial,sans-serif; font-size:8px; color:#555; padding:0 15mm;">
    <tr>
        <td style="width:34%; text-align:left; font-size:8px;">
            @if($invoice->issued_by_name)
            <strong>{{ __('app.invoice_pdf_issued_by') }}:</strong> {{ $invoice->issued_by_name }}
            @endif
        </td>
        <td style="width:33%; text-align:center; font-size:8px;">
            @if($invoice->supplier_contact_phone)
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px;margin-right:3px"><path d="M7 2a1 1 0 0 0-1 1v18a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H7zm5 18a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>{{ $invoice->supplier_contact_phone }}
            @endif
        </td>
        <td style="width:33%; text-align:right; font-size:8px;">
            @if($invoice->supplier_contact_email)
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px;margin-right:3px"><path d="M2 4h20v16H2V4zm2 2v.01L12 12l8-5.99V6H4zm16 2.24l-7.4 5.55a1 1 0 0 1-1.2 0L4 8.24V18h16V8.24z"/></svg>{{ $invoice->supplier_contact_email }}
            @endif
        </td>
    </tr>
    <tr>
        <td style="width:34%; border-top:1px dotted #9a9a9a; padding-top:3px; font-size:8px;"></td>
        <td style="width:33%; border-top:1px dotted #9a9a9a; padding-top:3px; font-size:8px;"></td>
        <td style="width:33%; text-align:right; border-top:1px dotted #9a9a9a; padding-top:3px; font-size:8px;">
            @if($preview ?? false)
            {{ __('app.invoice_pdf_page') }} 1/1
            @else
            {{ __('app.invoice_pdf_page') }} <span class="pageNumber"></span>/<span class="totalPages"></span>
            @endif
        </td>
    </tr>
</table>
