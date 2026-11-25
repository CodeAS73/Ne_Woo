import './bootstrap';

const money = value => 'KES ' + new Intl.NumberFormat('en-KE', { maximumFractionDigits: 2 }).format(value);
const toast = message => {
    const element = document.querySelector('.toast');
    element.textContent = message;
    element.hidden = false;
    clearTimeout(window.toastTimer);
    window.toastTimer = setTimeout(() => element.hidden = true, 4000);
};
const dialog = document.querySelector('.photo-dialog');
document.querySelector('.dialog-close')?.addEventListener('click', () => dialog.close());
dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });

function renderProduct(card, data, { preview = false } = {}) {
    card.querySelector('[data-product-name]').textContent = data.name;
    card.querySelector('[data-product-description]').textContent = data.description;
    const price = Number(data.currentPrice);
    const discounted = price < Number(data.price);
    card.querySelector('[data-current-price]').textContent = money(price);
    const original = card.querySelector('[data-original-price]');
    original.textContent = money(data.price);
    original.hidden = !discounted;
    const badge = card.querySelector('[data-discount]');
    badge.textContent = '-' + Math.round((1 - price / data.price) * 100) + '%';
    badge.hidden = !discounted;
    const main = card.querySelector('[data-main-photo]');
    const images = data.photos.length ? data.photos : ['/images/weekender.svg'];
    main.src = images[0];
    main.alt = data.name;
    const thumbnails = card.querySelector('[data-thumbnails]');
    thumbnails.replaceChildren();
    images.forEach((url, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = index === 0 ? 'active' : '';
        button.setAttribute('aria-label', 'Show photo ' + (index + 1));
        button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
        const img = document.createElement('img');
        img.src = url; img.alt = 'Product photo ' + (index + 1);
        button.append(img);
        const activate = () => {
            main.src = url;
            thumbnails.querySelectorAll('button').forEach(item => { item.classList.remove('active'); item.setAttribute('aria-pressed', 'false'); });
            button.classList.add('active'); button.setAttribute('aria-pressed', 'true');
        };
        button.addEventListener('click', activate);
        button.addEventListener('mouseenter', activate);
        button.addEventListener('focus', activate);
        thumbnails.append(button);
    });
    card.querySelector('.main-photo-button').onclick = () => {
        dialog.querySelector('img').src = main.src;
        dialog.querySelector('img').alt = data.name;
        dialog.showModal();
    };
    const buy = card.querySelector('[data-buy-link]');
    const soldOut = data.inStock === false || data.stock === 0;
    buy.querySelector('[data-buy-label]').textContent = soldOut ? 'Out of stock' : 'Buy now';
    card.querySelector('[data-stock-label]').textContent = soldOut ? 'Out of stock' : (typeof data.stock === 'number' ? `In stock · ${data.stock} available` : (data.inStock === true ? 'In stock' : 'Stock preview'));
    buy.href = data.buyUrl || '#';
    buy.setAttribute('aria-disabled', preview || soldOut ? 'true' : 'false');
    buy.classList.toggle('sold-out', soldOut);
    buy.target = '_top';
    buy.onclick = preview || soldOut ? event => { event.preventDefault(); toast(soldOut ? 'This product is currently out of stock.' : 'Save your product to activate the Buy now button.'); } : null;
    if (card.countdownInterval) clearInterval(card.countdownInterval);
    const clock = card.querySelector('[data-sale-clock]');
    clock.hidden = true;
    const updateClock = () => {
        const seconds = Math.max(0, Math.floor((new Date(data.offerEndsAt).getTime() - Date.now()) / 1000));
        if (!Number.isFinite(seconds) || seconds <= 0) {
            clock.hidden = true;
            clearInterval(card.countdownInterval);
            // The server remains authoritative at checkout. Restore the normal price visually at expiry.
            card.querySelector('[data-current-price]').textContent = money(data.price);
            original.hidden = true; badge.hidden = true;
            return;
        }
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor(seconds % 86400 / 3600);
        const minutes = Math.floor(seconds % 3600 / 60);
        const secs = seconds % 60;
        clock.hidden = false;
        card.querySelector('[data-countdown]').textContent = `${days ? days + 'd ' : ''}${[hours, minutes, secs].map(n => String(n).padStart(2, '0')).join(':')}`;
    };
    if (discounted && data.offerEndsAt) {
        updateClock();
        if (!clock.hidden) card.countdownInterval = setInterval(updateClock, 1000);
    }
}

const creator = document.querySelector('[data-creator]');
if (creator) {
    const saved = JSON.parse(creator.dataset.created);
    const card = creator.querySelector('[data-product-card]');
    const form = document.querySelector('#product-form');
    const name = form.elements.name;
    const description = form.elements.description;
    const price = form.elements.price;
    const offer = form.elements.offer;
    const ends = form.elements.offer_ends_at;
    const photoInput = document.querySelector('#photos');
    const demo = {
        name: 'Everyday canvas weekender',
        description: 'Your everyday carry, upgraded. A spacious canvas bag with warm leather details, ready for work, weekends and everything in between.',
        price: 4600, currentPrice: 3450, stock: 25, photos: ['/images/weekender.svg'], offerEndsAt: null,
    };
    let files = [];
    let objectUrls = [];
    let currentTab = 'widget';
    let dirty = false;
    const descriptions = { widget: 'Embed a complete product card on your website or blog.', button: 'Add a sale button that takes buyers straight to your product review.', link: 'One simple product link for your socials, messages and emails.' };
    const copyLabels = { widget: 'Copy embed code', button: 'Copy button code', link: 'Copy product link' };
    const placeholders = { widget: 'Create your widget to get your embed code.', button: 'Create your widget to get your sale button code.', link: 'Create your widget to get your product link.' };
    function updateSharing() {
        creator.querySelectorAll('[data-tab]').forEach(button => { const selected = button.dataset.tab === currentTab; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; });
        document.querySelector('[data-share-description]').textContent = descriptions[currentTab];
        document.querySelector('.button-sample').hidden = currentTab !== 'button';
        const code = document.querySelector('#share-code');
        const ready = saved && !dirty;
        code.value = ready ? (currentTab === 'widget'
            ? `<iframe src="${saved.embedUrl}" title="${saved.name.replace(/[&<>"']/g, '')}" width="420" height="740" style="border:0;max-width:100%;" loading="lazy"></iframe>`
            : currentTab === 'button'
                ? `<a href="${saved.buyUrl}" target="_blank" rel="noopener" style="display:inline-block;padding:14px 24px;background:#ffb66e;color:#171b18;border:1px solid #f2a352;border-radius:8px;font:600 16px Arial,sans-serif;text-decoration:none;">Buy now on Ne_Woo</a>`
                : saved.url) : '';
        code.placeholder = dirty ? 'Save your new product to generate its sharing options.' : placeholders[currentTab];
        const copy = document.querySelector('[data-copy]');
        copy.textContent = copyLabels[currentTab]; copy.disabled = !ready;
        document.querySelector('[data-share-help]').textContent = ready ? (['localhost', '127.0.0.1', '[::1]'].includes(new URL(saved.url).hostname) ? 'Local preview link · Public sharing needs a hosted domain.' : 'Your product link is ready. Choose a channel to share it.') : dirty ? 'Create your new widget to share these details.' : 'Create your widget to unlock sharing.';
        const destinations = {
            whatsapp: ready ? `https://wa.me/?text=${encodeURIComponent(saved.name + ' ' + saved.url)}` : '',
            facebook: ready ? `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(saved.url)}` : '',
            x: ready ? `https://twitter.com/intent/tweet?text=${encodeURIComponent(saved.name)}&url=${encodeURIComponent(saved.url)}` : '',
            email: ready ? `mailto:?subject=${encodeURIComponent(saved.name)}&body=${encodeURIComponent('Take a look: ' + saved.url)}` : '',
            sms: ready ? `sms:?body=${encodeURIComponent(saved.name + ' ' + saved.url)}` : '',
        };
        Object.entries(destinations).forEach(([key, href]) => {
            const element = document.querySelector(`[data-share-${key}]`);
            element.setAttribute('aria-disabled', String(!ready));
            if (ready) { element.href = href; if (['whatsapp', 'facebook', 'x'].includes(key)) { element.target = '_blank'; element.rel = 'noopener noreferrer'; } }
            else element.removeAttribute('href');
        });
    }
    creator.querySelectorAll('[data-tab]').forEach(button => {
        button.addEventListener('click', () => { currentTab = button.dataset.tab; updateSharing(); });
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const tabs = [...creator.querySelectorAll('[data-tab]')];
            let index = tabs.indexOf(button);
            index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[index].click(); tabs[index].focus();
        });
    });
    document.querySelector('[data-copy]').addEventListener('click', async () => {
        const code = document.querySelector('#share-code');
        try { await navigator.clipboard.writeText(code.value); toast('Copied. Your product is ready to go places.'); }
        catch { code.select(); toast('Select and copy the code from the field above.'); }
    });
    function updatePreview() {
        document.querySelector('[data-name-count]').textContent = `${name.value.length}/120`;
        document.querySelector('[data-description-count]').textContent = `${description.value.length}/600`;
        offer.setCustomValidity(offer.value && Number(offer.value) >= Number(price.value) ? 'Offer price must be lower than the normal price.' : '');
        ends.setCustomValidity(ends.value && (!offer.value || new Date(ends.value + ':00+03:00') <= new Date()) ? 'Choose a future offer end time and enter an offer price.' : '');
        if (saved && !dirty) { renderProduct(card, saved); return; }
        const untouched = !name.value && !description.value && !price.value && !offer.value && !files.length && !form.elements.stock_quantity.value;
        const data = untouched ? { ...demo, stock: form.querySelector('#show_stock_level').checked ? demo.stock : null, inStock: true } : {
            name: name.value || 'Your product name', description: description.value || 'Your product story will appear here. Tell buyers what makes it special.',
            price: Number(price.value) || 0,
            currentPrice: offer.value && Number(offer.value) < Number(price.value) ? Number(offer.value) : Number(price.value) || 0,
            stock: form.querySelector('#show_stock_level').checked ? (form.elements.stock_quantity.value === '' ? undefined : Number(form.elements.stock_quantity.value)) : null,
            inStock: form.elements.stock_quantity.value === '' ? undefined : Number(form.elements.stock_quantity.value) > 0,
            photos: objectUrls, offerEndsAt: ends.value ? ends.value + ':00+03:00' : null,
        };
        renderProduct(card, data, { preview: true });
        document.querySelector('[data-preview-caption]').textContent = untouched ? 'Example product · make it yours' : 'Your product · live preview';
    }
    form.addEventListener('input', event => { if (event.target !== photoInput) { dirty = true; updatePreview(); updateSharing(); } });
    function syncPhotos() {
        objectUrls.forEach(URL.revokeObjectURL); objectUrls = files.map(file => URL.createObjectURL(file));
        const transfer = new DataTransfer(); files.forEach(file => transfer.items.add(file)); photoInput.files = transfer.files;
        const list = document.querySelector('[data-upload-thumbnails]'); list.replaceChildren();
        files.forEach((file, index) => {
            const item = document.createElement('div'); item.className = 'upload-thumb';
            const image = document.createElement('img'); image.src = objectUrls[index]; image.alt = file.name;
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = '×'; remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.addEventListener('click', () => { files.splice(index, 1); syncPhotos(); });
            item.append(image, remove);
            if (index === 0) { const label = document.createElement('span'); label.textContent = 'Cover'; item.append(label); }
            list.append(item);
        });
        dirty = true;
        document.querySelector('[data-upload-help]').textContent = files.length ? `${files.length}/5 photos added. The first photo is your cover.` : 'The first photo is your cover. Choose clear, well-lit images.';
        updatePreview(); updateSharing();
    }
    function addFiles(incoming) {
        const candidates = [...incoming];
        if (files.length + candidates.length > 5) { toast('Choose up to five photos in total.'); photoInput.value = ''; syncPhotos(); return; }
        if (candidates.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) { toast('Use JPG, PNG or WebP photos, up to 5 MB each.'); syncPhotos(); return; }
        files.push(...candidates); syncPhotos();
    }
    photoInput.addEventListener('change', () => addFiles(photoInput.files));
    const drop = document.querySelector('.upload-zone');
    ['dragenter', 'dragover'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.remove('dragover'); }));
    drop.addEventListener('drop', event => addFiles(event.dataTransfer.files));
    form.addEventListener('submit', () => { if (form.checkValidity()) { const button = form.querySelector('[type=submit]'); button.disabled = true; button.textContent = 'Creating your widget…'; } });
    updatePreview(); updateSharing();
}

document.querySelectorAll('[data-public-product]').forEach(root => renderProduct(root.querySelector('[data-product-card]'), JSON.parse(root.dataset.product)));

const deliverySettings = document.querySelector('[data-delivery-settings]');
if (deliverySettings) {
    const pickupCheck = deliverySettings.querySelector('[data-pickup-enabled]');
    const deliveryCheck = deliverySettings.querySelector('[data-delivery-enabled]');
    const update = () => {
        const pickupFields = deliverySettings.querySelector('[data-pickup-fields]');
        const deliveryFields = deliverySettings.querySelector('[data-delivery-fields]');
        pickupFields.hidden = !pickupCheck.checked;
        pickupFields.querySelectorAll('input,textarea').forEach(input => { input.disabled = !pickupCheck.checked; input.required = pickupCheck.checked; });
        deliveryFields.hidden = !deliveryCheck.checked;
        deliveryFields.querySelectorAll('input').forEach(input => { input.disabled = !deliveryCheck.checked; input.required = deliveryCheck.checked; });
        deliveryCheck.setCustomValidity(!pickupCheck.checked && !deliveryCheck.checked ? 'Offer pickup, delivery, or both.' : '');
    };
    pickupCheck.addEventListener('change', update); deliveryCheck.addEventListener('change', update); update();
}

const checkout = document.querySelector('[data-checkout]');
if (checkout) {
    const form = checkout.querySelector('[data-checkout-form]');
    const update = () => {
        const method = form.querySelector('input[name="fulfillment_method"]:checked')?.value;
        const delivery = method === 'delivery';
        const address = form.querySelector('[data-delivery-address]');
        if (address) { address.hidden = !delivery; address.disabled = !delivery; }
        form.querySelector('#email').required = form.querySelector('#notification_channel').value === 'email';
        const quantity = Math.min(99, Math.max(1, Number(form.querySelector('#quantity').value) || 1));
        const subtotal = Number(checkout.dataset.unitPrice) * quantity;
        const deliveryFee = delivery ? Number(checkout.dataset.deliveryFee) : 0;
        checkout.querySelector('[data-summary-quantity]').textContent = quantity;
        checkout.querySelector('[data-summary-subtotal]').textContent = money(subtotal / 100);
        checkout.querySelector('[data-summary-delivery]').textContent = deliveryFee ? money(deliveryFee / 100) : (delivery ? 'Free delivery' : 'Free pickup');
        const buyerFee = Math.max(3000, Math.min(250000, Math.floor((subtotal * Number(checkout.dataset.buyerRate) + 5000) / 10000)));
        checkout.querySelector('[data-summary-buyer-fee]').textContent = money(buyerFee / 100);
        checkout.querySelector('[data-summary-total]').textContent = money((subtotal + deliveryFee + buyerFee) / 100);
    };
    form.addEventListener('input', update); form.addEventListener('change', update); update();
}

for (const button of document.querySelectorAll('[data-copy-field]')) {
    button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copyField);
        try { await navigator.clipboard.writeText(field.value); toast('Copied. Ready to share.'); }
        catch { field.focus(); field.select(); toast('Select and copy the text from the field.'); }
    });
}
const deviceShare = document.querySelector('[data-device-share]');
if (deviceShare && navigator.share) {
    deviceShare.hidden = false;
    deviceShare.addEventListener('click', async () => {
        const data = {title:deviceShare.dataset.title,url:deviceShare.dataset.url,text:deviceShare.dataset.title};
        if (deviceShare.dataset.photo && navigator.canShare) {
            try {
                const response = await fetch(deviceShare.dataset.photo, {credentials:'same-origin'});
                if (response.ok) {
                    const blob = await response.blob();
                    const extension = {'image/jpeg':'jpg','image/png':'png','image/webp':'webp'}[blob.type];
                    if (extension) {
                        const file = new File([blob], `product-photo.${extension}`, {type:blob.type});
                        if (navigator.canShare({files:[file]})) data.files = [file];
                    }
                }
            } catch { /* Link sharing remains available if the photo cannot be loaded. */ }
        }
        try { await navigator.share(data); }
        catch (error) { if (error.name !== 'AbortError') toast('Choose a sharing channel or copy the product link below.'); }
    });
}

const feeForm = document.querySelector('[data-fee-policy]');
if (feeForm) {
    const rows = feeForm.querySelector('[data-fee-rows]');
    let nextIndex = Math.max(-1, ...[...rows.querySelectorAll('select')].map(input => Number(input.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
    feeForm.querySelector('[data-add-fee-tier]').addEventListener('click', () => {
        if (rows.children.length >= 20) return;
        const template = document.querySelector('[data-fee-template]');
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
    });
    rows.addEventListener('click', event => { if (event.target.matches('[data-remove-fee-tier]')) event.target.closest('fieldset').remove(); });
}

const discoveryCarousel = document.querySelector('[data-discovery-carousel]');
if (discoveryCarousel) {
    const track = discoveryCarousel.querySelector('[data-discovery-track]');
    const controls = discoveryCarousel.querySelector('[data-discovery-controls]');
    const [previous, next] = controls.querySelectorAll('button');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function updateDiscoveryControls() {
        const remaining = track.scrollWidth - track.clientWidth;
        controls.hidden = remaining <= 1;
        previous.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= remaining - 1;
    }

    for (const button of [previous, next]) {
        button.addEventListener('click', () => {
            const gap = Number.parseFloat(getComputedStyle(track).columnGap);
            const step = track.firstElementChild.getBoundingClientRect().width + gap;
            track.scrollBy({
                left: Number(button.dataset.discoveryStep) * step,
                behavior: reducedMotion.matches ? 'instant' : 'smooth',
            });
        });
    }

    track.addEventListener('scroll', updateDiscoveryControls, { passive: true });
    new ResizeObserver(updateDiscoveryControls).observe(track);
    updateDiscoveryControls();
}
