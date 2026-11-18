/* SuperShop POS — offline queue + sync engine.
 *
 * Sales completed while the terminal is offline are stored in IndexedDB, keyed by a
 * client-generated UUID. When the connection returns they are POSTed to /pos/sync in a
 * batch; the server is idempotent on client_uuid, so a re-sent sale is never double-posted.
 *
 * This module is pure data + networking. It owns no UI — it dispatches window events
 * ('pos-pending', 'pos-synced', 'pos-syncfail') that pos.js turns into the pill/badge/toasts.
 */
(function () {
    const D = window.POS_DATA || {};
    const DB_NAME = 'supershop-pos';
    const STORE = 'queue';
    let dbPromise = null;
    let syncing = false;

    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'client_uuid' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        return dbPromise;
    }

    function reqPromise(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function txDone(tx) {
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    }

    async function putSale(sale) {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).put(sale);
        await txDone(tx);
    }

    async function deleteSale(uuid) {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(uuid);
        await txDone(tx);
    }

    async function allSales() {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readonly');
        return reqPromise(tx.objectStore(STORE).getAll());
    }

    async function countSales() {
        const db = await openDb();
        const tx = db.transaction(STORE, 'readonly');
        return reqPromise(tx.objectStore(STORE).count());
    }

    function uuid() {
        if (crypto && crypto.randomUUID) return crypto.randomUUID();
        // Fallback for older browsers.
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    async function emitPending() {
        const count = await countSales();
        window.dispatchEvent(new CustomEvent('pos-pending', { detail: { count } }));
        return count;
    }

    // Store an offline sale and notify the UI.
    async function enqueue(sale) {
        await putSale(sale);
        await emitPending();
    }

    // Push every queued sale to the server. Safe to call repeatedly.
    async function flush() {
        if (syncing || !navigator.onLine) return;
        const pending = await allSales();
        if (!pending.length) { await emitPending(); return; }

        syncing = true;
        try {
            const res = await fetch(D.routes.sync, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': D.csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ sales: pending }),
            });
            if (!res.ok) throw new Error('sync failed: ' + res.status);
            const data = await res.json();

            const invoices = [];
            for (const r of (data.results || [])) {
                // 'synced' and 'duplicate' are both terminal — drop from the queue.
                // 'error' stays queued to retry on the next flush.
                if (r.status === 'synced' || r.status === 'duplicate') {
                    await deleteSale(r.client_uuid);
                    if (r.status === 'synced' && r.invoice_no) invoices.push(r.invoice_no);
                }
            }
            const remaining = await emitPending();
            if (invoices.length) {
                window.dispatchEvent(new CustomEvent('pos-synced', {
                    detail: { count: invoices.length, invoices, remaining },
                }));
            }
        } catch (e) {
            window.dispatchEvent(new CustomEvent('pos-syncfail', { detail: { message: String(e) } }));
        } finally {
            syncing = false;
        }
    }

    // Auto-sync: on reconnect, and on a slow poll as a safety net.
    window.addEventListener('online', flush);
    setInterval(() => { if (navigator.onLine) flush(); }, 20000);

    // On load, surface any leftover queue and try to drain it.
    emitPending().then(() => flush());

    window.POSOffline = { uuid, enqueue, flush, count: countSales, pending: allSales };
})();
