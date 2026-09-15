import './bootstrap';
import { createApp } from 'vue';

import Homepage from './Homepage.vue';
import App from './Storefront.vue';
import Admin from './Admin.vue';
import ProductDetail from './ProductDetail.vue';
import CartPage from './CartPage.vue';
import LoginPage from './LoginPage.vue';
import RegisterPage from './RegisterPage.vue';
import AccountPage from './AccountPage.vue';
import WishlistPage from './WishlistPage.vue';

function resolveApp() {
    const path = location.pathname;

    if (path.startsWith('/admin')) return Admin;
    if (path === '/login') return LoginPage;
    if (path === '/register') return RegisterPage;
    if (path === '/account') return AccountPage;
    if (path === '/cart') return CartPage;
    if (path === '/wishlist' || path === '/wishlist/') return WishlistPage;
    if (/^\/products\/\d+/.test(path)) return ProductDetail;
    if (path === '/' || path === '') return Homepage;

    return App;
}

createApp(resolveApp()).mount('#app');
