<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const categories = ref([]);
const filters = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref('');

const state = reactive({
    category: '',
    values: {},
    maxPrice: null,
    inStock: false,
    sort: 'newest',
});

const categoryOptions = computed(() => flattenCategories(categories.value));

const visibleProducts = computed(() => {
    const result = products.value.filter((product) => {
        if (state.maxPrice !== null && product.price > state.maxPrice) {
            return false;
        }

        return !state.inStock || product.in_stock;
    });

    if (state.sort === 'price-asc') {
        return result.sort((left, right) => left.price - right.price);
    }

    if (state.sort === 'price-desc') {
        return result.sort((left, right) => right.price - left.price);
    }

    return result.sort(
        (left, right) => new Date(right.published_at || 0) - new Date(left.published_at || 0)
    );
});

const activeFilters = computed(() => {
    const selected = [];

    for (const filter of filters.value) {
        for (const value of selectedValues(filter.slug)) {
            const option = filter.values.find((item) => item.value === value);

            selected.push({
                slug: filter.slug,
                value,
                label: `${filter.name}: ${option?.label ?? value}`,
            });
        }
    }

    return selected;
});

function flattenCategories(items, depth = 0) {
    return items.flatMap((category) => [
        {
            id: category.id,
            slug: category.slug,
            label: `${'— '.repeat(depth)}${category.name}`,
        },
        ...flattenCategories(category.children || [], depth + 1),
    ]);
}

function selectedValues(slug) {
    return state.values[slug] || [];
}

function isSelected(slug, value) {
    return selectedValues(slug).includes(value);
}

async function selectCategory() {
    state.values = {};
    filters.value = [];

    if (state.category) {
        await loadFilters();
    }

    await loadProducts();
}

async function toggleValue(filter, value) {
    const current = selectedValues(filter.slug);
    const allowsMultiple = filter.type === 'multiselect';

    if (allowsMultiple) {
        state.values[filter.slug] = current.includes(value)
            ? current.filter((item) => item !== value)
            : [...current, value];
    } else {
        state.values[filter.slug] = current.includes(value) ? [] : [value];
    }

    await loadProducts();
}

async function setScalarValue(filter, value) {
    state.values[filter.slug] = value === '' ? [] : [value];
    await loadProducts();
}

async function removeFilter(filter) {
    state.values[filter.slug] = selectedValues(filter.slug).filter(
        (value) => value !== filter.value
    );

    await loadProducts();
}

async function clearFilters() {
    state.values = {};
    state.maxPrice = null;
    state.inStock = false;
    await loadProducts();
}

async function loadCategories() {
    const response = await axios.get('/api/categories/tree');
    categories.value = response.data.data || [];
}

async function loadFilters() {
    const response = await axios.get(
        `/api/categories/${state.category}/filters`
    );

    filters.value = response.data.data || [];
}

async function loadProducts() {
    loading.value = true;
    error.value = '';

    try {
        const params = {};

        if (state.category) {
            params.category = state.category;
        }

        for (const [slug, values] of Object.entries(state.values)) {
            if (values.length) {
                params[slug] = values.join(',');
            }
        }

        const response = await axios.get('/api/products', { params });
        products.value = response.data.data || [];
    } catch (requestError) {
        products.value = [];
        error.value = requestError.response?.data?.message || 'دریافت محصولات ناموفق بود.';
    } finally {
        loading.value = false;
    }
}

function productImage(product) {
    const image = product.images?.find((item) => item.is_primary) || product.images?.[0];
    return image?.path ? `/storage/${image.path}` : '';
}

function formatPrice(price) {
    return Number(price || 0).toLocaleString('fa-IR');
}

onMounted(async () => {
    try {
        await loadCategories();
        await loadProducts();
    } catch (requestError) {
        error.value = 'دریافت اطلاعات فروشگاه ناموفق بود.';
    }
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
                        دسته‌بندی و فیلترها به‌صورت پویا از API دریافت می‌شوند.
                    </p>
                </div>

                <label class="flex min-w-64 flex-col gap-2 text-sm font-medium">
                    دسته‌بندی
                    <select v-model="state.category" class="rounded-xl border border-black/15 bg-white px-3 py-2 dark:border-white/20 dark:bg-white/10" @change="selectCategory">
                        <option value="">همه محصولات</option>
                        <option v-for="category in categoryOptions" :key="category.id" :value="category.slug">
                            {{ category.label }}
                        </option>
                    </select>
                </label>
            </header>

            <div class="grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="font-semibold">فیلترها</h2>
                        <button type="button" class="text-xs text-plum underline dark:text-rose" @click="clearFilters">
                            پاک کردن
                        </button>
                    </div>

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

                    <section class="border-t border-black/10 py-5 dark:border-white/10">
                        <label class="flex items-center justify-between gap-3 text-sm">
                            فقط موجود
                            <input v-model="state.inStock" type="checkbox" class="check" />
                        </label>
                    </section>

                    <section class="border-t border-black/10 pt-5 dark:border-white/10">
                        <label class="flex flex-col gap-2 text-sm">
                            ترتیب
                            <select v-model="state.sort" class="rounded-lg border border-black/15 bg-white px-2 py-2 text-xs dark:border-white/20 dark:bg-white/10">
                                <option value="newest">جدیدترین</option>
                                <option value="price-asc">ارزان‌ترین</option>
                                <option value="price-desc">گران‌ترین</option>
                            </select>
                        </label>
                    </section>
                </aside>

                <section>
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-black/60 dark:text-white/60">
                            {{ visibleProducts.length }} محصول
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="filter in activeFilters"
                                :key="`${filter.slug}-${filter.value}`"
                                type="button"
                                class="rounded-full bg-black/5 px-3 py-1 text-xs dark:bg-white/10"
                                @click="removeFilter(filter)"
                            >
                                {{ filter.label }} ×
                            </button>
                        </div>
                    </div>

                    <p v-if="error" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                        {{ error }}
                    </p>

                    <div v-if="loading" class="rounded-2xl border border-dashed border-black/20 p-10 text-center text-sm dark:border-white/20">
                        در حال دریافت محصولات…
                    </div>

                    <div v-else-if="!visibleProducts.length" class="rounded-2xl border border-dashed border-black/20 p-10 text-center text-sm dark:border-white/20">
                        محصولی با این شرایط پیدا نشد.
                    </div>

                    <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <article v-for="product in visibleProducts" :key="product.id" class="overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                            <div class="aspect-square bg-black/5 dark:bg-white/10">
                                <img v-if="productImage(product)" :src="productImage(product)" :alt="product.name" class="h-full w-full object-cover" />
                            </div>
                            <div class="p-4">
                                <h2 class="font-semibold">{{ product.name }}</h2>
                                <p class="mt-2 text-sm text-plum dark:text-rose">{{ formatPrice(product.price) }} تومان</p>
                                <dl v-if="product.attributes" class="mt-3 space-y-1 text-xs text-black/60 dark:text-white/60">
                                    <div v-for="(values, slug) in product.attributes" :key="slug" class="flex gap-2">
                                        <dt>{{ slug }}:</dt>
                                        <dd>{{ values.map((value) => value.label).join('، ') }}</dd>
                                    </div>
                                    <div v-for="attribute in product.custom_attributes || []" :key="attribute.id" class="flex gap-2">
                                        <dt>{{ attribute.name }}:</dt>
                                        <dd>{{ attribute.value }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </main>
</template>
