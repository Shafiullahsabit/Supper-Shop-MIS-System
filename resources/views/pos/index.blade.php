<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d4ed8">
    <title>{{ __('POS Terminal') }} · {{ setting('shop_name', 'SuperShop') }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}">
</head>
<body class="pos-body">
@php
    $products = $products->map(fn($p) => [
        'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode,
        'price' => (float)$p->sale_price, 'tax_rate' => (float)$p->tax_rate,
        'stock' => (float)$p->stock_quantity, 'unit' => $p->unit->short_name ?? 'pcs',
        'allow_decimal' => (bool)($p->unit->allow_decimal ?? false),
        'category_id' => $p->category_id, 'color' => $p->category->color ?? '#2563eb',
    ]);
@endphp

<div class="pos-shell" id="pos"
     data-currency="{{ setting('currency_symbol','$') }}"
     data-currency-pos="{{ setting('currency_position','before') }}"
     data-has-session="{{ $session ? 1 : 0 }}">

    <!-- Top bar -->
    <div class="pos-top">
        <div class="brand-mark">S</div>
        <div class="title">{{ setting('shop_name','SuperShop') }} <small>{{ __('POS Terminal') }} · {{ auth()->user()->name }}</small></div>
        @if($session)
            <span class="sess badge badge-green" style="background:rgba(255,255,255,.16);color:#eafff4"><span class="dot"></span> {{ __('Register open') }}</span>
        @else
            <span class="sess badge badge-amber" style="background:rgba(255,255,255,.16);color:#fff2d9">{{ __('Register closed') }}</span>
        @endif
        <div class="pos-top-right">
            <div class="lang-switch" style="border-color:rgba(255,255,255,.25);background:rgba(255,255,255,.12)">
                <a href="{{ route('locale.switch','en') }}" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}" style="color:{{ app()->getLocale() === 'en' ? '#fff' : '#cdddff' }}">English</a>
                <a href="{{ route('locale.switch','prs') }}" class="{{ app()->getLocale() === 'prs' ? 'on' : '' }}" style="color:{{ app()->getLocale() === 'prs' ? '#fff' : '#cdddff' }}">دری</a>
                <a href="{{ route('locale.switch','ps') }}" class="{{ app()->getLocale() === 'ps' ? 'on' : '' }}" style="color:{{ app()->getLocale() === 'ps' ? '#fff' : '#cdddff' }}">پښتو</a>
            </div>
            <span class="net-pill online" id="netPill" title="{{ __('Connection status') }}">
                <span class="dot"></span><span id="netLabel">{{ __('Online') }}</span>
            </span>
            <span class="sync-badge" id="syncBadge" style="display:none" title="{{ __('Sales waiting to sync') }}">
                @include('partials.icon',['name'=>'inbox']) <span id="syncCount">0</span>
            </span>
            <span class="pos-clock" id="clock">--:--:--</span>
            <a class="pos-exit" href="{{ route('dashboard') }}">@include('partials.icon',['name'=>'arrow-left']) {{ __('Exit') }}</a>
        </div>
    </div>

    <!-- Left: catalog -->
    <div class="pos-left">
        @unless($session)
            <div class="alert alert-info" style="margin-bottom:14px">
                @include('partials.icon',['name'=>'info'])
                <div>{{ __("Your cash register is closed. You can still sell, but cash totals won't be tracked.") }}
                    <a href="{{ route('register.index') }}" class="fw-700">{{ __('Open register') }} →</a></div>
            </div>
        @endunless

        <div class="scan-row">
            <div class="scan-box">
                @include('partials.icon',['name'=>'search'])
                <input type="text" id="scan" autocomplete="off" placeholder="{{ __('Scan barcode or search product name / SKU…') }}" autofocus>
            </div>
        </div>
        <div class="scan-hint">{{ __('Tip: connect a USB/Bluetooth barcode scanner — it types the code and presses Enter automatically.') }}</div>

        <div class="cat-tabs" id="catTabs" style="margin-top:12px">
            <button class="cat-tab active" data-cat="all">{{ __('All Items') }}</button>
            @foreach($categories as $c)
                <button class="cat-tab" data-cat="{{ $c->id }}" style="--c:{{ $c->color }}">{{ $c->name }}</button>
            @endforeach
        </div>

        <div class="product-grid" id="grid"></div>
    </div>

    <!-- Right: cart -->
    <div class="pos-right" id="cartPane">
        <div class="cart-head">
            @include('partials.icon',['name'=>'cart'])
            <h3>{{ __('Current Sale') }}</h3>
            <span id="itemCount" class="badge badge-blue" style="margin-inline-start:auto">0 {{ __('items') }}</span>
        </div>

        <div class="cust-select">
            <select class="select" id="customer">
                <option value="">{{ __('Walk-in Customer') }}</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} @if($c->phone)· {{ $c->phone }}@endif</option>
                @endforeach
            </select>
            <button class="icon-btn" id="addCustomerBtn" title="{{ __('New customer') }}">@include('partials.icon',['name'=>'plus'])</button>
        </div>

        <div class="cart-items" id="cartItems"></div>

        <div class="cart-foot">
            <div class="tot-row"><span>{{ __('Subtotal') }}</span><span class="mono" id="sumSubtotal">—</span></div>
            <div class="tot-row"><span>{{ __('Tax') }}</span><span class="mono" id="sumTax">—</span></div>
            <div class="tot-row">
                <span>{{ __('Discount') }}</span>
                <span><input type="number" min="0" step="0.01" value="0" class="disc-input mono" id="orderDiscount"></span>
            </div>
            <div class="tot-row grand"><span>{{ __('Total') }}</span><span class="amt mono" id="sumTotal">—</span></div>

            <div class="pay-methods" id="payMethods">
                <div class="pay-opt active" data-pm="cash">@include('partials.icon',['name'=>'money']) {{ __('Cash') }}</div>
                <div class="pay-opt" data-pm="card">@include('partials.icon',['name'=>'card']) {{ __('Card') }}</div>
                <div class="pay-opt" data-pm="mobile">@include('partials.icon',['name'=>'phone']) {{ __('Mobile') }}</div>
            </div>

            <div class="cart-actions">
                <button class="btn btn-light" id="holdBtn">@include('partials.icon',['name'=>'pause']) {{ __('Hold') }}</button>
                <button class="btn btn-light" id="clearBtn">@include('partials.icon',['name'=>'trash']) {{ __('Clear') }}</button>
            </div>
            @if($held->count())
                <button class="btn btn-soft btn-block" id="resumeBtn" style="margin-bottom:10px">
                    @include('partials.icon',['name'=>'inbox']) {{ __('Resume held') }} ({{ $held->count() }})
                </button>
            @endif
            <button class="pay-btn" id="payBtn" disabled>@include('partials.icon',['name'=>'check-circle']) <span id="payLabel">{{ __('Charge') }}</span></button>
        </div>
    </div>
</div>

<!-- Payment modal -->
<div class="modal-overlay" id="payModal" style="display:none">
    <div class="modal">
        <div class="modal-head"><h3>{{ __('Take Payment') }}</h3>
            <button class="icon-btn" style="margin-inline-start:auto" onclick="POS.closePay()">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="pay-summary">
                <div class="due-label">{{ __('Amount due') }}</div>
                <div class="due-amt mono" id="payDue">—</div>
            </div>
            <div id="cashPane">
                <label class="field" style="margin:0"><span style="font-size:12.5px;font-weight:650;color:var(--ink-soft)">{{ __('Cash received') }}</span>
                    <input type="number" class="tender-input mono" id="tender" min="0" step="0.01" style="margin-top:8px"></label>
                <div class="quick-cash" id="quickCash"></div>
                <div class="change-box" id="changeBox" style="display:none">
                    <div class="lbl">{{ __('Change due') }}</div>
                    <div class="amt mono" id="changeAmt">—</div>
                </div>
                <label class="drawer-toggle switch">
                    <input type="checkbox" id="openDrawer" checked>
                    <span class="track"></span>
                    <span style="font-weight:650;font-size:13.5px;color:var(--ink-soft)">{{ __('Open cash drawer after sale') }}</span>
                </label>
            </div>
            <div id="nonCashPane" style="display:none">
                <div class="alert alert-info" style="margin:0">@include('partials.icon',['name'=>'info']) <span id="nonCashText"></span></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-light" onclick="POS.closePay()">{{ __('Cancel') }}</button>
            <button class="btn btn-success" id="confirmPay" style="min-width:150px">@include('partials.icon',['name'=>'check']) {{ __('Complete Sale') }}</button>
        </div>
    </div>
</div>

<!-- Quick customer modal -->
<div class="modal-overlay" id="custModal" style="display:none">
    <div class="modal" style="max-width:440px">
        <div class="modal-head"><h3>{{ __('New Customer') }}</h3>
            <button class="icon-btn" style="margin-inline-start:auto" onclick="POS.hide('custModal')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field"><label>{{ __('Name') }}</label><input class="input" id="ncName" placeholder="{{ __('Customer name') }}"></div>
            <div class="field" style="margin:0"><label>{{ __('Phone') }}</label><input class="input" id="ncPhone" placeholder="{{ __('Phone number') }}"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-light" onclick="POS.hide('custModal')">{{ __('Cancel') }}</button>
            <button class="btn btn-primary" id="saveCustomer">{{ __('Save & select') }}</button>
        </div>
    </div>
</div>

<div class="toast-stack" id="toasts"></div>

@php
    $posData = [
        'products' => $products,
        'routes' => [
            'lookup' => route('pos.lookup'),
            'catalog' => route('pos.catalog'),
            'checkout' => route('pos.checkout'),
            'sync' => route('pos.sync'),
            'hold' => route('pos.hold'),
            'resumeBase' => url('pos/resume'),
            'customer' => route('customers.store'),
            'receiptBase' => url('pos/receipt'),
        ],
        'shop' => [
            'name' => setting('shop_name', 'SuperShop'),
            'address' => setting('shop_address'),
            'phone' => setting('shop_phone'),
            'footer' => setting('receipt_footer', __('Thank you for shopping with us!')),
            'currency' => setting('currency_symbol', '$'),
            'currencyPos' => setting('currency_position', 'before'),
        ],
        'cashier' => auth()->user()->name,
        'held' => $held->map(fn ($h) => [
            'id' => $h->id, 'reference' => $h->reference, 'total' => (float) $h->total,
            'customer_id' => $h->customer_id, 'cart' => $h->cart,
        ])->values(),
        'csrf' => csrf_token(),
        'locale' => app()->getLocale(),
        'i18n' => [
            'item' => __('item'),
            'items' => __('items'),
            'charge' => __('Charge'),
            'cartEmpty' => __('Cart is empty'),
            'cartEmptyHint' => __('Scan or tap a product to begin.'),
            'noMatch' => __('No product matched'),
            'outOfStock' => __('is out of stock'),
            'saleDone' => __('Sale completed'),
            'inStock' => __('in stock'),
            'stockLimit' => __('Reached stock limit'),
            'nothingHold' => __('Nothing to hold'),
            'heldAs' => __('Sale held as'),
            'resumed' => __('Resumed'),
            'cardExact' => __('payment for the exact total'),
            'card' => __('Card'),
            'mobile' => __('Mobile'),
            'enterName' => __('Enter a name'),
            'customerAdded' => __('Customer added'),
            'noProducts' => __('No products found'),
            'tryAnother' => __('Try another search or category.'),
            'outBadge' => __('Out'),
            'outOfStockPlain' => __('Out of stock'),
            'online' => __('Online'),
            'offline' => __('Offline'),
            'savedOffline' => __('Saved offline'),
            'offlineBanner' => __("You're offline — sales are saved on this device and will sync automatically."),
            'backOnline' => __('Back online'),
            'synced' => __('offline sales synced'),
            'syncFailed' => __('Sync failed — will retry'),
            'drawerOffline' => __('Cash drawer will open once back online'),
            'lookupFailed' => __('Product lookup failed.'),
            'cashLessThanDue' => __('Cash received is less than total due'),
            'processing' => __('Processing…'),
            'checkoutFailed' => __('Checkout failed.'),
            'completeSale' => __('Complete Sale'),
            'clearSale' => __('Clear the current sale?'),
            'holdFailed' => __('Could not hold sale.'),
            'replaceCart' => __('Replace current cart with held sale :reference?'),
            // Offline receipt labels
            'rcInvoice' => __('Invoice'),
            'rcDate' => __('Date'),
            'rcCashier' => __('Cashier'),
            'rcCustomer' => __('Customer'),
            'rcWalkIn' => __('Walk-in'),
            'rcItem' => __('Item'),
            'rcQty' => __('Qty'),
            'rcAmount' => __('Amount'),
            'rcSubtotal' => __('Subtotal'),
            'rcDiscount' => __('Discount'),
            'rcTax' => __('Tax'),
            'rcTotal' => __('TOTAL'),
            'rcPaid' => __('Paid'),
            'rcChange' => __('Change'),
            'rcOfflineTag' => __('OFFLINE — will sync'),
            'rcPrint' => __('Print Receipt'),
            'rcClose' => __('Close'),
            'cash' => __('Cash'),
        ],
    ];
@endphp
<script>
    window.POS_DATA = {!! json_encode($posData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};

    // Register the offline service worker (works only over https / localhost).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
        });
    }
</script>
<script src="{{ asset('js/pos-offline.js') }}"></script>
<script src="{{ asset('js/pos.js') }}"></script>
</body>
</html>
