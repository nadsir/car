<script setup>
import { ref } from 'vue';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { login, isLoggedIn } from './auth-state.js';

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

if (isLoggedIn.value) {
    window.location.href = '/account';
}

async function onSubmit() {
    error.value = '';
    loading.value = true;

    try {
        await login(email.value, password.value);
        window.location.href = '/account';
    } catch (e) {
        const msg = e.response?.data?.message;
        const errors = e.response?.data?.errors;
        if (errors?.email) {
            error.value = errors.email[0];
        } else if (msg) {
            error.value = msg;
        } else {
            error.value = 'ورود انجام نشد. لطفاً دوباره تلاش کنید.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                <div class="text-center mb-6">
                    <div class="w-12 h-12 rounded-xl bg-brand-accent/10 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-user text-brand-accent text-lg"></i>
                    </div>
                    <h1 class="text-lg font-black text-ink">ورود به حساب</h1>
                    <p class="text-xs text-slate-500 mt-1">ایمیل و رمز عبور خود را وارد کنید</p>
                </div>

                <div v-if="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-600">
                    {{ error }}
                </div>

                <form class="space-y-4" @submit.prevent="onSubmit">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">ایمیل</label>
                        <input
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="example@email.com"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink placeholder-slate-400 focus:border-brand-accent/60 focus:outline-none transition-colors"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1.5">رمز عبور</label>
                        <input
                            v-model="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="رمز عبور"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-ink placeholder-slate-400 focus:border-brand-accent/60 focus:outline-none transition-colors"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full rounded-lg bg-brand-accent text-dark-900 py-2.5 text-sm font-bold hover:bg-brand-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="loading" class="fa-solid fa-spinner fa-spin ml-1"></i>
                        {{ loading ? 'در حال ورود...' : 'ورود' }}
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-slate-500">
                    حساب ندارید؟
                    <a href="/register" class="text-brand-accent hover:text-brand-hover font-bold transition-colors">
                        ثبت‌نام کنید
                    </a>
                </p>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>
