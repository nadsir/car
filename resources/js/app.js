import './bootstrap';
import { createApp } from 'vue';

import Homepage from './Homepage.vue';
import App from './Storefront.vue';
import Admin from './Admin.vue';
import ProductDetail from './ProductDetail.vue';
import CartPage from './CartPage.vue';

function resolveApp() {
    const path = location.pathname;

    if (path.startsWith('/admin')) return Admin;
    if (path === '/cart') return CartPage;
    if (/^\/products\/\d+/.test(path)) return ProductDetail;
    if (path === '/' || path === '') return Homepage;

    return App;
}

createApp(resolveApp()).mount('#app');
