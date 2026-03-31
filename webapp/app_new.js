console.log('App_new.js yuklandi - Versiya:', new Date().toISOString());

// Debug funksiyasi
function updateDebug(text) {
    const debugEl = document.getElementById('debug-text');
    if (debugEl) {
        debugEl.textContent = text;
    }
    console.log('DEBUG:', text);
}

updateDebug('JavaScript yuklandi');

// Telegram WebApp mavjudligini tekshirish
if (!window.Telegram || !window.Telegram.WebApp) {
    console.error('Telegram WebApp mavjud emas!');
    updateDebug('Telegram WebApp mavjud emas');
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('menu').innerHTML = `
            <div style="padding:20px;text-align:center;color:red">
                <h3>Xato!</h3>
                <p>Bu ilova faqat Telegram bot ichida ishlaydi.</p>
                <p>Iltimos, botni Telegram orqali oching.</p>
                <button onclick="location.reload()" style="padding:10px;background:#2481cc;color:white;border:none;border-radius:4px">Qayta urinish</button>
            </div>
        `;
    });
} else {
    updateDebug('Telegram WebApp topildi');
    
    const tg = window.Telegram.WebApp;
    console.log('Telegram WebApp ishga tushmoqda...');
    tg.ready();
    tg.expand();
    tg.setBackgroundColor('#ffffff');
    tg.setHeaderColor('#ffffff');

    console.log('Telegram WebApp sozlamalari:', {
        isExpanded: tg.isExpanded,
        viewportHeight: tg.viewportHeight,
        themeParams: tg.themeParams
    });

    document.documentElement.style.background = '#ffffff';
    document.body.style.background = '#ffffff';
    document.body.style.color = tg.themeParams.text_color || '#000000';
    
    updateDebug('Telegram WebApp sozlandi');
}

const MENU_API = 'https://olmazor.bigsaver.ru/bot/api/menu.php';
const cart = {};

async function loadMenu() {
    updateDebug('Menu yuklanmoqda...');
    console.log('Menu yuklanmoqda...');
    document.getElementById('menu').innerHTML = '<p style="padding:20px;text-align:center">Menu yuklanmoqda...</p>';
    
    try {
        console.log('API chaqirilmoqda:', MENU_API);
        updateDebug('API chaqirilmoqda');
        
        const res = await fetch(MENU_API, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            cache: 'no-cache'
        });
        
        console.log('Javob holati:', res.status);
        updateDebug(`API javob: ${res.status}`);
        
        if (!res.ok) {
            throw new Error(`Server xatosi: ${res.status} - ${res.statusText}`);
        }
        
        const text = await res.text();
        console.log('Server javobi:', text);
        
        let categories;
        try {
            categories = JSON.parse(text);
        } catch (parseError) {
            console.error('JSON parse xatosi:', parseError);
            throw new Error('JSON parse xatosi: ' + parseError.message);
        }
        
        console.log('Kategoriyalar yuklandi:', categories);
        updateDebug(`${categories.length} kategoriya yuklandi`);
        
        if (!Array.isArray(categories) || categories.length === 0) {
            document.getElementById('menu').innerHTML = '<p style="padding:20px;text-align:center">Menyu bo\'sh yoki noto\'g\'ri format</p>';
            updateDebug('Menyu bo\'sh');
            return;
        }
        
        renderCategories(categories);
        renderMenu(categories);
        updateDebug('Menu muvaffaqiyatli yuklandi');
        console.log('Menu muvaffaqiyatli yuklandi');
        
    } catch (e) {
        console.error('Menu yuklashda xato:', e);
        updateDebug(`Xato: ${e.message}`);
        document.getElementById('menu').innerHTML = `
            <div style="padding:20px;color:red;text-align:center">
                <h3>Xato yuz berdi:</h3>
                <p>${e.message}</p>
                <p style="font-size:12px;margin-top:10px">API: ${MENU_API}</p>
                <button onclick="loadMenu()" style="margin-top:10px;padding:8px 16px;background:#2481cc;color:white;border:none;border-radius:4px">Qayta urinish</button>
                <button onclick="testAPI()" style="margin-top:10px;padding:8px 16px;background:#28a745;color:white;border:none;border-radius:4px">API test</button>
            </div>
        `;
    }
}

function testAPI() {
    updateDebug('API test...');
    window.open(MENU_API, '_blank');
}

function renderCategories(categories) {
    const el = document.getElementById('categories');
    el.innerHTML = '';
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
    el.innerHTML = '';
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
    if (!window.Telegram?.WebApp) return;
    
    const items = Object.values(cart);
    const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
    if (items.length === 0) {
        window.Telegram.WebApp.MainButton.hide();
    } else {
        window.Telegram.WebApp.MainButton.setText(`🛒 Buyurtma berish — ${formatPrice(total)} so'm`);
        window.Telegram.WebApp.MainButton.show();
        window.Telegram.WebApp.MainButton.color = window.Telegram.WebApp.themeParams.button_color || '#2481cc';
    }
}

if (window.Telegram?.WebApp) {
    window.Telegram.WebApp.MainButton.onClick(() => {
        const items = Object.values(cart).map(i => ({
            menu_item_id: i.id,
            name: i.name,
            price: i.price,
            quantity: i.quantity,
        }));
        const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
        const comment = document.getElementById('comment').value.trim();
        window.Telegram.WebApp.sendData(JSON.stringify({ items, total, comment }));
    });
}

function formatPrice(n) {
    return Number(n).toLocaleString('uz-UZ');
}

// Sahifa yuklanganda menuni yuklash
document.addEventListener('DOMContentLoaded', () => {
    updateDebug('DOM yuklandi');
    setTimeout(() => {
        loadMenu();
    }, 1000);
});

console.log('App_new.js to\'liq yuklandi');