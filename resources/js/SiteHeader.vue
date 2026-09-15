<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import { cartCount } from './cart-state.js';
import { state as authState, isLoggedIn, logout } from './auth-state.js';
import { wishlistCount, loadWishlist, resetWishlist } from './wishlist-state.js';

const userDropdownOpen = ref(false);
const userDropdown = ref(null);
const userDropdownButton = ref(null);
const loggingOut = ref(false);

watch(() => authState.user?.id, (userId) => {
    userDropdownOpen.value = false;
    if (userId) loadWishlist();
}, { immediate: true });

function onOutsideUserClick(event) {
    if (!userDropdown.value?.contains(event.target)) userDropdownOpen.value = false;
}

function onUserFocusOut(event) {
    // A pointer click can have no relatedTarget; let the outside-click handler
    // close the dropdown after its links/buttons have received the click.
    if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) {
        userDropdownOpen.value = false;
    }
}

async function handleLogout() {
    if (loggingOut.value) return;
    loggingOut.value = true;
    try {
        await logout();
        resetWishlist();
        userDropdownOpen.value = false;
        closeDrawer();
    } finally {
        loggingOut.value = false;
    }
}

const isLight = ref(true);
const mobileSearchOpen = ref(false);
const mobileSearchQuery = ref('');
const mobileSearchInput = ref(null);
const drawerOpen = ref(false);
const searchQuery = ref('');
const searchSuggestions = ref([]);
const searchSuggestionsLoading = ref(false);
const searchDropdownOpen = ref(false);
let searchTimer = null;

function initTheme() {
    const saved = localStorage.getItem('turbopart-theme');
    if (saved === 'dark') {
        isLight.value = false;
        document.documentElement.classList.add('dark');
    } else {
        isLight.value = true;
        document.documentElement.classList.remove('dark');
    }
}

function toggleTheme() {
    isLight.value = !isLight.value;
    if (isLight.value) {
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
    localStorage.setItem('turbopart-theme', isLight.value ? 'light' : 'dark');
}

function onStorageTheme(e) {
    if (e.key !== 'turbopart-theme') return;
    if (e.newValue === 'dark') {
        isLight.value = false;
        document.documentElement.classList.add('dark');
    } else {
        isLight.value = true;
        document.documentElement.classList.remove('dark');
    }
}

function openDrawer() { drawerOpen.value = true; }
function closeDrawer() { drawerOpen.value = false; }
function onDrawerBackdropClick(e) { if (e.target === e.currentTarget) closeDrawer(); }

function onKeydown(e) {
    if (e.key === 'Escape') {
        if (userDropdownOpen.value) {
            userDropdownOpen.value = false;
            userDropdownButton.value?.focus();
        }
        if (mobileSearchOpen.value) closeMobileSearch();
        if (drawerOpen.value) closeDrawer();
        if (searchDropdownOpen.value) searchDropdownOpen.value = false;
    }
}

function toPersianNumber(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function openMobileSearch() {
    mobileSearchOpen.value = true;
    nextTick(() => { mobileSearchInput.value?.focus(); });
}
function closeMobileSearch() {
    mobileSearchOpen.value = false;
    mobileSearchQuery.value = '';
}

/* Desktop search */
function onSearchInput(value) {
    clearTimeout(searchTimer);
    if (!value.trim()) {
        searchSuggestions.value = [];
        searchDropdownOpen.value = false;
        return;
    }
    searchSuggestionsLoading.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get('/api/products', {
                params: { search: value.trim(), per_page: 6, sort: 'newest' },
            });
            searchSuggestions.value = data.data || [];
            searchDropdownOpen.value = true;
        } catch {
            searchSuggestions.value = [];
        }
        searchSuggestionsLoading.value = false;
    }, 350);
}

function onViewAllResults() {
    searchDropdownOpen.value = false;
    window.location.href = `/store?search=${encodeURIComponent(searchQuery.value)}`;
}

function onSearchSubmit() {
    const value = searchQuery.value.trim();
    if (!value) return;
    searchDropdownOpen.value = false;
    window.location.href = `/store?search=${encodeURIComponent(value)}`;
}

function onMobileSearchSubmit() {
    const value = mobileSearchQuery.value.trim();
    if (!value) return;
    window.location.href = `/store?search=${encodeURIComponent(value)}`;
}

function onSearchBlur() { setTimeout(() => { searchDropdownOpen.value = false; }, 200); }

function goToProduct(id) {
    searchDropdownOpen.value = false;
    window.location.href = `/products/${id}`;
}

function productImage(product) {
    const image = product.images?.find((item) => item.is_primary) || product.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}

function onImgError(e) {
    e.target.onerror = null;
    e.target.src = '/images/placeholder.svg';
}

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

onMounted(() => {
    initTheme();
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onOutsideUserClick);
    window.addEventListener('storage', onStorageTheme);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onOutsideUserClick);
    window.removeEventListener('storage', onStorageTheme);
    clearTimeout(searchTimer);
});
</script>

<template>
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-xl border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3">

                <!-- LOGO -->
                <div class="flex items-center gap-2 min-w-0 shrink-0">
                    <button type="button" aria-label="منو" class="lg:hidden text-slate-500 hover:text-ink p-1.5" @click="openDrawer">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <a href="/" class="flex items-center gap-2.5 group">
                        <div class="w-9 h-9 rounded-lg bg-brand-accent flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                            <i class="fa-solid fa-bolt-lightning text-ink text-sm"></i>
                        </div>
                        <span class="text-lg font-extrabold tracking-tight text-ink hidden sm:block">توربو<span class="text-brand-accent">پارت</span></span>
                    </a>
                </div>

                <!-- DESKTOP SEARCH -->
                <div class="hidden md:flex flex-1 max-w-lg mx-4 relative">
                    <form class="relative w-full" @submit.prevent="onSearchSubmit">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="جستجوی کد فنی، نام قطعه، برند..."
                            class="w-full pl-24 pr-9 py-2 bg-white border border-gray-200 rounded-lg text-sm text-ink placeholder-slate-400 focus:outline-none focus:border-brand-accent/60 transition-colors"
                            @input="onSearchInput($event.target.value)"
                            @blur="onSearchBlur"
                            @focus="searchSuggestions.length && (searchDropdownOpen = true)"
                        />
                        <button type="submit" class="absolute left-1 top-1 bottom-1 px-3 bg-gray-200 hover:bg-brand-accent hover:text-dark-900 text-slate-600 text-xs rounded-md transition-colors font-medium">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>

                    <!-- Desktop suggestions -->
                    <Teleport to="body">
                        <div v-if="searchDropdownOpen" class="fixed inset-0 z-30" @click="searchDropdownOpen = false"></div>
                    </Teleport>
                    <div v-if="searchDropdownOpen" class="absolute top-full mt-2 left-0 right-0 z-40 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden max-h-80 overflow-y-auto">
                        <div v-if="searchSuggestionsLoading" class="p-5 text-center text-xs text-slate-400">
                            <i class="fa-solid fa-spinner fa-spin ml-1"></i> در حال جستجو...
                        </div>
                        <template v-else>
                            <button
                                v-for="product in searchSuggestions"
                                :key="product.id"
                                type="button"
                                class="w-full flex items-center gap-3 p-3 hover:bg-gray-50 transition-colors border-b border-gray-200/50 last:border-0"
                                @mousedown.prevent="goToProduct(product.id)"
                            >
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
<img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="w-full h-full object-cover" @error="onImgError($event)" />
                                    <i v-else class="fa-solid fa-box text-slate-600 text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0 text-right">
<div class="text-xs font-bold text-ink truncate">{{ product.name }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ formatPrice(product.price) }} تومان</div>
                                </div>
                                <i class="fa-solid fa-chevron-left text-[9px] text-slate-400 shrink-0"></i>
                            </button>
                            <div class="p-2.5 text-center border-t border-gray-200">
                            <button type="button" class="text-[11px] font-bold text-brand-accent hover:text-brand-hover transition-colors" @click="onViewAllResults">
                                مشاهده همه نتایج
                            </button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="flex items-center gap-1.5 shrink-0">
                    <!-- Mobile search trigger -->
                    <button type="button" aria-label="جستجو" class="md:hidden p-2 rounded-lg text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors" @click="openMobileSearch">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>

                    <button type="button" aria-label="تغییر حالت" class="p-2 rounded-lg text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors" @click="toggleTheme">
                        <i class="fa-solid fa-sun text-sm"></i>
                    </button>

                    <a href="/store" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors">
                        <i class="fa-solid fa-store text-[11px]"></i>
                        فروشگاه
                    </a>

                    <a href="/wishlist" aria-label="علاقه‌مندی‌ها" class="relative p-2 rounded-lg text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors">
                        <span class="inline-block text-xl leading-none" aria-hidden="true">♡</span>
                        <span v-if="isLoggedIn && wishlistCount > 0" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-0.5 bg-brand-accent text-ink text-[10px] font-bold rounded-full flex items-center justify-center">{{ toPersianNumber(wishlistCount) }}</span>
                    </a>

                    <a href="/cart" aria-label="سبد خرید" class="relative p-2 rounded-lg text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span v-if="cartCount > 0" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-0.5 bg-brand-accent text-ink text-[10px] font-bold rounded-full flex items-center justify-center">{{ toPersianNumber(cartCount) }}</span>
                    </a>

                    <template v-if="isLoggedIn">
                        <div ref="userDropdown" class="relative" @focusout="onUserFocusOut">
                            <button ref="userDropdownButton" type="button" aria-label="منوی حساب کاربری" :aria-expanded="userDropdownOpen" aria-controls="header-user-dropdown" class="flex items-center gap-1.5 p-2 sm:px-3 sm:py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-ink hover:bg-gray-100 transition-colors" @click="userDropdownOpen = !userDropdownOpen">
                                <i class="fa-regular fa-user text-[11px]" aria-hidden="true"></i>
                                <span class="hidden sm:block max-w-28 truncate">{{ authState.user?.name || 'حساب من' }}</span>
                                <i class="fa-solid fa-chevron-down text-[8px]" aria-hidden="true"></i>
                            </button>
                            <div v-if="userDropdownOpen" id="header-user-dropdown" class="absolute left-0 top-full mt-2 w-48 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl text-xs text-slate-600">
                                <a href="/account" class="flex items-center gap-2 rounded-lg p-2.5 hover:bg-gray-100"><i class="fa-regular fa-user w-4" aria-hidden="true"></i> حساب کاربری</a>
                                <a href="/orders" class="flex items-center gap-2 rounded-lg p-2.5 hover:bg-gray-100"><i class="fa-solid fa-box w-4" aria-hidden="true"></i> سفارش‌های من</a>
                                <a href="/wishlist" class="flex items-center gap-2 rounded-lg p-2.5 hover:bg-gray-100"><i class="fa-regular fa-heart w-4" aria-hidden="true"></i> علاقه‌مندی‌ها</a>
                                <a href="/cart" class="flex items-center gap-2 rounded-lg p-2.5 hover:bg-gray-100"><i class="fa-solid fa-cart-shopping w-4" aria-hidden="true"></i> سبد خرید</a>
                                <button type="button" :disabled="loggingOut" class="flex w-full items-center gap-2 rounded-lg p-2.5 text-red-600 hover:bg-gray-100 disabled:opacity-50" @click="handleLogout"><i class="fa-solid fa-right-from-bracket w-4" aria-hidden="true"></i> {{ loggingOut ? 'در حال خروج…' : 'خروج' }}</button>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <a href="/login" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-accent hover:bg-brand-hover text-dark-900 text-xs font-bold transition-colors">
                            <i class="fa-regular fa-user text-[11px]"></i>
                            ورود
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <!-- MOBILE SEARCH OVERLAY -->
        <Teleport to="body">
            <div v-if="mobileSearchOpen" class="fixed inset-0 z-50 bg-white/95 md:hidden flex flex-col">
                <div class="flex items-center justify-between px-4 pt-3 pb-2 border-b border-gray-200 bg-white">
                    <button type="button" aria-label="بستن" class="p-2 text-slate-500 hover:text-ink" @click="closeMobileSearch">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </div>
                        <input
                            ref="mobileSearchInput"
                            v-model="mobileSearchQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="جستجوی کد فنی، نام قطعه..."
                            class="w-full pl-4 pr-9 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-ink placeholder-slate-400 focus:outline-none focus:border-brand-accent/60"
                            @keydown.enter="onMobileSearchSubmit"
                        />
                    </div>
                    <button type="button" class="px-3 py-2 text-xs font-bold text-brand-accent hover:text-brand-hover" @click="onMobileSearchSubmit">
                        جستجو
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <div v-if="searchSuggestionsLoading" class="text-center text-xs text-slate-500 py-8">
                        <i class="fa-solid fa-spinner fa-spin ml-1"></i> در حال جستجو...
                    </div>
                    <template v-else-if="searchSuggestions.length">
                        <button
                            v-for="product in searchSuggestions"
                            :key="product.id"
                            type="button"
                            class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors border-b border-gray-200/30 last:border-0"
                            @click="goToProduct(product.id)"
                        >
                            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                <img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="w-full h-full object-cover" />
                                <i v-else class="fa-solid fa-box text-slate-600"></i>
                            </div>
                            <div class="flex-1 min-w-0 text-right">
                                <div class="text-sm font-bold text-ink truncate">{{ product.name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">{{ formatPrice(product.price) }} تومان</div>
                            </div>
                        </button>
                    </template>
                    <div v-else-if="mobileSearchQuery.trim().length >= 2 && !searchSuggestionsLoading" class="text-center text-xs text-slate-500 py-8">
                        نتیجه‌ای یافت نشد
                    </div>
                    <div v-else class="text-center text-xs text-slate-600 py-8">
                        حداقل ۲ کاراکتر تایپ کنید
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MOBILE DRAWER -->
        <Teleport to="body">
            <div v-if="drawerOpen" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm lg:hidden" @click="onDrawerBackdropClick">
                <div class="w-[min(82vw,300px)] h-full bg-white border-l border-gray-200 p-4 flex flex-col justify-between" @click.stop>
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-gray-200">
                            <span class="font-bold text-ink text-sm">منوی دسترسی</span>
                            <button type="button" aria-label="بستن" class="p-2 text-slate-500 hover:text-ink" @click="closeDrawer">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="mt-3 space-y-1.5 text-sm">
                            <a href="/" class="flex items-center gap-2 p-2.5 rounded-lg text-ink font-bold hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-house text-xs w-5 text-center"></i> خانه
                            </a>
                            <a href="/store" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-store text-xs w-5 text-center"></i> فروشگاه
                            </a>
                            <a href="/store?sort=newest" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-fire text-xs w-5 text-center text-brand-accent"></i> جدیدترین محصولات
                            </a>
                            <a v-if="isLoggedIn" href="/orders" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-box text-xs w-5 text-center"></i> سفارش‌های من
                            </a>
                            <a href="/wishlist" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-regular fa-heart text-xs w-5 text-center"></i> علاقه‌مندی‌ها
                                <span v-if="isLoggedIn && wishlistCount > 0" class="text-xs text-brand-accent">{{ toPersianNumber(wishlistCount) }}</span>
                            </a>
                            <a href="/cart" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-cart-shopping text-xs w-5 text-center"></i> سبد خرید
                                <span v-if="cartCount > 0" class="text-xs text-brand-accent">{{ toPersianNumber(cartCount) }}</span>
                            </a>
                            <a v-if="isLoggedIn" href="/account" class="flex items-center gap-2 p-2.5 rounded-lg text-slate-600 hover:bg-gray-100 transition-colors">
                                <i class="fa-regular fa-user text-xs w-5 text-center"></i> حساب من
                            </a>
                            <a v-else href="/login" class="flex items-center gap-2 p-2.5 rounded-lg text-brand-accent font-bold hover:bg-gray-100 transition-colors">
                                <i class="fa-regular fa-user text-xs w-5 text-center"></i> ورود / ثبت‌نام
                            </a>
                            <button v-if="isLoggedIn" type="button" :disabled="loggingOut" class="flex w-full items-center gap-2 p-2.5 rounded-lg text-red-600 hover:bg-gray-100 disabled:opacity-50" @click="handleLogout">
                                <i class="fa-solid fa-right-from-bracket text-xs w-5 text-center"></i> {{ loggingOut ? 'در حال خروج…' : 'خروج' }}
                            </button>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-gray-200 text-[11px] text-slate-500">
                        <p>تلفن پشتیبانی: <span dir="ltr" class="text-slate-600">۰۲۱-۸۸۹۹۰۰۰۰</span></p>
                    </div>
                </div>
            </div>
        </Teleport>
    </header>
</template>
