<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';

/* ────────────────────────────────────────────────────────────
 | State
 | ──────────────────────────────────────────────────────────── */

const product = ref(null);
const loading = ref(true);
const error = ref('');
const mainImageIndex = ref(0);
const quantity = ref(1);
const showAllVehicles = ref(false);
const VEHICLE_SHOW_LIMIT = 5;

/* ── Variant selection ──────────────────────────────────────── */
const selectedVariants = reactive({});

/* ────────────────────────────────────────────────────────────
 | Helpers
 | ──────────────────────────────────────────────────────────── */

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

function productImage(p) {
    const image = p.images?.find((item) => item.is_primary) || p.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}

/* ────────────────────────────────────────────────────────────
 | Images
 | ──────────────────────────────────────────────────────────── */

const images = computed(() => product.value?.images || []);

const sortedImages = computed(() => {
    return [...images.value].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
});

const mainImage = computed(() => {
    return sortedImages.value[mainImageIndex.value] || sortedImages.value[0];
});

const mainImageUrl = computed(() => {
    return mainImage.value?.path ? `/storage/${mainImage.value.path}` : '';
});

function selectImage(index) {
    mainImageIndex.value = index;
}

function prevImage() {
    mainImageIndex.value = mainImageIndex.value > 0
        ? mainImageIndex.value - 1
        : sortedImages.value.length - 1;
}

function nextImage() {
    mainImageIndex.value = mainImageIndex.value < sortedImages.value.length - 1
        ? mainImageIndex.value + 1
        : 0;
}

/* ────────────────────────────────────────────────────────────
 | Variant combination logic
 | ──────────────────────────────────────────────────────────── */

/**
 * Each key in selectedVariants is an attribute slug, value is the
 * attribute value id (string). When all axes have a selection we
 * try to match a variant.
 */
const attributeAxes = computed(() => {
    const attrs = product.value?.attributes;
    if (!attrs || typeof attrs !== 'object') return [];
    return Object.entries(attrs).map(([slug, values]) => ({
        slug,
        values: Array.isArray(values) ? values : [],
    }));
});

const hasVariants = computed(() => {
    return product.value?.variants?.length > 0;
});

/**
 * Build a lookup: map of `slug:valueId` → variant
 */
function buildVariantLookup() {
    const variants = product.value?.variants || [];
    const lookup = new Map();

    for (const variant of variants) {
        if (!variant.is_active) continue;
        const attrs = variant.attributes || {};
        const keys = [];
        for (const [slug, values] of Object.entries(attrs)) {
            for (const v of values) {
                keys.push(`${slug}:${v.id}`);
            }
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

/**
 * Determine if a specific value option is selectable given current
 * selections on other axes. A value is selectable if there exists at
 * least one variant that matches all other axes + this value.
 */
function isValueSelectable(axisSlug, valueId) {
    if (!hasVariants.value) return true;

    const variants = product.value?.variants || [];

    for (const variant of variants) {
        if (!variant.is_active) continue;
        const attrs = variant.attributes || {};

        let matches = true;
        for (const [slug, valueIdOther] of Object.entries(selectedVariants)) {
            if (slug === axisSlug) continue;
            if (!valueIdOther) continue;
            const variantValues = attrs[slug];
            if (!variantValues?.some((v) => String(v.id) === String(valueIdOther))) {
                matches = false;
                break;
            }
        }
        if (!matches) continue;

        const axisValues = attrs[axisSlug];
        if (axisValues?.some((v) => String(v.id) === String(valueId))) {
            return true;
        }
    }

    return false;
}

function onVariantSelect(axisSlug, valueId) {
    if (selectedVariants[axisSlug] === String(valueId)) {
        delete selectedVariants[axisSlug];
    } else {
        selectedVariants[axisSlug] = String(valueId);
    }
    mainImageIndex.value = 0;
}

/* ────────────────────────────────────────────────────────────
 | Display price / stock (variant-aware)
 | ──────────────────────────────────────────────────────────── */

const displayPrice = computed(() => {
    if (matchedVariant.value) return matchedVariant.value.price;
    return product.value?.price || 0;
});

const displayCompareAtPrice = computed(() => {
    if (matchedVariant.value) return matchedVariant.value.compare_at_price;
    return product.value?.compare_at_price || null;
});

const displaySku = computed(() => {
    if (matchedVariant.value?.sku) return matchedVariant.value.sku;
    return product.value?.sku || '';
});

const displayInStock = computed(() => {
    if (matchedVariant.value) return matchedVariant.value.stock > 0;
    return product.value?.in_stock ?? false;
});

const displayStock = computed(() => {
    if (matchedVariant.value) return matchedVariant.value.stock;
    return null;
});

/**
 * When variants exist but user hasn't selected a full combination yet.
 */
const needsVariantSelection = computed(() => {
    if (!hasVariants.value) return false;
    if (matchedVariant.value) return false;
    const totalAxes = attributeAxes.value.length;
    const selectedCount = Object.values(selectedVariants).filter(Boolean).length;
    return totalAxes > 0 && selectedCount < totalAxes;
});

/**
 * Non-variant attributes for the attributes table.
 * Excludes axes used for variant selection to avoid duplication.
 */
const displayAttributes = computed(() => {
    const attrs = product.value?.attributes;
    if (!attrs || typeof attrs !== 'object') return [];
    const variantSlugs = new Set(attributeAxes.value.map((a) => a.slug));
    return Object.entries(attrs)
        .filter(([slug]) => !variantSlugs.has(slug))
        .map(([slug, values]) => ({
            slug,
            values: Array.isArray(values) ? values : [],
        }));
});

/* ────────────────────────────────────────────────────────────
 | Vehicle compatibility
 | ──────────────────────────────────────────────────────────── */

const vehicleCompat = computed(() => product.value?.vehicle_compatibility || []);

const visibleVehicles = computed(() => {
    if (showAllVehicles.value) return vehicleCompat.value;
    return vehicleCompat.value.slice(0, VEHICLE_SHOW_LIMIT);
});

/* ────────────────────────────────────────────────────────────
 | Breadcrumb
 | ──────────────────────────────────────────────────────────── */

const categoryPath = computed(() => product.value?.categories || []);

/* ────────────────────────────────────────────────────────────
 | Fetch product
 | ──────────────────────────────────────────────────────────── */

async function fetchProduct() {
    const match = location.pathname.match(/\/products\/(\d+)/);
    if (!match) {
        error.value = 'محصول مورد نظر پیدا نشد.';
        loading.value = false;
        return;
    }

    try {
        const { data } = await axios.get(`/api/products/${match[1]}`);
        product.value = data.data || data;
    } catch (e) {
        if (e.response?.status === 404) {
            error.value = 'محصول مورد نظر پیدا نشد.';
        } else {
            error.value = 'خطا در دریافت اطلاعات محصول.';
        }
    } finally {
        loading.value = false;
    }
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = '/';
    }
}

/* ────────────────────────────────────────────────────────────
 | Lifecycle
 | ──────────────────────────────────────────────────────────── */

onMounted(fetchProduct);
</script>

<template>
    <main class="min-h-screen bg-cream text-ink dark:bg-[#1c1721] dark:text-rose">
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-32">
            <p class="text-sm text-black/50 dark:text-white/50">در حال بارگذاری محصول…</p>
        </div>

        <!-- Error / Not Found -->
        <div v-else-if="error" class="mx-auto max-w-4xl px-4 py-32 text-center">
            <p class="mb-6 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
            <button
                type="button"
                class="rounded-lg bg-plum px-6 py-2 text-sm text-white dark:bg-rose dark:text-ink"
                @click="goBack"
            >
                بازگشت به محصولات
            </button>
        </div>

        <!-- Product -->
        <div v-else-if="product" class="mx-auto max-w-6xl px-4 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-6 flex items-center gap-2 text-xs text-black/50 dark:text-white/50">
                <a href="/" class="hover:underline">فروشگاه</a>
                <span v-for="(cat, idx) in categoryPath" :key="cat.id" class="flex items-center gap-2">
                    <span>/</span>
                    <span class="text-ink dark:text-rose">{{ cat.name }}</span>
                </span>
            </nav>

            <div class="grid gap-8 lg:grid-cols-2">
                <!-- ── Image Gallery ────────────────────────────── -->
                <div>
                    <!-- Main image -->
                    <div class="relative overflow-hidden rounded-2xl border border-black/10 bg-white dark:border-white/10 dark:bg-white/5">
                        <img
                            v-if="mainImageUrl"
                            :src="mainImageUrl"
                            :alt="mainImage?.alt_text || product.name"
                            class="aspect-square w-full object-cover"
                        />
                        <div v-else class="aspect-square flex items-center justify-center text-sm text-black/30 dark:text-white/30">
                            تصویری موجود نیست
                        </div>

                        <!-- Arrows (RTL: right = prev, left = next) -->
                        <button
                            v-if="sortedImages.length > 1"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white backdrop-blur-sm hover:bg-black/50"
                            @click="prevImage"
                        >
                            &#8594;
                        </button>
                        <button
                            v-if="sortedImages.length > 1"
                            type="button"
                            class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white backdrop-blur-sm hover:bg-black/50"
                            @click="nextImage"
                        >
                            &#8592;
                        </button>
                    </div>

                    <!-- Thumbnails -->
                    <div v-if="sortedImages.length > 1" class="mt-3 flex gap-2 overflow-x-auto">
                        <button
                            v-for="(img, idx) in sortedImages"
                            :key="img.id"
                            type="button"
                            class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg border-2 transition"
                            :class="idx === mainImageIndex ? 'border-plum dark:border-rose' : 'border-transparent'"
                            @click="selectImage(idx)"
                        >
                            <img
                                :src="`/storage/${img.path}`"
                                :alt="img.alt_text || product.name"
                                class="h-full w-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <!-- ── Product Info ──────────────────────────────── -->
                <div>
                    <h1 class="text-2xl font-bold">{{ product.name }}</h1>

                    <p v-if="displaySku" class="mt-2 text-xs text-black/40 dark:text-white/40">
                        SKU: {{ displaySku }}
                    </p>

                    <!-- Price -->
                    <div class="mt-4 flex items-baseline gap-3">
                        <span class="text-xl font-bold text-plum dark:text-rose">
                            {{ formatPrice(displayPrice) }} تومان
                        </span>
                        <span
                            v-if="displayCompareAtPrice"
                            class="text-sm text-black/40 line-through dark:text-white/40"
                        >
                            {{ formatPrice(displayCompareAtPrice) }} تومان
                        </span>
                    </div>

                    <!-- Stock -->
                    <div class="mt-3">
                        <span
                            v-if="displayInStock"
                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-400"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                            موجود
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs text-red-600 dark:bg-red-900/30 dark:text-red-400"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            ناموجود
                        </span>
                        <span
                            v-if="displayInStock && displayStock !== null"
                            class="mr-2 text-xs text-black/40 dark:text-white/40"
                        >
                            ({{ displayStock }})
                        </span>
                    </div>

                    <!-- Variant selection prompt -->
                    <p
                        v-if="needsVariantSelection"
                        class="mt-3 text-xs text-amber-600 dark:text-amber-400"
                    >
                        لطفاً ویژگی‌های محصول را انتخاب کنید.
                    </p>

                    <!-- Short description -->
                    <p v-if="product.short_description" class="mt-4 text-sm leading-relaxed text-black/70 dark:text-white/70">
                        {{ product.short_description }}
                    </p>

                    <!-- ── Variant Selector ──────────────────────── -->
                    <div v-if="hasVariants && attributeAxes.length" class="mt-6 space-y-4">
                        <div v-for="axis in attributeAxes" :key="axis.slug">
                            <p class="mb-2 text-xs font-medium text-black/60 dark:text-white/60">
                                {{ axis.slug }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="val in axis.values"
                                    :key="val.id"
                                    type="button"
                                    :disabled="!isValueSelectable(axis.slug, val.id)"
                                    class="rounded-lg border px-3 py-1.5 text-xs transition"
                                    :class="selectedVariants[axis.slug] === String(val.id)
                                        ? 'border-plum bg-plum text-white dark:border-rose dark:bg-rose dark:text-ink'
                                        : 'border-black/15 bg-white dark:border-white/20 dark:bg-white/10'
                                    "
                                    :style="val.hex_color ? { '--tw-border-opacity': 1 } : {}"
                                    @click="onVariantSelect(axis.slug, val.id)"
                                >
                                    <span
                                        v-if="val.hex_color"
                                        class="ml-1 inline-block h-3 w-3 rounded-full border border-black/10"
                                        :style="{ backgroundColor: val.hex_color }"
                                    ></span>
                                    {{ val.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Add to Cart (placeholder) ─────────────── -->
                    <div class="mt-8 flex items-center gap-3">
                        <div class="flex items-center rounded-lg border border-black/15 dark:border-white/20">
                            <button
                                type="button"
                                class="px-3 py-2 text-sm hover:bg-black/5 dark:hover:bg-white/10"
                                @click="quantity = Math.max(1, quantity - 1)"
                            >
                                −
                            </button>
                            <span class="min-w-[2rem] text-center text-sm">{{ quantity }}</span>
                            <button
                                type="button"
                                class="px-3 py-2 text-sm hover:bg-black/5 dark:hover:bg-white/10"
                                @click="quantity++"
                            >
                                +
                            </button>
                        </div>
                        <button
                            type="button"
                            disabled
                            class="flex-1 rounded-xl bg-plum px-6 py-3 text-sm font-medium text-white opacity-50 cursor-not-allowed dark:bg-rose dark:text-ink"
                        >
                            افزودن به سبد خرید
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Attributes ──────────────────────────────────── -->
            <div
                v-if="displayAttributes.length || (product.custom_attributes && product.custom_attributes.length)"
                class="mt-12"
            >
                <h2 class="mb-4 text-lg font-bold">مشخصات محصول</h2>
                <div class="overflow-hidden rounded-2xl border border-black/10 dark:border-white/10">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="attr in displayAttributes" :key="attr.slug" class="border-b border-black/5 dark:border-white/5">
                                <td class="w-1/3 bg-black/5 px-4 py-3 font-medium dark:bg-white/5">{{ attr.slug }}</td>
                                <td class="px-4 py-3">{{ attr.values.map((v) => v.label).join('، ') }}</td>
                            </tr>
                            <tr v-for="attr in product.custom_attributes || []" :key="attr.id" class="border-b border-black/5 dark:border-white/5">
                                <td class="w-1/3 bg-black/5 px-4 py-3 font-medium dark:bg-white/5">{{ attr.name }}</td>
                                <td class="px-4 py-3">{{ attr.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Description ─────────────────────────────────── -->
            <div v-if="product.description" class="mt-12">
                <h2 class="mb-4 text-lg font-bold">توضیحات</h2>
                <div class="rounded-2xl border border-black/10 bg-white p-6 text-sm leading-relaxed dark:border-white/10 dark:bg-white/5">
                    {{ product.description }}
                </div>
            </div>

            <!-- ── Vehicle Compatibility ───────────────────────── -->
            <div v-if="vehicleCompat.length" class="mt-12">
                <h2 class="mb-4 text-lg font-bold">خودروهای سازگار</h2>
                <div class="rounded-2xl border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <ul class="space-y-3 text-sm">
                        <li
                            v-for="(v, idx) in visibleVehicles"
                            :key="idx"
                            class="rounded-lg border border-black/5 px-4 py-3 dark:border-white/5"
                        >
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-ink dark:text-rose">{{ v.brand.name }}</span>
                                </div>
                                <div class="mr-4 flex items-center gap-2 text-xs text-black/60 dark:text-white/60">
                                    <span>→</span>
                                    <span>{{ v.model.name }}</span>
                                </div>
                                <div class="mr-4 flex items-center gap-2 text-xs text-black/60 dark:text-white/60">
                                    <span>→</span>
                                    <span>{{ v.generation.name }} ({{ v.generation.year_start }}<template v-if="v.generation.year_end">-{{ v.generation.year_end }}</template>)</span>
                                </div>
                                <div class="mr-4 flex items-center gap-2 text-xs text-black/50 dark:text-white/50">
                                    <span>→</span>
                                    <span>{{ v.trim.name }}</span>
                                </div>
                                <div class="mr-4 flex items-center gap-2 text-xs text-black/40 dark:text-white/40">
                                    <span>→</span>
                                    <span>{{ v.engine.name }}</span>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <button
                        v-if="vehicleCompat.length > VEHICLE_SHOW_LIMIT"
                        type="button"
                        class="mt-3 text-xs text-plum hover:underline dark:text-rose"
                        @click="showAllVehicles = !showAllVehicles"
                    >
                        {{ showAllVehicles ? 'نمایش کمتر' : `نمایش همه (${vehicleCompat.length})` }}
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
