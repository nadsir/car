<script setup>
import {
    adminOrders,
    adminOrderLoading,
    adminOrderError,
    adminOrderSearch,
    adminOrderStatusFilter,
    adminOrderPage,
    adminOrderTotalPages,
    adminOrderTotal,
    loadAdminOrders,
    getStatusLabel,
} from './admin-state.js';

import { onMounted, watch, computed } from 'vue';

const formatPrice = (value) => {
    if (value === null || value === undefined || value === '') return '۰';
    return Number(value).toLocaleString('fa-IR');
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const debouncedSearch = computed({
    get() { return adminOrderSearch.value; },
    set(val) {
        adminOrderSearch.value = val;
        adminOrderPage.value = 1;
        loadAdminOrders();
    },
});

watch(() => adminOrderStatusFilter.value, () => {
    adminOrderPage.value = 1;
    loadAdminOrders();
});

watch(() => adminOrderPage.value, () => {
    loadAdminOrders();
});

onMounted(() => {
    loadAdminOrders();
});

function goToPage(page) {
    if (page < 1 || page > adminOrderTotalPages.value) return;
    adminOrderPage.value = page;
}

function clearSearch() {
    adminOrderSearch.value = '';
    adminOrderPage.value = 1;
    loadAdminOrders();
}

function clearFilters() {
    adminOrderSearch.value = '';
    adminOrderStatusFilter.value = '';
    adminOrderPage.value = 1;
    loadAdminOrders();
}

function openOrderDetail(orderId) {
    const { loadAdminOrder } = require('./admin-state.js');
    loadAdminOrder(orderId).then(() => {
        const { section } = require('./Admin.vue');
        // We'll use a custom event to communicate with Admin.vue
        window.dispatchEvent(new CustomEvent('admin-open-order', { detail: orderId }));
    });
}
</script>

<template>
    <section class="orders-section">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <p class="section-label">مدیریت فروشگاه</p>
                    <h2>سفارش‌ها</h2>
                </div>
            </div>

            <div class="orders-filters">
                <div class="orders-search-bar">
                    <input
                        type="text"
                        class="search-input"
                        placeholder="جستجو بر اساس نام، تلفن، ایمیل یا شماره سفارش..."
                        v-model="debouncedSearch"
                    >
                    <span v-if="adminOrderSearch" class="search-clear" @click="clearSearch">✕</span>
                </div>

                <div class="orders-filter-bar">
                    <select v-model="adminOrderStatusFilter" class="filter-select">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="pending">در انتظار پرداخت</option>
                        <option value="confirmed">تأیید شده</option>
                        <option value="processing">در حال پردازش</option>
                        <option value="shipped">ارسال شده</option>
                        <option value="delivered">تحویل شده</option>
                        <option value="cancelled">لغو شده</option>
                    </select>

                    <button v-if="adminOrderSearch || adminOrderStatusFilter" type="button" class="text-button" @click="clearFilters">
                        پاک کردن فیلترها
                    </button>
                </div>
            </div>

            <div v-if="adminOrderLoading" class="loading">
                <div class="spinner"></div>
                <span>در حال بارگذاری سفارش‌ها...</span>
            </div>

            <div v-else-if="adminOrderError" class="alert error">
                {{ adminOrderError }}
                <button type="button" class="btn-retry" @click="loadAdminOrders">تلاش دوباره</button>
            </div>

            <div v-else-if="adminOrders.length" class="orders-table-wrapper">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>شماره سفارش</th>
                            <th>مشتری</th>
                            <th>مبلغ</th>
                            <th>وضعیت سفارش</th>
                            <th>وضعیت پرداخت</th>
                            <th>تاریخ ثبت</th>
                            <th>اقلام</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in adminOrders" :key="order.id" class="order-row">
                            <td>
                                <span class="order-id" dir="ltr">#{{ order.id }}</span>
                            </td>
                            <td class="customer-cell">
                                <div class="customer-name">{{ order.customer?.name || '—' }}</div>
                                <div class="customer-email" v-if="order.customer?.email">{{ order.customer.email }}</div>
                            </td>
                            <td>
                                <span class="amount">{{ formatPrice(order.total) }} تومان</span>
                            </td>
                            <td>
                                <span class="status" :class="order.status === 'cancelled' ? 'bad' : order.status === 'delivered' ? 'ok' : ''">
                                    {{ getStatusLabel(order.status) }}
                                </span>
                            </td>
                            <td>
                                <span class="payment-status" :class="order.paid_at ? 'paid' : 'unpaid'">
                                    {{ order.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                </span>
                                <span v-if="order.payment_method" class="payment-method">
                                    ({{ order.payment_method }})
                                </span>
                            </td>
                            <td class="date-cell">{{ formatDate(order.created_at) }}</td>
                            <td class="items-count">{{ order.items_count }} قلم</td>
                            <td>
                                <button type="button" class="btn-view" @click="openOrderDetail(order.id)">
                                    مشاهده
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="empty">
                <div class="empty-icon">📦</div>
                <h3 v-if="adminOrderSearch || adminOrderStatusFilter">سفارشی یافت نشد</h3>
                <h3 v-else>هنوز سفارشی ثبت نشده است</h3>
                <button v-if="adminOrderSearch || adminOrderStatusFilter" type="button" class="text-button" @click="clearFilters">
                    پاک کردن فیلترها
                </button>
            </div>

            <div v-if="adminOrderTotalPages > 1" class="products-pagination">
                <span class="pagination-info">
                    صفحه {{ adminOrderPage }} از {{ adminOrderTotalPages }} — {{ adminOrderTotal }} سفارش
                </span>
                <div class="pagination-buttons">
                    <button type="button" class="pagination-btn" :disabled="adminOrderPage <= 1" @click="goToPage(adminOrderPage - 1)">
                        ◀ قبلی
                    </button>
                    <button type="button" class="pagination-btn" :disabled="adminOrderPage >= adminOrderTotalPages" @click="goToPage(adminOrderPage + 1)">
                        بعدی ▶
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>