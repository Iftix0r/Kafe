// ===== SIMPLE FOOD APP =====

class SimpleFoodApp {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.cart = {};
        this.categories = [];
        
        this.MENU_API = 'https://olmazorgo.bigsaver.ru/bot/api/menu.php';
        
        this.init();
    }

    init() {
        console.log('🍽 Simple Food App starting...');
        
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
        
        // Setup main button
        this.tg.MainButton.onClick(() => this.submitOrder());
        
        console.log('Telegram WebApp ready');
    }

    setupEventListeners() {
        // Comment textarea
        const comment = document.getElementById('comment');
        if (comment) {
            comment.addEventListener('input', (e) => {
                // Auto resize
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
            this.hideLoading();
            this.renderCategories();
            this.renderMenu();
            
            console.log('Menu loaded successfully');
            
        } catch (error) {
            console.error('Menu loading error:', error);
            this.hideLoading();
            this.showError(error.message);
        }
    }

    hideLoading() {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('app').classList.remove('hidden');
    }

    renderCategories() {
        const container = document.getElementById('categories');
        container.innerHTML = '';

        this.categories.forEach((category, index) => {
            const button = document.createElement('button');
            button.className = `category-btn ${index === 0 ? 'active' : ''}`;
            button.textContent = category.name;
            button.onclick = () => this.selectCategory(button, category.id);
            
            container.appendChild(button);
        });
    }

    selectCategory(button, categoryId) {
        // Update active state
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');

        // Scroll to section
        const section = document.getElementById(`section-${categoryId}`);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth' });
        }
    }

    renderMenu() {
        const container = document.getElementById('menu');
        container.innerHTML = '';

        this.categories.forEach(category => {
            if (!category.items || category.items.length === 0) return;

            const section = document.createElement('div');
            section.className = 'section';
            section.id = `section-${category.id}`;

            const title = document.createElement('h2');
            title.className = 'section-title';
            title.textContent = category.name;

            const items = document.createElement('div');
            items.className = 'items';

            category.items.forEach(item => {
                const itemElement = this.createItem(item);
                items.appendChild(itemElement);
            });

            section.appendChild(title);
            section.appendChild(items);
            container.appendChild(section);
        });
    }

    createItem(item) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'item';

        const currentQty = this.cart[item.id]?.quantity || 0;

        itemDiv.innerHTML = `
            <div class="item-header">
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

    addToCart(itemId, itemName, itemPrice) {
        this.cart[itemId] = {
            id: itemId,
            name: itemName,
            price: parseFloat(itemPrice),
            quantity: 1
        };

        this.updateItemDisplay(itemId);
        this.updateMainButton();
        
        console.log('Added to cart:', this.cart[itemId]);
    }

    changeQuantity(itemId, delta) {
        if (!this.cart[itemId]) return;

        this.cart[itemId].quantity += delta;

        if (this.cart[itemId].quantity <= 0) {
            delete this.cart[itemId];
        }

        this.updateItemDisplay(itemId);
        this.updateMainButton();
    }

    updateItemDisplay(itemId) {
        // Find and re-render the item
        const itemData = this.findItemById(itemId);
        if (!itemData) return;

        // Find all item elements and update the one with matching ID
        const items = document.querySelectorAll('.item');
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

    updateMainButton() {
        if (!this.tg) return;

        const items = Object.values(this.cart);
        const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
        const totalPrice = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        if (totalItems > 0) {
            this.tg.MainButton.setText(`Buyurtma berish (${totalItems} ta) — ${this.formatPrice(totalPrice)} so'm`);
            this.tg.MainButton.show();
            this.tg.MainButton.color = '#007AFF';
        } else {
            this.tg.MainButton.hide();
        }
    }

    async submitOrder() {
        const items = Object.values(this.cart);
        if (items.length === 0) return;

        const totalPrice = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const comment = document.getElementById('comment')?.value.trim() || '';

        const orderData = {
            items: items.map(item => ({
                menu_item_id: item.id,
                name: item.name,
                price: item.price,
                quantity: item.quantity
            })),
            total: totalPrice,
            comment: comment
        };

        console.log('Submitting order:', orderData);

        try {
            if (this.tg) {
                this.tg.sendData(JSON.stringify(orderData));
            }

            // Clear cart
            this.cart = {};
            this.updateMainButton();
            this.refreshAllItems();

        } catch (error) {
            console.error('Order submission error:', error);
        }
    }

    refreshAllItems() {
        // Re-render all items to reset to "Add" buttons
        this.categories.forEach(category => {
            category.items.forEach(item => {
                this.updateItemDisplay(item.id);
            });
        });
    }

    findItemById(itemId) {
        for (const category of this.categories) {
            const item = category.items.find(item => item.id == itemId);
            if (item) return item;
        }
        return null;
    }

    showError(message) {
        const container = document.getElementById('menu');
        container.innerHTML = `
            <div class="error">
                <div class="error-icon">😕</div>
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

// Initialize app
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new SimpleFoodApp();
});

// Global reference for onclick handlers
window.app = app;