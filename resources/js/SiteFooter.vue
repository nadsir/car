<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const categories = ref([]);

async function loadCategories() {
    try {
        const { data } = await axios.get('/api/categories/tree');
        categories.value = (data.data || []).slice(0, 6);
    } catch {
        categories.value = [];
    }
}

onMounted(loadCategories);
</script>

<template>
    <footer class="bg-cream border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 py-12 border-b border-gray-200/50">

                <!-- Brand -->
                <div class="space-y-4">
                    <a href="/" class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-brand-accent flex items-center justify-center">
                            <i class="fa-solid fa-bolt-lightning text-ink text-xs"></i>
                        </div>
                        <span class="text-base font-extrabold text-ink">توربو<span class="text-brand-accent">پارت</span></span>
                    </a>
                    <p class="text-xs text-slate-500 leading-6">فروشگاه تخصصی قطعات خودرو با تمرکز بر جستجوی ساده‌تر و انتخاب دقیق‌تر.</p>
                    <div class="flex items-center gap-2">
                        <a href="#" aria-label="Instagram" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-brand-accent hover:text-ink text-slate-500 flex items-center justify-center transition-colors"><i class="fa-brands fa-instagram text-sm"></i></a>
                        <a href="#" aria-label="Telegram" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-brand-accent hover:text-ink text-slate-500 flex items-center justify-center transition-colors"><i class="fa-brands fa-telegram text-sm"></i></a>
                        <a href="#" aria-label="WhatsApp" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-brand-accent hover:text-ink text-slate-500 flex items-center justify-center transition-colors"><i class="fa-brands fa-whatsapp text-sm"></i></a>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="space-y-3">
                    <h4 class="font-bold text-ink text-xs uppercase tracking-wider">دسترسی سریع</h4>
                    <ul class="space-y-2 text-xs">
                        <li><a href="/" class="text-slate-500 hover:text-brand-accent transition-colors">خانه</a></li>
                        <li><a href="/store" class="text-slate-500 hover:text-brand-accent transition-colors">فروشگاه</a></li>
                        <li><a href="/store?in_stock=1" class="text-slate-500 hover:text-brand-accent transition-colors">محصولات موجود</a></li>
                        <li><a href="/store?sort=newest" class="text-slate-500 hover:text-brand-accent transition-colors">جدیدترین محصولات</a></li>
                    </ul>
                </div>

                <!-- Categories -->
                <div class="space-y-3">
                    <h4 class="font-bold text-ink text-xs uppercase tracking-wider">دسته‌بندی محصولات</h4>
                    <ul class="space-y-2 text-xs">
                        <li v-for="cat in categories" :key="cat.slug">
                            <a :href="`/store?category=${encodeURIComponent(cat.slug)}`" class="text-slate-500 hover:text-brand-accent transition-colors">{{ cat.name }}</a>
                        </li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="space-y-3">
                    <h4 class="font-bold text-ink text-xs uppercase tracking-wider">تماس با ما</h4>
                    <div class="space-y-2 text-xs text-slate-500">
                        <p>دفتر مرکزی و انبار تهران</p>
                        <p dir="ltr" class="text-slate-600 font-mono">021-88990000</p>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
                            <i class="fa-solid fa-shield-halved text-brand-accent"></i>
                            <span>ضمانت اصالت</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-slate-500">
                            <i class="fa-solid fa-truck text-brand-accent"></i>
                            <span>ارسال سریع</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-slate-500">
                <p>&copy; ۲۰۲۶ تمامی حقوق مادی و معنوی این وب‌سایت متعلق به توربوپارت می‌باشد.</p>
                <p class="flex items-center gap-1 text-slate-500">
                    طراحی شده برای تجربه بهتر خرید قطعات خودرو
                    <i class="fa-solid fa-heart text-brand-accent text-[10px]"></i>
                </p>
            </div>
        </div>
    </footer>
</template>
