// ===== MODERN WEBAPP JAVASCRIPT =====

class ModernFoodApp {
    constructor() {
        this.tg = window.Telegram?.WebApp;
        this.cart = {};
        this.categories = [];
        this.isLoading = true;
        
        this.MENU_API = 'https://olmazorgo.bigsaver.ru/bot/api/menu.php';
        
        this.init();
    }

    async init() {
        console.log('🚀 Modern Food App initializing...');
        
        // Initialize Telegram WebApp
        this.initTelegram();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Load menu with delay for smooth loading animation
        setTimeout(() => {
            this.loadMenu();
        }, 1500);
    }

    initTelegram() {
        if (!this.tg) {
            console.error('❌ Telegram WebApp not available');
            this.showError('Bu ilova faqat Telegram bot ichida ishlaydi');
            return;
        }

        console.log('✅ Telegram WebApp initialized');
        
        // Configure Telegram WebApp
        this.tg.ready();
        this.tg.expand();
        this.tg.setBackgroundColor('#ffffff');
        this.tg.setHeaderColor('#ffffff');
        
        // Setup main button click handler
        this.tg.MainButton.onClick(() => this.submitOrder());
        
        console.log('📱 Telegram WebApp configured:', {
            isExpanded: this.tg.isExpanded,
            viewportHeight: this.tg.viewportHeight,
            themeParams: this.tg.themeParams
        });
    }

    setupEventListeners() {
        // Comment character counter
        const commentTextarea = document.getElementById('comment');
        const commentCounter = document.getElementById('comment-count');
        
        commentTextarea?.addEventListener('input', (e) => {
            const count = e.target.value.length;
            commentCounter.textContent = count;
            
            if (count > 180) {
                commentCounter.style.color = 'var(--danger-color)';
            } else if (count > 150) {
                commentCounter.style.color = 'var(--warning-color)';
            } else {
                commentCounter.style.color = 'var(--text-muted)';
            }
        });

        // Cart button click
        document.getElementById('cart-button')?.addEventListener('click', () => {
            this.submitOrder();
        });
    }

    async loadMenu() {
        try {
            console.log('📋 Loading menu from:', this.MENU_API);
            
            const response = await fetch(this.MENU_API, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                cache: 'no-cache'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const text = await response.text();
            console.log('📄 Raw response:', text);

            let categories;
            try {
                categories = JSON.parse(text);
            } catch (parseError) {
                throw new Error(`JSON Parse Error: ${parseError.message}`);
            }

            if (!Array.isArray(categories) || categories.length === 0) {
                throw new Error('Menu bo\'sh yoki noto\'g\'ri format');
            }

            console.log('✅ Menu loaded successfully:', categories);
            
            this.categories = categories;
            this.hideLoading();
            this.renderCategories();
            this.renderMenu();
            
        } catch (error) {
            console.error('❌ Menu loading error:', error);
            this.hideLoading();
            this.showError(`Menyu yuklanmadi: ${error.message}`);
        }
    }

    hideLoading() {
        const loadingScreen = document.getElementById('loading-screen');
        const app = document.getElementById('app');
        
        loadingScreen?.classList.add('fade-out');
        
        setTimeout(() => {
            loadingScreen?.classList.add('hidden');
            app?.classList.remove('hidden');
            
            // Trigger entrance animations
            this.triggerEntranceAnimations();
        }, 500);
    }

    triggerEntranceAnimations() {
        // Animate header
        const header = document.querySelector('.header');
        header?.style.setProperty('animation', 'slideDown 0.6s ease');
        
        // Animate categories with stagger
        const categoryBtns = document.querySelectorAll('.category-btn');
        categoryBtns.forEach((btn, index) => {
            setTimeout(() => {
                btn.style.setProperty('animation', 'fadeInUp 0.5s ease both');
            }, index * 100);
        });
    }

    renderCategories() {
        const container = document.getElementById('categories');
        if (!container) return;

        container.innerHTML = '';

        this.categories.forEach((category, index) => {
            const button = document.createElement('button');
            button.className = `category-btn ${index === 0 ? 'active' : ''}`;
            button.textContent = category.name;
            button.dataset.categoryId = category.id;
            
            button.addEventListener('click', () => this.selectCategory(button, category.id));
            
            container.appendChild(button);
        });
    }

    selectCategory(button, categoryId) {
        // Update active state
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');

        // Smooth scroll to category section
        const section = document.getElementById(`category-${categoryId}`);
        if (section) {
            section.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }

        // Add ripple effect
        this.addRippleEffect(button);
    }

    renderMenu() {
        const container = document.getElementById('menu');
        if (!container) return;

        container.innerHTML = '';

        this.categories.forEach(category => {
            if (!category.items || category.items.length === 0) return;

            const section = document.createElement('div');
            section.className = 'menu-section';
            section.id = `category-${category.id}`;

            const title = document.createElement('h2');
            title.className = 'section-title';
            title.textContent = category.name;

            const grid = document.createElement('div');
            grid.className = 'items-grid';

            category.items.forEach((item, index) => {
                const card = this.createItemCard(item, index);
                grid.appendChild(card);
            });

            section.appendChild(title);
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    createItemCard(item, index) {
        const card = document.createElement('div');
        card.className = 'item-card';
        card.style.setProperty('animation-delay', `${index * 0.1}s`);

        const imageHtml = item.image_url 
            ? `<img src="${item.image_url}" alt="${item.name}" class="item-image" loading="lazy">`
            : `<div class="no-image">🍽️</div>`;

        const currentQty = this.cart[item.id]?.quantity || 0;
        const isInCart = currentQty > 0;

        card.innerHTML = `
            ${imageHtml}
            <div class="item-info">
                <div class="item-name">${this.escapeHtml(item.name)}</div>
                ${item.description ? `<div class="item-description">${this.escapeHtml(item.description)}</div>` : ''}
                <div class="item-price">${this.formatPrice(item.price)} so'm</div>
                <div class="item-controls">
                    ${isInCart ? `
                        <div class="qty-controls">
                            <button class="qty-btn minus" onclick="app.changeQuantity(${item.id}, -1)">−</button>
                            <span class="qty-display" id="qty-${item.id}">${currentQty}</span>
                            <button class="qty-btn plus" onclick="app.changeQuantity(${item.id}, 1)">+</button>
                        </div>
                    ` : `
                        <button class="add-btn" onclick="app.addToCart(${item.id}, '${this.escapeHtml(item.name)}', ${item.price})">
                            Qo'shish
                        </button>
                    `}
                </div>
            </div>
        `;

        return card;
    }

    addToCart(itemId, itemName, itemPrice) {
        this.cart[itemId] = {
            id: itemId,
            name: itemName,
            price: parseFloat(itemPrice),
            quantity: 1
        };

        this.updateItemDisplay(itemId);
        this.updateCartSummary();
        this.animateCartBadge();
        
        console.log('➕ Added to cart:', this.cart[itemId]);
    }

    changeQuantity(itemId, delta) {
        if (!this.cart[itemId]) return;

        this.cart[itemId].quantity += delta;

        if (this.cart[itemId].quantity <= 0) {
            delete this.cart[itemId];
        }

        this.updateItemDisplay(itemId);
        this.updateCartSummary();
        
        console.log('🔄 Quantity changed:', itemId, delta, this.cart[itemId]);
    }

    updateItemDisplay(itemId) {
        const qtyDisplay = document.getElementById(`qty-${itemId}`);
        const itemCard = qtyDisplay?.closest('.item-card');
        
        if (!itemCard) return;

        const currentQty = this.cart[itemId]?.quantity || 0;
        const isInCart = currentQty > 0;

        // Find the item data
        let itemData = null;
        for (const category of this.categories) {
            itemData = category.items.find(item => item.id == itemId);
            if (itemData) break;
        }

        if (!itemData) return;

        // Update controls
        const controlsContainer = itemCard.querySelector('.item-controls');
        controlsContainer.innerHTML = isInCart ? `
            <div class="qty-controls">
                <button class="qty-btn minus" onclick="app.changeQuantity(${itemId}, -1)">−</button>
                <span class="qty-display" id="qty-${itemId}">${currentQty}</span>
                <button class="qty-btn plus" onclick="app.changeQuantity(${itemId}, 1)">+</button>
            </div>
        ` : `
            <button class="add-btn" onclick="app.addToCart(${itemId}, '${this.escapeHtml(itemData.name)}', ${itemData.price})">
                Qo'shish
            </button>
        `;

        // Add animation
        controlsContainer.style.animation = 'fadeInUp 0.3s ease';
    }

    updateCartSummary() {
        const cartItems = Object.values(this.cart);
        const totalItems = cartItems.reduce((sum, item) => sum + item.quantity, 0);
        const totalPrice = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        // Update badge
        const badge = document.getElementById('cart-badge');
        if (badge) {
            badge.textContent = totalItems;
            if (totalItems > 0) {
                badge.classList.add('show');
            } else {
                badge.classList.remove('show');
            }
        }

        // Update cart summary
        const cartSummary = document.getElementById('cart-summary');
        const cartItemsText = document.getElementById('cart-items-text');
        const cartTotalText = document.getElementById('cart-total-text');

        if (totalItems > 0) {
            cartSummary?.classList.add('show');
            cartSummary?.classList.remove('hidden');
            
            if (cartItemsText) {
                cartItemsText.textContent = `${totalItems} ta mahsulot`;
            }
            if (cartTotalText) {
                cartTotalText.textContent = `${this.formatPrice(totalPrice)} so'm`;
            }

            // Update Telegram MainButton
            if (this.tg) {
                this.tg.MainButton.setText(`🛒 Buyurtma berish — ${this.formatPrice(totalPrice)} so'm`);
                this.tg.MainButton.show();
                this.tg.MainButton.color = '#10b981';
            }
        } else {
            cartSummary?.classList.remove('show');
            setTimeout(() => {
                cartSummary?.classList.add('hidden');
            }, 300);

            // Hide Telegram MainButton
            if (this.tg) {
                this.tg.MainButton.hide();
            }
        }
    }

    animateCartBadge() {
        const badge = document.getElementById('cart-badge');
        if (badge) {
            badge.style.animation = 'bounceIn 0.5s ease';
            setTimeout(() => {
                badge.style.animation = '';
            }, 500);
        }
    }

    async submitOrder() {
        const cartItems = Object.values(this.cart);
        if (cartItems.length === 0) return;

        const totalPrice = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const comment = document.getElementById('comment')?.value.trim() || '';

        const orderData = {
            items: cartItems.map(item => ({
                menu_item_id: item.id,
                name: item.name,
                price: item.price,
                quantity: item.quantity
            })),
            total: totalPrice,
            comment: comment
        };

        console.log('📤 Submitting order:', orderData);

        try {
            // Show success animation
            this.showSuccessAnimation();

            // Send data to Telegram
            if (this.tg) {
                this.tg.sendData(JSON.stringify(orderData));
            }

            // Clear cart after successful submission
            setTimeout(() => {
                this.cart = {};
                this.updateCartSummary();
                this.refreshItemDisplays();
            }, 2000);

        } catch (error) {
            console.error('❌ Order submission error:', error);
            this.showError('Buyurtma yuborishda xatolik yuz berdi');
        }
    }

    showSuccessAnimation() {
        const successAnimation = document.getElementById('success-animation');
        successAnimation?.classList.remove('hidden');

        setTimeout(() => {
            successAnimation?.classList.add('hidden');
        }, 3000);
    }

    refreshItemDisplays() {
        // Refresh all item displays to show "Add" buttons
        this.categories.forEach(category => {
            category.items.forEach(item => {
                this.updateItemDisplay(item.id);
            });
        });
    }

    showError(message) {
        const container = document.getElementById('menu');
        if (container) {
            container.innerHTML = `
                <div class="error-container" style="
                    text-align: center;
                    padding: 3rem 2rem;
                    color: var(--danger-color);
                ">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">😕</div>
                    <h3 style="margin-bottom: 1rem;">Xatolik yuz berdi</h3>
                    <p style="margin-bottom: 2rem; color: var(--text-secondary);">${message}</p>
                    <button onclick="location.reload()" style="
                        background: var(--primary-color);
                        color: white;
                        border: none;
                        padding: 0.75rem 2rem;
                        border-radius: var(--border-radius);
                        font-weight: 600;
                        cursor: pointer;
                    ">Qayta urinish</button>
                </div>
            `;
        }
    }

    addRippleEffect(element) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        
        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            width: ${size}px;
            height: ${size}px;
            left: ${rect.width / 2 - size / 2}px;
            top: ${rect.height / 2 - size / 2}px;
        `;
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
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

// Add ripple animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .fade-out {
        animation: fadeOut 0.5s ease forwards;
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);

// Initialize app when DOM is loaded
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new ModernFoodApp();
});

// Global functions for onclick handlers
window.app = {
    addToCart: (itemId, itemName, itemPrice) => app?.addToCart(itemId, itemName, itemPrice),
    changeQuantity: (itemId, delta) => app?.changeQuantity(itemId, delta)
};