const tg = window.Telegram.WebApp;
tg.ready();
tg.expand();

// Theme ranglarini darhol o'rnatish
document.body.style.background = tg.themeParams.bg_color || '#ffffff';
document.body.style.color = tg.themeParams.text_color || '#000000';

const MENU_API = '/bot/api/menu.php';
const cart = {};

async function loadMenu() {
    try {
        const res = await fetch(MENU_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const categories = await res.json();
        if (!categories.length) {
            document.getElementById('menu').innerHTML = '<p style="padding:20px">Menyu bo\'sh</p>';
            return;
        }
        renderCategories(categories);
        renderMenu(categories);
    } catch (e) {
        document.getElementById('menu').innerHTML =
            `<p style="padding:20px;color:red">Xato: ${e.message}</p>`;
    }
}

function renderCategories(categories) {
    const el = document.getElementById('categories');
    categories.forEach((cat, i) => {
        const btn = document.createElement('button');
        btn.className = 'cat-btn' + (i === 0 ? ' active' : '');
        btn.textContent = cat.name;
        btn.onclick = () => {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('cat-' + cat.id)?.scrollIntoView({ behavior: 'smooth' });
        };
        el.appendChild(btn);
    });
}

function renderMenu(categories) {
    const el = document.getElementById('menu');
    categories.forEach(cat => {
        const section = document.createElement('div');
        section.id = 'cat-' + cat.id;
        section.innerHTML = `<div class="section-title">${cat.name}</div>`;
        const grid = document.createElement('div');
        grid.className = 'items-grid';
        cat.items.forEach(item => grid.appendChild(createCard(item)));
        section.appendChild(grid);
        el.appendChild(section);
    });
}

function createCard(item) {
    const card = document.createElement('div');
    card.className = 'item-card';
    const img = item.image_url
        ? `<img src="${item.image_url}" alt="${item.name}" loading="lazy">`
        : `<div class="no-img">🍽</div>`;
    card.innerHTML = `
        ${img}
        <div class="item-info">
            <div class="item-name">${item.name}</div>
            ${item.description ? `<div class="item-desc">${item.description}</div>` : ''}
            <div class="item-price">${formatPrice(item.price)} so'm</div>
        </div>
        <div class="item-controls">
            <button class="qty-btn" onclick="changeQty(${item.id}, -1, this)">−</button>
            <span class="qty-display" id="qty-${item.id}">0</span>
            <button class="qty-btn" onclick="changeQty(${item.id}, 1, this)" data-item='${JSON.stringify({id: item.id, name: item.name, price: parseFloat(item.price)})}'>+</button>
        </div>`;
    return card;
}

function changeQty(id, delta, btn) {
    const itemData = JSON.parse(btn.closest('.item-controls').querySelector('[data-item]').dataset.item);
    cart[id] = cart[id] || { ...itemData, quantity: 0 };
    cart[id].quantity = Math.max(0, cart[id].quantity + delta);
    if (cart[id].quantity === 0) delete cart[id];
    document.getElementById('qty-' + id).textContent = cart[id]?.quantity ?? 0;
    updateMainButton();
}

function updateMainButton() {
    const items = Object.values(cart);
    const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
    if (items.length === 0) {
        tg.MainButton.hide();
    } else {
        tg.MainButton.setText(`🛒 Buyurtma berish — ${formatPrice(total)} so'm`);
        tg.MainButton.show();
        tg.MainButton.color = tg.themeParams.button_color || '#2481cc';
    }
}

tg.MainButton.onClick(() => {
    const items = Object.values(cart).map(i => ({
        menu_item_id: i.id,
        name: i.name,
        price: i.price,
        quantity: i.quantity,
    }));
    const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
    const comment = document.getElementById('comment').value.trim();
    tg.sendData(JSON.stringify({ items, total, comment }));
});

function formatPrice(n) {
    return Number(n).toLocaleString('uz-UZ');
}

loadMenu();
