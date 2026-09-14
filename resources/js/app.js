import './bootstrap';
import { createApp } from 'vue';

import App from './Storefront.vue';
import Admin from './Admin.vue';
import ProductDetail from './ProductDetail.vue';

function resolveApp() {
    const path = location.pathname;

    if (path.startsWith('/admin')) return Admin;
    if (/^\/products\/\d+/.test(path)) return ProductDetail;

    return App;
}

createApp(resolveApp()).mount('#app');
