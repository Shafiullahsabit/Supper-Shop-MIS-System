@extends('layouts.app')
@section('title', 'New Purchase')
@section('crumb', 'Record stock received from a supplier')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('New Purchase') }}</h2><p>{{ __('Add products received into inventory') }}</p></div>
    <div class="actions"><a href="{{ route('purchases.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a></div>
</div>

<form method="POST" action="{{ route('purchases.store') }}" id="purchaseForm">
    @csrf
    <div class="grid" style="grid-template-columns: 2fr 1fr; align-items:start">
        <div style="display:flex;flex-direction:column;gap:18px">
            <div class="card card-pad">
                <div class="form-row-3">
                    <div class="field"><label>{{ __('Supplier') }}</label><select class="select" name="supplier_id"><option value="">{{ __('— none —') }}</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('Purchase date *') }}</label><input class="input" type="date" name="purchase_date" value="{{ date('Y-m-d') }}" required></div>
                    <div class="field"><label>{{ __('Status *') }}</label><select class="select" name="status"><option value="received">{{ __('Received (add to stock)') }}</option><option value="ordered">{{ __('Ordered') }}</option><option value="pending">{{ __('Pending') }}</option></select></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>{{ __('Items') }}</h3>
                    <div style="margin-inline-start:auto;position:relative;width:280px">
                        <div class="search-box"><input type="text" id="prodSearch" placeholder="{{ __('Search product to add…') }}" autocomplete="off"></div>
                        <div id="prodResults" style="position:absolute;top:44px;inset-inline:0;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:var(--shadow-lg);z-index:20;max-height:280px;overflow:auto;display:none"></div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data" id="itemsTable">
                        <thead><tr><th>{{ __('Product') }}</th><th style="width:130px">{{ __('Cost') }}</th><th style="width:120px">{{ __('Qty') }}</th><th class="t-right" style="width:120px">{{ __('Subtotal') }}</th><th style="width:40px"></th></tr></thead>
                        <tbody id="itemsBody"><tr id="noItems"><td colspan="5"><div class="empty" style="padding:30px">@include('partials.icon',['name'=>'inbox'])<h4>{{ __('No items added') }}</h4><p>{{ __('Search above to add products.') }}</p></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-pad" style="position:sticky;top:84px">
            <h3 style="margin-bottom:16px">{{ __('Totals') }}</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:10px"><span class="text-muted">{{ __('Items subtotal') }}</span><span class="t-mono fw-700" id="subtotalOut">{{ money(0) }}</span></div>
            <div class="field"><label>{{ __('Discount') }}</label><input class="input t-mono" type="number" step="0.01" min="0" name="discount" value="0" id="discIn"></div>
            <div class="field"><label>{{ __('Tax') }}</label><input class="input t-mono" type="number" step="0.01" min="0" name="tax" value="0" id="taxIn"></div>
            <div class="field"><label>{{ __('Shipping') }}</label><input class="input t-mono" type="number" step="0.01" min="0" name="shipping" value="0" id="shipIn"></div>
            <div style="display:flex;justify-content:space-between;padding-top:12px;border-top:2px dashed var(--line);font-size:20px;font-weight:800;margin-bottom:14px"><span>{{ __('Total') }}</span><span class="text-blue t-mono" id="totalOut">{{ money(0) }}</span></div>
            <div class="field"><label>{{ __('Amount paid') }}</label><input class="input t-mono" type="number" step="0.01" min="0" name="paid" value="0"></div>
            <div class="field"><label>{{ __('Note') }}</label><textarea class="input" name="note"></textarea></div>
            <button class="btn btn-primary btn-block" type="submit" id="submitBtn">@include('partials.icon',['name'=>'check']) {{ __('Save Purchase') }}</button>
        </div>
    </div>
</form>

@php
    $productData = $products->map(fn ($p) => [
        'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
        'barcode' => $p->barcode, 'cost' => (float) $p->cost_price,
    ])->values();
@endphp
@push('scripts')
<script>
    const purchaseMessages = {
        noMatch: @json(__('No match')),
        noItemsAdded: @json(__('No items added')),
        addAtLeastOneProduct: @json(__('Add at least one product.')),
    };
    const PRODUCTS = {!! json_encode($productData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    const CUR = @json(setting('currency_symbol','$'));
    const CPOS = @json(setting('currency_position','before'));
    const money = n => { const v=(Math.round((+n||0)*100)/100).toFixed(2); return CPOS==='after'? v+CUR : CUR+v; };
    let rows = [];
    const body = document.getElementById('itemsBody');
    const search = document.getElementById('prodSearch');
    const results = document.getElementById('prodResults');

    search.addEventListener('input', () => {
        const t = search.value.trim().toLowerCase();
        if (!t) { results.style.display='none'; return; }
        const hits = PRODUCTS.filter(p => p.name.toLowerCase().includes(t) || (p.sku&&p.sku.toLowerCase().includes(t)) || (p.barcode&&p.barcode.includes(t))).slice(0,8);
        results.innerHTML = hits.length ? hits.map(p=>`<div class="pr-item" data-id="${p.id}" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--line-2)"><b>${p.name}</b><div class="t-sub">${p.sku} · ${money(p.cost)}</div></div>`).join('') : `<div style="padding:12px 14px" class="text-muted">${purchaseMessages.noMatch}</div>`;
        results.style.display='block';
        results.querySelectorAll('.pr-item').forEach(el=>el.onclick=()=>{ addRow(+el.dataset.id); search.value=''; results.style.display='none'; });
    });
    document.addEventListener('click', e=>{ if(!e.target.closest('#prodResults')&&e.target!==search) results.style.display='none'; });

    function addRow(id){
        if (rows.find(r=>r.id===id)) return;
        const p = PRODUCTS.find(x=>x.id===id);
        rows.push({id:p.id, name:p.name, cost:p.cost, qty:1});
        render();
    }
    function render(){
        document.getElementById('noItems')?.remove();
        body.innerHTML='';
        if(!rows.length){ body.innerHTML=`<tr id="noItems"><td colspan="5"><div class="empty" style="padding:30px">${purchaseMessages.noItemsAdded}</div></td></tr>`; }
        rows.forEach((r,i)=>{
            const tr=document.createElement('tr');
            tr.innerHTML=`<td><b>${r.name}</b>
                <input type="hidden" name="items[${i}][product_id]" value="${r.id}"></td>
                <td><input class="input t-mono" style="padding:7px 10px" type="number" step="0.01" min="0" name="items[${i}][cost]" value="${r.cost}"></td>
                <td><input class="input t-mono" style="padding:7px 10px" type="number" step="0.001" min="0.001" name="items[${i}][quantity]" value="${r.qty}"></td>
                <td class="t-right t-mono fw-700 sub">${money(r.cost*r.qty)}</td>
                <td><button type="button" class="icon-btn danger">${'✕'}</button></td>`;
            const [costI,qtyI]=tr.querySelectorAll('input:not([type=hidden])');
            costI.oninput=()=>{ r.cost=+costI.value||0; tr.querySelector('.sub').textContent=money(r.cost*r.qty); totals(); };
            qtyI.oninput=()=>{ r.qty=+qtyI.value||0; tr.querySelector('.sub').textContent=money(r.cost*r.qty); totals(); };
            tr.querySelector('button').onclick=()=>{ rows.splice(i,1); render(); };
            body.appendChild(tr);
        });
        totals();
    }
    function totals(){
        const sub=rows.reduce((s,r)=>s+r.cost*r.qty,0);
        const disc=+document.getElementById('discIn').value||0;
        const tax=+document.getElementById('taxIn').value||0;
        const ship=+document.getElementById('shipIn').value||0;
        document.getElementById('subtotalOut').textContent=money(sub);
        document.getElementById('totalOut').textContent=money(sub-disc+tax+ship);
    }
    ['discIn','taxIn','shipIn'].forEach(id=>document.getElementById(id).addEventListener('input',totals));
    document.getElementById('purchaseForm').addEventListener('submit', e=>{ if(!rows.length){ e.preventDefault(); alert(purchaseMessages.addAtLeastOneProduct); }});
</script>
@endpush
@endsection
