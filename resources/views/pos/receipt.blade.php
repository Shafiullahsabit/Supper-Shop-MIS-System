<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $sale->invoice_no }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Noto Sans Arabic", "SF Mono", "Consolas", "Menlo", monospace; background: #eef2f8; color: #16233d; padding: 16px; }
        .receipt { width: 300px; margin: 0 auto; background: #fff; padding: 20px 22px; box-shadow: 0 6px 24px rgba(0,0,0,.1); }
        .center { text-align: center; }
        .shop { font-size: 19px; font-weight: 800; letter-spacing: .04em; }
        .muted { color: #64748b; font-size: 11px; }
        .divider { border: none; border-top: 1px dashed #cbd5e1; margin: 12px 0; }
        .row { display: flex; justify-content: space-between; font-size: 12px; margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: start; font-size: 10px; text-transform: uppercase; color: #64748b; padding-bottom: 5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 4px 0; vertical-align: top; }
        .qty { color: #64748b; font-size: 11px; }
        .r { text-align: end; direction: ltr; }
        .total-row { font-size: 16px; font-weight: 800; }
        .badge-paid { display:inline-block; border:1px solid #16a86b; color:#16a86b; padding:2px 10px; border-radius:20px; font-size:10px; font-weight:700; letter-spacing:.05em; }
        .foot { text-align: center; font-size: 11px; color: #475569; margin-top: 14px; line-height: 1.6; }
        .actions { width: 300px; margin: 16px auto 0; display: flex; gap: 8px; }
        .actions button { flex: 1; padding: 11px; border: none; border-radius: 9px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: system-ui, sans-serif; }
        .print-btn { background: #2258c9; color: #fff; }
        .close-btn { background: #eef2f8; color: #16233d; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; width: 100%; padding: 4px; }
            .actions { display: none; }
            @page { margin: 4mm; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            <div class="shop">{{ setting('shop_name', 'SuperShop') }}</div>
            @if(setting('shop_address'))<div class="muted">{{ setting('shop_address') }}</div>@endif
            @if(setting('shop_phone'))<div class="muted">Tel: {{ setting('shop_phone') }}</div>@endif
        </div>
        <hr class="divider">
        <div class="row"><span>{{ __('Invoice') }}</span><span><b>{{ $sale->invoice_no }}</b></span></div>
        <div class="row"><span>{{ __('Date') }}</span><span>{{ $sale->created_at->translatedFormat('d M Y, h:i A') }}</span></div>
        <div class="row"><span>{{ __('Cashier') }}</span><span>{{ $sale->cashier->name ?? '—' }}</span></div>
        <div class="row"><span>{{ __('Customer') }}</span><span>{{ $sale->customer->name ?? __('Walk-in') }}</span></div>
        <hr class="divider">
        <table>
            <thead><tr><th>{{ __('Item') }}</th><th class="r">{{ __('Qty') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
            <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->name }}<div class="qty">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }} × {{ money($item->price) }}</div></td>
                    <td class="r">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }}</td>
                    <td class="r">{{ money($item->subtotal) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <hr class="divider">
        <div class="row"><span>{{ __('Subtotal') }}</span><span>{{ money($sale->subtotal) }}</span></div>
        @if($sale->discount > 0)<div class="row"><span>{{ __('Discount') }}</span><span>− {{ money($sale->discount) }}</span></div>@endif
        @if($sale->tax > 0)<div class="row"><span>{{ __('Tax') }}</span><span>{{ money($sale->tax) }}</span></div>@endif
        <hr class="divider">
        <div class="row total-row"><span>{{ __('TOTAL') }}</span><span>{{ money($sale->total) }}</span></div>
        <div class="row" style="margin-top:8px"><span>{{ __('Paid') }} ({{ __(ucfirst($sale->payment_method)) }})</span><span>{{ money($sale->paid) }}</span></div>
        @if($sale->change_due > 0)<div class="row"><span>{{ __('Change') }}</span><span>{{ money($sale->change_due) }}</span></div>@endif
        <div class="center" style="margin-top:12px"><span class="badge-paid">● {{ __('PAID') }}</span></div>
        <div class="foot">
            {{ setting('receipt_footer', __('Thank you for shopping with us!')) }}<br>
            <span class="muted">{{ __('Items') }}: {{ $sale->items->count() }} · {{ $sale->invoice_no }}</span>
        </div>
    </div>
    <div class="actions">
        <button class="print-btn" onclick="window.print()">{{ __('Print Receipt') }}</button>
        <button class="close-btn" onclick="window.close()">{{ __('Close') }}</button>
    </div>
    <script>
        // Auto-print when opened in a popup window (POS flow)
        if (window.opener) { window.addEventListener('load', () => setTimeout(() => window.print(), 350)); }
    </script>
</body>
</html>
