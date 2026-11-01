<!-- Chrome footer template: isolated from page CSS, everything must be inline. Chrome defaults font-size to 0. -->
<div style="width:100%; box-sizing:border-box; padding:0 18mm; font-family:'Geist','Liberation Sans',sans-serif; font-size:8px; line-height:11px; color:#5A6661;">
    <div style="border-top:1px solid #E9ECEA; padding-top:6px;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top; font-size:8px; color:#5A6661;">
                    <span style="font-weight:600; color:#111C18;">{{ $entity['name'] }}</span>
                    @if($entity['address'])· {{ $entity['address'] }}@endif
                    @if($entity['ico'])· {{ __('app.invoice_pdf_ico') }}: {{ $entity['ico'] }}@endif
                    @if($entity['dic'])· {{ __('app.invoice_pdf_dic') }}: {{ $entity['dic'] }}@endif
                    @if($entity['registration'])<br>{{ $entity['registration'] }}@endif
                    @if($entity['phone'] || $entity['email'])
                    <br>{{ implode(' · ', array_filter([$entity['phone'] ? format_phone($entity['phone']) : null, $entity['email']])) }}
                    @endif
                    @if(! empty($issuedBy))
                    <br><strong>{{ __('app.invoice_pdf_issued_by') }}:</strong> {{ $issuedBy }}
                    @endif
                    @if(config('documents.show_brand_line'))
                    <br>{{ __('app.pdf_under_brand') }}
                    @endif
                </td>
                <td style="vertical-align:top; text-align:right; white-space:nowrap; font-size:8px; color:#5A6661; padding-left:12px;">
                    @if($preview ?? false)
                    {{ __('app.invoice_pdf_page') }} 1/1
                    @else
                    {{ __('app.invoice_pdf_page') }} <span class="pageNumber"></span>/<span class="totalPages"></span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>
