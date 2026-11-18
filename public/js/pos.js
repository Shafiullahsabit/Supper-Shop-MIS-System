/* SuperShop POS terminal engine */
(function () {
    const D = window.POS_DATA;
    const T = D.i18n || {};
    const shell = document.getElementById('pos');
    const CUR = shell.dataset.currency || '$';
    const CUR_POS = shell.dataset.currencyPos || 'before';
    const dateLocale = D.locale === 'prs' ? 'fa-AF' : (D.locale === 'ps' ? 'ps-AF' : 'en');

    const money = (n) => {
        const v = (Math.round((+n || 0) * 100) / 100).toFixed(2);
        return CUR_POS === 'after' ? v + CUR : CUR + v;
    };
    // Raw numeric formatter for <input> values (must stay Western so number inputs work).
    const qtyFmt = (n) => (Number.isInteger(n) ? n : parseFloat(n.toFixed(3)));
    // Display formatter for quantities shown as text.
    const qtyDisp = (n) => String(qtyFmt(n));

    let cart = [];          // {id,name,price,tax_rate,qty,stock,unit,allow_decimal,discount}
    let activeCat = 'all';
    let payMethod = 'cash';

    // ---------- rendering ----------
    const grid = document.getElementById('grid');

    function renderGrid(list) {
        grid.innerHTML = '';
        if (!list.length) {
            grid.innerHTML = '<div class="empty" style="grid-column:1/-1"><h4>' + (T.noProducts || 'No products found') + '</h4><p>' + (T.tryAnother || 'Try another search or category.') + '</p></div>';
            return;
        }
        list.forEach(p => {
            const out = p.stock <= 0;
            const el = document.createElement('div');
            el.className = 'p-card' + (out ? ' out' : '');
            el.innerHTML =
                (out ? '<span class="p-oos">' + escapeHtml(T.outBadge || 'Out') + '</span>' : '') +
                '<div class="p-top" style="background:' + p.color + '">' + p.name.trim().charAt(0).toUpperCase() + '</div>' +
                '<div class="p-name">' + escapeHtml(p.name) + '</div>' +
                '<div class="p-meta"><span class="p-price">' + money(p.price) + '</span>' +
                '<span class="p-stock">' + qtyDisp(p.stock) + ' ' + p.unit + '</span></div>';
            if (!out) el.addEventListener('click', () => addToCart(p));
            grid.appendChild(el);
        });
    }

    function filtered() {
        if (activeCat === 'all') return D.products;
        return D.products.filter(p => String(p.category_id) === String(activeCat));
    }

    // ---------- cart ----------
    function addToCart(p, qty) {
        const line = cart.find(l => l.id === p.id);
        const add = qty || 1;
        if (line) {
            if (line.qty + add > p.stock) return toast(qtyDisp(p.stock) + ' ' + p.unit + ' ' + (T.inStock || 'in stock'), 'err');
            line.qty += add;
        } else {
            if (add > p.stock) return toast(T.outOfStockPlain || 'Out of stock', 'err');
            cart.push({ id: p.id, name: p.name, price: p.price, tax_rate: p.tax_rate, qty: add, stock: p.stock, unit: p.unit, allow_decimal: p.allow_decimal, discount: 0 });
        }
        renderCart();
        flashProduct();
    }

    function setQty(id, qty) {
        const line = cart.find(l => l.id === id);
        if (!line) return;
        qty = line.allow_decimal ? parseFloat(qty) : Math.round(qty);
        if (isNaN(qty) || qty <= 0) return removeLine(id);
        if (qty > line.stock) { qty = line.stock; toast((T.stockLimit || 'Reached stock limit') + ' (' + qtyDisp(line.stock) + ')', 'err'); }
        line.qty = qty;
        renderCart();
    }

    function removeLine(id) { cart = cart.filter(l => l.id !== id); renderCart(); }

    const cartItems = document.getElementById('cartItems');

    function renderCart() {
        if (!cart.length) {
            cartItems.innerHTML = '<div class="cart-empty">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>' +
                '<h4 style="color:var(--ink-soft);margin-bottom:4px">' + (T.cartEmpty || 'Cart is empty') + '</h4><p>' + (T.cartEmptyHint || 'Scan or tap a product to begin.') + '</p></div>';
        } else {
            cartItems.innerHTML = '';
            cart.forEach(l => {
                const row = document.createElement('div');
                row.className = 'cart-line';
                const step = l.allow_decimal ? '0.5' : '1';
                row.innerHTML =
                    '<div class="cl-info"><div class="cl-name">' + escapeHtml(l.name) + '</div>' +
                    '<div class="cl-price">' + money(l.price) + ' / ' + l.unit + (l.tax_rate ? ' · tax ' + l.tax_rate + '%' : '') + '</div></div>' +
                    '<div class="qty-stepper"><button data-act="dec">−</button>' +
                    '<input type="number" value="' + qtyFmt(l.qty) + '" step="' + step + '" min="0"><button data-act="inc">+</button></div>' +
                    '<div class="cl-total mono">' + money(lineTotal(l)) + '</div>' +
                    '<div class="cl-remove" data-act="rm"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></div>';
                row.querySelector('[data-act=dec]').onclick = () => setQty(l.id, l.qty - (l.allow_decimal ? 0.5 : 1));
                row.querySelector('[data-act=inc]').onclick = () => setQty(l.id, l.qty + (l.allow_decimal ? 0.5 : 1));
                row.querySelector('[data-act=rm]').onclick = () => removeLine(l.id);
                const inp = row.querySelector('input');
                inp.onchange = () => setQty(l.id, inp.value);
                cartItems.appendChild(row);
            });
        }
        updateTotals();
    }

    function lineTotal(l) {
        const base = l.price * l.qty - (l.discount || 0);
        return base + base * (l.tax_rate / 100);
    }

    function totals() {
        let subtotal = 0, tax = 0;
        cart.forEach(l => {
            const base = l.price * l.qty - (l.discount || 0);
            subtotal += l.price * l.qty;
            tax += base * (l.tax_rate / 100);
        });
        const orderDisc = parseFloat(document.getElementById('orderDiscount').value) || 0;
        let total = subtotal - orderDisc + tax;
        if (total < 0) total = 0;
        return { subtotal, tax, orderDisc, total };
    }

    function updateTotals() {
        const t = totals();
        document.getElementById('sumSubtotal').textContent = money(t.subtotal);
        document.getElementById('sumTax').textContent = money(t.tax);
        document.getElementById('sumTotal').textContent = money(t.total);
        const count = cart.reduce((s, l) => s + l.qty, 0);
        document.getElementById('itemCount').textContent = qtyDisp(count) + ' ' + (count === 1 ? (T.item || 'item') : (T.items || 'items'));
        document.getElementById('payLabel').textContent = cart.length ? (T.charge || 'Charge') + ' ' + money(t.total) : (T.charge || 'Charge');
        document.getElementById('payBtn').disabled = cart.length === 0;
    }

    // ---------- search / scan ----------
    const scan = document.getElementById('scan');
    let searchTimer;
    scan.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const term = scan.value.trim();
            if (!term) return;
            lookup(term, true);
        }
    });
    scan.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const term = scan.value.trim();
        if (!term) { activeCat = 'all'; syncTabs(); renderGrid(filtered()); return; }
        searchTimer = setTimeout(() => {
            const local = D.products.filter(p =>
                p.name.toLowerCase().includes(term.toLowerCase()) ||
                (p.sku && p.sku.toLowerCase().includes(term.toLowerCase())) ||
                (p.barcode && p.barcode.includes(term)));
            renderGrid(local);
        }, 120);
    });

    async function lookup(term, addExact) {
        // exact barcode/sku in local set first
        let p = D.products.find(x => x.barcode === term || x.sku === term);
        if (p) { if (p.stock <= 0) toast(p.name + ' ' + (T.outOfStock || 'is out of stock'), 'err'); else { addToCart(p); scan.value = ''; renderGrid(filtered()); } return; }
        try {
            const r = await fetch(D.routes.lookup + '?q=' + encodeURIComponent(term), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await r.json();
            if (data.exact && data.products[0]) {
                const hit = data.products[0];
                const known = D.products.find(x => x.id === hit.id) || hit;
                addToCart(known); scan.value = '';
            } else if (data.products.length) {
                renderGrid(data.products);
                if (addExact && data.products.length === 1) { addToCart(data.products[0]); scan.value = ''; }
            } else {
                toast((T.noMatch || 'No product matched') + ' "' + term + '"', 'err');
            }
        } catch (e) { toast(T.lookupFailed || 'Lookup failed', 'err'); }
    }

    // ---------- category tabs ----------
    const tabs = document.getElementById('catTabs');
    tabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.cat-tab');
        if (!btn) return;
        activeCat = btn.dataset.cat;
        syncTabs();
        renderGrid(filtered());
    });
    function syncTabs() { tabs.querySelectorAll('.cat-tab').forEach(b => b.classList.toggle('active', b.dataset.cat === activeCat)); }

    // ---------- payment methods ----------
    document.getElementById('payMethods').addEventListener('click', (e) => {
        const opt = e.target.closest('.pay-opt');
        if (!opt) return;
        payMethod = opt.dataset.pm;
        document.querySelectorAll('.pay-opt').forEach(o => o.classList.toggle('active', o === opt));
    });
    document.getElementById('orderDiscount').addEventListener('input', updateTotals);

    // ---------- pay modal ----------
    const payModal = document.getElementById('payModal');
    document.getElementById('payBtn').onclick = openPay;

    function openPay() {
        if (!cart.length) return;
        const t = totals();
        document.getElementById('payDue').textContent = money(t.total);
        const cashPane = document.getElementById('cashPane');
        const nonCash = document.getElementById('nonCashPane');
        if (payMethod === 'cash') {
            cashPane.style.display = ''; nonCash.style.display = 'none';
            const tender = document.getElementById('tender');
            tender.value = t.total.toFixed(2);
            buildQuickCash(t.total);
            updateChange();
            setTimeout(() => { tender.focus(); tender.select(); }, 50);
        } else {
            cashPane.style.display = 'none'; nonCash.style.display = '';
            document.getElementById('nonCashText').textContent =
                (payMethod === 'card' ? (T.card || 'Card') : (T.mobile || 'Mobile')) + ' ' + (T.cardExact || 'payment for the exact total') + ' · ' + money(t.total);
        }
        payModal.style.display = 'grid';
    }

    function buildQuickCash(total) {
        const box = document.getElementById('quickCash');
        const denos = [];
        denos.push(Math.ceil(total));
        [5, 10, 20, 50, 100].forEach(d => { if (d > total && !denos.includes(d)) denos.push(d); });
        const rounded = Math.ceil(total / 10) * 10;
        if (!denos.includes(rounded) && rounded > total) denos.push(rounded);
        box.innerHTML = '';
        denos.slice(0, 4).forEach(d => {
            const b = document.createElement('button');
            b.textContent = money(d);
            b.onclick = () => { document.getElementById('tender').value = (+d).toFixed(2); updateChange(); };
            box.appendChild(b);
        });
    }

    const tender = document.getElementById('tender');
    tender.addEventListener('input', updateChange);
    function updateChange() {
        const t = totals();
        const paid = parseFloat(tender.value) || 0;
        const change = paid - t.total;
        const box = document.getElementById('changeBox');
        if (paid > 0 && change >= 0) {
            box.style.display = '';
            document.getElementById('changeAmt').textContent = money(change);
        } else box.style.display = 'none';
    }

    document.getElementById('confirmPay').onclick = doCheckout;

    async function doCheckout() {
        const t = totals();
        const paid = payMethod === 'cash' ? (parseFloat(tender.value) || 0) : t.total;
        if (payMethod === 'cash' && paid < t.total) return toast(T.cashLessThanDue || 'Cash received is less than total due', 'err');

        const btn = document.getElementById('confirmPay');
        btn.disabled = true; btn.textContent = T.processing || 'Processing…';

        const clientUuid = window.POSOffline ? POSOffline.uuid() : String(Date.now());
        const sel = document.getElementById('customer');
        const payload = {
            client_uuid: clientUuid,
            items: cart.map(l => ({ product_id: l.id, quantity: l.qty, price: l.price, discount: l.discount || 0 })),
            customer_id: sel.value || null,
            payment_method: payMethod,
            paid: paid,
            discount: t.orderDisc,
            open_drawer: document.getElementById('openDrawer').checked,
        };

        // Snapshot for the offline queue + client-printed receipt (captured before reset).
        const snapshot = {
            client_uuid: clientUuid,
            offline_created_at: new Date().toISOString(),
            payment_method: payMethod,
            paid: paid,
            change: Math.max(0, paid - t.total),
            totals: { subtotal: t.subtotal, tax: t.tax, discount: t.orderDisc, total: t.total },
            customer_name: sel.value ? (sel.options[sel.selectedIndex] || {}).text : null,
            lines: cart.map(l => ({ name: l.name, qty: l.qty, price: l.price, unit: l.unit, subtotal: lineTotal(l) })),
        };

        // Known-offline: skip the network entirely and queue.
        if (!navigator.onLine) {
            await completeOffline(payload, snapshot);
            return finishCheckoutBtn(btn);
        }

        try {
            const r = await fetch(D.routes.checkout, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await r.json();
            if (!r.ok) {
                // Server reachable but rejected (e.g. real stock issue) — do NOT queue offline.
                const msg = data.message || (data.errors ? Object.values(data.errors)[0][0] : (T.checkoutFailed || 'Checkout failed'));
                toast(msg, 'err');
                return finishCheckoutBtn(btn);
            }
            if (data.drawer && data.drawer.configured && !data.drawer.ok) toast(data.drawer.message, 'err');
            closePay();
            toast((T.saleDone || 'Sale completed') + ' ' + data.invoice_no, 'ok');
            window.open(data.receipt_url, 'receipt', 'width=380,height=640');
            applyStockAndReset(payload);
            finishCheckoutBtn(btn);
        } catch (e) {
            // Network died mid-checkout — fall back to the offline queue so the sale isn't lost.
            await completeOffline(payload, snapshot);
            finishCheckoutBtn(btn);
        }
    }

    function finishCheckoutBtn(btn) { btn.disabled = false; btn.innerHTML = escapeHtml(T.completeSale || 'Complete Sale'); }

    function applyStockAndReset(payload) {
        payload.items.forEach(it => { const p = D.products.find(x => x.id === it.product_id); if (p) p.stock -= it.quantity; });
        resetSale();
        renderGrid(filtered());
    }

    async function completeOffline(payload, snapshot) {
        if (window.POSOffline) {
            await POSOffline.enqueue({
                client_uuid: payload.client_uuid,
                items: payload.items,
                customer_id: payload.customer_id,
                payment_method: payload.payment_method,
                paid: payload.paid,
                discount: payload.discount,
                offline_created_at: snapshot.offline_created_at,
            });
        }
        closePay();
        let msg = T.savedOffline || 'Saved offline';
        if (payload.payment_method === 'cash' && payload.open_drawer) {
            msg += ' · ' + (T.drawerOffline || 'Cash drawer will open once back online');
        }
        toast(msg, 'ok');
        openOfflineReceipt(snapshot);
        applyStockAndReset(payload);
    }

    // ---------- offline receipt (rendered fully client-side, no server) ----------
    function offlineRef(uuid) { return 'OFFLINE-' + String(uuid).replace(/-/g, '').slice(0, 6).toUpperCase(); }

    function openOfflineReceipt(s) {
        const w = window.open('', 'receipt', 'width=380,height=640');
        if (!w) return; // popup blocked
        const shop = D.shop || {};
        const ref = offlineRef(s.client_uuid);
        const dt = new Date();
        const dateStr = new Intl.DateTimeFormat(dateLocale, {
            calendar: 'gregory',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(dt);
        const pm = s.payment_method === 'cash' ? (T.cash || 'Cash')
            : (s.payment_method === 'card' ? (T.card || 'Card') : (T.mobile || 'Mobile'));
        const rows = s.lines.map(l =>
            '<tr><td>' + escapeHtml(l.name) + '<div class="qty">' + qtyDisp(l.qty) + ' × ' + money(l.price) + '</div></td>' +
            '<td class="r">' + qtyDisp(l.qty) + '</td><td class="r">' + money(l.subtotal) + '</td></tr>'
        ).join('');

        const html =
            '<!DOCTYPE html><html lang="' + escapeHtml(D.locale) + '" dir="' + (D.locale === 'en' ? 'ltr' : 'rtl') + '"><head><meta charset="utf-8"><title>Receipt ' + ref + '</title>' +
            '<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet"><style>' +
            '*{margin:0;padding:0;box-sizing:border-box}' +
            'body{font-family:"Noto Sans Arabic","SF Mono","Consolas","Menlo",monospace;background:#eef2f8;color:#16233d;padding:16px}' +
            '.receipt{width:300px;margin:0 auto;background:#fff;padding:20px 22px;box-shadow:0 6px 24px rgba(0,0,0,.1)}' +
            '.center{text-align:center}.shop{font-size:19px;font-weight:800;letter-spacing:.04em}' +
            '.muted{color:#64748b;font-size:11px}.divider{border:none;border-top:1px dashed #cbd5e1;margin:12px 0}' +
            '.row{display:flex;justify-content:space-between;font-size:12px;margin:3px 0}' +
            'table{width:100%;border-collapse:collapse;font-size:12px}' +
            'th{text-align:start;font-size:10px;text-transform:uppercase;color:#64748b;padding-bottom:5px;border-bottom:1px solid #e2e8f0}' +
            'td{padding:4px 0;vertical-align:top}.qty{color:#64748b;font-size:11px}.r{text-align:end;direction:ltr}' +
            '.total-row{font-size:16px;font-weight:800}' +
            '.tag-offline{display:inline-block;border:1px solid #d97706;color:#b45309;background:#fffbeb;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;letter-spacing:.05em}' +
            '.foot{text-align:center;font-size:11px;color:#475569;margin-top:14px;line-height:1.6}' +
            '.actions{width:300px;margin:16px auto 0;display:flex;gap:8px}' +
            '.actions button{flex:1;padding:11px;border:none;border-radius:9px;font-weight:700;font-size:13px;cursor:pointer;font-family:system-ui,sans-serif}' +
            '.print-btn{background:#2258c9;color:#fff}.close-btn{background:#eef2f8;color:#16233d}' +
            '@media print{body{background:#fff;padding:0}.receipt{box-shadow:none;width:100%;padding:4px}.actions{display:none}@page{margin:4mm}}' +
            '</style></head><body><div class="receipt"><div class="center"><div class="shop">' + escapeHtml(shop.name || 'SuperShop') + '</div>' +
            (shop.address ? '<div class="muted">' + escapeHtml(shop.address) + '</div>' : '') +
            (shop.phone ? '<div class="muted">Tel: ' + escapeHtml(shop.phone) + '</div>' : '') +
            '</div><hr class="divider">' +
            '<div class="row"><span>' + (T.rcInvoice || 'Invoice') + '</span><span><b>' + ref + '</b></span></div>' +
            '<div class="row"><span>' + (T.rcDate || 'Date') + '</span><span>' + dateStr + '</span></div>' +
            '<div class="row"><span>' + (T.rcCashier || 'Cashier') + '</span><span>' + escapeHtml(D.cashier || '—') + '</span></div>' +
            '<div class="row"><span>' + (T.rcCustomer || 'Customer') + '</span><span>' + escapeHtml(s.customer_name || (T.rcWalkIn || 'Walk-in')) + '</span></div>' +
            '<div class="center" style="margin:8px 0"><span class="tag-offline">● ' + (T.rcOfflineTag || 'OFFLINE — will sync') + '</span></div>' +
            '<hr class="divider"><table><thead><tr><th>' + (T.rcItem || 'Item') + '</th><th class="r">' + (T.rcQty || 'Qty') + '</th><th class="r">' + (T.rcAmount || 'Amount') + '</th></tr></thead><tbody>' +
            rows + '</tbody></table><hr class="divider">' +
            '<div class="row"><span>' + (T.rcSubtotal || 'Subtotal') + '</span><span>' + money(s.totals.subtotal) + '</span></div>' +
            (s.totals.discount > 0 ? '<div class="row"><span>' + (T.rcDiscount || 'Discount') + '</span><span>− ' + money(s.totals.discount) + '</span></div>' : '') +
            (s.totals.tax > 0 ? '<div class="row"><span>' + (T.rcTax || 'Tax') + '</span><span>' + money(s.totals.tax) + '</span></div>' : '') +
            '<hr class="divider"><div class="row total-row"><span>' + (T.rcTotal || 'TOTAL') + '</span><span>' + money(s.totals.total) + '</span></div>' +
            '<div class="row" style="margin-top:8px"><span>' + (T.rcPaid || 'Paid') + ' (' + pm + ')</span><span>' + money(s.paid) + '</span></div>' +
            (s.change > 0 ? '<div class="row"><span>' + (T.rcChange || 'Change') + '</span><span>' + money(s.change) + '</span></div>' : '') +
            '<div class="foot">' + escapeHtml(shop.footer || 'Thank you for shopping with us!') + '<br><span class="muted">' + ref + '</span></div>' +
            '</div><div class="actions"><button class="print-btn" onclick="window.print()">' + (T.rcPrint || 'Print Receipt') + '</button>' +
            '<button class="close-btn" onclick="window.close()">' + (T.rcClose || 'Close') + '</button></div>' +
            '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},350)})<\/script></body></html>';

        w.document.open();
        w.document.write(html);
        w.document.close();
    }

    function resetSale() {
        cart = [];
        document.getElementById('orderDiscount').value = 0;
        document.getElementById('customer').value = '';
        payMethod = 'cash';
        document.querySelectorAll('.pay-opt').forEach(o => o.classList.toggle('active', o.dataset.pm === 'cash'));
        renderCart();
        scan.focus();
    }

    function closePay() { payModal.style.display = 'none'; }
    window.POS = { closePay, hide: (id) => document.getElementById(id).style.display = 'none' };

    // ---------- clear / hold / resume ----------
    document.getElementById('clearBtn').onclick = () => { if (cart.length && confirm(T.clearSale || 'Clear the current sale?')) resetSale(); };

    document.getElementById('holdBtn').onclick = async () => {
        if (!cart.length) return toast(T.nothingHold || 'Nothing to hold', 'err');
        try {
            const r = await fetch(D.routes.hold, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ cart, customer_id: document.getElementById('customer').value || null, total: totals().total }),
            });
            const data = await r.json();
            if (data.ok) { toast((T.heldAs || 'Sale held as') + ' ' + data.reference, 'ok'); resetSale(); setTimeout(() => location.reload(), 900); }
        } catch (e) { toast(T.holdFailed || 'Could not hold sale', 'err'); }
    };

    const resumeBtn = document.getElementById('resumeBtn');
    if (resumeBtn) resumeBtn.onclick = () => {
        if (!D.held.length) return;
        const h = D.held[0];
        if (cart.length && !confirm((T.replaceCart || 'Replace current cart with held sale :reference?').replace(':reference', h.reference))) return;
        fetch(D.routes.resumeBase + '/' + h.id, {
            method: 'POST', headers: { 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
        }).then(r => r.json()).then(data => {
            if (data.ok) {
                cart = data.held.cart.map(l => ({ ...l, discount: l.discount || 0 }));
                if (data.held.customer_id) document.getElementById('customer').value = data.held.customer_id;
                renderCart(); toast((T.resumed || 'Resumed') + ' ' + data.held.reference, 'ok');
                setTimeout(() => location.reload(), 1200);
            }
        });
    };

    // ---------- quick customer ----------
    document.getElementById('addCustomerBtn').onclick = () => { document.getElementById('custModal').style.display = 'grid'; document.getElementById('ncName').focus(); };
    document.getElementById('saveCustomer').onclick = async () => {
        const name = document.getElementById('ncName').value.trim();
        if (!name) return toast(T.enterName || 'Enter a name', 'err');
        const r = await fetch(D.routes.customer, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ name, phone: document.getElementById('ncPhone').value.trim() }),
        });
        const data = await r.json();
        if (data.ok) {
            const sel = document.getElementById('customer');
            const opt = new Option(data.customer.name + (data.customer.phone ? ' · ' + data.customer.phone : ''), data.customer.id, true, true);
            sel.add(opt);
            document.getElementById('custModal').style.display = 'none';
            document.getElementById('ncName').value = ''; document.getElementById('ncPhone').value = '';
            toast(T.customerAdded || 'Customer added', 'ok');
        }
    };

    // ---------- toast ----------
    function toast(msg, type) {
        const stack = document.getElementById('toasts');
        const el = document.createElement('div');
        el.className = 'toast ' + (type || '');
        const ico = type === 'err'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e0483d" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a86b" stroke-width="2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg>';
        el.innerHTML = ico + '<span>' + escapeHtml(msg) + '</span>';
        stack.appendChild(el);
        const offset = document.documentElement.dir === 'rtl' ? '-30px' : '30px';
        setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(' + offset + ')'; el.style.transition = '.25s'; setTimeout(() => el.remove(), 260); }, 2600);
    }

    function flashProduct() {
        const pane = document.getElementById('cartPane');
        pane.classList.add('open');
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    // ---------- clock ----------
    function tick() {
        const d = new Date();
        document.getElementById('clock').textContent = new Intl.DateTimeFormat(dateLocale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(d);
    }
    setInterval(tick, 1000); tick();

    // ---------- keyboard shortcuts ----------
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') { e.preventDefault(); openPay(); }
        if (e.key === 'Escape') { closePay(); document.getElementById('custModal').style.display = 'none'; }
        if (e.key === 'F4') { e.preventDefault(); scan.focus(); }
    });

    // ---------- offline status UI ----------
    const netPill = document.getElementById('netPill');
    const netLabel = document.getElementById('netLabel');
    const syncBadge = document.getElementById('syncBadge');
    const syncCount = document.getElementById('syncCount');

    function renderNet() {
        const on = navigator.onLine;
        if (netPill) { netPill.classList.toggle('online', on); netPill.classList.toggle('offline', !on); }
        if (netLabel) netLabel.textContent = on ? (T.online || 'Online') : (T.offline || 'Offline');
        toggleOfflineBanner(!on);
    }

    function toggleOfflineBanner(show) {
        let b = document.getElementById('offlineBanner');
        if (show && !b) {
            b = document.createElement('div');
            b.id = 'offlineBanner';
            b.className = 'offline-banner';
            b.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 1l22 22M16.72 11.06A10.94 10.94 0 0 1 19 12.55M5 12.55a10.94 10.94 0 0 1 5.17-2.39M10.71 5.05A16 16 0 0 1 22.58 9M1.42 9a15.91 15.91 0 0 1 4.7-2.88M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>' +
                '<span>' + (T.offlineBanner || "You're offline — sales are saved on this device and will sync automatically.") + '</span>';
            const left = document.querySelector('.pos-left');
            if (left) left.insertBefore(b, left.firstChild);
        } else if (!show && b) {
            b.remove();
        }
    }

    window.addEventListener('offline', renderNet);
    window.addEventListener('online', () => {
        renderNet();
        toast(T.backOnline || 'Back online', 'ok');
        if (window.POSOffline) POSOffline.flush();
    });

    // Queue size changed (a sale was saved offline, or some synced).
    window.addEventListener('pos-pending', (e) => {
        const n = (e.detail && e.detail.count) || 0;
        if (!syncBadge) return;
        if (n > 0) { syncBadge.style.display = ''; if (syncCount) syncCount.textContent = qtyDisp(n); }
        else syncBadge.style.display = 'none';
    });
    // Some queued sales made it to the server.
    window.addEventListener('pos-synced', (e) => {
        const n = (e.detail && e.detail.count) || 0;
        if (n > 0) toast(qtyDisp(n) + ' ' + (T.synced || 'offline sales synced'), 'ok');
    });

    renderNet();

    // ---------- init ----------
    renderGrid(filtered());
    renderCart();
    scan.focus();
})();
