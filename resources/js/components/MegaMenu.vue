<script>
/**
 * Module-scoped tree cache: shared by every MegaMenu instance
 * (desktop + mobile), so /api/categories/tree is fetched exactly once.
 */
import axios from 'axios';
import { reactive } from 'vue';

const shared = reactive({ status: 'idle', categories: [], error: '' });
let loadPromise = null;

async function loadTree() {
    if (shared.status === 'ready') return shared.categories;
    if (loadPromise) return loadPromise;
    shared.status = 'loading';
    loadPromise = (async () => {
        try {
            const { data } = await axios.get('/api/categories/tree');
            shared.categories = data.data || [];
            shared.status = 'ready';
        } catch (err) {
            shared.status = 'error';
            shared.error = err?.response?.data?.message || 'دریافت دسته‌بندی‌ها ناموفق بود.';
        }
    })();
    return loadPromise;
}

function retry() {
    loadPromise = null;
    shared.status = 'loading';
    shared.error = '';
    loadTree();
}
</script>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, onUnmounted, reactive, ref, watchEffect } from 'vue';

defineOptions({ name: 'MegaMenu' });

const props = defineProps({
    variant: { type: String, default: 'desktop' },
});

const status = computed(() => shared.status);
const categories = computed(() => shared.categories);
const errorMessage = computed(() => shared.error);

function categoryHref(slug) {
    return '/c/' + encodeURIComponent(slug);
}

function currentPathSlug() {
    try {
        const match = window.location.pathname.match(/^\/c\/([^/]+)\/?$/);
        return match ? match[1] : null;
    } catch {
        return null;
    }
}

function isCurrentSlug(slug) {
    const current = currentPathSlug();
    return current !== null && decodeURIComponent(current) === slug;
}

onMounted(() => { loadTree(); });

/* ------------------------------------------------------------------ */
/* Recursive sub-trees (desktop columns) — arbitrary depth             */
/* ------------------------------------------------------------------ */
const MenuSubTree = defineComponent({
    name: 'MenuSubTree',
    props: {
        items: { type: Array, default: () => [] },
        depth: { type: Number, default: 0 },
    },
    setup(_props) {
        return () =>
            h(
                'ul',
                {
                    class: [
                        _props.depth > 0 ? 'mt-1.5 space-y-1 pr-3' : 'mt-2 space-y-1.5',
                        'text-[13px] leading-5',
                    ],
                },
                _props.items.map((item) =>
                    h(
                        'li',
                        { key: item.slug },
                        [
                            h(
                                'a',
                                {
                                    href: categoryHref(item.slug),
                                    class: [
                                        'inline-block text-slate-500 hover:text-brand-accent hover:text-ink transition-colors',
                                        _props.depth >= 2 ? 'text-[12px] text-slate-500 hover:text-brand-accent' : 'text-slate-500 hover:text-ink',
                                    ],
                                },
                                item.name
                            ),
                            item.children && item.children.length
                                ? h(MenuSubTree, { items: item.children, depth: _props.depth + 1 })
                                : null,
                        ]
                    )
                )
            );
    },
});

/* ------------------------------------------------------------------ */
/* Recursive accordion (mobile drawer) — arbitrary depth               */
/* ------------------------------------------------------------------ */
const MenuMobileNode = defineComponent({
    name: 'MenuMobileNode',
    props: {
        items: { type: Array, default: () => [] },
        depth: { type: Number, default: 0 },
    },
    setup(_props) {
        const expanded = reactive({});
        return () => {
            _props.items.forEach((item) => {
                if (!(item.slug in expanded)) expanded[item.slug] = _props.depth === 0;
            });
            return h(
                'ul',
                { class: _props.depth > 0 ? 'relative mr-3 mt-1 space-y-0.5 border-r border-gray-200 pr-2.5' : 'space-y-0.5' },
                _props.items.map((item) => {
                    const hasKids = !!(item.children && item.children.length);
                    const open = !!expanded[item.slug];
                    const current = isCurrentSlug(item.slug);
                    return h('li', { key: item.slug }, [
                        h(
                            'div',
                            {
                                class: [
                                    'my-0.5 flex items-center rounded-xl',
                                    current ? 'bg-brand-accent/10 ring-1 ring-brand-accent/25' : '',
                                ],
                            },
                            [
                                h(
                                    'a',
                                    {
                                        href: categoryHref(item.slug),
                                        class: [
                                            'min-w-0 flex-1 rounded-xl px-2.5 py-2.5 text-sm transition-colors',
                                            current ? 'font-bold text-brand-accent' : 'text-slate-600 hover:text-ink',
                                        ],
                                    },
                                    item.name
                                ),
                                hasKids
                                    ? h(
                                          'button',
                                          {
                                              type: 'button',
                                              'aria-expanded': String(open),
                                              'aria-label': open ? 'بستن ' + item.name : 'بازکردن ' + item.name,
                                              class: 'rounded-xl p-2.5 text-slate-400 transition-colors hover:bg-gray-100 hover:text-ink',
                                              onClick: () => { expanded[item.slug] = !expanded[item.slug]; },
                                          },
                                          [
                                              h('i', {
                                                  class: open ? 'fa-solid fa-chevron-up pl-0.5 pe-0.5 text-[10px]' : 'fa-solid fa-chevron-down pe-0.5 text-[10px]',
                                              }),
                                          ]
                                      )
                                    : null,
                            ]
                        ),
                        hasKids
                            ? h(
                                  'div',
                                  {
                                      class: 'grid transition-[grid-template-rows] duration-300 ease-out',
                                      style: { gridTemplateRows: open ? '1fr' : '0fr' },
                                  },
                                  [h('div', { class: 'overflow-hidden' }, [h(MenuMobileNode, { items: item.children, depth: _props.depth + 1 })])]
                              )
                            : null,
                    ]);
                })
            );
        };
    },
});

/* ------------------------------------------------------------------ */
/* Desktop mega menu interaction                                       */
/* ------------------------------------------------------------------ */
const open = ref(false);
const activeIndex = ref(0);
const triggerEl = ref(null);
const railEls = ref([]);
const openedBy = ref(null);
let leaveTimer = null;

const activeCategory = computed(() => categories.value[activeIndex.value] || null);
const activeChildren = computed(() => (activeCategory.value ? activeCategory.value.children || [] : []));
const activeImage = computed(() => {
    const image = activeCategory.value?.image;
    if (!image) return '';
    if (image.startsWith('http') || image.startsWith('/')) return image;
    return '/storage/' + image;
});

const isSingleRoot = computed(() => categories.value.length === 1);

function openMenu(restoreFocus = false, source = 'click') {
    clearTimeout(leaveTimer);
    if (!categories.value.length && shared.status !== 'loading') loadTree();
    openedBy.value = source;
    open.value = true;
    if (restoreFocus) {
        nextTick(() => railEls.value[activeIndex.value]?.focus());
    }
}

function closeMenu() {
    clearTimeout(leaveTimer);
    open.value = false;
    openedBy.value = null;
}

function toggleMenu() {
    if (open.value) {
        if (openedBy.value !== 'hover') closeMenu();
    } else {
        openMenu(true, 'click');
    }
}

function onTriggerKeydown(e) {
    if (e.key === 'ArrowDown' && !open.value) {
        e.preventDefault();
        openMenu(true);
    } else if (e.key === 'Escape') {
        closeMenu();
    }
}

function focusRail(index) {
    activeIndex.value = index;
    nextTick(() => railEls.value[index]?.focus());
}

function onRailKeydown(e, index) {
    const count = categories.value.length;
    if (e.key === 'ArrowDown') { e.preventDefault(); focusRail((index + 1) % count); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); focusRail((index - 1 + count) % count); }
    else if (e.key === 'Home') { e.preventDefault(); focusRail(0); }
    else if (e.key === 'End') { e.preventDefault(); focusRail(count - 1); }
    else if (e.key === 'Escape') { closeMenu(); triggerEl.value?.focus(); }
}

function onPanelKeydown(e) {
    if (e.key === 'Escape') {
        closeMenu();
        triggerEl.value?.focus();
    }
}

function onWindowKeydown(e) {
    if (open.value && e.key === 'Escape') {
        closeMenu();
        triggerEl.value?.focus();
    }
}

function onScrollOrResize(e) {
    if (!open.value) return;
    if (e.type === 'scroll') {
        const panel = document.getElementById('category-mega-panel');
        if (panel && panel.contains(e.target)) return;
    }
    closeMenu();
}

function onHoverEnter() {
    clearTimeout(leaveTimer);
    openMenu(false, 'hover');
}

function onHoverLeave() {
    clearTimeout(leaveTimer);
    leaveTimer = setTimeout(() => closeMenu(), 160);
}

function onRailActivate() {
    if (!activeCategory.value) return;
    if (!activeCategory.value.children || !activeCategory.value.children.length) {
        window.location.href = categoryHref(activeCategory.value.slug);
    }
}
let unwatchCat = null;
onMounted(() => {
    unwatchCat = watchEffect(() => {
        const list = categories.value;
        if (!list.length) return;
        const idx = list.findIndex((c) => c.slug === currentPathSlug());
        activeIndex.value = idx >= 0 ? idx : 0;
    });
    window.addEventListener('keydown', onWindowKeydown);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
});
onUnmounted(() => {
    unwatchCat?.();
    window.removeEventListener('keydown', onWindowKeydown);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
    clearTimeout(leaveTimer);
});
</script>

<template>
    <!-- ================= DESKTOP ================= -->
    <div v-if="variant === 'desktop'" class="relative" @mouseenter="onHoverEnter" @mouseleave="onHoverLeave">
        <button
            ref="triggerEl"
            id="category-mega-trigger"
            type="button"
            aria-haspopup="dialog"
            :aria-expanded="open"
            aria-controls="category-mega-panel"
            class="relative z-50 flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-ink transition-all hover:border-brand-accent/60 hover:text-brand-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50"
            @click="toggleMenu"
            @keydown="onTriggerKeydown"
        >
            <i class="fa-solid fa-layer-group text-brand-accent"></i>
            <span>دسته‌بندی قطعات</span>
            <i
                :class="[
                    open ? 'fa-solid fa-chevron-up text-[9px] text-brand-accent' : 'fa-solid fa-chevron-down text-[9px] text-slate-400 transition-colors',
                ]"
            ></i>
        </button>

        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-40 bg-ink/20" @click="closeMenu"></div>
        </Transition>

        <Transition name="mega">
            <div
                v-if="open"
                id="category-mega-panel"
                ref="panelEl"
                role="dialog"
                aria-label="منوی دسته‌بندی قطعات"
                class="absolute right-0 top-full z-50 mt-0.5 w-[min(94vw,72rem)] overflow-hidden rounded-2xl border border-gray-200 border-t-2 border-t-brand-accent bg-white shadow-[0_32px_64px_-32px_rgba(9,9,17,0.4)]"
                @keydown="onPanelKeydown"
            >
                <!-- loading skeleton -->
                <div v-if="status === 'loading'" class="flex gap-6 p-6">
                    <div class="w-64 max-h-[60vh] space-y-2.5">
                        <div v-for="n in 6" :key="n" class="h-9 animate-pulse rounded-lg bg-gray-100"></div>
                    </div>
                    <div class="flex-1 space-y-3">
                        <div class="h-5 w-1/3 animate-pulse rounded bg-gray-100"></div>
                        <div class="grid grid-cols-3 gap-6">
                            <div v-for="n in 6" :key="n" class="space-y-2">
                                <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100"></div>
                                <div class="h-3 w-full animate-pulse rounded bg-gray-50"></div>
                                <div class="h-3 w-3/4 animate-pulse rounded bg-gray-50"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- error -->
                <div v-else-if="status === 'error'" class="flex items-center justify-between gap-4 p-6">
                    <p class="text-sm text-slate-500">{{ errorMessage }}</p>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-bold text-brand-accent transition-colors hover:border-brand-accent/50 hover:bg-brand-accent/5"
                        @click="retry"
                    >
                        تلاش مجدد
                    </button>
                </div>

                <!-- empty -->
                <div v-else-if="!categories.length" class="p-6 text-sm text-slate-400">
                    دسته‌ای یافت نشد.
                </div>

                <!-- ready -->
                <div v-else class="flex">
                    <!-- right: children of selected category -->
                    <div class="min-w-0 flex-1 border-l border-gray-100 p-6" :class="{ 'border-l-0': isSingleRoot }">
                        <div class="mb-5 flex items-center justify-between gap-4 border-b border-gray-200/80 pb-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <img v-if="activeImage" :src="activeImage" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-gray-200" />
                                <div class="min-w-0">
                                    <h4 class="truncate text-lg font-black text-ink">{{ activeCategory.name }}</h4>
                                    <p v-if="activeCategory.description" class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ activeCategory.description }}</p>
                                </div>
                            </div>
                            <a
                                :href="categoryHref(activeCategory.slug)"
                                class="group/sa flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold text-brand-accent transition-colors hover:bg-brand-accent/5"
                            >
                                مشاهده همه
                                <i class="fa-solid fa-chevron-left text-[9px] transition-transform group-hover/sa:-translate-x-0.5"></i>
                            </a>
                        </div>

                        <div class="max-h-[calc(70vh-8rem)] overflow-y-auto pl-1 pr-1 columns-2 2xl:columns-3" dir="rtl">
                            <div v-for="group in activeChildren" :key="group.slug" class="mb-7 break-inside-avoid">
                                <a
                                    :href="categoryHref(group.slug)"
                                    class="group/gh inline-flex items-center gap-1.5 text-sm font-bold text-ink transition-colors hover:text-brand-accent"
                                >
                                    <span>{{ group.name }}</span>
                                    <i class="fa-solid fa-minus text-[8px] text-brand-accent opacity-0 transition-opacity group-hover/gh:opacity-100"></i>
                                </a>
                                <MenuSubTree v-if="group.children && group.children.length" :items="group.children" :depth="1" />
                            </div>
                        </div>
                    </div>

                    <!-- left: root categories rail -->
                    <nav v-if="!isSingleRoot" class="w-72 shrink-0 bg-gray-50/70 py-3" aria-label="دسته‌های اصلی">
                        <ul class="max-h-[calc(70vh-3rem)] space-y-0.5 overflow-y-auto px-2">
                            <li v-for="(cat, index) in categories" :key="cat.slug">
                                <button
                                    :ref="(el) => { railEls[index] = el; }"
                                    type="button"
                                    :tabindex="index === activeIndex ? 0 : -1"
                                    :aria-current="activeIndex === index ? 'true' : undefined"
                                    class="group flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/50"
                                    :class="index === activeIndex
                                        ? 'bg-brand-accent/10 font-bold text-brand-accent ring-1 ring-brand-accent/20'
                                        : 'text-slate-600 hover:bg-white hover:text-ink'"
                                    @mouseenter="activeIndex = index"
                                    @click="activeIndex = index; onRailActivate()"
                                    @keydown="onRailKeydown($event, index)"
                                >
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <img
                                            v-if="cat.image"
                                            :src="cat.image.startsWith('http') || cat.image.startsWith('/') ? cat.image : '/storage/' + cat.image"
                                            alt=""
                                            class="h-7 w-7 shrink-0 rounded-md object-cover ring-1 ring-gray-200"
                                        />
                                        <span class="truncate">{{ cat.name }}</span>
                                    </span>
                                    <i
                                        v-if="cat.children && cat.children.length"
                                        class="fa-solid fa-chevron-left text-[9px] text-slate-300 transition-colors group-hover:text-brand-accent"
                                        :class="index === activeIndex ? 'text-brand-accent' : ''"
                                    ></i>
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </Transition>
    </div>

    <!-- ================= MOBILE ================= -->
    <div v-else class="text-sm">
        <div v-if="status === 'loading'" class="space-y-2 py-1.5">
            <div v-for="n in 5" :key="n" class="h-10 animate-pulse rounded-lg bg-gray-100"></div>
        </div>

        <div v-else-if="status === 'error'" class="flex items-center justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2.5">
            <p class="text-xs text-slate-500">{{ errorMessage }}</p>
            <button type="button" class="shrink-0 text-xs font-bold text-brand-accent hover:text-brand-hover" @click="retry">
                تلاش مجدد
            </button>
        </div>

        <p v-else-if="!categories.length" class="px-2 py-3 text-xs text-slate-400">
            دسته‌ای یافت نشد.
        </p>

        <MenuMobileNode v-else :items="categories" :depth="0" />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.mega-enter-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.mega-leave-active {
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.mega-enter-from,
.mega-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>