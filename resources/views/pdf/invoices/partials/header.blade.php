{{-- Invoice header: supplier + customer blocks --}}
<table style="width:100%; margin-bottom:20px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:20px;">
            <strong style="font-size:14px;">{{ $invoice->supplier_name }}</strong><br>
            @if($invoice->supplier_address_line)
                {{ $invoice->supplier_address_line }}<br>
            @endif
            @if($invoice->supplier_city || $invoice->supplier_postal_code)
                {{ implode(' ', array_filter([$invoice->supplier_postal_code, $invoice->supplier_city])) }}<br>
            @endif
            @if($invoice->supplier_country)
                {{ $invoice->supplier_country }}<br>
            @endif
            @if($invoice->supplier_ico)
                {{ __('app.invoices.pdf.ico') }}: {{ $invoice->supplier_ico }}<br>
            @endif
            @if($invoice->supplier_dic)
                {{ __('app.invoices.pdf.dic') }}: {{ $invoice->supplier_dic }}<br>
            @endif
            @if($invoice->supplier_vat_number)
                {{ __('app.invoices.pdf.vat_number') }}: {{ $invoice->supplier_vat_number }}<br>
            @endif
        </td>
        <td style="width:50%; vertical-align:top;">
            <strong>{{ __('app.invoices.pdf.customer') }}</strong><br>
            <strong>{{ $invoice->customer_name }}</strong><br>
            @if($invoice->customer_street)
                {{ $invoice->customer_street }}<br>
            @endif
            @if($invoice->customer_city || $invoice->customer_postal_code)
                {{ implode(' ', array_filter([$invoice->customer_postal_code, $invoice->customer_city])) }}<br>
            @endif
            @if($invoice->customer_country)
                {{ $invoice->customer_country }}<br>
            @endif
            @if($invoice->customer_ico)
                {{ __('app.invoices.pdf.ico') }}: {{ $invoice->customer_ico }}<br>
            @endif
            @if($invoice->customer_dic)
                {{ __('app.invoices.pdf.dic') }}: {{ $invoice->customer_dic }}<br>
            @endif
            @if($invoice->customer_vat_number)
                {{ __('app.invoices.pdf.vat_number') }}: {{ $invoice->customer_vat_number }}<br>
            @endif
        </td>
    </tr>
</table>
