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

import { onMounted, watch, computed, ref } from 'vue';

const emit = defineEmits(['open-detail']);

const formatPrice = (value) => {
    if (value === null || value === undefined || value === '') return '۰';
    return Number(value).toLocaleString('fa-IR');
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusOptions = [
    { value: '', label: 'همه وضعیت‌ها' },
    { value: 'pending', label: 'در انتظار پرداخت' },
    { value: 'confirmed', label: 'تأیید شده' },
    { value: 'processing', label: 'در حال پردازش' },
    { value: 'shipped', label: 'ارسال شده' },
    { value: 'delivered', label: 'تحویل شده' },
    { value: 'cancelled', label: 'لغو شده' },
];

const summaryCards = [
    { status: '', label: 'کل سفارش‌ها', icon: '📦', color: '#6563d9' },
    { status: 'pending', label: 'در انتظار پرداخت', icon: '⏳', color: '#e6a817' },
    { status: 'confirmed', label: 'تأیید شده', icon: '✓', color: '#3b82f6' },
    { status: 'processing', label: 'در حال پردازش', icon: '⚙', color: '#8b5cf6' },
    { status: 'shipped', label: 'ارسال شده', icon: '🚚', color: '#06b6d4' },
    { status: 'delivered', label: 'تحویل شده', icon: '✔', color: '#10b981' },
];

const activeFilterLabel = computed(() => {
    const found = statusOptions.find(o => o.value === adminOrderStatusFilter.value);
    return found ? found.label : 'همه وضعیت‌ها';
});

const hasActiveFilters = computed(() => {
    return adminOrderSearch.value || adminOrderStatusFilter.value;
});

const localSearch = ref(adminOrderSearch.value);

watch(localSearch, (val) => {
    adminOrderSearch.value = val;
    adminOrderPage.value = 1;
    loadAdminOrders();
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

function clearFilters() {
    localSearch.value = '';
    adminOrderSearch.value = '';
    adminOrderStatusFilter.value = '';
    adminOrderPage.value = 1;
    loadAdminOrders();
}

function filterByStatus(status) {
    adminOrderStatusFilter.value = status;
}

function viewOrder(orderId) {
    emit('open-detail', orderId);
}

function getStatusClass(status) {
    const map = {
        pending: 'status-pending',
        confirmed: 'status-confirmed',
        processing: 'status-processing',
        shipped: 'status-shipped',
        delivered: 'status-delivered',
        cancelled: 'status-cancelled',
    };
    return map[status] || '';
}
</script>

<template>
    <section class="orders-section">
        <div class="panel">
            <!-- Header -->
            <div class="panel-head">
                <div>
                    <p class="section-label">مدیریت فروشگاه</p>
                    <h2>سفارش‌ها</h2>
                </div>
                <div class="header-meta" v-if="adminOrderTotal > 0 && !adminOrderLoading">
                    <span class="header-total">{{ adminOrderTotal.toLocaleString('fa-IR') }} سفارش</span>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="orders-summary">
                <button
                    v-for="card in summaryCards"
                    :key="card.status"
                    type="button"
                    class="summary-card"
                    :class="{ active: adminOrderStatusFilter === card.status }"
                    @click="filterByStatus(card.status)"
                >
                    <span class="summary-icon" :style="{ background: card.color + '14', color: card.color }">{{ card.icon }}</span>
                    <span class="summary-label">{{ card.label }}</span>
                </button>
            </div>

            <!-- Search & Filters -->
            <div class="orders-toolbar">
                <div class="orders-search">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text"
                        class="search-input"
                        placeholder="جستجو بر اساس نام، تلفن، ایمیل یا شماره سفارش..."
                        v-model="localSearch"
                    >
                    <button
                        v-if="localSearch"
                        type="button"
                        class="search-clear-btn"
                        @click="localSearch = ''"
                    >✕</button>
                </div>

                <div class="orders-toolbar-actions">
                    <select v-model="adminOrderStatusFilter" class="filter-select">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="btn-clear-filters"
                        @click="clearFilters"
                    >
                        پاک کردن فیلترها
                    </button>

                    <button type="button" class="btn-refresh" @click="loadAdminOrders" :disabled="adminOrderLoading">
                        <span v-if="adminOrderLoading" class="btn-spinner"></span>
                        <span v-else>🔄</span>
                        بارگذاری مجدد
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="adminOrderLoading" class="loading-state">
                <div class="spinner"></div>
                <span>در حال بارگذاری سفارش‌ها...</span>
            </div>

            <!-- Error -->
            <div v-else-if="adminOrderError" class="alert error">
                {{ adminOrderError }}
                <button type="button" class="btn-retry" @click="loadAdminOrders">تلاش دوباره</button>
            </div>

            <!-- Orders Table (Desktop) -->
            <div v-else-if="adminOrders.length" class="orders-content">
                <div class="orders-table-wrapper">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th class="th-id">شماره</th>
                                <th class="th-customer">مشتری</th>
                                <th class="th-amount">مبلغ</th>
                                <th class="th-status">وضعیت</th>
                                <th class="th-payment">پرداخت</th>
                                <th class="th-date">تاریخ</th>
                                <th class="th-items">اقلام</th>
                                <th class="th-action">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in adminOrders" :key="order.id" class="order-row">
                                <td>
                                    <span class="order-id" dir="ltr">#{{ order.id }}</span>
                                </td>
                                <td>
                                    <div class="customer-cell">
                                        <div class="customer-name">{{ order.customer?.name || '—' }}</div>
                                        <div class="customer-phone" v-if="order.customer?.phone" dir="ltr">{{ order.customer.phone }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount">{{ formatPrice(order.total) }} <small>تومان</small></span>
                                </td>
                                <td>
                                    <span class="status-badge" :class="getStatusClass(order.status)">
                                        {{ getStatusLabel(order.status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-badge" :class="order.paid_at ? 'paid' : 'unpaid'">
                                        {{ order.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="date-cell">{{ formatDate(order.created_at) }}</span>
                                </td>
                                <td>
                                    <span class="items-count">{{ order.items_count }} قلم</span>
                                </td>
                                <td>
                                    <button type="button" class="btn-view" @click="viewOrder(order.id)">
                                        مشاهده
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="orders-mobile-list">
                    <div v-for="order in adminOrders" :key="'m-' + order.id" class="order-card">
                        <div class="order-card-header">
                            <span class="order-id" dir="ltr">#{{ order.id }}</span>
                            <span class="status-badge" :class="getStatusClass(order.status)">
                                {{ getStatusLabel(order.status) }}
                            </span>
                        </div>
                        <div class="order-card-body">
                            <div class="order-card-row">
                                <span class="card-label">مشتری</span>
                                <span class="card-value">{{ order.customer?.name || '—' }}</span>
                            </div>
                            <div class="order-card-row">
                                <span class="card-label">مبلغ</span>
                                <span class="card-value amount">{{ formatPrice(order.total) }} تومان</span>
                            </div>
                            <div class="order-card-row">
                                <span class="card-label">پرداخت</span>
                                <span class="payment-badge" :class="order.paid_at ? 'paid' : 'unpaid'">
                                    {{ order.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                </span>
                            </div>
                            <div class="order-card-row">
                                <span class="card-label">تاریخ</span>
                                <span class="card-value date-cell">{{ formatDate(order.created_at) }}</span>
                            </div>
                        </div>
                        <div class="order-card-footer">
                            <button type="button" class="btn-view btn-view-full" @click="viewOrder(order.id)">
                                مشاهده جزئیات
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="adminOrderTotalPages > 1" class="orders-pagination">
                    <span class="pagination-info">
                        صفحه {{ adminOrderPage.toLocaleString('fa-IR') }} از {{ adminOrderTotalPages.toLocaleString('fa-IR') }}
                    </span>
                    <div class="pagination-buttons">
                        <button
                            type="button"
                            class="pagination-btn"
                            :disabled="adminOrderPage <= 1"
                            @click="goToPage(adminOrderPage - 1)"
                        >
                            ◀ قبلی
                        </button>
                        <button
                            type="button"
                            class="pagination-btn"
                            :disabled="adminOrderPage >= adminOrderTotalPages"
                            @click="goToPage(adminOrderPage + 1)"
                        >
                            بعدی ▶
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <div class="empty-icon">📦</div>
                <h3 v-if="hasActiveFilters">سفارشی یافت نشد</h3>
                <h3 v-else>هنوز سفارشی ثبت نشده است</h3>
                <p v-if="hasActiveFilters" class="empty-hint">فیلترهای فعلی نتیجه‌ای نداشت</p>
                <button v-if="hasActiveFilters" type="button" class="btn-clear-filters" @click="clearFilters">
                    پاک کردن فیلترها
                </button>
            </div>
        </div>
    </section>
</template>
