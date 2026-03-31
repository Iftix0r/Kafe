// ===== FULL FEATURED FOOD APP =====

class FullFoodApp {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.cart = {};
        this.categories = [];
        this.allItems = [];
        this.currentScreen = 'menu';
        
        this.MENU_API = 'https://olmazorgo.bigsaver.ru/bot/api/menu.php';
        
        this.init();
    }

    init() {
        console.log('🍽 Full Food App starting...');
        
        // Initialize Telegram WebApp
        this.initTelegram();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Load menu
        setTimeout(() => {
            this.loadMenu();
        }, 1000);
    }

    initTelegram() {
        if (!this.tg) {
            console.error('Telegram WebApp not available');
            return;
        }

        this.tg.ready();
        this.tg.expand();
        this.tg.setBackgroundColor('#f5f5f5');
        this.tg.setHeaderColor('#ffffff');
        
        // Hide main button initially
        this.tg.MainButton.hide();
        
        console.log('Telegram WebApp ready');
    }

    setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', () => this.searchItems());
        }

        // Comment textarea auto-resize
        const comment = document.getElementById('order-comment');
        if (comment) {
            comment.addEventListener('input', (e) => {
                e.target.style.height = 'auto';
                e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            });
        }
    }

    async loadMenu() {
        try {
            console.log('Loading menu...');
            
            const response = await fetch(this.MENU_API);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const categories = await response.json();
            
            if (!Array.isArray(categories) || categories.length === 0) {
                throw new Error('Menu bo\'sh');
            }

            this.categories = categories;
            this.allItems = categories.flatMap(cat => 
                cat.items.map(item => ({...item, categoryId: cat.id, categoryName: cat.name}))
            );
            
            this.hideLoading();
            this.renderCategories();
            this.renderMenuItems();
            
            console.log('Menu loaded successfully');
            
        } catch (error) {
            console.error('Menu loading error:', error);
            this.hideLoading();
            this.showError(error.message);
        }
    }

    hideLoading() {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('menu-screen').classList.remove('hidden');
    }

    renderCategories() {
        const container = document.getElementById('categories');
        const allBtn = container.querySelector('.category-btn');
        
        this.categories.forEach(category => {
            const button = document.createElement('button');
            button.className = 'category-btn';
            button.textContent = category.name;
            button.onclick = () => this.filterCategory(category.id);
            
            container.appendChild(button);
        });
    }

    renderMenuItems(items = this.allItems) {
        const container = document.getElementById('menu-items');
        container.innerHTML = '';

        if (items.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <h3>Hech narsa topilmadi</h3>
                    <p>Boshqa kalit so'z bilan qidiring</p>
                </div>
            `;
            return;
        }

        items.forEach(item => {
            const itemElement = this.createMenuItem(item);
            container.appendChild(itemElement);
        });
    }

    createMenuItem(item) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'menu-item';

        const currentQty = this.cart[item.id]?.quantity || 0;

        itemDiv.innerHTML = `
            <div class="item-content">
                ${item.image_url 
                    ? `<img src="${item.image_url}" alt="${item.name}" class="item-image">`
                    : `<div class="no-image">🍽️</div>`
                }
                <div class="item-info">
                    <div class="item-name">${this.escapeHtml(item.name)}</div>
                    ${item.description ? `<div class="item-description">${this.escapeHtml(item.description)}</div>` : ''}
                    <div class="item-price">${this.formatPrice(item.price)} so'm</div>
                </div>
            </div>
            <div class="item-controls">
                ${currentQty > 0 ? `
                    <div class="quantity-controls">
                        <button class="qty-btn minus" onclick="app.changeQuantity(${item.id}, -1)">−</button>
                        <span class="qty-display">${currentQty}</span>
                        <button class="qty-btn" onclick="app.changeQuantity(${item.id}, 1)">+</button>
                    </div>
                ` : `
                    <button class="add-btn" onclick="app.addToCart(${item.id}, '${this.escapeHtml(item.name)}', ${item.price})">
                        Qo'shish
                    </button>
                `}
            </div>
        `;

        return itemDiv;
    }

    filterCategory(categoryId) {
        // Update active button
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        if (categoryId === 'all') {
            document.querySelector('.category-btn').classList.add('active');
            this.renderMenuItems(this.allItems);
        } else {
            event.target.classList.add('active');
            const filteredItems = this.allItems.filter(item => item.categoryId == categoryId);
            this.renderMenuItems(filteredItems);
        }
    }

    searchItems() {
        const query = document.getElementById('search').value.toLowerCase().trim();
        
        if (query === '') {
            this.renderMenuItems(this.allItems);
            return;
        }

        const filteredItems = this.allItems.filter(item => 
            item.name.toLowerCase().includes(query) ||
            (item.description && item.description.toLowerCase().includes(query))
        );

        this.renderMenuItems(filteredItems);
    }

    addToCart(itemId, itemName, itemPrice) {
        this.cart[itemId] = {
            id: itemId,
            name: itemName,
            price: parseFloat(itemPrice),
            quantity: 1
        };

        this.updateItemDisplay(itemId);
        this.updateCartUI();
        
        console.log('Added to cart:', this.cart[itemId]);
    }

    changeQuantity(itemId, delta) {
        if (!this.cart[itemId]) return;

        this.cart[itemId].quantity += delta;

        if (this.cart[itemId].quantity <= 0) {
            delete this.cart[itemId];
        }

        this.updateItemDisplay(itemId);
        this.updateCartUI();
    }

    updateItemDisplay(itemId) {
        // Find and re-render the item
        const itemData = this.allItems.find(item => item.id == itemId);
        if (!itemData) return;

        // Find all item elements and update the one with matching ID
        const items = document.querySelectorAll('.menu-item');
        items.forEach(itemElement => {
            const controls = itemElement.querySelector('.item-controls');
            const nameElement = itemElement.querySelector('.item-name');
            
            if (nameElement && nameElement.textContent === itemData.name) {
                const currentQty = this.cart[itemId]?.quantity || 0;
                
                controls.innerHTML = currentQty > 0 ? `
                    <div class="quantity-controls">
                        <button class="qty-btn minus" onclick="app.changeQuantity(${itemId}, -1)">−</button>
                        <span class="qty-display">${currentQty}</span>
                        <button class="qty-btn" onclick="app.changeQuantity(${itemId}, 1)">+</button>
                    </div>
                ` : `
                    <button class="add-btn" onclick="app.addToCart(${itemId}, '${this.escapeHtml(itemData.name)}', ${itemData.price})">
                        Qo'shish
                    </button>
                `;
            }
        });
    }

    updateCartUI() {
        const items = Object.values(this.cart);
        const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
        const totalPrice = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        // Update cart badge
        const badge = document.getElementById('cart-badge');
        if (badge) {
            badge.textContent = totalItems;
        }

        // Update cart summary
        const cartSummary = document.getElementById('cart-summary');
        const cartCount = document.getElementById('cart-count');
        const cartTotal = document.getElementById('cart-total');

        if (totalItems > 0) {
            cartSummary?.classList.remove('hidden');
            if (cartCount) cartCount.textContent = `${totalItems} ta mahsulot`;
            if (cartTotal) cartTotal.textContent = `${this.formatPrice(totalPrice)} so'm`;
        } else {
            cartSummary?.classList.add('hidden');
        }
    }

    showCart() {
        const items = Object.values(this.cart);
        if (items.length === 0) {
            alert('Savat bo\'sh');
            return;
        }

        this.currentScreen = 'cart';
        document.getElementById('menu-screen').classList.add('hidden');
        document.getElementById('cart-screen').classList.remove('hidden');
        
        this.renderCartItems();
        this.updateCartSummary();
    }

    showMenu() {
        this.currentScreen = 'menu';
        document.getElementById('cart-screen').classList.add('hidden');
        document.getElementById('menu-screen').classList.remove('hidden');
    }

    renderCartItems() {
        const container = document.getElementById('cart-items');
        const items = Object.values(this.cart);

        if (items.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="icon">🛒</div>
                    <h3>Savat bo'sh</h3>
                    <p>Mahsulot qo'shish uchun menuga qayting</p>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        items.forEach(item => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'cart-item';

            itemDiv.innerHTML = `
                <div class="cart-item-header">
                    <div class="cart-item-name">${this.escapeHtml(item.name)}</div>
                    <div class="cart-item-price">${this.formatPrice(item.price * item.quantity)} so'm</div>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button class="qty-btn minus" onclick="app.changeQuantity(${item.id}, -1)">−</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="app.changeQuantity(${item.id}, 1)">+</button>
                    </div>
                    <button class="remove-btn" onclick="app.removeFromCart(${item.id})">O'chirish</button>
                </div>
            `;

            container.appendChild(itemDiv);
        });
    }

    removeFromCart(itemId) {
        delete this.cart[itemId];
        this.updateCartUI();
        this.renderCartItems();
        this.updateCartSummary();
    }

    updateCartSummary() {
        const items = Object.values(this.cart);
        const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        const subtotalEl = document.getElementById('summary-subtotal');
        const totalEl = document.getElementById('summary-total');
        
        if (subtotalEl) subtotalEl.textContent = `${this.formatPrice(subtotal)} so'm`;
        if (totalEl) totalEl.textContent = `${this.formatPrice(subtotal)} so'm`;
    }

    getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    // Reverse geocoding (simplified)
                    document.getElementById('address').value = `Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`;
                },
                (error) => {
                    alert('Joylashuvni aniqlab bo\'lmadi');
                }
            );
        } else {
            alert('Geolocation qo\'llab-quvvatlanmaydi');
        }
    }

    submitOrder() {
        const items = Object.values(this.cart);
        if (items.length === 0) return;

        const address = document.getElementById('address').value.trim();
        if (!address) {
            alert('Manzilni kiriting');
            return;
        }

        const comment = document.getElementById('order-comment').value.trim();
        const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
        const totalPrice = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        const orderData = {
            items: items.map(item => ({
                menu_item_id: item.id,
                name: item.name,
                price: item.price,
                quantity: item.quantity
            })),
            total: totalPrice,
            comment: comment,
            address: address,
            payment_method: paymentMethod
        };

        console.log('Submitting order:', orderData);

        try {
            // Show confirmation screen
            this.showConfirmation(orderData);

            // Send to Telegram (if available)
            if (this.tg) {
                this.tg.sendData(JSON.stringify(orderData));
            }

        } catch (error) {
            console.error('Order submission error:', error);
            alert('Buyurtma yuborishda xatolik yuz berdi');
        }
    }

    showConfirmation(orderData) {
        this.currentScreen = 'confirmation';
        document.getElementById('cart-screen').classList.add('hidden');
        document.getElementById('confirmation-screen').classList.remove('hidden');

        // Generate order number
        const orderNumber = '#' + Math.floor(Math.random() * 10000);
        const orderDate = new Date().toLocaleString('uz-UZ');

        document.getElementById('order-number').textContent = orderNumber;
        document.getElementById('order-date').textContent = orderDate;
        document.getElementById('order-amount').textContent = `${this.formatPrice(orderData.total)} so'm`;

        // Animate status progress
        setTimeout(() => {
            this.animateStatusProgress();
        }, 1000);
    }

    animateStatusProgress() {
        const steps = document.querySelectorAll('.status-step');
        
        // Simulate progress
        setTimeout(() => {
            steps[1].classList.add('active');
        }, 2000);
        
        setTimeout(() => {
            steps[2].classList.add('active');
        }, 4000);
    }

    goHome() {
        // Clear cart and return to menu
        this.cart = {};
        this.updateCartUI();
        this.currentScreen = 'menu';
        
        document.getElementById('confirmation-screen').classList.add('hidden');
        document.getElementById('menu-screen').classList.remove('hidden');
        
        // Refresh menu items
        this.renderMenuItems();
    }

    trackOrder() {
        alert('Buyurtmani kuzatish funksiyasi tez orada qo\'shiladi');
    }

    showError(message) {
        const container = document.getElementById('menu-items');
        container.innerHTML = `
            <div class="empty-state">
                <div class="icon">😕</div>
                <h3>Xatolik yuz berdi</h3>
                <p>${message}</p>
                <button class="retry-btn" onclick="location.reload()">Qayta urinish</button>
            </div>
        `;
    }

    formatPrice(price) {
        return Number(price).toLocaleString('uz-UZ');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for onclick handlers
window.showCart = () => app?.showCart();
window.showMenu = () => app?.showMenu();
window.filterCategory = (categoryId) => app?.filterCategory(categoryId);
window.searchItems = () => app?.searchItems();
window.getLocation = () => app?.getLocation();
window.submitOrder = () => app?.submitOrder();
window.goHome = () => app?.goHome();
window.trackOrder = () => app?.trackOrder();

// Initialize app
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new FullFoodApp();
});

// Global reference for onclick handlers
window.app = app;