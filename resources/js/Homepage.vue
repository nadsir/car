<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';

/* ── Theme ────────────────────────────────────────────────── */
const isLight = ref(false);

/* ── Hero Slider ──────────────────────────────────────────── */
const heroSlides = [
    {
        image: '/images/hero-parts-1.svg',
        alt: 'قطعات خودرو در نور کارگاه',
        eyebrow: 'انتخاب دقیق، خرید مطمئن',
        title: 'قطعهٔ درست برای خودروی شما',
        description: 'جستجو بر اساس کمپانی، مدل، نسل، تیپ و موتور؛ بدون حدس و گمان.',
    },
    {
        image: '/images/hero-parts-2.svg',
        alt: 'موتور و قطعات مکانیکی خودرو',
        eyebrow: 'تنوعی برای هر نیاز',
        title: 'از موتور تا بدنه، یک انتخاب مطمئن',
        description: 'قطعات مصرفی و اصلی برای خودروهای داخلی، چینی و وارداتی.',
    },
    {
        image: '/images/hero-parts-3.svg',
        alt: 'دیسک ترمز و قطعات یدکی خودرو',
        eyebrow: 'انتخاب آسان، خرید مطمئن',
        title: 'قطعهٔ مناسب را با اطمینان پیدا کنید',
        description: 'با اطلاعات دقیق سازگاری و راهنمایی فنی، خرید خود را نهایی کنید.',
    },
];

const activeHeroSlide = ref(0);
const activeHero = computed(() => heroSlides[activeHeroSlide.value]);
let heroTimer = null;

function setHeroSlide(index) {
    activeHeroSlide.value = (index + heroSlides.length) % heroSlides.length;
}

function nextHeroSlide() {
    setHeroSlide(activeHeroSlide.value + 1);
}

function prevHeroSlide() {
    setHeroSlide(activeHeroSlide.value - 1);
}

function stopHeroAutoplay() {
    if (heroTimer) {
        clearInterval(heroTimer);
        heroTimer = null;
    }
}

function startHeroAutoplay() {
    stopHeroAutoplay();
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) return;
    heroTimer = setInterval(nextHeroSlide, 7000);
}

/* ── Toast ────────────────────────────────────────────────── */
const toastVisible = ref(false);
const toastTitle = ref('');
const toastMessage = ref('');
const toastType = ref('success');
let toastTimer = null;

function showToast(message, title = 'انجام شد', type = 'success') {
    toastTitle.value = title;
    toastMessage.value = message;
    toastType.value = type;
    toastVisible.value = true;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastVisible.value = false; }, 3500);
}

function onToastEvent(e) {
    const d = e.detail;
    if (d) showToast(d.message, d.title, d.type);
}

/* ── Cart ─────────────────────────────────────────────────── */
function toPersianNumber(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

/* ── Helpers ──────────────────────────────────────────────── */
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

/* ── Vehicle Finder ───────────────────────────────────────── */
const vehicleBrands = ref([]);
const vehicleModels = ref([]);
const vehicleGenerations = ref([]);
const vehicleTrims = ref([]);
const vehicleEngines = ref([]);

const vfBrand = ref('');
const vfModel = ref('');
const vfGeneration = ref('');
const vfTrim = ref('');
const vfEngine = ref('');

const vfBrandsLoading = ref(false);
const vfModelsLoading = ref(false);
const vfGenerationsLoading = ref(false);
const vfTrimsLoading = ref(false);
const vfEnginesLoading = ref(false);

async function loadVehicleBrands() {
    vfBrandsLoading.value = true;
    try {
        const { data } = await axios.get('/api/vehicles/brands');
        vehicleBrands.value = data.data || [];
    } catch { vehicleBrands.value = []; }
    vfBrandsLoading.value = false;
}

async function loadVehicleModels(brandId) {
    if (!brandId) { vehicleModels.value = []; return; }
    vfModelsLoading.value = true;
    try {
        const { data } = await axios.get(`/api/vehicles/brands/${brandId}/models`);
        vehicleModels.value = data.data || [];
    } catch { vehicleModels.value = []; }
    vfModelsLoading.value = false;
}

async function loadVehicleGenerations(modelId) {
    if (!modelId) { vehicleGenerations.value = []; return; }
    vfGenerationsLoading.value = true;
    try {
        const { data } = await axios.get(`/api/vehicles/models/${modelId}/generations`);
        vehicleGenerations.value = data.data || [];
    } catch { vehicleGenerations.value = []; }
    vfGenerationsLoading.value = false;
}

async function loadVehicleTrims(generationId) {
    if (!generationId) { vehicleTrims.value = []; return; }
    vfTrimsLoading.value = true;
    try {
        const { data } = await axios.get(`/api/vehicles/generations/${generationId}/trims`);
        vehicleTrims.value = data.data || [];
    } catch { vehicleTrims.value = []; }
    vfTrimsLoading.value = false;
}

async function loadVehicleEngines(trimId) {
    if (!trimId) { vehicleEngines.value = []; return; }
    vfEnginesLoading.value = true;
    try {
        const { data } = await axios.get(`/api/vehicles/trims/${trimId}/engines`);
        vehicleEngines.value = data.data || [];
    } catch { vehicleEngines.value = []; }
    vfEnginesLoading.value = false;
}

async function onVfBrandChange() {
    vfModel.value = ''; vfGeneration.value = ''; vfTrim.value = ''; vfEngine.value = '';
    vehicleModels.value = []; vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = [];
    if (vfBrand.value) await loadVehicleModels(vfBrand.value);
}

async function onVfModelChange() {
    vfGeneration.value = ''; vfTrim.value = ''; vfEngine.value = '';
    vehicleGenerations.value = []; vehicleTrims.value = []; vehicleEngines.value = [];
    if (vfModel.value) await loadVehicleGenerations(vfModel.value);
}

async function onVfGenerationChange() {
    vfTrim.value = ''; vfEngine.value = '';
    vehicleTrims.value = []; vehicleEngines.value = [];
    if (vfGeneration.value) await loadVehicleTrims(vfGeneration.value);
}

async function onVfTrimChange() {
    vfEngine.value = '';
    vehicleEngines.value = [];
    if (vfTrim.value) await loadVehicleEngines(vfTrim.value);
}

function onVfSubmit() {
    const params = new URLSearchParams();
    if (vfEngine.value) params.set('vehicle_engine_id', vfEngine.value);
    else if (vfTrim.value) params.set('vehicle_trim_id', vfTrim.value);
    else if (vfGeneration.value) params.set('vehicle_generation_id', vfGeneration.value);
    else if (vfModel.value) params.set('vehicle_model_id', vfModel.value);
    else if (vfBrand.value) params.set('vehicle_brand_id', vfBrand.value);
    if (params.toString()) window.location.href = `/store?${params.toString()}`;
    else showToast('لطفاً حداقل یک گزینه خودرو را انتخاب کنید.', 'انتخاب ناقص', 'warning');
}

/* ── Categories ───────────────────────────────────────────── */
const categories = ref([]);
const categoriesLoading = ref(false);

async function loadCategories() {
    categoriesLoading.value = true;
    try {
        const { data } = await axios.get('/api/categories/tree');
        categories.value = data.data || [];
    } catch { categories.value = []; }
    categoriesLoading.value = false;
}

const categoryItems = computed(() => {
    const flattened = categories.value.flatMap((category) => (
        category.children?.length ? category.children : [category]
    ));
    return flattened.slice(0, 9);
});

const categoryIconMap = [
    { tokens: ['engine', 'موتور'], icon: 'fa-solid fa-gears' },
    { tokens: ['powertrain', 'انتقال'], icon: 'fa-solid fa-gear' },
    { tokens: ['brake', 'ترمز'], icon: 'fa-solid fa-compact-disc' },
    { tokens: ['suspension', 'steering', 'تعلیق', 'فرمان'], icon: 'fa-solid fa-car-side' },
    { tokens: ['electrical', 'electronics', 'برق', 'الکترونیک'], icon: 'fa-solid fa-bolt' },
    { tokens: ['air-conditioning', 'تهویه', 'کولر'], icon: 'fa-solid fa-snowflake' },
    { tokens: ['body', 'lighting', 'بدنه', 'روشنایی'], icon: 'fa-solid fa-lightbulb' },
    { tokens: ['filter', 'فیلتر', 'سرویس'], icon: 'fa-solid fa-filter' },
    { tokens: ['consumable', 'accessories', 'مصرفی', 'متفرقه'], icon: 'fa-solid fa-box-open' },
];

const categoryAccentStyles = [
    'text-[#a87f00]',
    'text-cyan-700',
    'text-emerald-700',
    'text-violet-700',
    'text-rose-700',
    'text-amber-700',
    'text-blue-700',
    'text-teal-700',
    'text-slate-700',
];

const categoryImageMap = [
    { tokens: ['engine', 'موتور'], image: '/images/categories/engine.svg' },
    { tokens: ['powertrain', 'انتقال'], image: '/images/categories/powertrain.svg' },
    { tokens: ['brake', 'ترمز'], image: '/images/categories/brake.svg' },
    { tokens: ['suspension', 'steering', 'تعلیق', 'فرمان'], image: '/images/categories/suspension.svg' },
    { tokens: ['electrical', 'electronics', 'برق', 'الکترونیک'], image: '/images/categories/electrical.svg' },
    { tokens: ['air-conditioning', 'تهویه', 'کولر'], image: '/images/categories/air-conditioning.svg' },
    { tokens: ['body', 'lighting', 'بدنه', 'روشنایی'], image: '/images/categories/body-lighting.svg' },
    { tokens: ['filter', 'فیلتر', 'سرویس'], image: '/images/categories/filters.svg' },
    { tokens: ['consumable', 'accessories', 'مصرفی', 'متفرقه'], image: '/images/categories/consumables.svg' },
];

const categoryImageFallbacks = [
    '/images/categories/engine.svg',
    '/images/categories/powertrain.svg',
    '/images/categories/brake.svg',
    '/images/categories/suspension.svg',
    '/images/categories/electrical.svg',
    '/images/categories/air-conditioning.svg',
    '/images/categories/body-lighting.svg',
    '/images/categories/filters.svg',
    '/images/categories/consumables.svg',
];

function categoryIcon(category) {
    const haystack = `${category.slug || ''} ${category.name || ''}`.toLowerCase();
    return categoryIconMap.find(({ tokens }) => tokens.some((token) => haystack.includes(token)))?.icon
        || 'fa-solid fa-layer-group';
}

function categoryImage(category, index = 0) {
    const haystack = `${category.slug || ''} ${category.name || ''}`.toLowerCase();
    return categoryImageMap.find(({ tokens }) => tokens.some((token) => haystack.includes(token)))?.image
        || categoryImageFallbacks[index % categoryImageFallbacks.length];
}

function categoryAccent(index) {
    return categoryAccentStyles[index % categoryAccentStyles.length];
}

/* ── Featured Products ────────────────────────────────────── */
const featuredProducts = ref([]);
const featuredLoading = ref(false);
const featuredError = ref('');

async function loadFeaturedProducts() {
    featuredLoading.value = true;
    featuredError.value = '';
    try {
        const { data } = await axios.get('/api/products', { params: { per_page: 8, sort: 'newest' } });
        featuredProducts.value = data.data || [];
    } catch {
        featuredProducts.value = [];
        featuredError.value = 'دریافت محصولات ناموفق بود.';
    }
    featuredLoading.value = false;
}

/* ── Articles ─────────────────────────────────────────────── */
const articles = ref([]);
const articlesLoading = ref(false);
const articlesError = ref('');

const latestArticles = computed(() => articles.value.slice(0, 4));
const featuredArticle = computed(() => latestArticles.value[0] || null);
const articleGrid = computed(() => latestArticles.value.slice(1, 4));

function articleImage(a) {
    if (!a.featured_image) return '';
    if (/^https?:\/\//i.test(a.featured_image)) return a.featured_image;
    return `/storage/${a.featured_image.replace(/^\/?storage\//, '')}`;
}

function formatArticleDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' }).format(d);
}

async function loadArticles() {
    articlesLoading.value = true;
    articlesError.value = '';
    try {
        const { data } = await axios.get('/api/articles');
        articles.value = data.data || [];
    } catch {
        articles.value = [];
        articlesError.value = 'دریافت مقالات ناموفق بود.';
    }
    articlesLoading.value = false;
}

/* ── Authenticity ─────────────────────────────────────────── */
const authCode = ref('');
function onAuthSubmit() {
    const code = authCode.value.trim();
    if (!code) { showToast('کد رهگیری را وارد کنید.', 'استعلام اصالت', 'warning'); return; }
    showToast(`کد «${code}» برای بررسی به سامانه اصالت ارسال می‌شود.`, 'درخواست استعلام');
    authCode.value = '';
}

/* ── B2B ──────────────────────────────────────────────────── */
const b2bForm = ref({ name: '', phone: '', city: '', business: '' });
function onB2bSubmit() {
    showToast('در نسخه نهایی این اطلاعات به API فروشگاه ارسال خواهد شد.', 'درخواست همکاری ثبت شد');
    b2bForm.value = { name: '', phone: '', city: '', business: '' };
}

/* ── Newsletter ───────────────────────────────────────────── */
const newsletterPhone = ref('');
function onNewsletterSubmit() {
    const phone = newsletterPhone.value.trim();
    if (!/^09\d{9}$/.test(phone)) { showToast('شماره موبایل واردشده معتبر نیست.', 'شماره موبایل', 'warning'); return; }
    showToast('شماره موبایل شما با موفقیت ثبت شد.', 'عضویت موفق');
    newsletterPhone.value = '';
}

/* ── Lifecycle ────────────────────────────────────────────── */
onMounted(async () => {
    window.addEventListener('toast', onToastEvent);
    startHeroAutoplay();
    await Promise.all([loadVehicleBrands(), loadCategories(), loadFeaturedProducts(), loadArticles()]);
});

onUnmounted(() => {
    window.removeEventListener('toast', onToastEvent);
    clearTimeout(toastTimer);
    stopHeroAutoplay();
});
</script>

<template>
    <div dir="rtl" class="bg-cream text-ink font-sans antialiased overflow-x-hidden selection:bg-brand-accent selection:text-ink min-h-screen">

        <SiteHeader />

        <!-- ANNOUNCEMENT -->
        <div class="bg-white border-b border-gray-200 text-xs py-2 px-4 relative z-30">
            <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-brand-accent text-ink">ویژه همکاران B2B</span>
                    <span class="text-slate-500 hidden sm:inline">شرایط ویژه خرید عمده و همکاری با فروشگاه‌ها و تعمیرگاه‌ها</span>
                </div>
                <div class="hidden sm:flex items-center gap-4 text-slate-500">
                    <a href="#wholesale" class="hover:text-brand-accent transition-colors flex items-center gap-1 font-semibold text-brand-accent">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        درخواست پیش‌فاکتور عمده
                    </a>
                    <span class="text-slate-500">|</span>
                    <div class="flex items-center gap-1">
                        <i class="fa-solid fa-headset text-brand-accent"></i>
                        <span>پشتیبانی: <span class="font-bold text-ink" dir="ltr">021-88990000</span></span>
                    </div>
                </div>
            </div>
        </div>

        <main>

            <!-- HERO / FULL-WIDTH SLIDER -->
            <section
                class="relative w-full overflow-hidden bg-[#0c0d0f]"
                aria-roledescription="carousel"
                aria-label="بنر اصلی"
                @mouseenter="stopHeroAutoplay"
                @mouseleave="startHeroAutoplay"
                @focusin="stopHeroAutoplay"
                @focusout="startHeroAutoplay"
            >
                <div class="relative h-[640px] w-full sm:h-[660px] lg:h-[600px] xl:h-[640px]">

                    <!-- BACKGROUND SLIDES -->
                    <div
                        v-for="(slide, index) in heroSlides"
                        :key="slide.image"
                        class="absolute inset-0 transition-opacity duration-700 ease-out"
                        :class="index === activeHeroSlide ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                        :aria-hidden="index !== activeHeroSlide"
                    >
                        <img
                            :src="slide.image"
                            :alt="slide.alt"
                            class="h-full w-full object-cover"
                            :loading="index === 0 ? 'eager' : 'lazy'"
                            draggable="false"
                        />
                        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(0,0,0,0.8),rgba(0,0,0,0.45),rgba(0,0,0,0.1))]"></div>
                        <div class="absolute inset-0 bg-[linear-gradient(to_top,rgba(0,0,0,0.75),rgba(0,0,0,0.1),rgba(0,0,0,0.2))]"></div>
                    </div>

                    <!-- SUBTLE TEXTURE -->
                    <div class="pointer-events-none absolute inset-0 opacity-40 [background-image:radial-gradient(#FFCD00_0.6px,transparent_0.6px)] [background-size:28px_28px]"></div>

                    <div class="relative z-10 mx-auto h-full max-w-7xl px-4 sm:px-6 lg:px-8">

                        <!-- DESKTOP CAPTION -->
                        <div class="hidden h-full max-w-xl lg:ml-auto lg:flex lg:max-w-[520px] lg:flex-col lg:justify-center lg:pr-4 xl:max-w-xl xl:pr-10">
                            <div :key="activeHeroSlide" class="fade-in max-w-full text-right">
                                <span class="inline-flex items-center gap-2 rounded-full border border-brand-accent/30 bg-[rgba(255,255,255,0.1)] px-3 py-1.5 text-[11px] font-bold text-brand-accent backdrop-blur-sm">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-accent"></span>
                                    {{ activeHero.eyebrow }}
                                </span>
                                <h1 class="mt-5 text-4xl font-black leading-[1.35] text-[#ffffff] xl:text-5xl">
                                    {{ activeHero.title }}
                                </h1>
                                <p class="mt-4 max-w-lg text-sm leading-relaxed text-[#d7d7d7] xl:text-base">
                                    {{ activeHero.description }}
                                </p>
                            </div>
                        </div>

                        <!-- VEHICLE FINDER -->
                        <div
                            id="vehicle-finder"
                            class="absolute inset-x-4 bottom-4 z-20 sm:inset-x-6 sm:bottom-6 lg:inset-x-auto lg:bottom-auto lg:left-8 lg:top-1/2 lg:w-[360px] lg:-translate-y-1/2 xl:left-10 xl:w-[420px]"
                        >
                            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-[0_24px_80px_rgba(0,0,0,0.35)] backdrop-blur-xl sm:p-5 lg:p-6">
                                <div class="flex items-center justify-between gap-3 border-b border-gray-200 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent/15 text-brand-accent">
                                            <i class="fa-solid fa-sliders text-sm"></i>
                                        </div>
                                        <div>
                                            <h2 class="text-sm font-black text-ink">یافتن سریع قطعه</h2>
                                            <p class="text-[11px] text-slate-500">خودرو را انتخاب کنید</p>
                                        </div>
                                    </div>
                                    <span class="hidden shrink-0 rounded border border-brand-accent/30 bg-brand-accent/10 px-2 py-0.5 text-[10px] font-bold text-brand-accent sm:inline">انتخاب هوشمند</span>
                                </div>

                                <form class="mt-4 space-y-2.5" @submit.prevent="onVfSubmit">
                                    <div>
                                        <label class="mb-1 block text-[10px] font-semibold text-slate-500 sm:text-[11px]">۱. کمپانی سازنده</label>
                                        <div class="relative">
                                            <select v-model="vfBrand" class="min-h-[44px] w-full cursor-pointer appearance-none rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-[12px] text-ink focus:border-brand-accent focus:outline-none sm:px-3 sm:text-sm" required @change="onVfBrandChange">
                                                <option value="">{{ vfBrandsLoading ? 'بارگذاری...' : 'انتخاب کمپانی' }}</option>
                                                <option v-for="b in vehicleBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                            </select>
                                            <i class="fa-solid fa-chevron-down pointer-events-none absolute left-2.5 top-3.5 text-[10px] text-slate-500 sm:left-3"></i>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-500 sm:text-[11px]">۲. مدل</label>
                                            <div class="relative">
                                                <select v-model="vfModel" :disabled="!vfBrand || vfModelsLoading" class="min-h-[44px] w-full cursor-pointer appearance-none rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-[12px] text-ink focus:border-brand-accent focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" required @change="onVfModelChange">
                                                    <option value="">{{ vfModelsLoading ? 'بارگذاری...' : (vfBrand ? 'انتخاب مدل' : '—') }}</option>
                                                    <option v-for="m in vehicleModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down pointer-events-none absolute left-2.5 top-3.5 text-[10px] text-slate-500 sm:left-3"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-500 sm:text-[11px]">۳. نسل</label>
                                            <div class="relative">
                                                <select v-model="vfGeneration" :disabled="!vfModel || vfGenerationsLoading" class="min-h-[44px] w-full cursor-pointer appearance-none rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-[12px] text-ink focus:border-brand-accent focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" @change="onVfGenerationChange">
                                                    <option value="">{{ vfGenerationsLoading ? 'بارگذاری...' : (vfModel ? 'انتخاب نسل' : '—') }}</option>
                                                    <option v-for="g in vehicleGenerations" :key="g.id" :value="g.id">{{ g.name }}{{ g.year_start ? ` (${g.year_start})` : '' }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down pointer-events-none absolute left-2.5 top-3.5 text-[10px] text-slate-500 sm:left-3"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-500 sm:text-[11px]">۴. تیپ</label>
                                            <div class="relative">
                                                <select v-model="vfTrim" :disabled="!vfGeneration || vfTrimsLoading" class="min-h-[44px] w-full cursor-pointer appearance-none rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-[12px] text-ink focus:border-brand-accent focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" @change="onVfTrimChange">
                                                    <option value="">{{ vfTrimsLoading ? 'بارگذاری...' : (vfGeneration ? 'انتخاب تیپ' : '—') }}</option>
                                                    <option v-for="t in vehicleTrims" :key="t.id" :value="t.id">{{ t.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down pointer-events-none absolute left-2.5 top-3.5 text-[10px] text-slate-500 sm:left-3"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[10px] font-semibold text-slate-500 sm:text-[11px]">۵. موتور</label>
                                            <div class="relative">
                                                <select v-model="vfEngine" :disabled="!vfTrim || vfEnginesLoading" class="min-h-[44px] w-full cursor-pointer appearance-none rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-[12px] text-ink focus:border-brand-accent focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 sm:px-3 sm:text-sm" @change="onVfEngineChange">
                                                    <option value="">{{ vfEnginesLoading ? 'بارگذاری...' : (vfTrim ? 'انتخاب موتور' : '—') }}</option>
                                                    <option v-for="e in vehicleEngines" :key="e.id" :value="e.id">{{ e.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down pointer-events-none absolute left-2.5 top-3.5 text-[10px] text-slate-500 sm:left-3"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="mt-1 flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg bg-brand-accent py-3.5 text-sm font-bold text-ink shadow-glow-yellow transition-all hover:bg-brand-hover active:scale-[.98]">
                                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                                        نمایش قطعات سازگار
                                    </button>
                                </form>

                                <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-200 pt-3 text-[10px] font-semibold text-slate-600">
                                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-check-double text-brand-accent"></i> بررسی سازگاری</span>
                                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-arrow-rotate-left text-brand-accent"></i> امکان مرجوعی</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SLIDER CONTROLS -->
                    <div class="absolute right-4 top-4 z-30 flex items-center gap-2 sm:right-6 sm:top-6 lg:right-8 lg:top-8">
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full border border-[rgba(255,255,255,0.25)] bg-[rgba(0,0,0,0.32)] text-[#ffffff] backdrop-blur-sm transition-colors hover:bg-[rgba(0,0,0,0.52)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent" aria-label="اسلاید قبلی" @click="prevHeroSlide">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </button>
                        <span class="min-w-[3.5rem] rounded-full border border-[rgba(255,255,255,0.2)] bg-[rgba(0,0,0,0.32)] px-2.5 py-1.5 text-center text-[11px] font-bold text-[#ffffff] backdrop-blur-sm">
                            {{ toPersianNumber(activeHeroSlide + 1) }} / {{ toPersianNumber(heroSlides.length) }}
                        </span>
                        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full border border-[rgba(255,255,255,0.25)] bg-[rgba(0,0,0,0.32)] text-[#ffffff] backdrop-blur-sm transition-colors hover:bg-[rgba(0,0,0,0.52)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent" aria-label="اسلاید بعدی" @click="nextHeroSlide">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </button>
                    </div>

                    <div class="absolute bottom-8 right-8 z-20 hidden items-center gap-2 lg:flex" role="tablist" aria-label="انتخاب اسلاید">
                        <button
                            v-for="(slide, index) in heroSlides"
                            :key="`dot-${slide.image}`"
                            type="button"
                            role="tab"
                            :aria-label="`اسلاید ${toPersianNumber(index + 1)}`"
                            :aria-selected="index === activeHeroSlide"
                            class="h-2.5 rounded-full transition-all duration-300"
                            :class="index === activeHeroSlide ? 'w-8 bg-brand-accent' : 'w-2.5 bg-[rgba(255,255,255,0.4)] hover:bg-[rgba(255,255,255,0.7)]'"
                            @click="setHeroSlide(index)"
                        ></button>
                    </div>
                </div>
            </section>

            <!-- TRUST -->
            <section class="border-y border-gray-200 bg-white/60 py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-white/80 border border-gray-200/50">
                            <div class="w-10 h-10 rounded-lg bg-brand-accent/10 flex items-center justify-center text-brand-accent shrink-0"><i class="fa-solid fa-certificate text-sm"></i></div>
                            <div><h4 class="font-bold text-xs text-ink">ضمانت اصالت</h4><p class="text-[10px] text-slate-500 mt-0.5">پیگیری اصالت کالا</p></div>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-white/80 border border-gray-200/50">
                            <div class="w-10 h-10 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400 shrink-0"><i class="fa-solid fa-truck-fast text-sm"></i></div>
                            <div><h4 class="font-bold text-xs text-ink">ارسال سریع</h4><p class="text-[10px] text-slate-500 mt-0.5">تهران و سراسر کشور</p></div>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-white/80 border border-gray-200/50">
                            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 shrink-0"><i class="fa-solid fa-hand-holding-dollar text-sm"></i></div>
                            <div><h4 class="font-bold text-xs text-ink">قیمت همکاری</h4><p class="text-[10px] text-slate-500 mt-0.5">شرایط عمده</p></div>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-xl bg-white/80 border border-gray-200/50">
                            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 shrink-0"><i class="fa-solid fa-headset text-sm"></i></div>
                            <div><h4 class="font-bold text-xs text-ink">مشاوره تخصصی</h4><p class="text-[10px] text-slate-500 mt-0.5">انتخاب قطعه مناسب</p></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CATEGORIES -->
            <section id="categories" class="relative overflow-hidden py-16 sm:py-20">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_0%,rgba(255,205,0,0.12),transparent_38%)]"></div>
                <div class="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-brand-accent/5 blur-3xl"></div>

                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-8 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div class="max-w-2xl">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-brand-accent/20 bg-brand-accent/10 px-2.5 py-1 text-[11px] font-bold text-brand-accent">
                                    <i class="fa-solid fa-layer-group"></i>
                                    دسته‌بندی قطعات
                                </span>
                                <span class="rounded-full border border-gray-200 bg-white/70 px-2.5 py-1 text-[10px] font-bold text-slate-500">
                                    {{ toPersianNumber(categoryItems.length) }} دسته‌بندی اصلی
                                </span>
                            </div>
                            <h2 class="text-2xl font-black leading-tight text-ink sm:text-3xl lg:text-4xl">قطعات مورد نیاز خودروی شما</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">از سیستم ترمز تا قطعات موتوری و برقی؛ مسیر خرید قطعه را سریع‌تر پیدا کنید.</p>
                        </div>
                        <a href="/store" class="inline-flex w-fit items-center gap-2 rounded-full border border-brand-accent/40 bg-white/70 px-4 py-2.5 text-sm font-bold text-ink shadow-sm backdrop-blur transition-all hover:-translate-y-0.5 hover:border-brand-accent hover:bg-brand-accent hover:shadow-glow-yellow">
                            مشاهده همه
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </a>
                    </div>

                    <div v-if="categoriesLoading" class="flex flex-wrap justify-center gap-3 sm:gap-4">
                        <div v-for="i in 9" :key="i" class="min-h-[188px] w-[calc(50%-0.375rem)] animate-pulse rounded-[1.4rem] border border-[rgba(255,255,255,0.6)] bg-[rgba(255,255,255,0.55)] sm:min-h-[200px] sm:w-[calc(33.333%-0.667rem)] lg:w-[calc(25%-0.75rem)]"></div>
                    </div>

                    <div v-else-if="categoryItems.length" class="flex flex-wrap justify-center gap-3 sm:gap-4">
                        <a
                            v-for="(cat, index) in categoryItems"
                            :key="cat.slug || cat.id"
                            :href="`/store?category=${encodeURIComponent(cat.slug)}`"
                            class="home-category-card group relative flex min-h-[188px] w-[calc(50%-0.375rem)] flex-col overflow-hidden rounded-[1.4rem] border border-[rgba(255,255,255,0.55)] p-3 sm:min-h-[200px] sm:w-[calc(33.333%-0.667rem)] sm:p-4 lg:w-[calc(25%-0.75rem)]"
                        >
                            <img
                                :src="categoryImage(cat, index)"
                                :alt="`قطعات ${cat.name}`"
                                class="home-category-card__image pointer-events-none absolute inset-0 h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.3),rgba(255,255,255,0.06)_38%,rgba(15,23,42,0.18))]"></div>
                            <div class="pointer-events-none absolute inset-0 bg-[rgba(255,255,255,0.14)] backdrop-blur-[2px]"></div>
                            <span class="home-category-card__glare pointer-events-none absolute inset-0"></span>

                            <div class="relative z-10 flex items-start justify-between gap-2">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-[rgba(255,255,255,0.7)] bg-[rgba(255,255,255,0.55)] shadow-[inset_0_1px_0_rgba(255,255,255,0.85),0_6px_16px_rgba(17,24,39,0.14)] backdrop-blur-md transition-transform duration-500 group-hover:-rotate-6 group-hover:scale-110 sm:h-12 sm:w-12" :class="categoryAccent(index)">
                                    <i :class="categoryIcon(cat)" class="text-base sm:text-lg"></i>
                                </span>
                                <span class="rounded-full border border-[rgba(255,255,255,0.65)] bg-[rgba(255,255,255,0.45)] px-2 py-0.5 text-[10px] font-bold text-[#334155] shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] backdrop-blur-md">
                                    {{ cat.children?.length ? `${toPersianNumber(cat.children.length)} زیردسته` : 'دسته‌بندی' }}
                                </span>
                            </div>

                            <div class="relative z-10 mt-auto pt-8">
                                <div class="rounded-[1.05rem] border border-[rgba(255,255,255,0.55)] bg-[rgba(255,255,255,0.38)] px-3 py-2.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-md transition-colors duration-300 group-hover:bg-[rgba(255,255,255,0.52)]">
                                    <h3 class="text-[13px] font-black leading-snug text-[#111827] sm:text-sm">{{ cat.name }}</h3>
                                    <span class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-[#475569] transition-colors group-hover:text-[#111827]">
                                        مشاهده قطعات
                                        <i class="fa-solid fa-arrow-left text-[9px] transition-transform duration-300 group-hover:-translate-x-1"></i>
                                    </span>
                                </div>
                            </div>

                            <span class="pointer-events-none absolute inset-x-3 bottom-0 h-px bg-gradient-to-l from-transparent via-[rgba(255,255,255,0.85)] to-transparent"></span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- FEATURED PRODUCTS -->
            <section id="products" class="relative overflow-hidden border-y border-gray-200 bg-gradient-to-b from-cream via-white/60 to-cream py-16 sm:py-20">
                <div class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-brand-accent/5 blur-3xl"></div>
                <div class="pointer-events-none absolute -left-20 bottom-0 h-80 w-80 rounded-full border border-brand-accent/10"></div>
                <div class="pointer-events-none absolute left-1/3 top-20 hidden h-44 w-44 rotate-12 rounded-[2rem] border border-gray-200/60 lg:block"></div>

                <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-8 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div class="max-w-2xl">
                            <span class="mb-3 inline-flex items-center gap-1.5 rounded-lg border border-brand-accent/20 bg-brand-accent/10 px-2.5 py-1 text-[11px] font-bold text-brand-accent">
                                <i class="fa-solid fa-fire-flame-curved"></i>
                                پرفروش‌ترین‌ها
                            </span>
                            <h2 class="text-2xl font-black leading-tight text-ink sm:text-3xl lg:text-4xl">پرفروش‌ترین قطعات بازار</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">گلچینی از پرتقاضاترین قطعات خودرو با قیمت شفاف و امکان مشاهده سریع.</p>
                        </div>
                        <a href="/store" class="inline-flex w-fit items-center gap-2 rounded-full border border-brand-accent/40 bg-white/70 px-4 py-2.5 text-sm font-bold text-ink shadow-sm backdrop-blur transition-all hover:-translate-y-0.5 hover:border-brand-accent hover:bg-brand-accent hover:text-[#1a1a1a] hover:shadow-glow-yellow">
                            مشاهده همه محصولات
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </a>
                    </div>

                    <div v-if="featuredLoading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                        <div v-for="i in 8" :key="i" class="animate-pulse overflow-hidden rounded-2xl border border-gray-200 bg-white/70">
                            <div class="aspect-square bg-sand"></div>
                            <div class="space-y-2 p-3">
                                <div class="h-2.5 w-3/4 rounded bg-gray-100"></div>
                                <div class="h-3.5 w-1/2 rounded bg-gray-100"></div>
                                <div class="h-8 w-full rounded-lg bg-gray-100"></div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="featuredError" class="rounded-2xl border border-gray-200 bg-white/60 py-12 text-center">
                        <i class="fa-solid fa-circle-exclamation mb-3 text-3xl text-slate-500"></i>
                        <p class="text-xs text-slate-500">{{ featuredError }}</p>
                    </div>

                    <div v-else-if="!featuredProducts.length" class="rounded-2xl border border-gray-200 bg-white/60 py-12 text-center">
                        <i class="fa-solid fa-box-open mb-3 text-3xl text-slate-500"></i>
                        <p class="text-xs text-slate-500">محصولی یافت نشد.</p>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                        <a
                            v-for="product in featuredProducts"
                            :key="product.id"
                            :href="`/products/${product.id}`"
                            class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 p-3 transition-all duration-300 hover:-translate-y-1 hover:border-brand-accent/45 hover:shadow-[0_20px_50px_rgba(0,0,0,0.09)]"
                        >
                            <div class="relative mb-3 flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-sand via-blush to-white p-3">
                                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,205,0,0.16),transparent_48%)]"></div>
                                <span v-if="product.compare_at_price" class="absolute right-2 top-2 z-20 rounded bg-red-600 px-1.5 py-0.5 text-[9px] font-bold text-[#ffffff]">تخفیف</span>
                                <span v-if="product.in_stock" class="absolute left-2 top-2 z-20 rounded bg-emerald-600 px-1.5 py-0.5 text-[9px] font-bold text-[#ffffff]">موجود</span>
                                <span v-else class="absolute left-2 top-2 z-20 rounded bg-slate-600 px-1.5 py-0.5 text-[9px] font-bold text-[#ffffff]">ناموجود</span>
                                <img
                                    v-if="productImage(product)"
                                    :src="productImage(product)"
                                    :alt="product.name"
                                    class="relative z-10 max-h-full max-w-full object-contain transition-transform duration-500 group-hover:scale-105"
                                    loading="lazy"
                                    @error="onImgError($event)"
                                />
                                <i v-else class="fa-solid fa-box relative z-10 text-4xl text-slate-500"></i>
                                <span class="absolute inset-x-3 bottom-3 z-20 translate-y-2 rounded-lg bg-[rgba(15,15,15,0.82)] py-1.5 text-center text-[10px] font-bold text-[#ffffff] opacity-0 backdrop-blur transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">مشاهده سریع</span>
                            </div>

                            <div v-if="product.sku" class="mb-0.5 font-mono text-[10px] text-slate-500">SKU: {{ product.sku }}</div>
                            <h3 class="line-clamp-2 text-xs font-bold leading-snug text-ink transition-colors group-hover:text-brand-accent">{{ product.name }}</h3>

                            <div class="mt-auto pt-2.5">
                                <div v-if="product.compare_at_price" class="flex flex-wrap items-end gap-x-2 gap-y-0.5">
                                    <span class="font-mono text-[10px] text-slate-500 line-through">{{ formatPrice(product.compare_at_price) }}</span>
                                    <span class="font-mono text-sm font-black text-brand-accent">{{ formatPrice(product.price) }} <span class="font-sans text-[10px] text-slate-500">تومان</span></span>
                                </div>
                                <div v-else>
                                    <span class="font-mono text-sm font-black text-ink">{{ formatPrice(product.price) }} <span class="font-sans text-[10px] text-slate-500">تومان</span></span>
                                </div>

                                <span class="mt-2.5 flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-[10px] font-bold text-ink transition-all group-hover:border-brand-accent group-hover:bg-brand-accent group-hover:text-[#1a1a1a]">
                                    مشاهده قطعه
                                    <i class="fa-solid fa-arrow-left text-[9px]"></i>
                                </span>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <!-- ARTICLES -->
            <section id="articles" class="py-14">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-brand-accent/10 text-brand-accent border border-brand-accent/20 inline-block mb-2">
                                <i class="fa-solid fa-book-open ml-1.5"></i> مجله توربوپارت
                            </span>
                            <h2 class="text-2xl sm:text-3xl font-black text-ink">مقالات، راهنمای انتخاب قطعه</h2>
                            <p class="text-xs text-slate-500 mt-2">راهنمای تخصصی انتخاب و نگهداری قطعات خودرو</p>
                        </div>
                        <a href="/articles" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-accent hover:text-brand-hover transition-colors">
                            مشاهده همه مقالات <i class="fa-solid fa-arrow-left text-xs"></i>
                        </a>
                    </div>

                    <div v-if="articlesLoading" class="space-y-4">
                        <div class="h-44 sm:h-48 rounded-2xl bg-white border border-gray-200 animate-pulse"></div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div v-for="i in 3" :key="i" class="h-52 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                        </div>
                    </div>

                    <div v-else-if="articlesError" class="text-center py-12">
                        <i class="fa-solid fa-circle-exclamation text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-500 text-xs">{{ articlesError }}</p>
                    </div>

                    <div v-else-if="articles.length" class="space-y-4">
                        <!-- Featured -->
                        <a v-if="featuredArticle" :href="`/articles/${featuredArticle.slug}`" class="group grid sm:grid-cols-2 gap-0 overflow-hidden rounded-2xl bg-white border border-gray-200 hover:border-brand-accent/40 transition-all">
                            <div class="relative bg-gradient-to-br from-brand-accent/25 via-amber-100 to-sand min-h-[180px] sm:min-h-0 overflow-hidden">
                                <img v-if="articleImage(featuredArticle)" :src="articleImage(featuredArticle)" :alt="featuredArticle.title" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" @error="onImgError($event)" loading="lazy" />
                                <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-brand-accent text-ink text-[10px] font-bold shadow">
                                    <i class="fa-solid fa-star text-[9px]"></i> منتخب سردبیر
                                </span>
                            </div>
                            <div class="p-5 sm:p-6 flex flex-col justify-center">
                                <div class="mb-2.5 flex flex-wrap items-center gap-2 text-[10px] text-slate-500">
                                    <span v-if="featuredArticle.categories?.[0]" class="px-2 py-0.5 rounded-full bg-brand-accent/10 text-brand-accent border border-brand-accent/20 font-bold">
                                        {{ featuredArticle.categories[0].name }}
                                    </span>
                                    <span class="inline-flex items-center gap-1"><i class="fa-solid fa-calendar-day"></i> {{ formatArticleDate(featuredArticle.published_at) }}</span>
                                </div>
                                <h3 class="font-black text-base sm:text-lg text-ink leading-relaxed group-hover:text-brand-accent transition-colors line-clamp-2">{{ featuredArticle.title }}</h3>
                                <p v-if="featuredArticle.excerpt" class="mt-2 text-xs text-slate-500 leading-relaxed line-clamp-2">{{ featuredArticle.excerpt }}</p>
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-accent mt-4">
                                    ادامه مطلب <i class="fa-solid fa-arrow-left text-[10px] group-hover:-translate-x-0.5 transition-transform"></i>
                                </span>
                            </div>
                        </a>

                        <!-- Grid -->
                        <div v-if="articleGrid.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <a v-for="article in articleGrid" :key="article.id" :href="`/articles/${article.slug}`" class="group rounded-xl bg-white border border-gray-200 hover:border-brand-accent/40 overflow-hidden transition-all flex flex-col">
                                <div class="relative aspect-[16/10] bg-gradient-to-br from-brand-accent/20 via-amber-100 to-sand overflow-hidden">
                                    <img v-if="articleImage(article)" :src="articleImage(article)" :alt="article.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" @error="onImgError($event)" loading="lazy" />
                                </div>
                                <div class="p-4 flex flex-col flex-1">
                                    <div class="mb-2 flex items-center gap-1.5 text-[10px] text-slate-500">
                                        <span v-if="article.categories?.[0]" class="px-2 py-0.5 rounded-full bg-brand-accent/10 text-brand-accent border border-brand-accent/20 font-bold">{{ article.categories[0].name }}</span>
                                        <span class="inline-flex items-center gap-1"><i class="fa-solid fa-calendar-day"></i> {{ formatArticleDate(article.published_at) }}</span>
                                    </div>
                                    <h3 class="font-bold text-sm text-ink leading-relaxed group-hover:text-brand-accent transition-colors line-clamp-2 flex-1">{{ article.title }}</h3>
                                    <span class="inline-flex items-center gap-1 text-[10px] text-brand-accent font-bold mt-3">
                                        ادامه مطلب <i class="fa-solid fa-arrow-left text-[9px]"></i>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- B2B -->
            <section id="wholesale" class="py-16 relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(#FFCD00_0.5px,transparent_0.5px)] [background-size:24px_24px] opacity-[0.04] pointer-events-none"></div>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="rounded-2xl bg-white border border-gray-200 p-8 sm:p-10">
<div class="grid lg:grid-cols-12 gap-8 items-center">
                        <div class="lg:col-span-7 space-y-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 text-[11px] font-bold">
                                    <i class="fa-solid fa-handshake"></i> ویژه فروشگاه‌ها و تعمیرگاه‌ها
                                </span>
                                <h2 class="text-2xl sm:text-3xl font-black text-ink leading-tight">
                                    خرید عمده قطعات خودرو با <span class="text-brand-accent">شرایط همکاری</span>
                                </h2>
                                <p class="text-slate-500 text-sm leading-relaxed">
                                    برای فروشگاه لوازم یدکی، تعمیرگاه یا کسب‌وکار مرتبط با خودرو، شرایط همکاری و قیمت‌های عمده را دریافت کنید.
                                </p>
                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-check text-brand-accent text-xs"></i><span class="text-xs text-ink">دریافت لیست قیمت همکاری</span></div>
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-check text-brand-accent text-xs"></i><span class="text-xs text-ink">سفارش کارتنی و حجمی</span></div>
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-check text-brand-accent text-xs"></i><span class="text-xs text-ink">پشتیبانی اختصاصی فروش</span></div>
                                    <div class="flex items-center gap-2"><i class="fa-solid fa-check text-brand-accent text-xs"></i><span class="text-xs text-ink">صدور پیش‌فاکتور</span></div>
                                </div>
                            </div>
                            <div class="lg:col-span-5 bg-white rounded-xl p-5 border border-gray-200">
                                <h3 class="text-sm font-bold text-ink flex items-center gap-2 mb-3">
                                    <i class="fa-solid fa-file-pen text-brand-accent"></i> درخواست همکاری
                                </h3>
                                <form class="space-y-2.5" @submit.prevent="onB2bSubmit">
                                    <input v-model="b2bForm.name" type="text" placeholder="نام و نام خانوادگی / نام فروشگاه" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-ink focus:border-brand-accent focus:outline-none" required />
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <input v-model="b2bForm.phone" type="tel" placeholder="شماره تماس" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-ink focus:border-brand-accent focus:outline-none" required dir="ltr" />
                                        <input v-model="b2bForm.city" type="text" placeholder="شهر" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-ink focus:border-brand-accent focus:outline-none" required />
                                    </div>
                                    <select v-model="b2bForm.business" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-ink focus:border-brand-accent focus:outline-none">
                                        <option value="">نوع فعالیت</option>
                                        <option>فروشگاه لوازم یدکی</option>
                                        <option>تعمیرگاه / مکانیکی</option>
                                        <option>ناوگان و سازمان</option>
                                        <option>سایر</option>
                                    </select>
                                    <button type="submit" class="w-full py-2.5 rounded-lg bg-brand-accent hover:bg-brand-hover text-ink text-xs font-bold shadow-glow-yellow transition-colors">
                                        ارسال درخواست همکاری
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- AUTHENTICITY -->
            <section id="authenticity" class="py-12 border-y border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="rounded-2xl bg-white border border-gray-200 p-6 flex flex-col lg:flex-row items-center justify-between gap-6">
                        <div class="text-center lg:text-right space-y-2">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-lg">
                                <i class="fa-solid fa-shield-halved"></i> استعلام اصالت
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black text-ink">بررسی کد اصالت کالا</h3>
                            <p class="text-xs text-slate-500 max-w-md">کد رهگیری یا شماره سریال درج‌شده روی محصول را وارد کنید.</p>
                        </div>
                        <form class="w-full lg:max-w-sm" @submit.prevent="onAuthSubmit">
                            <div class="flex gap-2">
                                <input v-model="authCode" type="text" placeholder="کد رهگیری یا سریال..." class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2.5 text-xs text-ink focus:border-emerald-500 focus:outline-none font-mono" dir="ltr" required />
                                <button type="submit" class="px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-ink text-xs font-bold transition-colors shrink-0">استعلام</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- TESTIMONIALS -->
<section class="py-16 bg-white/50 border-y border-gray-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="text-center max-w-xl mx-auto mb-10 space-y-2">
                            <div class="text-[11px] font-bold text-brand-accent uppercase tracking-wider">تجربه خرید</div>
                            <h2 class="text-2xl sm:text-3xl font-black text-ink">مشتریان چه می‌گویند؟</h2>
                        </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-xl p-5 bg-white border border-gray-200 space-y-3">
                            <div class="flex items-center gap-0.5 text-amber-500 text-[11px]"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                            <p class="text-xs text-slate-500 leading-relaxed">«فرآیند پیدا کردن قطعه برای خودرو خیلی راحت بود و مشخصات فنی محصول واضح درج شده بود.»</p>
                            <div class="flex items-center gap-2.5 pt-2 border-t border-gray-200/50">
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center font-bold text-brand-accent text-[11px]">اح</div>
                                <div><h4 class="font-bold text-[11px] text-ink">احسان</h4><span class="text-[10px] text-slate-500">خریدار قطعه</span></div>
                            </div>
                        </div>
                        <div class="rounded-xl p-5 bg-amber-50/50 border border-amber-200/30 space-y-3">
                            <div class="flex items-center gap-0.5 text-amber-500 text-[11px]"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                            <p class="text-xs text-slate-500 leading-relaxed">«برای خرید عمده امکان دریافت شرایط همکاری خیلی کاربردی است و قیمت‌ها را راحت‌تر مقایسه کنیم.»</p>
                            <div class="flex items-center gap-2.5 pt-2 border-t border-gray-200/50">
                                <div class="w-8 h-8 rounded-full bg-brand-accent/10 flex items-center justify-center font-bold text-brand-accent text-[11px]">رض</div>
                                <div><h4 class="font-bold text-[11px] text-ink">رضا</h4><span class="text-[10px] text-slate-500">فروشگاه لوازم یدکی</span></div>
                            </div>
                        </div>
                        <div class="rounded-xl p-5 bg-white border border-gray-200 space-y-3">
                            <div class="flex items-center gap-0.5 text-amber-500 text-[11px]"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i></div>
                            <p class="text-xs text-slate-500 leading-relaxed">«جستجو بر اساس مدل خودرو ایده خوبی است، مخصوصاً وقتی کد فنی قطعه را نمی‌دانیم.»</p>
                            <div class="flex items-center gap-2.5 pt-2 border-t border-gray-200/50">
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center font-bold text-cyan-400 text-[11px]">ام</div>
                                <div><h4 class="font-bold text-[11px] text-ink">امید</h4><span class="text-[10px] text-slate-500">خریدار تکی</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- NEWSLETTER -->
            <section class="py-10 border-y border-gray-200">
                <div class="max-w-4xl mx-auto px-4 text-center space-y-5">
                    <div class="w-10 h-10 rounded-xl bg-brand-accent text-ink flex items-center justify-center mx-auto shadow-glow-yellow">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
<h3 class="text-xl sm:text-2xl font-black text-ink">عضویت در باشگاه مشتریان</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto">شماره موبایل خود را ثبت کنید تا پیشنهادهای ویژه را دریافت کنید.</p>
                    <form class="flex flex-col sm:flex-row gap-2.5 max-w-sm mx-auto" @submit.prevent="onNewsletterSubmit">
                        <input v-model="newsletterPhone" type="tel" placeholder="شماره موبایل" class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2.5 text-xs text-ink focus:border-brand-accent focus:outline-none" dir="ltr" required />
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-brand-accent hover:bg-brand-hover text-ink text-xs font-bold shadow-glow-yellow transition-colors whitespace-nowrap">عضویت</button>
                    </form>
                </div>
            </section>

        </main>

        <SiteFooter />

        <!-- MOBILE BOTTOM NAV -->
        <div class="sm:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-lg border-t border-gray-200 px-3 py-2 flex items-center justify-around text-[10px] shadow-lg">
            <a href="/" class="flex flex-col items-center gap-0.5 text-brand-accent font-bold"><i class="fa-solid fa-house text-sm"></i><span>خانه</span></a>
            <a href="/store" class="flex flex-col items-center gap-0.5 text-slate-500"><i class="fa-solid fa-store text-sm"></i><span>فروشگاه</span></a>
            <a href="#wholesale" class="flex flex-col items-center gap-0.5 text-amber-600 font-bold"><i class="fa-solid fa-boxes-stacked text-sm"></i><span>عمده</span></a>
            <a href="tel:02188990000" class="flex flex-col items-center gap-0.5 text-emerald-600 font-bold"><i class="fa-solid fa-phone text-sm"></i><span>تماس</span></a>
        </div>

        <!-- TOAST -->
        <Teleport to="body">
            <div v-if="toastVisible" class="fixed bottom-20 sm:bottom-6 right-4 left-4 sm:left-auto sm:w-80 z-[60]">
                <div class="bg-white border border-gray-200 rounded-xl shadow-2xl p-3 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="toastType === 'success' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600'">
                        <i :class="toastType === 'success' ? 'fa-solid fa-check' : 'fa-solid fa-circle-exclamation'" class="text-sm"></i>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-ink">{{ toastTitle }}</div>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ toastMessage }}</p>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>

<style>
html { scroll-padding-top: 80px; scroll-behavior: smooth; }
body { min-width: 320px; }

.line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 1; }
.line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }

select { background-image: none; }
input::placeholder { opacity: 0.75; }

:focus-visible { outline: 2px solid #FFCD00; outline-offset: 2px; }

/* Homepage category cards — glass + 3D */
.home-category-card {
    transform-style: preserve-3d;
    transition: transform .5s cubic-bezier(.2, .8, .2, 1), box-shadow .5s ease, border-color .3s ease;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, .7) inset,
        0 12px 24px -18px rgba(17, 24, 39, .5),
        0 28px 52px -34px rgba(17, 24, 39, .55);
    will-change: transform;
}

.home-category-card:hover {
    transform: perspective(1100px) rotateX(3.5deg) rotateY(-3.5deg) translateY(-8px);
    border-color: rgba(255, 255, 255, .92);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, .85) inset,
        0 22px 40px -20px rgba(17, 24, 39, .48),
        0 44px 76px -40px rgba(17, 24, 39, .6);
}

.home-category-card__image {
    transform: scale(1.02);
    transition: transform .8s cubic-bezier(.2, .8, .2, 1);
}

.home-category-card:hover .home-category-card__image {
    transform: scale(1.08);
}

.home-category-card__glare {
    background: linear-gradient(115deg, transparent 35%, rgba(255, 255, 255, .55) 48%, rgba(255, 255, 255, .15) 55%, transparent 65%);
    transform: translateX(-80%) rotate(6deg);
    opacity: 0;
    transition: transform .9s ease, opacity .5s ease;
    mix-blend-mode: screen;
}

.home-category-card:hover .home-category-card__glare {
    transform: translateX(80%) rotate(6deg);
    opacity: .55;
}

@media (prefers-reduced-motion: reduce) {
    .home-category-card,
    .home-category-card__image,
    .home-category-card__glare {
        transition: none;
    }

    .home-category-card:hover {
        transform: none;
    }
}
</style>
