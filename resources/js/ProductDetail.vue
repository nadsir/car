<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import SiteHeader from './SiteHeader.vue';
import SiteFooter from './SiteFooter.vue';
import { addToCart } from './cart-state.js';

const product = ref(null);
const loading = ref(true);
const error = ref('');
const mainImageIndex = ref(0);
const quantity = ref(1);
const showAllVehicles = ref(false);
const VEHICLE_SHOW_LIMIT = 5;

const selectedVariants = reactive({});

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

function productImage(p) {
    const image = p.images?.find((item) => item.is_primary) || p.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}

function onImgError(e) {
    e.target.onerror = null;
    e.target.src = '/images/placeholder.svg';
}

const images = computed(() => product.value?.images || []);
const sortedImages = computed(() => [...images.value].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));
const mainImage = computed(() => sortedImages.value[mainImageIndex.value] || sortedImages.value[0]);
const mainImageUrl = computed(() => mainImage.value?.path ? `/storage/${mainImage.value.path}` : '');

function selectImage(index) { mainImageIndex.value = index; }
function prevImage() { mainImageIndex.value = mainImageIndex.value > 0 ? mainImageIndex.value - 1 : sortedImages.value.length - 1; }
function nextImage() { mainImageIndex.value = mainImageIndex.value < sortedImages.value.length - 1 ? mainImageIndex.value + 1 : 0; }

const attributeAxes = computed(() => {
    const attrs = product.value?.attributes;
    if (!attrs || typeof attrs !== 'object') return [];
    return Object.entries(attrs).map(([slug, values]) => ({ slug, values: Array.isArray(values) ? values : [] }));
});
const hasVariants = computed(() => product.value?.variants?.length > 0);

function buildVariantLookup() {
    const variants = product.value?.variants || [];
    const lookup = new Map();
    for (const variant of variants) {
        if (!variant.is_active) continue;
        const keys = [];
        for (const [slug, values] of Object.entries(variant.attributes || {})) {
            for (const v of values) keys.push(`${slug}:${v.id}`);
        }
        keys.sort();
        lookup.set(keys.join('|'), variant);
    }
    return lookup;
}

const matchedVariant = computed(() => {
    if (!hasVariants.value) return null;
    const lookup = buildVariantLookup();
    const keys = [];
    for (const [slug, valueId] of Object.entries(selectedVariants)) {
        if (valueId) keys.push(`${slug}:${valueId}`);
    }
    if (keys.length === 0) return null;
    keys.sort();
    return lookup.get(keys.join('|')) || null;
});

function isValueSelectable(axisSlug, valueId) {
    if (!hasVariants.value) return true;
    for (const variant of product.value?.variants || []) {
        if (!variant.is_active) continue;
        let matches = true;
        for (const [slug, valueIdOther] of Object.entries(selectedVariants)) {
            if (slug === axisSlug || !valueIdOther) continue;
            const variantValues = variant.attributes?.[slug];
            if (!variantValues?.some((v) => String(v.id) === String(valueIdOther))) { matches = false; break; }
        }
        if (!matches) continue;
        if (variant.attributes?.[axisSlug]?.some((v) => String(v.id) === String(valueId))) return true;
    }
    return false;
}

function onVariantSelect(axisSlug, valueId) {
    if (selectedVariants[axisSlug] === String(valueId)) delete selectedVariants[axisSlug];
    else selectedVariants[axisSlug] = String(valueId);
    mainImageIndex.value = 0;
}

const displayPrice = computed(() => matchedVariant.value?.price ?? product.value?.price ?? 0);
const displayCompareAtPrice = computed(() => matchedVariant.value?.compare_at_price ?? product.value?.compare_at_price ?? null);
const displaySku = computed(() => matchedVariant.value?.sku || product.value?.sku || '');
const displayInStock = computed(() => matchedVariant.value ? matchedVariant.value.stock > 0 : product.value?.in_stock ?? false);
const displayStock = computed(() => matchedVariant.value?.stock ?? null);

const needsVariantSelection = computed(() => {
    if (!hasVariants.value || matchedVariant.value) return false;
    return attributeAxes.value.length > 0 && Object.values(selectedVariants).filter(Boolean).length < attributeAxes.value.length;
});

const displayAttributes = computed(() => {
    const attrs = product.value?.attributes;
    if (!attrs || typeof attrs !== 'object') return [];
    const variantSlugs = new Set(attributeAxes.value.map((a) => a.slug));
    return Object.entries(attrs).filter(([slug]) => !variantSlugs.has(slug)).map(([slug, values]) => ({ slug, values: Array.isArray(values) ? values : [] }));
});

const vehicleCompat = computed(() => product.value?.vehicle_compatibility || []);
const visibleVehicles = computed(() => showAllVehicles.value ? vehicleCompat.value : vehicleCompat.value.slice(0, VEHICLE_SHOW_LIMIT));
const categoryPath = computed(() => product.value?.categories || []);

async function fetchProduct() {
    const match = location.pathname.match(/\/products\/(\d+)/);
    if (!match) { error.value = 'محصول مورد نظر پیدا نشد.'; loading.value = false; return; }
    try {
        const { data } = await axios.get(`/api/products/${match[1]}`);
        product.value = data.data || data;
    } catch (e) {
        error.value = e.response?.status === 404 ? 'محصول مورد نظر پیدا نشد.' : 'خطا در دریافت اطلاعات محصول.';
    } finally { loading.value = false; }
}

function goBack() {
    if (window.history.length > 1) window.history.back();
    else window.location.href = '/store';
}

function addToCartToCart() {
    if (!displayInStock.value) return;
    if (needsVariantSelection.value) return;

    const p = product.value;
    if (!p) return;

    const v = matchedVariant.value;
    const key = v ? `variant_${v.id}` : `product_${p.id}`;

    const image =
        p.images?.find((img) => img.is_primary)?.path ||
        p.images?.[0]?.path ||
        null;

    let attributes = null;
    if (v?.attributes) {
        attributes = {};
        for (const [slug, vals] of Object.entries(
            v.attributes
        )) {
            attributes[slug] = vals;
        }
    }

    addToCart({
        key,
        product_id: p.id,
        variant_id: v?.id || null,
        name: p.name,
        price: displayPrice.value,
        quantity: quantity.value,
        image: image ? `/storage/${image}` : null,
        attributes,
        sku: displaySku.value || null,
        stock: v?.stock ?? p.stock ?? 999,
    });

    window.dispatchEvent(
        new CustomEvent('toast', {
            detail: {
                message: `${p.name} به سبد خرید اضافه شد.`,
                title: 'افزودن به سبد',
                type: 'success',
            },
        })
    );
}

onMounted(fetchProduct);
</script>

<template>
    <div class="min-h-screen bg-cream text-ink font-sans antialiased">
        <SiteHeader />

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-32">
            <div class="text-center">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-brand-accent mb-3"></i>
                <p class="text-xs text-slate-500">در حال بارگذاری محصول…</p>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="mx-auto max-w-4xl px-4 py-24 text-center">
            <i class="fa-solid fa-circle-exclamation text-4xl text-slate-500 mb-4"></i>
            <p class="mb-5 text-sm text-red-600">{{ error }}</p>
            <button type="button" class="px-5 py-2 rounded-lg bg-brand-accent text-ink text-xs font-bold hover:bg-brand-hover transition-colors" @click="goBack">
                بازگشت به فروشگاه
            </button>
        </div>

        <!-- Product -->
        <div v-else-if="product" class="max-w-6xl mx-auto px-4 sm:px-6 py-6">
            <!-- Breadcrumb -->
            <nav class="mb-5 flex items-center gap-1.5 text-[11px] text-slate-500">
                <a href="/store" class="hover:text-brand-accent transition-colors">فروشگاه</a>
                <template v-for="(cat, idx) in categoryPath" :key="cat.id">
                    <i class="fa-solid fa-chevron-left text-[8px] text-slate-600"></i>
                    <span class="text-ink">{{ cat.name }}</span>
                </template>
            </nav>

            <div class="grid gap-8 lg:grid-cols-[1fr_1fr]">
                <!-- Gallery -->
                <div>
                    <div class="relative rounded-xl bg-white border border-gray-200 overflow-hidden">
                        <img v-if="mainImageUrl" :src="mainImageUrl" :alt="mainImage?.alt_text || product.name" class="aspect-square w-full object-cover" @error="onImgError($event)" />
                        <div v-else class="aspect-square flex items-center justify-center text-sm text-slate-400">
                            <i class="fa-solid fa-image text-4xl"></i>
                        </div>
                        <button v-if="sortedImages.length > 1" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/20 backdrop-blur-sm flex items-center justify-center text-ink hover:bg-black/40 transition-colors" @click="prevImage">
                            <i class="fa-solid fa-chevron-right text-xs"></i>
                        </button>
                        <button v-if="sortedImages.length > 1" type="button" class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/20 backdrop-blur-sm flex items-center justify-center text-ink hover:bg-black/40 transition-colors" @click="nextImage">
                            <i class="fa-solid fa-chevron-left text-xs"></i>
                        </button>
                    </div>
                    <div v-if="sortedImages.length > 1" class="mt-2.5 flex gap-2 overflow-x-auto no-scrollbar">
                        <button v-for="(img, idx) in sortedImages" :key="img.id" type="button" class="h-14 w-14 flex-shrink-0 rounded-lg overflow-hidden border-2 transition-colors" :class="idx === mainImageIndex ? 'border-brand-accent' : 'border-gray-200 hover:border-gray-400'" @click="selectImage(idx)">
                            <img :src="`/storage/${img.path}`" :alt="img.alt_text || product.name" class="h-full w-full object-cover" @error="onImgError($event)" />
                        </button>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="space-y-4">
                    <h1 class="text-xl sm:text-2xl font-black text-ink leading-relaxed">{{ product.name }}</h1>

                    <p v-if="displaySku" class="text-[11px] text-slate-500 font-mono">SKU: {{ displaySku }}</p>

                    <!-- Price -->
                    <div class="flex items-baseline gap-3">
                        <span class="text-xl font-black text-brand-accent font-mono">{{ formatPrice(displayPrice) }} <span class="text-xs font-sans text-slate-500">تومان</span></span>
                        <span v-if="displayCompareAtPrice" class="text-xs text-slate-500 line-through font-mono">{{ formatPrice(displayCompareAtPrice) }}</span>
                    </div>

                    <!-- Stock -->
                    <div class="flex items-center gap-2">
                        <span v-if="displayInStock" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-400 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> موجود
                        </span>
                        <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-500/10 border border-red-500/20 text-[11px] text-red-400 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> ناموجود
                        </span>
                        <span v-if="displayInStock && displayStock !== null" class="text-[10px] text-slate-500">({{ displayStock }} عدد)</span>
                    </div>

                    <p v-if="needsVariantSelection" class="text-[11px] text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <i class="fa-solid fa-exclamation-triangle ml-1"></i> لطفاً ویژگی‌های محصول را انتخاب کنید.
                    </p>

                    <p v-if="product.short_description" class="text-xs text-slate-600 leading-relaxed">{{ product.short_description }}</p>

                    <!-- Variant Selector -->
                    <div v-if="hasVariants && attributeAxes.length" class="space-y-3 pt-2">
                        <div v-for="axis in attributeAxes" :key="axis.slug">
                            <p class="mb-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ axis.slug }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="val in axis.values" :key="val.id" type="button" :disabled="!isValueSelectable(axis.slug, val.id)" class="rounded-lg border px-3 py-1.5 text-[11px] transition-colors" :class="selectedVariants[axis.slug] === String(val.id) ? 'bg-brand-accent text-dark-900 border-brand-accent font-bold' : 'border-gray-200 text-ink hover:border-slate-600 disabled:opacity-30'" @click="onVariantSelect(axis.slug, val.id)">
                                    <span v-if="val.hex_color" class="ml-1 inline-block w-3 h-3 rounded-full border border-gray-300" :style="{ backgroundColor: val.hex_color }"></span>
                                    {{ val.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Add to Cart -->
                    <div class="flex items-center gap-3 pt-3">
                        <div class="flex items-center rounded-lg border border-gray-200">
                            <button type="button" class="px-3 py-2 text-sm text-ink hover:bg-gray-100 transition-colors" @click="quantity = Math.max(1, quantity - 1)">−</button>
                            <span class="min-w-[2rem] text-center text-sm font-mono">{{ quantity }}</span>
                            <button type="button" class="px-3 py-2 text-sm text-ink hover:bg-gray-100 transition-colors" @click="quantity++">+</button>
                        </div>
                        <button
                            type="button"
                            :disabled="!displayInStock || needsVariantSelection"
                            class="flex-1 rounded-lg bg-brand-accent text-dark-900 py-2.5 text-sm font-bold shadow-glow-yellow transition-colors"
                            :class="!displayInStock || needsVariantSelection ? 'opacity-50 cursor-not-allowed' : 'hover:bg-brand-hover'"
                            @click="addToCartToCart"
                        >
                            <i class="fa-solid fa-cart-plus ml-1.5 text-xs"></i> افزودن به سبد خرید
                        </button>
                    </div>
                </div>
            </div>

            <!-- Specifications -->
<div v-if="displayAttributes.length || product.custom_attributes?.length" class="mt-12">
                    <h2 class="mb-4 text-lg font-black text-ink">مشخصات محصول</h2>
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="attr in displayAttributes" :key="attr.slug" class="border-b border-gray-200/50 last:border-0">
                                <td class="w-1/3 bg-white px-4 py-2.5 text-[11px] font-bold text-slate-500">{{ attr.slug }}</td>
                                <td class="px-4 py-2.5 text-xs text-ink">{{ attr.values.map((v) => v.label).join('، ') }}</td>
                            </tr>
                            <tr v-for="attr in product.custom_attributes || []" :key="attr.id" class="border-b border-gray-200/50 last:border-0">
                                <td class="w-1/3 bg-white px-4 py-2.5 text-[11px] font-bold text-slate-400">{{ attr.name }}</td>
                                <td class="px-4 py-2.5 text-xs text-ink">{{ attr.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Description -->
            <div v-if="product.description" class="mt-12">
<h2 class="mb-4 text-lg font-black text-ink">توضیحات</h2>
                    <div class="rounded-xl border border-gray-200 bg-white p-5 text-xs text-slate-600 leading-relaxed">
                    {{ product.description }}
                </div>
            </div>

            <!-- Vehicle Compatibility -->
            <div v-if="vehicleCompat.length" class="mt-12">
                <h2 class="mb-4 text-lg font-black text-ink">خودروهای سازگار</h2>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <ul class="space-y-2">
                        <li v-for="(v, idx) in visibleVehicles" :key="idx" class="rounded-lg border border-gray-200/50 px-3 py-2.5">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="font-bold text-brand-accent">{{ v.brand.name }}</span>
                                <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                                <span class="text-ink">{{ v.model.name }}</span>
                                <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                                <span class="text-slate-500">{{ v.generation.name }} ({{ v.generation.year_start }}<template v-if="v.generation.year_end">-{{ v.generation.year_end }}</template>)</span>
                                <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                                <span class="text-slate-600">{{ v.trim.name }}</span>
                                <i class="fa-solid fa-chevron-left text-[8px] text-slate-400"></i>
                                <span class="text-slate-500">{{ v.engine.name }}</span>
                            </div>
                        </li>
                    </ul>
                    <button v-if="vehicleCompat.length > VEHICLE_SHOW_LIMIT" type="button" class="mt-2 text-[11px] text-brand-accent hover:text-brand-hover font-medium transition-colors" @click="showAllVehicles = !showAllVehicles">
                        {{ showAllVehicles ? 'نمایش کمتر' : `نمایش همه (${vehicleCompat.length})` }}
                    </button>
                </div>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>

<style>
.no-scrollbar { scrollbar-width: none; -ms-overflow-style: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }
select { background-image: none; }
input::placeholder { opacity: 0.7; }
:focus-visible { outline: 2px solid #FFCD00; outline-offset: 2px; }
</style>
