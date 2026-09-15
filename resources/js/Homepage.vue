<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';

/* ── Theme ────────────────────────────────────────────────── */
const isLight = ref(false);

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
const cartCount = ref(0);

function toPersianNumber(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

function addToCart(product) {
    cartCount.value++;
    localStorage.setItem('turbopart-cart-count', String(cartCount.value));
    showToast(`${product} به سبد خرید اضافه شد.`, 'افزودن به سبد');
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
    await Promise.all([loadVehicleBrands(), loadCategories(), loadFeaturedProducts()]);
});

onUnmounted(() => {
    window.removeEventListener('toast', onToastEvent);
    clearTimeout(toastTimer);
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

            <!-- HERO -->
            <section class="relative min-h-[560px] pt-12 pb-20 overflow-hidden">
                <div class="absolute top-1/4 -right-40 w-96 h-96 bg-brand-accent/15 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute bottom-10 -left-40 w-96 h-96 bg-cyan-500/8 rounded-full blur-3xl pointer-events-none"></div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="grid lg:grid-cols-12 gap-12 items-center">

                        <!-- HERO CONTENT -->
                        <div class="lg:col-span-7 space-y-6 text-center lg:text-right">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/90 border border-brand-accent/30 text-brand-accent text-xs font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-brand-accent animate-pulse"></span>
                                مرجع تخصصی قطعات خودروهای داخلی، چینی و وارداتی
                            </div>

                            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-ink leading-tight">
                                قطعه مناسب، قیمت رقابتی؛
                                <br>
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-accent via-yellow-300 to-amber-200">ساده‌تر از همیشه پیدا کن!</span>
                            </h1>

                            <p class="text-slate-500 text-base sm:text-lg max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                                تأمین قطعات خودرو با امکان خرید تکی و عمده، بررسی سازگاری با خودرو و پشتیبانی تخصصی.
                            </p>

                            <div class="pt-4 grid grid-cols-3 gap-3 sm:gap-4 max-w-lg mx-auto lg:mx-0 border-b border-gray-200 py-4">
                                <div>
                                    <div class="text-xl sm:text-3xl font-black text-ink font-mono">+۵۰,۰۰۰</div>
                                    <div class="text-[10px] sm:text-xs text-slate-500 mt-1">تنوع قطعات</div>
                                </div>
                                <div>
                                    <div class="text-xl sm:text-3xl font-black text-brand-accent font-mono">B2B</div>
                                    <div class="text-sm font-bold text-slate-500 mt-1">فروش همکاری</div>
                                </div>
                                <div>
                                    <div class="text-xl sm:text-3xl font-black text-slate-500 font-mono">OEM</div>
                                    <div class="text-[10px] sm:text-xs text-slate-500 mt-1">جستجوی کد فنی</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                                <a href="#vehicle-finder" class="px-7 py-3.5 rounded-lg bg-brand-accent hover:bg-brand-hover text-ink font-bold shadow-glow-yellow flex items-center gap-3 transition-all hover:scale-[1.02] active:scale-[.98]">
                                    <i class="fa-solid fa-car-side"></i>
                                    <span>انتخاب خودرو و قطعه</span>
                                </a>
                                <a href="#wholesale" class="px-6 py-3.5 rounded-lg bg-white hover:bg-gray-100 border border-gray-200 hover:border-slate-600 text-ink font-semibold flex items-center gap-2 transition-colors">
                                    <i class="fa-solid fa-truck-ramp-box text-brand-accent"></i>
                                    <span>خرید عمده</span>
                                </a>
                            </div>
                        </div>

                        <!-- VEHICLE FINDER -->
                        <div class="lg:col-span-5" id="vehicle-finder">
                            <div class="rounded-2xl bg-white border border-gray-200 p-6 sm:p-7">
                                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                    <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-brand-accent/10 flex items-center justify-center text-brand-accent">
                                                <i class="fa-solid fa-sliders text-sm"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-ink text-sm">یافتن سریع قطعه</h3>
                                                <p class="text-[11px] text-slate-500">خودرو را انتخاب کنید</p>
                                            </div>
                                        </div>
                                    <span class="hidden sm:inline text-[10px] font-mono text-cyan-400 bg-cyan-950/50 border border-cyan-800/30 px-2 py-0.5 rounded">Smart Match</span>
                                </div>

                                <form class="space-y-3 mt-5" @submit.prevent="onVfSubmit">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">۱. کمپانی سازنده</label>
                                        <div class="relative">
                                            <select v-model="vfBrand" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-ink focus:border-brand-accent focus:outline-none appearance-none cursor-pointer" required @change="onVfBrandChange">
                                                <option value="">{{ vfBrandsLoading ? 'بارگذاری...' : 'انتخاب کمپانی' }}</option>
                                                <option v-for="b in vehicleBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                            </select>
                                            <i class="fa-solid fa-chevron-down absolute left-3 top-3 text-[10px] text-slate-500 pointer-events-none"></i>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">۲. مدل</label>
                                            <div class="relative">
                                                <select v-model="vfModel" :disabled="!vfBrand || vfModelsLoading" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-ink focus:border-brand-accent focus:outline-none appearance-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" required @change="onVfModelChange">
                                                    <option value="">{{ vfModelsLoading ? 'بارگذاری...' : (vfBrand ? 'انتخاب مدل' : '—') }}</option>
                                                    <option v-for="m in vehicleModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down absolute left-3 top-3 text-[10px] text-slate-500 pointer-events-none"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">۳. نسل</label>
                                            <div class="relative">
                                                <select v-model="vfGeneration" :disabled="!vfModel || vfGenerationsLoading" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-ink focus:border-brand-accent focus:outline-none appearance-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" @change="onVfGenerationChange">
                                                    <option value="">{{ vfGenerationsLoading ? 'بارگذاری...' : (vfModel ? 'انتخاب نسل' : '—') }}</option>
                                                    <option v-for="g in vehicleGenerations" :key="g.id" :value="g.id">{{ g.name }}{{ g.year_start ? ` (${g.year_start})` : '' }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down absolute left-3 top-3 text-[10px] text-slate-500 pointer-events-none"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">۴. تیپ</label>
                                            <div class="relative">
                                                <select v-model="vfTrim" :disabled="!vfGeneration || vfTrimsLoading" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-ink focus:border-brand-accent focus:outline-none appearance-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" @change="onVfTrimChange">
                                                    <option value="">{{ vfTrimsLoading ? 'بارگذاری...' : (vfGeneration ? 'انتخاب تیپ' : '—') }}</option>
                                                    <option v-for="t in vehicleTrims" :key="t.id" :value="t.id">{{ t.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down absolute left-3 top-3 text-[10px] text-slate-500 pointer-events-none"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-500 mb-1">۵. موتور</label>
                                            <div class="relative">
                                                <select v-model="vfEngine" :disabled="!vfTrim || vfEnginesLoading" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-ink focus:border-brand-accent focus:outline-none appearance-none cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                                                    <option value="">{{ vfEnginesLoading ? 'بارگذاری...' : (vfTrim ? 'انتخاب موتور' : '—') }}</option>
                                                    <option v-for="e in vehicleEngines" :key="e.id" :value="e.id">{{ e.name }}</option>
                                                </select>
                                                <i class="fa-solid fa-chevron-down absolute left-3 top-3 text-[10px] text-slate-500 pointer-events-none"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="w-full py-3 rounded-lg bg-brand-accent hover:bg-brand-hover text-ink font-bold text-sm shadow-glow-yellow flex items-center justify-center gap-2 transition-all active:scale-[.98]">
                                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                                        نمایش قطعات سازگار
                                    </button>
                                </form>

                                <div class="flex items-center justify-between gap-2 text-[10px] text-slate-500 pt-3 mt-3 border-t border-gray-200/50">
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-check-double text-brand-accent"></i> بررسی سازگاری</span>
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-arrow-rotate-left text-brand-accent"></i> امکان مرجوعی</span>
                                </div>
                            </div>
                        </div>
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
            <section id="categories" class="py-14">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-brand-accent/10 text-brand-accent border border-brand-accent/20 mb-2">
                                <i class="fa-solid fa-layer-group ml-1.5"></i> دسته‌بندی قطعات
                            </span>
                            <h2 class="text-xl sm:text-2xl font-black text-ink">قطعات مورد نیاز خودروی شما</h2>
                            <p class="text-xs text-slate-500 mt-2">از سیستم ترمز تا قطعات موتوری و برقی</p>
                        </div>
                        <a href="/store" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-accent hover:text-brand-hover transition-colors">
                            مشاهده همه <i class="fa-solid fa-arrow-left text-xs"></i>
                        </a>
                    </div>

                    <div v-if="categoriesLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="i in 6" :key="i" class="rounded-xl bg-white border border-gray-200 animate-pulse min-h-[180px]"></div>
                    </div>

                    <div v-else-if="categories.length" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <a v-if="categories[0]" :href="`/store?category=${encodeURIComponent(categories[0].slug)}`" class="col-span-2 min-h-[220px] rounded-xl p-6 bg-white border border-gray-200 hover:border-brand-accent/40 transition-all relative overflow-hidden group">
                            <div class="absolute -left-8 -bottom-8 w-40 h-40 bg-brand-accent/10 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
                            <div class="relative z-10 h-full flex flex-col justify-between">
                                <span class="inline-flex self-start px-2 py-0.5 rounded text-[10px] font-bold bg-brand-accent/15 text-brand-accent border border-brand-accent/20">پرفروش</span>
                                <div>
                                    <h3 class="text-xl sm:text-2xl font-black text-ink">{{ categories[0].name }}</h3>
                                    <p class="text-xs text-slate-500 mt-1">{{ categories[0].children?.length ? `${categories[0].children.length} زیرمجموعه` : 'مشاهده محصولات' }}</p>
                                </div>
                            </div>
                        </a>

                        <a v-for="cat in categories.slice(1, 3)" :key="cat.slug" :href="`/store?category=${encodeURIComponent(cat.slug)}`" class="min-h-[220px] rounded-xl p-5 bg-white border border-gray-200 hover:border-cyan-500/40 transition-all flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-950/50 text-cyan-400 border border-cyan-800/30">{{ cat.children?.length ? `${cat.children.length} زیرمجموعه` : 'دسته‌بندی' }}</span>
                                <i class="fa-solid fa-layer-group text-slate-500 group-hover:text-cyan-400 transition-colors"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-ink">{{ cat.name }}</h3>
                                <span class="text-[11px] text-brand-accent mt-1 inline-flex items-center gap-1">مشاهده محصولات <i class="fa-solid fa-arrow-left text-[9px]"></i></span>
                            </div>
                        </a>

                        <a v-for="cat in categories.slice(3, 7)" :key="cat.slug" :href="`/store?category=${encodeURIComponent(cat.slug)}`" class="rounded-xl p-4 bg-blush border border-gray-200 hover:border-brand-accent/40 transition-all">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-brand-accent shrink-0">
                                    <i class="fa-solid fa-layer-group text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-ink text-xs">{{ cat.name }}</h4>
                                    <span class="text-[10px] text-slate-500">{{ cat.children?.length ? `${cat.children.length} زیرمجموعه` : 'مشاهده' }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <!-- FEATURED PRODUCTS -->
            <section id="products" class="py-14 bg-white/50 border-y border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                        <div>
                            <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-brand-accent/10 text-brand-accent border border-brand-accent/20 inline-block mb-2">محصولات منتخب</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-ink">قطعات پرتقاضای بازار</h2>
                        </div>
                        <a href="/store" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-accent hover:text-brand-hover transition-colors">
                            مشاهده همه <i class="fa-solid fa-arrow-left text-xs"></i>
                        </a>
                    </div>

                    <div v-if="featuredLoading" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="i in 8" :key="i" class="rounded-xl bg-white border border-gray-200 animate-pulse">
                            <div class="aspect-square bg-gray-100 rounded-t-xl"></div>
                            <div class="p-3 space-y-2">
                                <div class="h-2.5 bg-gray-100 rounded w-3/4"></div>
                                <div class="h-3.5 bg-gray-100 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="featuredError" class="text-center py-12">
                        <i class="fa-solid fa-circle-exclamation text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-500 text-xs">{{ featuredError }}</p>
                    </div>

                    <div v-else-if="!featuredProducts.length" class="text-center py-12">
                        <i class="fa-solid fa-box-open text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-500 text-xs">محصولی یافت نشد.</p>
                    </div>

                    <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <a
                            v-for="product in featuredProducts"
                            :key="product.id"
                            :href="`/products/${product.id}`"
                            class="rounded-xl bg-white border border-gray-200 hover:border-brand-accent/40 transition-all p-3 flex flex-col group"
                        >
                            <div class="relative rounded-lg bg-sand p-3 flex items-center justify-center overflow-hidden mb-3 aspect-square">
                                <span v-if="product.compare_at_price" class="absolute top-2 right-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-600 text-ink">تخفیف</span>
                                <span v-if="product.in_stock" class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-600 text-ink">موجود</span>
                                <span v-else class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-600 text-ink">ناموجود</span>
                                <img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300" @error="onImgError($event)" />
                                <i v-else class="fa-solid fa-box text-4xl text-slate-500"></i>
                            </div>
                            <div v-if="product.sku" class="text-[10px] font-mono text-slate-500 mb-0.5">SKU: {{ product.sku }}</div>
                            <h3 class="font-bold text-ink text-xs leading-snug group-hover:text-brand-accent transition-colors line-clamp-2">{{ product.name }}</h3>
                            <div class="mt-auto pt-2">
                                <div v-if="product.compare_at_price" class="flex items-end gap-2">
                                    <span class="text-[10px] text-slate-500 line-through font-mono">{{ formatPrice(product.compare_at_price) }}</span>
                                    <span class="text-sm font-black text-brand-accent font-mono">{{ formatPrice(product.price) }} <span class="text-[10px] font-sans text-slate-500">تومان</span></span>
                                </div>
                                <div v-else>
                                    <span class="text-sm font-black text-ink font-mono">{{ formatPrice(product.price) }} <span class="text-[10px] font-sans text-slate-500">تومان</span></span>
                                </div>
                            </div>
                        </a>
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
</style>
