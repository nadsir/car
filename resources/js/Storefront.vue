<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import axios from 'axios';

/* ────────────────────────────────────────────────────────────
 | State
 | ──────────────────────────────────────────────────────────── */

const categories = ref([]);
const filters = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');
const mobileFiltersOpen = ref(false);

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 12,
});

const state = reactive({
    category: '',
    values: {},
    priceMin: '',
    priceMax: '',
    inStock: false,
    search: '',
    sort: 'newest',
    page: 1,
    vehicleBrandId: '',
    vehicleModelId: '',
    vehicleGenerationId: '',
    vehicleTrimId: '',
    vehicleEngineId: '',
});

const categorySearchQuery = ref('');
const expandedSlugs = ref(new Set());

/* ────────────────────────────────────────────────────────────
 | Vehicle cascade
 | ──────────────────────────────────────────────────────────── */

const vehicleBrands = ref([]);
const vehicleModels = ref([]);
const vehicleGenerations = ref([]);
const vehicleTrims = ref([]);
const vehicleEngines = ref([]);

async function loadVehicleBrands() {
    const { data } = await axios.get('/api/vehicles/brands');
    vehicleBrands.value = data.data || [];
}

async function loadVehicleModels(brandId) {
    if (!brandId) { vehicleModels.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/brands/${brandId}/models`);
    vehicleModels.value = data.data || [];
}

async function loadVehicleGenerations(modelId) {
    if (!modelId) { vehicleGenerations.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/models/${modelId}/generations`);
    vehicleGenerations.value = data.data || [];
}

async function loadVehicleTrims(generationId) {
    if (!generationId) { vehicleTrims.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/generations/${generationId}/trims`);
    vehicleTrims.value = data.data || [];
}

async function loadVehicleEngines(trimId) {
    if (!trimId) { vehicleEngines.value = []; return; }
    const { data } = await axios.get(`/api/vehicles/trims/${trimId}/engines`);
    vehicleEngines.value = data.data || [];
}

async function onBrandChange() {
    state.vehicleModelId = '';
    state.vehicleGenerationId = '';
    state.vehicleTrimId = '';
    state.vehicleEngineId = '';
    vehicleModels.value = [];
    vehicleGenerations.value = [];
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    if (state.vehicleBrandId) {
        await loadVehicleModels(state.vehicleBrandId);
    }
    triggerFetch();
}

async function onModelChange() {
    state.vehicleGenerationId = '';
    state.vehicleTrimId = '';
    state.vehicleEngineId = '';
    vehicleGenerations.value = [];
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    if (state.vehicleModelId) {
        await loadVehicleGenerations(state.vehicleModelId);
    }
    triggerFetch();
}

async function onGenerationChange() {
    state.vehicleTrimId = '';
    state.vehicleEngineId = '';
    vehicleTrims.value = [];
    vehicleEngines.value = [];
    if (state.vehicleGenerationId) {
        await loadVehicleTrims(state.vehicleGenerationId);
    }
    triggerFetch();
}

async function onTrimChange() {
    state.vehicleEngineId = '';
    vehicleEngines.value = [];
    if (state.vehicleTrimId) {
        await loadVehicleEngines(state.vehicleTrimId);
    }
    triggerFetch();
}

function onEngineChange() {
    triggerFetch();
}

/* ────────────────────────────────────────────────────────────
 | URL sync
 | ──────────────────────────────────────────────────────────── */

const RESERVED_KEYS = [
    'category', 'categories', 'page', 'per_page', 'sort', 'search',
    'price_min', 'price_max', 'in_stock',
    'vehicle_brand_id', 'vehicle_model_id', 'vehicle_generation_id',
    'vehicle_trim_id', 'vehicle_engine_id',
];

function stateToParams() {
    const params = new URLSearchParams();

    if (state.category) params.set('category', state.category);

    for (const [slug, values] of Object.entries(state.values)) {
        if (values.length) params.set(slug, values.join(','));
    }

    if (state.priceMin !== '') params.set('price_min', state.priceMin);
    if (state.priceMax !== '') params.set('price_max', state.priceMax);
    if (state.inStock) params.set('in_stock', '1');
    if (state.search) params.set('search', state.search);
    if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);
    if (state.page > 1) params.set('page', String(state.page));

    if (state.vehicleBrandId) params.set('vehicle_brand_id', state.vehicleBrandId);
    if (state.vehicleModelId) params.set('vehicle_model_id', state.vehicleModelId);
    if (state.vehicleGenerationId) params.set('vehicle_generation_id', state.vehicleGenerationId);
    if (state.vehicleTrimId) params.set('vehicle_trim_id', state.vehicleTrimId);
    if (state.vehicleEngineId) params.set('vehicle_engine_id', state.vehicleEngineId);

    return params;
}

function pushUrl() {
    const params = stateToParams();
    const qs = params.toString();
    const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
    history.pushState({}, '', url);
}

function restoreFromUrl() {
    const params = new URLSearchParams(window.location.search);

    state.category = params.get('category') || '';
    categorySearchQuery.value = '';
    state.priceMin = params.get('price_min') || '';
    state.priceMax = params.get('price_max') || '';
    state.inStock = params.get('in_stock') === '1';
    state.search = params.get('search') || '';
    state.sort = params.get('sort') || 'newest';
    state.page = parseInt(params.get('page') || '1', 10);

    state.vehicleBrandId = params.get('vehicle_brand_id') || '';
    state.vehicleModelId = params.get('vehicle_model_id') || '';
    state.vehicleGenerationId = params.get('vehicle_generation_id') || '';
    state.vehicleTrimId = params.get('vehicle_trim_id') || '';
    state.vehicleEngineId = params.get('vehicle_engine_id') || '';

    state.values = {};

    for (const [key, val] of params.entries()) {
        if (!RESERVED_KEYS.includes(key)) {
            state.values[key] = val.split(',').filter(Boolean);
        }
    }
}

function onPopState() {
    restoreFromUrl();
    if (state.category) {
        expandAncestorsOf(state.category);
    }
    refreshAfterRestore();
}

/* ────────────────────────────────────────────────────────────
 | Category + Filters
 | ──────────────────────────────────────────────────────────── */

function findCategoryBySlug(items, slug) {
    for (const item of items) {
        if (item.slug === slug) return item;
        const found = findCategoryBySlug(item.children || [], slug);
        if (found) return found;
    }
    return null;
}

function getCategoryPath(items, slug, path = []) {
    for (const item of items) {
        const currentPath = [...path, { name: item.name, slug: item.slug }];
        if (item.slug === slug) return currentPath;
        const found = getCategoryPath(item.children || [], slug, currentPath);
        if (found) return found;
    }
    return null;
}

function collectAllSlugs(items) {
    const slugs = [];
    for (const item of items) {
        slugs.push(item.slug);
        slugs.push(...collectAllSlugs(item.children || []));
    }
    return slugs;
}

const selectedCategoryBreadcrumb = computed(() => {
    if (!state.category) return [];
    return getCategoryPath(categories.value, state.category) || [];
});

const flatCategorySearchResults = computed(() => {
    const query = categorySearchQuery.value.trim().toLowerCase();
    if (!query) return [];

    const results = [];
    function walk(items, ancestors) {
        for (const item of items) {
            const currentPath = [...ancestors, item.name];
            if (item.name.toLowerCase().includes(query)) {
                results.push({
                    slug: item.slug,
                    name: item.name,
                    path: currentPath.join(' / '),
                });
            }
            walk(item.children || [], currentPath);
        }
    }
    walk(categories.value, []);
    return results;
});

const filteredCategories = computed(() => {
    const query = categorySearchQuery.value.trim().toLowerCase();
    if (!query) return categories.value;

    function filterTree(items) {
        return items.reduce((acc, item) => {
            const childResults = filterTree(item.children || []);
            if (item.name.toLowerCase().includes(query) || childResults.length > 0) {
                acc.push({ ...item, children: childResults });
            }
            return acc;
        }, []);
    }
    return filterTree(categories.value);
});

function selectedValues(slug) {
    return state.values[slug] || [];
}

function isSelected(slug, value) {
    return selectedValues(slug).includes(value);
}

async function selectCategory() {
    state.values = {};
    state.page = 1;
    filters.value = [];

    if (state.category) {
        expandAncestorsOf(state.category);
        await loadFilters();
    }

    pushUrl();
    await loadProducts();
}

async function loadCategories() {
    const { data } = await axios.get('/api/categories/tree');
    categories.value = data.data || [];
}

async function loadFilters() {
    if (!state.category) { filters.value = []; return; }
    const { data } = await axios.get(`/api/categories/${state.category}/filters`);
    filters.value = data.data || [];
}

function expandAncestorsOf(slug) {
    const path = getCategoryPath(categories.value, slug);
    if (!path) return;
    const newSet = new Set(expandedSlugs.value);
    for (const node of path) {
        if (node.slug !== slug) newSet.add(node.slug);
    }
    expandedSlugs.value = newSet;
}

function toggleCategoryExpand(slug) {
    const newSet = new Set(expandedSlugs.value);
    if (newSet.has(slug)) {
        newSet.delete(slug);
    } else {
        newSet.add(slug);
    }
    expandedSlugs.value = newSet;
}

async function selectCategorySlug(slug) {
    if (state.category === slug) {
        clearCategorySelection();
        return;
    }
    state.category = slug;
    state.values = {};
    state.page = 1;
    filters.value = [];
    categorySearchQuery.value = '';
    expandAncestorsOf(slug);
    await loadFilters();
    pushUrl();
    await loadProducts();
}

async function clearCategorySelection() {
    state.category = '';
    state.values = {};
    state.page = 1;
    filters.value = [];
    categorySearchQuery.value = '';
    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Attribute filter toggle
 | ──────────────────────────────────────────────────────────── */

async function toggleValue(filter, value) {
    const current = [...selectedValues(filter.slug)];
    const idx = current.indexOf(value);

    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        current.push(value);
    }

    if (current.length) {
        state.values[filter.slug] = current;
    } else {
        delete state.values[filter.slug];
    }

    state.page = 1;
    pushUrl();
    await loadProducts();
}

async function setScalarValue(filter, value) {
    if (value === '') {
        delete state.values[filter.slug];
    } else {
        state.values[filter.slug] = [value];
    }

    state.page = 1;
    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Active filter chips
 | ──────────────────────────────────────────────────────────── */

const activeFilters = computed(() => {
    const selected = [];

    if (state.category) {
        const path = getCategoryPath(categories.value, state.category);
        const label = path ? path.map((n) => n.name).join(' › ') : state.category;
        selected.push({ type: 'category', slug: 'category', value: state.category, label: `دسته: ${label}` });
    }

    for (const filter of filters.value) {
        for (const value of selectedValues(filter.slug)) {
            const option = filter.values.find((item) => item.value === value);
            selected.push({
                type: 'attribute',
                slug: filter.slug,
                value,
                label: `${filter.name}: ${option?.label ?? value}`,
            });
        }
    }

    if (state.priceMin !== '' || state.priceMax !== '') {
        const min = state.priceMin !== '' ? formatPrice(state.priceMin) : '۰';
        const max = state.priceMax !== '' ? formatPrice(state.priceMax) : '∞';
        selected.push({ type: 'price', slug: 'price', value: 'price', label: `قیمت: ${min} — ${max}` });
    }

    if (state.inStock) {
        selected.push({ type: 'stock', slug: 'in_stock', value: '1', label: 'فقط موجود' });
    }

    if (state.search) {
        selected.push({ type: 'search', slug: 'search', value: state.search, label: `جستجو: ${state.search}` });
    }

    if (state.vehicleEngineId) {
        const e = vehicleEngines.value.find((v) => String(v.id) === String(state.vehicleEngineId));
        if (e) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'engine', label: `خودرو: ${e.name}` });
    } else if (state.vehicleTrimId) {
        const t = vehicleTrims.value.find((v) => String(v.id) === String(state.vehicleTrimId));
        if (t) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'trim', label: `خودرو: ${t.name}` });
    } else if (state.vehicleGenerationId) {
        const g = vehicleGenerations.value.find((v) => String(v.id) === String(state.vehicleGenerationId));
        if (g) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'generation', label: `خودرو: ${g.name}` });
    } else if (state.vehicleModelId) {
        const m = vehicleModels.value.find((v) => String(v.id) === String(state.vehicleModelId));
        if (m) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'model', label: `خودرو: ${m.name}` });
    } else if (state.vehicleBrandId) {
        const b = vehicleBrands.value.find((v) => String(v.id) === String(state.vehicleBrandId));
        if (b) selected.push({ type: 'vehicle', slug: 'vehicle', value: 'brand', label: `خودرو: ${b.name}` });
    }

    return selected;
});

async function removeChip(chip) {
    if (chip.type === 'category') {
        await clearCategorySelection();
        return;
    } else if (chip.type === 'attribute') {
        const current = selectedValues(chip.slug).filter((v) => v !== chip.value);
        if (current.length) {
            state.values[chip.slug] = current;
        } else {
            delete state.values[chip.slug];
        }
    } else if (chip.type === 'price') {
        state.priceMin = '';
        state.priceMax = '';
    } else if (chip.type === 'stock') {
        state.inStock = false;
    } else if (chip.type === 'search') {
        state.search = '';
    } else if (chip.type === 'vehicle') {
        state.vehicleBrandId = '';
        state.vehicleModelId = '';
        state.vehicleGenerationId = '';
        state.vehicleTrimId = '';
        state.vehicleEngineId = '';
        vehicleModels.value = [];
        vehicleGenerations.value = [];
        vehicleTrims.value = [];
        vehicleEngines.value = [];
    }

    state.page = 1;
    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Search debounce
 | ──────────────────────────────────────────────────────────── */

let searchTimer = null;

function onSearchInput(value) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        state.search = value;
        state.page = 1;
        pushUrl();
        loadProducts();
    }, 400);
}

/* ────────────────────────────────────────────────────────────
 | Sort / Price / Stock change handlers
 | ──────────────────────────────────────────────────────────── */

let priceTimer = null;

async function onSortChange() {
    state.page = 1;
    pushUrl();
    await loadProducts();
}

function onPriceInput(which, value) {
    clearTimeout(priceTimer);
    priceTimer = setTimeout(() => {
        if (which === 'min') state.priceMin = value;
        else state.priceMax = value;
        state.page = 1;
        pushUrl();
        loadProducts();
    }, 500);
}

async function onStockToggle() {
    state.page = 1;
    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Fetch trigger
 | ──────────────────────────────────────────────────────────── */

async function triggerFetch() {
    state.page = 1;
    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Pagination
 | ──────────────────────────────────────────────────────────── */

async function goToPage(page) {
    if (page < 1 || page > pagination.lastPage || page === pagination.currentPage) return;
    state.page = page;
    pushUrl();
    await loadProducts();
    await nextTick();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const pageNumbers = computed(() => {
    const pages = [];
    const total = pagination.lastPage;
    const current = pagination.currentPage;
    const delta = 2;

    for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
        pages.push(i);
    }

    return pages;
});

/* ────────────────────────────────────────────────────────────
 | Clear all filters
 | ──────────────────────────────────────────────────────────── */

async function clearAllFilters() {
    state.category = '';
    state.values = {};
    state.priceMin = '';
    state.priceMax = '';
    state.inStock = false;
    state.search = '';
    state.sort = 'newest';
    state.page = 1;
    state.vehicleBrandId = '';
    state.vehicleModelId = '';
    state.vehicleGenerationId = '';
    state.vehicleTrimId = '';
    state.vehicleEngineId = '';
    categorySearchQuery.value = '';
    filters.value = [];
    vehicleModels.value = [];
    vehicleGenerations.value = [];
    vehicleTrims.value = [];
    vehicleEngines.value = [];

    pushUrl();
    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Product loading
 | ──────────────────────────────────────────────────────────── */

async function loadProducts() {
    loading.value = true;
    error.value = '';

    try {
        const params = {};

        if (state.category) params.category = state.category;

        for (const [slug, values] of Object.entries(state.values)) {
            if (values.length) params[slug] = values.join(',');
        }

        if (state.priceMin !== '') params.price_min = state.priceMin;
        if (state.priceMax !== '') params.price_max = state.priceMax;
        if (state.inStock) params.in_stock = '1';
        if (state.search) params.search = state.search;
        if (state.sort) params.sort = state.sort;
        params.page = state.page;
        params.per_page = 12;

        if (state.vehicleEngineId) params.vehicle_engine_id = state.vehicleEngineId;
        else if (state.vehicleTrimId) params.vehicle_trim_id = state.vehicleTrimId;
        else if (state.vehicleGenerationId) params.vehicle_generation_id = state.vehicleGenerationId;
        else if (state.vehicleModelId) params.vehicle_model_id = state.vehicleModelId;
        else if (state.vehicleBrandId) params.vehicle_brand_id = state.vehicleBrandId;

        const { data } = await axios.get('/api/products', { params });
        products.value = data.data || [];

        const meta = data.meta || {};
        pagination.currentPage = meta.current_page || 1;
        pagination.lastPage = meta.last_page || 1;
        pagination.total = meta.total || products.value.length;
        pagination.perPage = meta.per_page || 12;
    } catch (requestError) {
        products.value = [];
        error.value = requestError.response?.data?.message || 'دریافت محصولات ناموفق بود.';
        pagination.currentPage = 1;
        pagination.lastPage = 1;
        pagination.total = 0;
    } finally {
        loading.value = false;
    }
}

async function refreshAfterRestore() {
    if (state.category) {
        expandAncestorsOf(state.category);
        await loadFilters();
    } else {
        filters.value = [];
    }

    if (state.vehicleBrandId) await loadVehicleModels(state.vehicleBrandId);
    else { vehicleModels.value = []; }

    if (state.vehicleModelId) await loadVehicleGenerations(state.vehicleModelId);
    else { vehicleGenerations.value = []; }

    if (state.vehicleGenerationId) await loadVehicleTrims(state.vehicleGenerationId);
    else { vehicleTrims.value = []; }

    if (state.vehicleTrimId) await loadVehicleEngines(state.vehicleTrimId);
    else { vehicleEngines.value = []; }

    await loadProducts();
}

/* ────────────────────────────────────────────────────────────
 | Helpers
 | ──────────────────────────────────────────────────────────── */

function productImage(product) {
    const image = product.images?.find((item) => item.is_primary) || product.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

const hasActiveFilters = computed(() => {
    return activeFilters.value.length > 0 || state.category !== '';
});

/* ────────────────────────────────────────────────────────────
 | Lifecycle
 | ──────────────────────────────────────────────────────────── */

onMounted(async () => {
    restoreFromUrl();
    window.addEventListener('popstate', onPopState);

    try {
        await loadCategories();
        await loadVehicleBrands();
        await refreshAfterRestore();
    } catch (requestError) {
        error.value = 'دریافت اطلاعات فروشگاه ناموفق بود.';
    }
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
    clearTimeout(searchTimer);
    clearTimeout(priceTimer);
});
</script>

<template>
    <main dir="rtl" class="min-h-screen bg-cream px-4 py-8 text-ink dark:bg-[#1c1721] dark:text-white sm:px-8">
        <div class="mx-auto max-w-7xl">
            <header class="mb-8 flex flex-col gap-5 border-b border-black/10 pb-6 dark:border-white/10 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.24em] text-plum dark:text-rose">CAR</p>
                    <h1 class="mt-2 text-3xl font-bold">فروشگاه قطعات خودرو</h1>
                    <p class="mt-2 text-sm text-black/60 dark:text-white/60">
                        {{ pagination.total }} محصول یافت شد
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-black/15 bg-white px-4 py-2 text-sm dark:border-white/20 dark:bg-white/10 lg:hidden"
                        @click="mobileFiltersOpen = true"
                    >
                        فیلترها
                    </button>
                </div>
            </header>

            <!-- Active filter chips -->
            <div v-if="activeFilters.length" class="mb-5 flex flex-wrap items-center gap-2">
                <button
                    v-for="chip in activeFilters"
                    :key="`${chip.type}-${chip.slug}-${chip.value}`"
                    type="button"
                    class="rounded-full bg-black/5 px-3 py-1 text-xs dark:bg-white/10"
                    @click="removeChip(chip)"
                >
                    {{ chip.label }} &times;
                </button>

                <button
                    type="button"
                    class="rounded-full bg-plum/10 px-3 py-1 text-xs font-medium text-plum dark:bg-rose/10 dark:text-rose"
                    @click="clearAllFilters"
                >
                    حذف همه فیلترها
                </button>
            </div>

            <!-- Mobile overlay backdrop -->
            <Teleport to="body">
                <div
                    v-if="mobileFiltersOpen"
                    class="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    @click="mobileFiltersOpen = false"
                />
            </Teleport>

            <div class="grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <!-- Sidebar (desktop: static, mobile: fixed overlay) -->
                <aside
                    :class="[
                        'border-black/10 bg-white p-5 dark:border-white/10 dark:bg-white/5',
                        'lg:block lg:rounded-2xl lg:shadow-sm',
                        mobileFiltersOpen
                            ? 'fixed inset-y-0 right-0 z-50 w-80 overflow-y-auto shadow-xl dark:bg-[#1c1721] lg:relative lg:inset-auto lg:w-auto lg:shadow-sm'
                            : 'hidden',
                    ]"
                    @click.stop
                >
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="font-semibold">فیلترها</h2>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="text-xs text-plum underline dark:text-rose"
                                @click="clearAllFilters"
                            >
                                پاک کردن
                            </button>
                            <button
                                type="button"
                                class="text-lg text-black/50 dark:text-white/50 lg:hidden"
                                @click="mobileFiltersOpen = false"
                            >
                                &times;
                            </button>
                        </div>
                    </div>

                    <!-- Category tree -->
                    <section class="border-t border-black/10 py-5 first:border-t-0 first:pt-0 dark:border-white/10">
                        <h3 class="mb-3 text-sm font-semibold">دسته‌بندی محصولات</h3>

                        <!-- Breadcrumb -->
                        <div v-if="selectedCategoryBreadcrumb.length" class="mb-3 flex flex-wrap items-center gap-1 text-xs text-black/50 dark:text-white/50">
                            <template v-for="(crumb, idx) in selectedCategoryBreadcrumb" :key="crumb.slug">
                                <button
                                    type="button"
                                    class="hover:text-plum dark:hover:text-rose"
                                    @click="selectCategorySlug(crumb.slug)"
                                >
                                    {{ crumb.name }}
                                </button>
                                <span v-if="idx < selectedCategoryBreadcrumb.length - 1">›</span>
                            </template>
                        </div>

                        <!-- Clear category -->
                        <button
                            v-if="state.category"
                            type="button"
                            class="mb-3 flex items-center gap-1 rounded-md bg-plum/10 px-2 py-1 text-xs font-medium text-plum dark:bg-rose/10 dark:text-rose"
                            @click="clearCategorySelection"
                        >
                            <span>× حذف دسته‌بندی</span>
                        </button>

                        <!-- Category search -->
                        <div class="relative mb-3">
                            <input
                                v-model="categorySearchQuery"
                                type="text"
                                placeholder="🔍 جستجوی دسته‌بندی..."
                                class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 pr-8 text-xs dark:border-white/20 dark:bg-white/10"
                            />
                            <button
                                v-if="categorySearchQuery"
                                type="button"
                                class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-black/40 dark:text-white/40"
                                @click="categorySearchQuery = ''"
                            >
                                ×
                            </button>
                        </div>

                        <!-- Search results -->
                        <div v-if="categorySearchQuery.trim() && flatCategorySearchResults.length" class="mb-3 max-h-48 overflow-y-auto rounded-lg border border-black/10 dark:border-white/10">
                            <button
                                v-for="result in flatCategorySearchResults"
                                :key="result.slug"
                                type="button"
                                class="block w-full border-b border-black/5 px-3 py-2 text-right text-xs last:border-b-0 hover:bg-black/5 dark:border-white/5 dark:hover:bg-white/5"
                                :class="{ 'bg-plum/10 text-plum dark:bg-rose/10 dark:text-rose': state.category === result.slug }"
                                @click="selectCategorySlug(result.slug)"
                            >
                                <span class="block truncate font-medium">{{ result.name }}</span>
                                <span class="mt-0.5 block truncate text-[10px] text-black/40 dark:text-white/40">{{ result.path }}</span>
                            </button>
                        </div>
                        <p v-else-if="categorySearchQuery.trim() && !flatCategorySearchResults.length" class="mb-3 text-xs text-black/40 dark:text-white/40">
                            دسته‌بندی موردنظر پیدا نشد
                        </p>

                        <!-- Tree -->
                        <div v-if="!categorySearchQuery.trim()" class="max-h-64 overflow-y-auto">
                            <template v-for="category in filteredCategories" :key="category.slug">
                                <!-- Category node -->
                                <div class="flex items-center gap-1">
                                    <button
                                        v-if="category.children && category.children.length"
                                        type="button"
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] text-black/50 hover:bg-black/10 dark:text-white/50 dark:hover:bg-white/10"
                                        @click.stop="toggleCategoryExpand(category.slug)"
                                    >
                                        {{ expandedSlugs.has(category.slug) ? '−' : '+' }}
                                    </button>
                                    <span v-else class="h-5 w-5 shrink-0" />
                                    <button
                                        type="button"
                                        class="flex-1 truncate rounded px-2 py-1 text-right text-xs hover:bg-black/5 dark:hover:bg-white/5"
                                        :class="state.category === category.slug
                                            ? 'bg-plum/10 font-semibold text-plum dark:bg-rose/10 dark:text-rose'
                                            : 'text-ink dark:text-white'"
                                        @click="selectCategorySlug(category.slug)"
                                    >
                                        {{ category.name }}
                                    </button>
                                </div>

                                <!-- Children -->
                                <div v-if="expandedSlugs.has(category.slug) && category.children && category.children.length" class="mr-4 border-r-2 border-black/5 pr-1 dark:border-white/5">
                                    <template v-for="child1 in category.children" :key="child1.slug">
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="child1.children && child1.children.length"
                                                type="button"
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] text-black/50 hover:bg-black/10 dark:text-white/50 dark:hover:bg-white/10"
                                                @click.stop="toggleCategoryExpand(child1.slug)"
                                            >
                                                {{ expandedSlugs.has(child1.slug) ? '−' : '+' }}
                                            </button>
                                            <span v-else class="h-5 w-5 shrink-0" />
                                            <button
                                                type="button"
                                                class="flex-1 truncate rounded px-2 py-1 text-right text-xs hover:bg-black/5 dark:hover:bg-white/5"
                                                :class="state.category === child1.slug
                                                    ? 'bg-plum/10 font-semibold text-plum dark:bg-rose/10 dark:text-rose'
                                                    : 'text-ink dark:text-white'"
                                                @click="selectCategorySlug(child1.slug)"
                                            >
                                                {{ child1.name }}
                                            </button>
                                        </div>

                                        <!-- Level 3 -->
                                        <div v-if="expandedSlugs.has(child1.slug) && child1.children && child1.children.length" class="mr-4 border-r-2 border-black/5 pr-1 dark:border-white/5">
                                            <template v-for="child2 in child1.children" :key="child2.slug">
                                                <div class="flex items-center gap-1">
                                                    <button
                                                        v-if="child2.children && child2.children.length"
                                                        type="button"
                                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] text-black/50 hover:bg-black/10 dark:text-white/50 dark:hover:bg-white/10"
                                                        @click.stop="toggleCategoryExpand(child2.slug)"
                                                    >
                                                        {{ expandedSlugs.has(child2.slug) ? '−' : '+' }}
                                                    </button>
                                                    <span v-else class="h-5 w-5 shrink-0" />
                                                    <button
                                                        type="button"
                                                        class="flex-1 truncate rounded px-2 py-1 text-right text-xs hover:bg-black/5 dark:hover:bg-white/5"
                                                        :class="state.category === child2.slug
                                                            ? 'bg-plum/10 font-semibold text-plum dark:bg-rose/10 dark:text-rose'
                                                            : 'text-ink dark:text-white'"
                                                        @click="selectCategorySlug(child2.slug)"
                                                    >
                                                        {{ child2.name }}
                                                    </button>
                                                </div>

                                                <!-- Level 4 -->
                                                <div v-if="expandedSlugs.has(child2.slug) && child2.children && child2.children.length" class="mr-4 border-r-2 border-black/5 pr-1 dark:border-white/5">
                                                    <template v-for="child3 in child2.children" :key="child3.slug">
                                                        <div class="flex items-center gap-1">
                                                            <span class="h-5 w-5 shrink-0" />
                                                            <button
                                                                type="button"
                                                                class="flex-1 truncate rounded px-2 py-1 text-right text-xs hover:bg-black/5 dark:hover:bg-white/5"
                                                                :class="state.category === child3.slug
                                                                    ? 'bg-plum/10 font-semibold text-plum dark:bg-rose/10 dark:text-rose'
                                                                    : 'text-ink dark:text-white'"
                                                                @click="selectCategorySlug(child3.slug)"
                                                            >
                                                                {{ child3.name }}
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <p v-if="!categorySearchQuery.trim() && !filteredCategories.length" class="text-xs text-black/40 dark:text-white/40">
                            دسته‌بندی‌ای موجود نیست.
                        </p>
                    </section>

                    <!-- Attribute filters -->
                    <section v-for="filter in filters" :key="filter.id" class="border-t border-black/10 py-5 first:border-t-0 first:pt-0 dark:border-white/10">
                        <h3 class="mb-3 text-sm font-semibold">{{ filter.name }}</h3>

                        <div v-if="filter.values.length && filter.type === 'color'" class="flex flex-wrap gap-3">
                            <button
                                v-for="value in filter.values"
                                :key="value.id"
                                type="button"
                                :title="value.label"
                                :aria-label="value.label"
                                class="h-8 w-8 rounded-full border-2 border-white ring-1 ring-black/20 dark:border-[#1c1721]"
                                :class="{ 'ring-2 ring-plum dark:ring-rose': isSelected(filter.slug, value.value) }"
                                :style="{ backgroundColor: value.hex_color || '#cbd5e1' }"
                                @click="toggleValue(filter, value.value)"
                            />
                        </div>

                        <div v-else-if="filter.values.length" class="flex flex-wrap gap-2">
                            <button
                                v-for="value in filter.values"
                                :key="value.id"
                                type="button"
                                class="rounded-full border border-black/15 px-3 py-1.5 text-xs dark:border-white/20"
                                :class="{ 'bg-ink text-white dark:bg-rose dark:text-ink': isSelected(filter.slug, value.value) }"
                                @click="toggleValue(filter, value.value)"
                            >
                                {{ value.label }}
                            </button>
                        </div>

                        <div v-else-if="filter.type === 'boolean'" class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-black/15 px-3 py-1.5 text-xs dark:border-white/20"
                                :class="{ 'bg-ink text-white dark:bg-rose dark:text-ink': isSelected(filter.slug, 'true') }"
                                @click="toggleValue(filter, 'true')"
                            >
                                بله
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-black/15 px-3 py-1.5 text-xs dark:border-white/20"
                                :class="{ 'bg-ink text-white dark:bg-rose dark:text-ink': isSelected(filter.slug, 'false') }"
                                @click="toggleValue(filter, 'false')"
                            >
                                خیر
                            </button>
                        </div>

                        <input
                            v-else-if="filter.type === 'number' || filter.type === 'text'"
                            :type="filter.type === 'number' ? 'number' : 'text'"
                            :value="selectedValues(filter.slug)[0] || ''"
                            class="w-full rounded-lg border border-black/15 bg-white px-3 py-2 text-sm dark:border-white/20 dark:bg-white/10"
                            @change="setScalarValue(filter, $event.target.value)"
                        >

                        <p v-else class="text-xs text-black/50 dark:text-white/50">
                            برای این نوع ویژگی هنوز مقداری تعریف نشده است.
                        </p>
                    </section>

                    <!-- Price range -->
                    <section class="border-t border-black/10 py-5 dark:border-white/10">
                        <h3 class="mb-3 text-sm font-semibold">قیمت</h3>
                        <div class="flex gap-2">
                            <input
                                type="number"
                                placeholder="حداقل"
                                :value="state.priceMin"
                                class="w-1/2 rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10"
                                @input="onPriceInput('min', $event.target.value)"
                            />
                            <input
                                type="number"
                                placeholder="حداکثر"
                                :value="state.priceMax"
                                class="w-1/2 rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10"
                                @input="onPriceInput('max', $event.target.value)"
                            />
                        </div>
                    </section>

                    <!-- Stock -->
                    <section class="border-t border-black/10 py-5 dark:border-white/10">
                        <label class="flex items-center justify-between gap-3 text-sm">
                            فقط موجود
                            <input :checked="state.inStock" type="checkbox" class="check" @change="state.inStock = $event.target.checked; onStockToggle()" />
                        </label>
                    </section>

                    <!-- Vehicle selector -->
                    <section class="border-t border-black/10 py-5 dark:border-white/10">
                        <h3 class="mb-3 text-sm font-semibold">خودرو</h3>

                        <div class="flex flex-col gap-2">
                            <select
                                :value="state.vehicleBrandId"
                                class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10"
                                @change="state.vehicleBrandId = $event.target.value; onBrandChange()"
                            >
                                <option value="">انتخاب برند</option>
                                <option v-for="b in vehicleBrands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>

                            <select
                                :value="state.vehicleModelId"
                                :disabled="!state.vehicleBrandId"
                                class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10 disabled:opacity-40"
                                @change="state.vehicleModelId = $event.target.value; onModelChange()"
                            >
                                <option value="">انتخاب مدل</option>
                                <option v-for="m in vehicleModels" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>

                            <select
                                :value="state.vehicleGenerationId"
                                :disabled="!state.vehicleModelId"
                                class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10 disabled:opacity-40"
                                @change="state.vehicleGenerationId = $event.target.value; onGenerationChange()"
                            >
                                <option value="">انتخاب نسل</option>
                                <option v-for="g in vehicleGenerations" :key="g.id" :value="g.id">{{ g.name }} ({{ g.year_start }})</option>
                            </select>

                            <select
                                :value="state.vehicleTrimId"
                                :disabled="!state.vehicleGenerationId"
                                class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10 disabled:opacity-40"
                                @change="state.vehicleTrimId = $event.target.value; onTrimChange()"
                            >
                                <option value="">انتخاب تیپ</option>
                                <option v-for="t in vehicleTrims" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>

                            <select
                                :value="state.vehicleEngineId"
                                :disabled="!state.vehicleTrimId"
                                class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10 disabled:opacity-40"
                                @change="state.vehicleEngineId = $event.target.value; onEngineChange()"
                            >
                                <option value="">انتخاب موتور</option>
                                <option v-for="e in vehicleEngines" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                    </section>
                </aside>

                <!-- Product content area -->
                <section>
                    <!-- Search -->
                    <div class="mb-5">
                        <input
                            type="text"
                            :value="state.search"
                            placeholder="جستجوی محصول..."
                            class="w-full rounded-xl border border-black/15 bg-white px-4 py-3 text-sm dark:border-white/20 dark:bg-white/10"
                            @input="onSearchInput($event.target.value)"
                        />
                    </div>

                    <!-- Sort + count -->
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-black/60 dark:text-white/60">
                            {{ pagination.total }} محصول
                        </p>

                        <select
                            v-model="state.sort"
                            class="rounded-lg border border-black/15 bg-white px-3 py-2 text-xs dark:border-white/20 dark:bg-white/10"
                            @change="onSortChange"
                        >
                            <option value="newest">جدیدترین</option>
                            <option value="oldest">قدیمی‌ترین</option>
                            <option value="price_asc">ارزان‌ترین</option>
                            <option value="price_desc">گران‌ترین</option>
                            <option value="name_asc">نام: صعودی</option>
                            <option value="name_desc">نام: نزولی</option>
                        </select>
                    </div>

                    <!-- Error -->
                    <p v-if="error" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                        {{ error }}
                    </p>

                    <!-- Loading -->
                    <div v-if="loading" class="rounded-2xl border border-dashed border-black/20 p-10 text-center text-sm dark:border-white/20">
                        در حال دریافت محصولات…
                    </div>

                    <!-- Empty -->
                    <div v-else-if="!products.length" class="rounded-2xl border border-dashed border-black/20 p-10 text-center text-sm dark:border-white/20">
                        محصولی با این شرایط پیدا نشد.
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="mt-3 block mx-auto rounded-lg bg-plum px-4 py-2 text-xs text-white dark:bg-rose dark:text-ink"
                            @click="clearAllFilters"
                        >
                            حذف فیلترها
                        </button>
                    </div>

                    <!-- Product grid -->
                    <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="product in products" :key="product.id" class="overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                            <a :href="`/products/${product.id}`" class="block">
                                <div class="aspect-square bg-black/5 dark:bg-white/10">
                                    <img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="h-full w-full object-cover" />
                                </div>
                                <div class="p-4">
                                    <h2 class="font-semibold">{{ product.name }}</h2>
                                    <p class="mt-2 text-sm text-plum dark:text-rose">{{ formatPrice(product.price) }} تومان</p>
                                    <p v-if="product.compare_at_price" class="text-xs text-black/40 line-through dark:text-white/40">
                                        {{ formatPrice(product.compare_at_price) }} تومان
                                    </p>
                                    <dl v-if="product.attributes && Object.keys(product.attributes).length" class="mt-3 space-y-1 text-xs text-black/60 dark:text-white/60">
                                        <div v-for="(values, slug) in product.attributes" :key="slug" class="flex gap-2">
                                            <dt>{{ slug }}:</dt>
                                            <dd>{{ values.map((v) => v.label).join('، ') }}</dd>
                                        </div>
                                        <div v-for="attr in product.custom_attributes || []" :key="attr.id" class="flex gap-2">
                                            <dt>{{ attr.name }}:</dt>
                                            <dd>{{ attr.value }}</dd>
                                        </div>
                                    </dl>
                                    <p v-if="product.in_stock === false" class="mt-2 text-xs text-red-500">ناموجود</p>
                                </div>
                            </a>
                        </article>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.lastPage > 1 && !loading" class="mt-8 flex items-center justify-center gap-1">
                        <button
                            type="button"
                            :disabled="pagination.currentPage <= 1"
                            class="rounded-lg border border-black/10 px-3 py-2 text-xs dark:border-white/20 disabled:opacity-40"
                            @click="goToPage(pagination.currentPage - 1)"
                        >
                            &raquo;
                        </button>

                        <button
                            v-for="p in pageNumbers"
                            :key="p"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-xs"
                            :class="p === pagination.currentPage
                                ? 'border-plum bg-plum text-white dark:border-rose dark:bg-rose dark:text-ink'
                                : 'border-black/10 dark:border-white/20'"
                            @click="goToPage(p)"
                        >
                            {{ p }}
                        </button>

                        <button
                            type="button"
                            :disabled="pagination.currentPage >= pagination.lastPage"
                            class="rounded-lg border border-black/10 px-3 py-2 text-xs dark:border-white/20 disabled:opacity-40"
                            @click="goToPage(pagination.currentPage + 1)"
                        >
                            &laquo;
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
