<script setup>
import {
    adminOrder,
    adminOrderLoading,
    adminOrderError,
    loadAdminOrder,
    updateAdminOrderStatus,
    getAllowedTransitions,
    getStatusLabel,
} from './admin-state.js';

import { computed, ref, watch } from 'vue';

const props = defineProps({
    orderId: { type: [String, Number], required: true },
});

const showStatusModal = ref(false);
const newStatus = ref('');
const cancelledReason = ref('');
const statusLoading = ref(false);
const statusError = ref('');

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

const allowedTransitions = computed(() => {
    if (!adminOrder.value) return [];
    return getAllowedTransitions(adminOrder.value.status);
});

const canChangeStatus = computed(() => allowedTransitions.value.length > 0);

watch(() => props.orderId, (newId) => {
    if (newId) {
        loadAdminOrder(newId);
    }
}, { immediate: true });

async function openStatusModal() {
    if (!canChangeStatus.value) return;
    newStatus.value = '';
    cancelledReason.value = '';
    statusError.value = '';
    showStatusModal.value = true;
}

async function closeStatusModal() {
    showStatusModal.value = false;
    newStatus.value = '';
    cancelledReason.value = '';
    statusError.value = '';
}

async function confirmStatusChange() {
    if (!newStatus.value || statusLoading.value) return;
    statusLoading.value = true;
    statusError.value = '';

    try {
        await updateAdminOrderStatus(props.orderId, newStatus.value, newStatus.value === 'cancelled' ? cancelledReason.value : null);
        await loadAdminOrder(props.orderId);
        showStatusModal.value = false;
    } catch (error) {
        // Error handled in updateAdminOrderStatus
    } finally {
        statusLoading.value = false;
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
            <div class="panel-head">
                <div>
                    <p class="section-label">مدیریت فروشگاه / سفارش‌ها</p>
                    <h2 v-if="adminOrder">سفارش <span dir="ltr">#{{ adminOrder.id }}</span></h2>
                    <h2 v-else>سفارش <span dir="ltr">#{{ orderId }}</span></h2>
                </div>
                <div class="panel-head-actions">
                    <button type="button" class="text-button" @click="$emit('back')">
                        ← بازگشت به لیست
                    </button>
                </div>
            </div>

            <div v-if="adminOrderLoading" class="loading">
                <div class="spinner"></div>
                <span>در حال بارگذاری سفارش...</span>
            </div>

            <div v-else-if="adminOrderError" class="alert error">
                {{ adminOrderError }}
                <button type="button" class="btn-retry" @click="loadAdminOrder(orderId)">تلاش دوباره</button>
            </div>

            <template v-else-if="adminOrder">
                <!-- Status Bar -->
                <div class="order-status-bar mb-6">
                    <div class="status-info">
                        <span class="status-label">وضعیت:</span>
                        <span class="status" :class="adminOrder.status === 'cancelled' ? 'bad' : adminOrder.status === 'delivered' ? 'ok' : ''">
                            {{ getStatusLabel(adminOrder.status) }}
                        </span>
                    </div>
                    <div class="status-actions">
                        <button
                            v-if="canChangeStatus"
                            type="button"
                            class="primary"
                            @click="openStatusModal"
                        >
                            تغییر وضعیت
                        </button>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="order-grid">
                    <div class="panel order-info-panel">
                        <h3 class="panel-title">اطلاعات سفارش</h3>
                        <dl class="info-list">
                            <div><dt>شماره سفارش</dt><dd dir="ltr" class="font-mono">#{{ adminOrder.id }}</dd></div>
                            <div><dt>تاریخ ثبت</dt><dd>{{ formatDate(adminOrder.created_at) }}</dd></div>
                            <div><dt>وضعیت</dt><dd><span class="status" :class="adminOrder.status === 'cancelled' ? 'bad' : adminOrder.status === 'delivered' ? 'ok' : ''">{{ getStatusLabel(adminOrder.status) }}</span></dd></div>
                            <div v-if="adminOrder.cancelled_at"><dt>تاریخ لغو</dt><dd>{{ formatDate(adminOrder.cancelled_at) }}</dd></div>
                            <div v-if="adminOrder.cancelled_reason"><dt>دلیل لغو</dt><dd class="whitespace-pre-line">{{ adminOrder.cancelled_reason }}</dd></div>
                        </dl>
                    </div>

                    <div class="panel payment-info-panel">
                        <h3 class="panel-title">اطلاعات پرداخت</h3>
                        <dl class="info-list">
                            <div><dt>مبلغ نهایی</dt><dd><strong>{{ formatPrice(adminOrder.total) }} تومان</strong></dd></div>
                            <div><dt>جمع کالاها</dt><dd>{{ formatPrice(adminOrder.subtotal) }} تومان</dd></div>
                            <div><dt>تخفیف</dt><dd>{{ formatPrice(adminOrder.discount) }} تومان</dd></div>
                            <div><dt>هزینه ارسال</dt><dd>{{ formatPrice(adminOrder.shipping_cost) }} تومان</dd></div>
                            <div><dt>وضعیت پرداخت</dt><dd>{{ adminOrder.paid_at ? 'پرداخت شده' : 'پرداخت نشده' }}</dd></div>
                            <div v-if="adminOrder.paid_at"><dt>تاریخ پرداخت</dt><dd>{{ formatDate(adminOrder.paid_at) }}</dd></div>
                            <div v-if="adminOrder.payment_method"><dt>روش پرداخت</dt><dd>{{ adminOrder.payment_method }}</dd></div>
                            <div v-if="adminOrder.payment_ref"><dt>شماره مرجع</dt><dd dir="ltr" class="font-mono">{{ adminOrder.payment_ref }}</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="panel customer-info-panel">
                    <h3 class="panel-title">اطلاعات مشتری</h3>
                    <dl class="info-grid">
                        <div v-if="adminOrder.user"><dt>کاربر</dt><dd>{{ adminOrder.user.name }} ({{ adminOrder.user.email }})</dd></div>
                        <div><dt>نام گیرنده</dt><dd>{{ adminOrder.customer_name }}</dd></div>
                        <div><dt>تلفن</dt><dd dir="ltr">{{ adminOrder.customer_phone }}</dd></div>
                        <div><dt>ایمیل</dt><dd>{{ adminOrder.customer_email }}</dd></div>
                        <div><dt>استان و شهر</dt><dd>{{ adminOrder.shipping_province }}، {{ adminOrder.shipping_city }}</dd></div>
                        <div class="col-span-2"><dt>آدرس</dt><dd class="whitespace-pre-line">{{ adminOrder.shipping_address }}</dd></div>
                        <div><dt>کد پستی</dt><dd>{{ adminOrder.shipping_postal_code }}</dd></div>
                        <div v-if="adminOrder.notes" class="col-span-2"><dt>توضیحات</dt><dd class="whitespace-pre-line">{{ adminOrder.notes }}</dd></div>
                    </dl>
                </div>

                <!-- Order Items -->
                <div class="panel order-items-panel">
                    <h3 class="panel-title">اقلام سفارش</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>SKU</th>
                                <th>تعداد</th>
                                <th>قیمت واحد</th>
                                <th>مجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in adminOrder.items" :key="item.id">
                                <td>
                                    <div class="item-cell">
                                        <img v-if="item.image" :src="`/storage/${item.image}`" :alt="item.product_name" class="item-thumb" @error="onImgError" />
                                        <div class="item-info">
                                            <span class="item-name">{{ item.product_name }}</span>
                                            <span v-if="item.attributes?.length" class="item-attrs">
                                                {{ item.attributes.map(a => a.label || a.value).join('، ') }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td v-if="item.sku">{{ item.sku }}</td>
                                <td>{{ item.quantity }}</td>
                                <td>{{ formatPrice(item.unit_price) }} تومان</td>
                                <td><strong>{{ formatPrice(item.subtotal) }} تومان</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Payment Attempts -->
                <div v-if="adminOrder.payment_attempts?.length" class="panel payment-attempts-panel">
                    <h3 class="panel-title">تلاش‌های پرداخت</h3>
                    <table class="attempts-table">
                        <thead>
                            <tr>
                                <th>درگاه</th>
                                <th>مبلغ (تومان)</th>
                                <th>وضعیت</th>
                                <th>Authority</th>
                                <th>Reference</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="attempt in adminOrder.payment_attempts" :key="attempt.id">
                                <td>{{ attempt.gateway }}</td>
                                <td dir="ltr">{{ formatPrice(attempt.amount / 10) }}</td>
                                <td>
                                    <span class="status" :class="attempt.status === 'verified' ? 'ok' : attempt.status === 'failed' ? 'bad' : ''">
                                        {{ attempt.status }}
                                    </span>
                                </td>
                                <td dir="ltr" class="font-mono" v-if="attempt.authority">{{ attempt.authority }}</td>
                                <td dir="ltr" class="font-mono" v-if="attempt.reference">{{ attempt.reference }}</td>
                                <td>{{ formatDate(attempt.verified_at || attempt.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Status Change Modal -->
        <div v-if="showStatusModal" class="modal-overlay" @click.self="closeStatusModal">
            <div class="modal">
                <div class="modal-head">
                    <h3>تغییر وضعیت سفارش</h3>
                    <button type="button" class="modal-close" @click="closeStatusModal">✕</button>
                </div>
                <div class="modal-body">
                    <p class="mb-4">وضعیت فعلی: <strong>{{ getStatusLabel(adminOrder?.status) }}</strong></p>

                    <div class="form-field mb-4">
                        <label>وضعیت جدید</label>
                        <select v-model="newStatus" class="form-select" required>
                            <option value="">انتخاب کنید</option>
                            <option v-for="status in allowedTransitions" :key="status" :value="status">
                                {{ getStatusLabel(status) }}
                            </option>
                        </select>
                        <p class="form-hint" v-if="allowedTransitions.length === 1">تنها وضعیت مجاز برای انتقال.</p>
                    </div>

                    <div v-if="newStatus === 'cancelled'" class="form-field mb-4">
                        <label>دلیل لغو (اختیاری)</label>
                        <textarea v-model="cancelledReason" rows="3" class="form-textarea" placeholder="دلیل لغو سفارش را وارد کنید..."></textarea>
                    </div>

                    <div v-if="statusError" class="alert error mb-4">{{ statusError }}</div>

                    <div class="modal-actions">
                        <button type="button" class="text-button" @click="closeStatusModal" :disabled="statusLoading">انصراف</button>
                        <button type="button" class="primary" @click="confirmStatusChange" :disabled="statusLoading || !newStatus">
                            {{ statusLoading ? 'در حال ثبت...' : 'تأیید و تغییر' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>