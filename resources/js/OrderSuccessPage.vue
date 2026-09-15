<script setup>
import { computed } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { state as authState } from './auth-state.js';

let savedReceipt = null;
try {
    savedReceipt = JSON.parse(sessionStorage.getItem('turbopart-order-receipt') || 'null');
} catch {}
const receipt = computed(() => savedReceipt && authState.user
    && String(savedReceipt.user_id) === String(authState.user.id) ? savedReceipt : null);
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased" dir="rtl">
        <SiteHeader />
        <main class="max-w-xl mx-auto px-4 py-16">
            <section class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <p v-if="authState.loading" role="status">در حال بارگذاری…</p>
                <template v-else-if="receipt">
                    <h1 class="text-xl font-black mb-5">سفارش شما با موفقیت ثبت شد.</h1>
                    <p class="text-sm mb-3">شماره سفارش: <b dir="ltr">#{{ receipt.id }}</b></p>
                    <p class="text-sm mb-3">مبلغ نهایی: {{ Number(receipt.total).toLocaleString('fa-IR') }} تومان</p>
                    <p class="text-xs text-slate-500 mb-6">سفارش در انتظار تأیید است.</p>
                </template>
                <p v-else class="text-sm mb-6">اطلاعات سفارش ثبت‌شده در این مرورگر در دسترس نیست.</p>
                <div class="flex flex-wrap justify-center gap-3 text-xs font-bold">
                    <a v-if="receipt" :href="`/orders/${receipt.id}`" class="rounded-lg bg-brand-accent px-4 py-2.5 text-dark-900 hover:bg-brand-hover">مشاهده جزئیات سفارش</a>
                    <a href="/orders" class="rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">مشاهده همه سفارش‌ها</a>
                    <a href="/account" class="rounded-lg bg-brand-accent px-4 py-2.5 text-dark-900 hover:bg-brand-hover">مشاهده حساب کاربری</a>
                    <a href="/store" class="rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">بازگشت به فروشگاه</a>
                </div>
            </section>
        </main>
        <SiteFooter />
    </div>
</template>
