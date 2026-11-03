<div style="width:100%; box-sizing:border-box; padding:0 18mm; font-family:'Geist','Liberation Sans',sans-serif; font-size:10px; line-height:13px; color:#8A938F;">
    <div style="border-top:1px solid #E2E6E4; padding-top:8px;">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top; font-size:10px; color:#8A938F;">{{ implode(' · ', array_filter([$tenant->name, implode(', ', array_filter([$tenant->address_line, implode(' ', array_filter([$tenant->postal_code, $tenant->city]))]))])) }}</td>
                <td style="vertical-align:top; text-align:right; white-space:nowrap; font-size:10px; color:#8A938F; padding-left:12px;">{{ implode(' · ', array_filter([$tenant->ico ? __('app.quote_pdf_ico').' '.$tenant->ico : null, $tenant->dic ? __('app.quote_pdf_dic').' '.$tenant->dic : null])) }}</td>
            </tr>
        </table>
    </div>
</div>
