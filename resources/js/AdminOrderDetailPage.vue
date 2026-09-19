<script setup>
import {
    adminOrder,
    adminOrderLoading,
    adminOrderError,
    adminOrderSuccess,
    loadAdminOrder,
    updateAdminOrderStatus,
    getAllowedTransitions,
    getStatusLabel,
} from './admin-state.js';

import { computed, ref, watch } from 'vue';

const props = defineProps({
    orderId: { type: [String, Number], required: true },
});

const emit = defineEmits(['back']);

const showCancelModal = ref(false);
const showConfirmModal = ref(false);
const cancelledReason = ref('');
const processingAction = ref('');
const statusLoading = ref(false);
const pendingConfirmAction = ref(null);

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

const formatShortDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fa-IR', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const allowedTransitions = computed(() => {
    if (!adminOrder.value) return [];
    return getAllowedTransitions(adminOrder.value.status);
});

const canChangeStatus = computed(() => allowedTransitions.value.length > 0);

const actionLabels = {
    confirmed: 'تأیید سفارش',
    processing: 'شروع پردازش',
    shipped: 'ثبت ارسال',
    delivered: 'تحویل شد',
    cancelled: 'لغو سفارش',
};

const actionConfirmMessages = {
    confirmed: 'آیا از تأیید این سفارش مطمئن هستید؟',
    processing: 'آیا از شروع پردازش این سفارش مطمئن هستید؟',
    shipped: 'آیا از ثبت ارسال این سفارش مطمئن هستید؟',
    delivered: 'آیا از تحویل این سفارش مطمئن هستید؟',
    cancelled: 'آیا از لغو این سفارش مطمئن هستید؟ این عمل قابل بازگشت نیست.',
};

const statusFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

const statusProgress = computed(() => {
    if (!adminOrder.value) return 0;
    const idx = statusFlow.indexOf(adminOrder.value.status);
    if (idx === -1) return 0;
    return ((idx + 1) / statusFlow.length) * 100;
});

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

watch(() => props.orderId, (newId) => {
    if (newId) {
        loadAdminOrder(newId);
    }
}, { immediate: true });

function handleAction(status) {
    if (status === 'cancelled') {
        cancelledReason.value = '';
        showCancelModal.value = true;
        return;
    }

    pendingConfirmAction.value = status;
    showConfirmModal.value = true;
}

async function confirmAction() {
    const status = pendingConfirmAction.value;
    if (!status) return;
    pendingConfirmAction.value = null;
    showConfirmModal.value = false;
    await executeStatusChange(status, null);
}

function closeConfirmModal() {
    showConfirmModal.value = false;
    pendingConfirmAction.value = null;
}

async function confirmCancel() {
    await executeStatusChange('cancelled', cancelledReason.value || null);
    showCancelModal.value = false;
}

function closeCancelModal() {
    showCancelModal.value = false;
    cancelledReason.value = '';
}

async function executeStatusChange(status, reason) {
    statusLoading.value = true;
    processingAction.value = status;

    try {
        await updateAdminOrderStatus(props.orderId, status, reason);
        await loadAdminOrder(props.orderId);
    } catch (error) {
        // Error handled in admin-state
    } finally {
        statusLoading.value = false;
        processingAction.value = '';
    }
}

function onImgError(event) {
    event.target.onerror = null;
    event.target.src = '/images/placeholder.svg';
}
</script>

<template>
    <section class="order-detail-section">
        <div class="panel">
            <!-- Header -->
            <div class="panel-head">
                <div>
                    <p class="section-label">
                        <span class="breadcrumb-link" @click="$emit('back')">سفارش‌ها</span>
                        / جزئیات سفارش
                    </p>
                    <h2 v-if="adminOrder">
                        سفارش <span class="order-id-large" dir="ltr">#{{ adminOrder.id }}</span>
                    </h2>
                    <h2 v-else>
                        سفارش <span dir="ltr">#{{ orderId }}</span>
                    </h2>
                </div>
                <div class="panel-head-actions">
                    <button type="button" class="btn-back" @click="$emit('back')">
                        ← بازگشت به لیست
                    </button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="adminOrderLoading && !adminOrder" class="loading-state">
                <div class="spinner"></div>
                <span>در حال بارگذاری سفارش...</span>
            </div>

            <!-- Error -->
            <div v-else-if="adminOrderError && !adminOrder" class="alert error">
                {{ adminOrderError }}
                <button type="button" class="btn-retry" @click="loadAdminOrder(orderId)">تلاش دوباره</button>
            </div>

            <template v-else-if="adminOrder">
                <!-- Success Toast -->
                <div v-if="adminOrderSuccess" class="alert success order-success-toast">
                    {{ adminOrderSuccess }}
                </div>

                <!-- Error Toast (during status change) -->
                <div v-if="adminOrderError" class="alert error order-success-toast">
                    {{ adminOrderError }}
                </div>

                <!-- Order Header Bar -->
                <div class="order-header-bar">
                    <div class="order-header-info">
                        <div class="order-header-meta">
                            <span class="status-badge status-lg" :class="getStatusClass(adminOrder.status)">
                                {{ getStatusLabel(adminOrder.status) }}
                            </span>
                            <span class="payment-badge payment-lg" :class="adminOrder.paid_at ? 'paid' : 'unpaid'">
                                {{ adminOrder.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                            </span>
                        </div>
                        <div class="order-header-details">
                            <span class="header-detail">
                                <span class="detail-label">تاریخ ثبت:</span>
                                {{ formatDate(adminOrder.created_at) }}
                            </span>
                            <span class="header-detail">
                                <span class="detail-label">مبلغ:</span>
                                <strong>{{ formatPrice(adminOrder.total) }} تومان</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Status Progress -->
                <div v-if="adminOrder.status !== 'cancelled'" class="status-progress-section">
                    <div class="status-progress-bar">
                        <div class="status-progress-fill" :style="{ width: statusProgress + '%' }"></div>
                    </div>
                    <div class="status-steps">
                        <div
                            v-for="(step, index) in statusFlow"
                            :key="step"
                            class="status-step"
                            :class="{
                                active: statusFlow.indexOf(adminOrder.status) >= index,
                                current: adminOrder.status === step
                            }"
                        >
                            <span class="step-dot"></span>
                            <span class="step-label">{{ getStatusLabel(step) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cancelled Info -->
                <div v-if="adminOrder.status === 'cancelled'" class="cancelled-info-bar">
                    <div class="cancelled-icon">✕</div>
                    <div class="cancelled-details">
                        <strong>این سفارش لغو شده</strong>
                        <span v-if="adminOrder.cancelled_at">{{ formatDate(adminOrder.cancelled_at) }}</span>
                    </div>
                    <div v-if="adminOrder.cancelled_reason" class="cancelled-reason">
                        {{ adminOrder.cancelled_reason }}
                    </div>
                </div>

                <!-- Order Actions -->
                <div v-if="canChangeStatus" class="order-actions-section">
                    <h3 class="section-title">اقدامات سفارش</h3>
                    <div class="action-buttons">
                        <button
                            v-for="status in allowedTransitions"
                            :key="status"
                            type="button"
                            class="action-btn"
                            :class="status === 'cancelled' ? 'action-btn-danger' : 'action-btn-primary'"
                            :disabled="statusLoading"
                            @click="handleAction(status)"
                        >
                            <span v-if="statusLoading && processingAction === status" class="btn-spinner"></span>
                            {{ actionLabels[status] || getStatusLabel(status) }}
                        </button>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="order-detail-grid">
                    <!-- Customer Info -->
                    <div class="detail-card">
                        <h3 class="card-title">اطلاعات مشتری</h3>
                        <div class="card-body">
                            <div class="info-row" v-if="adminOrder.user">
                                <span class="info-label">کاربر سایت</span>
                                <span class="info-value">{{ adminOrder.user.name }} ({{ adminOrder.user.email }})</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">نام گیرنده</span>
                                <span class="info-value">{{ adminOrder.customer_name }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">تلفن</span>
                                <span class="info-value" dir="ltr">{{ adminOrder.customer_phone }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.customer_email">
                                <span class="info-label">ایمیل</span>
                                <span class="info-value">{{ adminOrder.customer_email }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">استان و شهر</span>
                                <span class="info-value">{{ adminOrder.shipping_province }}، {{ adminOrder.shipping_city }}</span>
                            </div>
                            <div class="info-row info-row-full">
                                <span class="info-label">آدرس</span>
                                <span class="info-value whitespace-pre-line">{{ adminOrder.shipping_address }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.shipping_postal_code">
                                <span class="info-label">کد پستی</span>
                                <span class="info-value" dir="ltr">{{ adminOrder.shipping_postal_code }}</span>
                            </div>
                            <div class="info-row info-row-full" v-if="adminOrder.notes">
                                <span class="info-label">توضیحات مشتری</span>
                                <span class="info-value whitespace-pre-line">{{ adminOrder.notes }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="detail-card">
                        <h3 class="card-title">اطلاعات پرداخت</h3>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">مبلغ نهایی</span>
                                <span class="info-value info-value-lg"><strong>{{ formatPrice(adminOrder.total) }} تومان</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">جمع کالاها</span>
                                <span class="info-value">{{ formatPrice(adminOrder.subtotal) }} تومان</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.discount > 0">
                                <span class="info-label">تخفیف</span>
                                <span class="info-value discount-value">{{ formatPrice(adminOrder.discount) }} تومان</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">هزینه ارسال</span>
                                <span class="info-value">{{ formatPrice(adminOrder.shipping_cost) }} تومان</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">وضعیت پرداخت</span>
                                <span class="payment-badge" :class="adminOrder.paid_at ? 'paid' : 'unpaid'">
                                    {{ adminOrder.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}
                                </span>
                            </div>
                            <div class="info-row" v-if="adminOrder.paid_at">
                                <span class="info-label">تاریخ پرداخت</span>
                                <span class="info-value">{{ formatDate(adminOrder.paid_at) }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.payment_method">
                                <span class="info-label">روش پرداخت</span>
                                <span class="info-value">{{ adminOrder.payment_method }}</span>
                            </div>
                            <div class="info-row" v-if="adminOrder.payment_ref">
                                <span class="info-label">شماره مرجع</span>
                                <span class="info-value font-mono" dir="ltr">{{ adminOrder.payment_ref }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="detail-card detail-card-full">
                    <h3 class="card-title">اقلام سفارش</h3>
                    <div class="card-body">
                        <div class="items-list">
                            <div v-for="item in adminOrder.items" :key="item.id" class="item-row">
                                <div class="item-image">
                                    <img
                                        v-if="item.image"
                                        :src="`/storage/${item.image}`"
                                        :alt="item.product_name"
                                        class="item-thumb"
                                        @error="onImgError"
                                    />
                                    <div v-else class="item-thumb-placeholder">📦</div>
                                </div>
                                <div class="item-details">
                                    <span class="item-name">{{ item.product_name }}</span>
                                    <span v-if="item.sku" class="item-sku" dir="ltr">SKU: {{ item.sku }}</span>
                                    <span v-if="item.attributes?.length" class="item-attrs">
                                        {{ item.attributes.map(a => a.label || a.value).join('، ') }}
                                    </span>
                                </div>
                                <div class="item-pricing">
                                    <span class="item-qty">{{ item.quantity }} × {{ formatPrice(item.unit_price) }}</span>
                                    <span class="item-subtotal">{{ formatPrice(item.subtotal) }} تومان</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Attempts -->
                <div v-if="adminOrder.payment_attempts?.length" class="detail-card detail-card-full">
                    <h3 class="card-title">تلاش‌های پرداخت</h3>
                    <div class="card-body">
                        <div class="attempts-list">
                            <div v-for="attempt in adminOrder.payment_attempts" :key="attempt.id" class="attempt-row">
                                <div class="attempt-main">
                                    <span class="attempt-gateway">{{ attempt.gateway }}</span>
                                    <span class="attempt-amount" dir="ltr">{{ formatPrice(attempt.amount / 10) }} تومان</span>
                                    <span class="status-badge status-sm" :class="attempt.status === 'verified' ? 'status-delivered' : attempt.status === 'failed' ? 'status-cancelled' : ''">
                                        {{ attempt.status }}
                                    </span>
                                </div>
                                <div class="attempt-meta">
                                    <span v-if="attempt.authority" class="attempt-code" dir="ltr">Authority: {{ attempt.authority }}</span>
                                    <span v-if="attempt.reference" class="attempt-code" dir="ltr">Ref: {{ attempt.reference }}</span>
                                    <span class="attempt-date">{{ formatShortDate(attempt.verified_at || attempt.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Payment Attempts -->
                <div v-else class="detail-card detail-card-full">
                    <h3 class="card-title">تلاش‌های پرداخت</h3>
                    <div class="card-body">
                        <div class="empty-inline">
                            <span class="empty-inline-icon">💳</span>
                            <span>تلاش پرداختی ثبت نشده است</span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Cancel Modal -->
        <div v-if="showCancelModal" class="modal-overlay" @click.self="closeCancelModal">
            <div class="modal">
                <div class="modal-head">
                    <h3>لغو سفارش</h3>
                    <button type="button" class="modal-close" @click="closeCancelModal">✕</button>
                </div>
                <div class="modal-body">
                    <div class="cancel-warning">
                        <span class="cancel-warning-icon">⚠</span>
                        <span>آیا از لغو سفارش <strong dir="ltr">#{{ adminOrder?.id }}</strong> مطمئن هستید؟</span>
                    </div>

                    <div class="form-field">
                        <label>دلیل لغو (اختیاری)</label>
                        <textarea
                            v-model="cancelledReason"
                            rows="3"
                            class="form-textarea"
                            placeholder="دلیل لغو سفارش را وارد کنید..."
                        ></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancel-modal" @click="closeCancelModal" :disabled="statusLoading">
                            بازگشت
                        </button>
                        <button
                            type="button"
                            class="btn-confirm-cancel"
                            @click="confirmCancel"
                            :disabled="statusLoading"
                        >
                            <span v-if="statusLoading" class="btn-spinner"></span>
                            {{ statusLoading ? 'در حال لغو...' : 'تأیید لغو سفارش' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Action Modal -->
        <div v-if="showConfirmModal" class="modal-overlay" @click.self="closeConfirmModal">
            <div class="modal">
                <div class="modal-head">
                    <h3>تأیید عملیات</h3>
                    <button type="button" class="modal-close" @click="closeConfirmModal">✕</button>
                </div>
                <div class="modal-body">
                    <div class="confirm-warning">
                        <span class="confirm-warning-icon">⚠</span>
                        <span>{{ actionConfirmMessages[pendingConfirmAction] || 'آیا از انجام این عملیات مطمئن هستید؟' }}</span>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancel-modal" @click="closeConfirmModal" :disabled="statusLoading">
                            انصراف
                        </button>
                        <button
                            type="button"
                            class="btn-confirm-action"
                            @click="confirmAction"
                            :disabled="statusLoading"
                        >
                            <span v-if="statusLoading" class="btn-spinner"></span>
                            {{ statusLoading ? 'در حال انجام...' : 'تأیید' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
