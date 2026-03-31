/**
 * Kafe WebApp - Modern JavaScript Implementation
 */

class KafeApp {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.cart = {};
        this.categories = [];
        this.currentView = 'menu';
        this.MENU_API = 'https://olmazorgo.bigsaver.ru/bot/api/menu.php';
        
        // Load cart from localStorage
        this.loadCartFromStorage();
        
        this.init();
    }

    async init() {
        console.log('🚀 Kafe App initializing...');
        this.initTelegram();
        this.setupEventListeners();
        
        // Initial load
        await this.loadMenu();
        this.renderProfile();
        
        // Update cart display after menu is loaded
        this.updateMainButton();
        
        // Hide loading screen after data is ready
        setTimeout(() => {
            this.hideLoading();
        }, 800);
    }

    initTelegram() {
        if (!this.tg) {
            console.warn('Telegram WebApp not available');
            return;
        }

        this.tg.ready();
        this.tg.expand();
        this.tg.enableClosingConfirmation();
        
        // Colors & Theme
        this.tg.setBackgroundColor('#ffffff');
        this.tg.setHeaderColor('#ffffff');

        // Main Button Setup - Hiding as per user request to use internal buttons
        this.tg.MainButton.hide();

        console.log('✅ Telegram initialized:', this.tg.initDataUnsafe?.user?.first_name);
    }

    setupEventListeners() {
        // Comment character counter
        const commentArea = document.getElementById('comment');
        const counter = document.getElementById('comment-count');
        
        commentArea?.addEventListener('input', (e) => {
            const length = e.target.value.length;
            if (counter) counter.textContent = length;
        });
    }

    async loadMenu() {
        try {
            const response = await fetch(this.MENU_API);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            this.categories = await response.json();
            this.renderCategories();
            this.renderMenu();
        } catch (error) {
            console.error('❌ Menu load error:', error);
            document.getElementById('menu').innerHTML = `
                <div style="padding:40px;text-align:center;color:red">
                    <p>Menyuni yuklab bo'lmadi.</p>
                    <button class="primary-btn" onclick="location.reload()">Qayta urinish</button>
                </div>
            `;
        }
    }

    hideLoading() {
        document.getElementById('loading-screen')?.classList.add('hidden');
        document.getElementById('app')?.classList.remove('hidden');
    }

    switchTab(tabId) {
        if (this.currentView === tabId) return;

        // Update UI state
        this.currentView = tabId;
        
        // Update Nav buttons (only for main tabs)
        document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));
        const navBtn = document.getElementById(`nav-${tabId}`);
        if (navBtn) {
            navBtn.classList.add('active');
        } else if (tabId === 'orders') {
            // For orders view, keep profile tab active
            document.getElementById('nav-profile')?.classList.add('active');
        }

        // Update Views
        document.querySelectorAll('.view').forEach(view => view.classList.remove('active'));
        document.getElementById(`view-${tabId}`)?.classList.add('active');

        // Header Update
        this.updateHeader();

        // Specific View Rendering
        if (tabId === 'cart') this.renderCart();
        if (tabId === 'orders') this.loadOrders();
        
        // Sync internal buttons visibility across views
        this.updateMainButton();
        
        // Trigger haptic feedback
        this.tg?.HapticFeedback.selectionChanged();
        
        window.scrollTo(0, 0);
    }

    updateHeader() {
        const texts = { menu: 'Menyu', cart: 'Savat', profile: 'Profil', orders: 'Buyurtmalarim' };
        const emojis = { menu: '🍽️', cart: '🛒', profile: '👤', orders: '📦' };
        
        document.getElementById('header-text').textContent = texts[this.currentView];
        document.getElementById('header-emoji').textContent = emojis[this.currentView];
    }

    renderCategories() {
        const container = document.getElementById('categories');
        if (!container) return;

        container.innerHTML = '';
        this.categories.forEach((cat, i) => {
            const btn = document.createElement('button');
            btn.className = `cat-btn ${i === 0 ? 'active' : ''}`;
            btn.textContent = cat.name;
            btn.onclick = (e) => {
                document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(`cat-${cat.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };
            container.appendChild(btn);
        });
    }

    renderMenu() {
        const container = document.getElementById('menu');
        if (!container) return;

        container.innerHTML = '';
        this.categories.forEach(cat => {
            const section = document.createElement('section');
            section.id = `cat-${cat.id}`;
            section.innerHTML = `<h2 class="section-title">${cat.name}</h2>`;
            
            const grid = document.createElement('div');
            grid.className = 'items-grid';
            
            cat.items.forEach(item => {
                grid.appendChild(this.createItemCard(item));
            });
            
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    createItemCard(item) {
        const card = document.createElement('div');
        card.className = 'item-card';
        
        const qty = this.cart[item.id]?.quantity || 0;
        
        card.innerHTML = `
            ${item.image_url ? `<img src="${item.image_url}" alt="${item.name}" loading="lazy">` : `<div class="no-img">🍽️</div>`}
            <div class="item-info">
                <div class="item-name">${item.name}</div>
                ${item.description ? `<div class="item-desc">${item.description}</div>` : ''}
                <div class="item-price">${this.formatPrice(item.price)} so'm</div>
            </div>
            <div class="item-controls" id="controls-${item.id}">
                ${qty > 0 ? this.getQtyControlsHtml(item, qty) : `<button class="add-btn" onclick="app.addToCart(${item.id}, '${item.name}', ${item.price})">Qo'shish</button>`}
            </div>
        `;
        return card;
    }

    getQtyControlsHtml(item, qty) {
        return `
            <div class="qty-controls">
                <button class="qty-btn" onclick="app.changeQty(${item.id}, -1)">−</button>
                <span class="qty-display">${qty}</span>
                <button class="qty-btn" onclick="app.changeQty(${item.id}, 1)">+</button>
            </div>
        `;
    }

    addToCart(id, name, price) {
        this.cart[id] = { id, name, price, quantity: 1 };
        this.updateItemDisplay(id);
        this.updateMainButton();
        this.saveCartToStorage();
        this.tg?.HapticFeedback.impactOccurred('light');
    }

    changeQty(id, delta) {
        if (!this.cart[id]) return;
        
        this.cart[id].quantity += delta;
        if (this.cart[id].quantity <= 0) {
            delete this.cart[id];
        }
        
        this.updateItemDisplay(id);
        this.updateMainButton();
        if (this.currentView === 'cart') this.renderCart();
        this.saveCartToStorage();
        this.tg?.HapticFeedback.impactOccurred('light');
    }

    updateItemDisplay(id) {
        const container = document.getElementById(`controls-${id}`);
        if (!container) return;
        
        const item = this.findItemById(id);
        const qty = this.cart[id]?.quantity || 0;
        
        container.innerHTML = qty > 0 
            ? this.getQtyControlsHtml(item, qty) 
            : `<button class="add-btn" onclick="app.addToCart(${item.id}, '${item.name}', ${item.price})">Qo'shish</button>`;
    }

    updateMainButton() {
        const items = Object.values(this.cart);
        const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
        const count = items.reduce((s, i) => s + i.quantity, 0);

        // Header Badge
        const badge = document.getElementById('cart-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'block' : 'none';
        }

        // Internal Checkout Footer (Menu view)
        const menuFooter = document.getElementById('menu-checkout-footer');
        const menuTotalPrice = document.getElementById('menu-total-price');
        if (menuFooter) {
            if (count > 0 && this.currentView === 'menu') {
                menuFooter.classList.remove('hidden');
                if (menuTotalPrice) menuTotalPrice.textContent = this.formatPrice(total);
            } else {
                menuFooter.classList.add('hidden');
            }
        }

        // Always hide Telegram's MainButton as requested by user
        if (this.tg) {
            this.tg.MainButton.hide();
        }
    }

    renderCart() {
        const list = document.getElementById('cart-items-list');
        const empty = document.getElementById('cart-empty');
        const summary = document.getElementById('cart-summary-details');
        
        const items = Object.values(this.cart);
        
        if (items.length === 0) {
            list.innerHTML = '';
            empty.classList.remove('hidden');
            summary.classList.add('hidden');
        } else {
            empty.classList.add('hidden');
            summary.classList.remove('hidden');
            list.innerHTML = '';
            
            items.forEach(item => {
                const itemData = this.findItemById(item.id);
                const row = document.createElement('div');
                row.className = 'cart-item';
                row.innerHTML = `
                    ${itemData?.image_url ? `<img src="${itemData.image_url}" class="cart-item-img">` : `<div class="cart-item-img no-img" style="font-size:1.5rem">🍽️</div>`}
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">${this.formatPrice(item.price)} so'm</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="qty-btn" style="width:28px;height:28px" onclick="app.changeQty(${item.id}, -1)">−</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" style="width:28px;height:28px" onclick="app.changeQty(${item.id}, 1)">+</button>
                    </div>
                `;
                list.appendChild(row);
            });
            
            const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
            const count = items.reduce((s, i) => s + i.quantity, 0);
            
            document.getElementById('summary-items-count').textContent = `${count} ta`;
            document.getElementById('summary-total-price').textContent = `${this.formatPrice(total)} so'm`;
            
            this.updateMainButton();
        }
    }



    findItemById(id) {
        for (const cat of this.categories) {
            const item = cat.items.find(i => i.id == id);
            if (item) return item;
        }
        return null;
    }

    renderProfile() {
        if (!this.tg || !this.tg.initDataUnsafe?.user) return;
        
        const user = this.tg.initDataUnsafe.user;
        document.getElementById('user-full-name').textContent = `${user.first_name} ${user.last_name || ''}`;
        document.getElementById('user-telegram-id').textContent = user.username ? `@${user.username}` : `ID: ${user.id}`;
        
        if (user.photo_url) {
            const avatar = document.getElementById('user-avatar');
            avatar.innerHTML = `<img src="${user.photo_url}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">`;
        }
    }

    async loadOrders() {
        const ordersList = document.getElementById('orders-list');
        const ordersEmpty = document.getElementById('orders-empty');
        
        try {
            // Bu yerda buyurtmalar API dan yuklanadi
            // Hozircha demo ma'lumotlar
            const orders = await this.fetchUserOrders();
            
            if (orders.length === 0) {
                ordersList.innerHTML = '';
                ordersEmpty.classList.remove('hidden');
            } else {
                ordersEmpty.classList.add('hidden');
                ordersList.innerHTML = '';
                
                orders.forEach(order => {
                    const orderCard = this.createOrderCard(order);
                    ordersList.appendChild(orderCard);
                });
            }
        } catch (error) {
            console.error('Buyurtmalarni yuklashda xato:', error);
            ordersList.innerHTML = '<div style="text-align:center;padding:20px;color:red">Buyurtmalarni yuklab bo\'lmadi</div>';
        }
    }

    async fetchUserOrders() {
        // Bu yerda haqiqiy API chaqiruvi bo'lishi kerak
        // Hozircha demo ma'lumotlar qaytaradi
        return [
            {
                id: 1001,
                date: '2024-03-15',
                status: 'completed',
                items: [
                    { name: 'Osh', quantity: 2, price: 25000 },
                    { name: 'Choy', quantity: 1, price: 5000 }
                ],
                total: 55000
            },
            {
                id: 1002,
                date: '2024-03-14',
                status: 'pending',
                items: [
                    { name: 'Manti', quantity: 1, price: 30000 }
                ],
                total: 30000
            }
        ];
    }

    createOrderCard(order) {
        const card = document.createElement('div');
        card.className = 'order-card';
        
        const statusText = {
            pending: 'Kutilmoqda',
            completed: 'Tayyor',
            cancelled: 'Bekor qilingan'
        };
        
        card.innerHTML = `
            <div class="order-header">
                <div>
                    <div class="order-id">Buyurtma #${order.id}</div>
                    <div class="order-date">${this.formatDate(order.date)}</div>
                </div>
                <div class="order-status ${order.status}">${statusText[order.status]}</div>
            </div>
            <div class="order-items">
                ${order.items.map(item => `
                    <div class="order-item">
                        <span>${item.name} x${item.quantity}</span>
                        <span>${this.formatPrice(item.price * item.quantity)} so'm</span>
                    </div>
                `).join('')}
            </div>
            <div class="order-total">
                <span>Jami:</span>
                <span>${this.formatPrice(order.total)} so'm</span>
            </div>
        `;
        
        return card;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('uz-UZ', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    submitOrder() {
        const items = Object.values(this.cart).map(i => ({
            menu_item_id: i.id,
            name: i.name,
            price: i.price,
            quantity: i.quantity,
        }));
        
        if (items.length === 0) return;
        
        const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
        const comment = document.getElementById('comment').value.trim();
        
        const data = JSON.stringify({ items, total, comment });
        
        // Show success
        document.getElementById('success-animation').classList.remove('hidden');
        this.tg?.HapticFeedback.notificationOccurred('success');
        
        // Clear cart after successful order
        this.cart = {};
        this.saveCartToStorage();
        
        setTimeout(() => {
            this.tg?.sendData(data);
            this.tg?.close();
        }, 2000);
    }

    // Cart storage methods
    saveCartToStorage() {
        try {
            localStorage.setItem('kafe_cart', JSON.stringify(this.cart));
        } catch (error) {
            console.warn('Could not save cart to localStorage:', error);
        }
    }

    loadCartFromStorage() {
        try {
            const savedCart = localStorage.getItem('kafe_cart');
            if (savedCart) {
                this.cart = JSON.parse(savedCart);
            }
        } catch (error) {
            console.warn('Could not load cart from localStorage:', error);
            this.cart = {};
        }
    }

    clearCart() {
        this.cart = {};
        this.saveCartToStorage();
        this.updateMainButton();
        if (this.currentView === 'cart') this.renderCart();
        // Update all item displays
        Object.keys(this.cart).forEach(id => this.updateItemDisplay(id));
    }
}

// Global initialization
let app;
window.addEventListener('DOMContentLoaded', () => {
    app = new KafeApp();
    // Expose app globally for onclick handlers
    window.app = app;
});
    formatPrice(n) {
        return Number(n).toLocaleString('uz-UZ').replace(/,/g, ' ');
    }