```vue
<script setup>
import {
    ref,
    computed,
    onMounted,
} from 'vue';

import {
    products,
    categories,
    adminCategories,
    attributes,
    categoryAttributes,
    loading,
    saving,
    editingProductId,
    form,
    load,
    loadProduct,
    resetForm,
    addVariant,
    removeVariant,
    save,
    updateProduct,
    removeProduct,
    uploadProductImage,
    removeProductImage,
    setPrimaryProductImage,
    reorderProductImages,
    adminUser,
    isAuthenticated,
    login,
    logout,
    restoreAdminSession,
    loadCategoryAttributeConfig,
    saveCategoryAttributeConfig,
    createCategory,
    updateCategory,
    removeCategory,
    createAttribute,
    updateAttribute,
    removeAttribute,
    createAttributeValue,
    updateAttributeValue,
    removeAttributeValue
} from './admin-state';


async function openEditProduct(id) {
    try {
        await loadProduct(id);

        showProductModal.value = true;
    } catch (error) {
        console.error('Failed to load product:', error);

        alert('اطلاعات محصول دریافت نشد.');
    }
}
/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const section = ref('dashboard');

const showProductModal = ref(false);

const errorMessage = ref('');

const successMessage = ref('');

const loginEmail = ref('');
const loginPassword = ref('');
const loginLoading = ref(false);
const loginError = ref('');
const configuredCategoryId = ref(null);
const categoryAttributeConfig = ref([]);
const categoryAttributeLoading = ref(false);
const categoryAttributeSaving = ref(false);
const categoryEditingId = ref(null);
const categorySaving = ref(false);
const categoryForm = ref(createEmptyCategoryForm());
const attributeEditingId = ref(null);
const attributeSaving = ref(false);
const attributeForm = ref(createEmptyAttributeForm());
const attributeValueEditingId = ref(null);
const attributeValueSaving = ref(false);
const attributeValueForm = ref(createEmptyAttributeValueForm());

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const nav = [
    {
        key: 'dashboard',
        label: 'داشبورد',
        icon: '▦',
    },
    {
        key: 'products',
        label: 'محصولات',
        icon: '◈',
    },
    {
        key: 'categories',
        label: 'دسته‌بندی‌ها',
        icon: '◫',
    },
    {
        key: 'attributes',
        label: 'ویژگی‌ها',
        icon: '◇',
    },
    {
        key: 'users',
        label: 'کاربران',
        icon: '◎',
    },
    {
        key: 'settings',
        label: 'تنظیمات',
        icon: '⚙',
    },
];

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const currentSection = computed(() => {
    return nav.find(
        item => item.key === section.value
    );
});

const selectedAttributes = computed(() => {
    const map = new Map();

    for (const categoryId of form.value.category_ids) {
        const list =
            categoryAttributes.value[categoryId] || [];

        for (const attribute of list) {
            if (!map.has(attribute.id)) {
                map.set(attribute.id, attribute);
            }
        }
    }

    return Array.from(map.values()).sort(
        (a, b) =>
            (a.sort_order ?? 0) -
            (b.sort_order ?? 0)
    );
});

const variantAttributes = computed(() => {
    return selectedAttributes.value.filter(
        attribute =>
            attribute.values?.length &&
            attribute.pivot?.is_variant_axis
    );
});

const stockProducts = computed(() => {
    return products.value.filter(
        product => product.in_stock
    ).length;
});

const outOfStockProducts = computed(() => {
    return products.value.length -
        stockProducts.value;
});

const categoryConfigurationOptions = computed(() => {
    const result = [];

    const visit = (category, depth = 0) => {
        result.push({ ...category, depth });

        for (const child of category.children || []) {
            visit(child, depth + 1);
        }
    };

    for (const category of adminCategories.value) {
        visit(category);
    }

    return result;
});

const configuredCategory = computed(() => {
    return categoryConfigurationOptions.value.find(
        category => category.id === configuredCategoryId.value
    );
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatPrice(value) {
    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {
        return '۰';
    }

    return Number(value).toLocaleString(
        'fa-IR'
    );
}

function categoryName(categoryId) {
    for (const category of categories.value) {
        if (category.id === categoryId) {
            return category.name;
        }

        for (const child of category.children || []) {
            if (child.id === categoryId) {
                return child.name;
            }
        }
    }

    return '';
}

function selectedCategoryNames() {
    return form.value.category_ids
        .map(categoryName)
        .filter(Boolean);
}

function categoryAttributeConfigIndex(attributeId) {
    return categoryAttributeConfig.value.findIndex(
        item => item.attribute_id === attributeId
    );
}

function createEmptyCategoryForm() {
    return {
        name: '',
        slug: '',
        parent_id: null,
        description: '',
        image: '',
        is_active: true,
        sort_order: 0,
    };
}

function createEmptyAttributeForm() {
    return {
        name: '',
        slug: '',
        type: 'select',
        sort_order: 0,
    };
}

function createEmptyAttributeValueForm() {
    return {
        label: '',
        value: '',
        hex_color: '',
        sort_order: 0,
    };
}

function isOptionAttribute(attribute) {
    return ['select', 'multiselect', 'color'].includes(attribute.type);
}

function openCreateAttribute() {
    attributeEditingId.value = null;
    attributeForm.value = createEmptyAttributeForm();
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
    errorMessage.value = '';
}

function openEditAttribute(attribute) {
    attributeEditingId.value = attribute.id;
    attributeForm.value = {
        name: attribute.name || '',
        slug: attribute.slug || '',
        type: attribute.type || 'select',
        sort_order: attribute.sort_order ?? 0,
    };
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
    errorMessage.value = '';
}

function currentEditingAttribute() {
    return attributes.value.find(
        attribute => attribute.id === attributeEditingId.value
    );
}

async function submitAttribute() {
    attributeSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const attribute = attributeEditingId.value
            ? await updateAttribute(attributeEditingId.value, attributeForm.value)
            : await createAttribute(attributeForm.value);

        await load();
        openEditAttribute(
            attributes.value.find(item => item.id === attribute.id) || attribute
        );
        successMessage.value = 'ویژگی ذخیره شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره ویژگی انجام نشد.';
    } finally {
        attributeSaving.value = false;
    }
}

async function deleteAttribute(attribute) {
    if (!confirm(`ویژگی «${attribute.name}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeAttribute(attribute.id);
        await load();

        if (attributeEditingId.value === attribute.id) {
            openCreateAttribute();
        }

        successMessage.value = 'ویژگی حذف شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف ویژگی انجام نشد.';
    }
}

function openEditAttributeValue(value) {
    attributeValueEditingId.value = value.id;
    attributeValueForm.value = {
        label: value.label || '',
        value: value.value || '',
        hex_color: value.hex_color || '',
        sort_order: value.sort_order ?? 0,
    };
}

function resetAttributeValueForm() {
    attributeValueEditingId.value = null;
    attributeValueForm.value = createEmptyAttributeValueForm();
}

async function submitAttributeValue() {
    const attribute = currentEditingAttribute();

    if (!attribute) {
        return;
    }

    attributeValueSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        if (attributeValueEditingId.value) {
            await updateAttributeValue(
                attribute.id,
                attributeValueEditingId.value,
                attributeValueForm.value
            );
        } else {
            await createAttributeValue(attribute.id, attributeValueForm.value);
        }

        await load();
        openEditAttribute(attributes.value.find(item => item.id === attribute.id) || attribute);
        successMessage.value = 'مقدار ویژگی ذخیره شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره مقدار ویژگی انجام نشد.';
    } finally {
        attributeValueSaving.value = false;
    }
}

async function deleteAttributeValue(value) {
    const attribute = currentEditingAttribute();

    if (!attribute || !confirm(`مقدار «${value.label}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeAttributeValue(attribute.id, value.id);
        await load();
        openEditAttribute(attributes.value.find(item => item.id === attribute.id) || attribute);
        successMessage.value = 'مقدار ویژگی حذف شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف مقدار ویژگی انجام نشد.';
    }
}

function openCreateCategory() {
    categoryEditingId.value = null;
    categoryForm.value = createEmptyCategoryForm();
    errorMessage.value = '';
}

function openEditCategory(category) {
    categoryEditingId.value = category.id;
    categoryForm.value = {
        name: category.name || '',
        slug: category.slug || '',
        parent_id: category.parent_id || null,
        description: category.description || '',
        image: category.image || '',
        is_active: Boolean(category.is_active),
        sort_order: category.sort_order ?? 0,
    };
    errorMessage.value = '';
}

async function submitCategory() {
    categorySaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const data = {
            ...categoryForm.value,
            parent_id: categoryForm.value.parent_id || null,
        };

        if (categoryEditingId.value) {
            await updateCategory(categoryEditingId.value, data);
        } else {
            await createCategory(data);
        }

        await load();
        openCreateCategory();
        successMessage.value = 'دسته‌بندی ذخیره شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'ذخیره دسته‌بندی انجام نشد.';
    } finally {
        categorySaving.value = false;
    }
}

async function deleteCategory(category) {
    if (!confirm(`دسته‌بندی «${category.name}» حذف شود؟`)) {
        return;
    }

    errorMessage.value = '';
    successMessage.value = '';

    try {
        await removeCategory(category.id);
        await load();

        if (categoryEditingId.value === category.id) {
            openCreateCategory();
        }

        successMessage.value = 'دسته‌بندی حذف شد.';
    } catch (error) {
        const errors = error.response?.data?.errors;
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : 'حذف دسته‌بندی انجام نشد.';
    }
}

function isCategoryAttributeConfigured(attributeId) {
    return categoryAttributeConfigIndex(attributeId) !== -1;
}

function categoryAttributeConfiguration(attributeId) {
    return categoryAttributeConfig.value[
        categoryAttributeConfigIndex(attributeId)
    ];
}

function setCategoryAttributeConfigured(attribute, enabled) {
    const index = categoryAttributeConfigIndex(attribute.id);

    if (!enabled && index !== -1) {
        categoryAttributeConfig.value.splice(index, 1);
        return;
    }

    if (enabled && index === -1) {
        categoryAttributeConfig.value.push({
            attribute_id: attribute.id,
            is_required: false,
            is_filterable: false,
            is_variant_axis: false,
            sort_order: categoryAttributeConfig.value.length,
        });
    }
}

function updateCategoryAttributeConfig(attributeId, field, value) {
    const index = categoryAttributeConfigIndex(attributeId);

    if (index === -1) {
        return;
    }

    categoryAttributeConfig.value[index][field] = value;
}

function supportsVariantAxis(attribute) {
    return ['select', 'multiselect', 'color'].includes(attribute.type);
}

async function selectCategoryForAttributes(categoryId) {
    configuredCategoryId.value = Number(categoryId) || null;
    categoryAttributeConfig.value = [];

    if (!configuredCategoryId.value) {
        return;
    }

    categoryAttributeLoading.value = true;
    errorMessage.value = '';

    try {
        const configurations = await loadCategoryAttributeConfig(
            configuredCategoryId.value
        );

        categoryAttributeConfig.value = configurations.map(attribute => ({
            attribute_id: attribute.id,
            is_required: Boolean(attribute.pivot?.is_required),
            is_filterable: Boolean(attribute.pivot?.is_filterable),
            is_variant_axis: Boolean(attribute.pivot?.is_variant_axis),
            sort_order: attribute.pivot?.sort_order ?? 0,
        }));
    } catch (error) {
        errorMessage.value = 'تنظیمات ویژگی‌های دسته دریافت نشد.';
    } finally {
        categoryAttributeLoading.value = false;
    }
}

async function saveCategoryAttributes() {
    if (!configuredCategoryId.value) {
        return;
    }

    categoryAttributeSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const configurations = await saveCategoryAttributeConfig(
            configuredCategoryId.value,
            categoryAttributeConfig.value
        );

        categoryAttributeConfig.value = configurations.map(attribute => ({
            attribute_id: attribute.id,
            is_required: Boolean(attribute.pivot?.is_required),
            is_filterable: Boolean(attribute.pivot?.is_filterable),
            is_variant_axis: Boolean(attribute.pivot?.is_variant_axis),
            sort_order: attribute.pivot?.sort_order ?? 0,
        }));
        successMessage.value = 'تنظیمات ویژگی‌های دسته ذخیره شد.';
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'ذخیره تنظیمات انجام نشد.';
    } finally {
        categoryAttributeSaving.value = false;
    }
}

function attributeSelected(attributeId) {
    return form.value.attribute_value_ids
        .some(id => {
            const attribute =
                attributes.value.find(
                    item => item.id === attributeId
                );

            return attribute?.values?.some(
                value => value.id === id
            );
        });
}

function isValueSelected(valueId) {
    return form.value.attribute_value_ids.includes(
        valueId
    );
}

function toggleAttributeValue(valueId) {
    const index =
        form.value.attribute_value_ids.indexOf(
            valueId
        );

    if (index === -1) {
        form.value.attribute_value_ids.push(
            valueId
        );
    } else {
        form.value.attribute_value_ids.splice(
            index,
            1
        );
    }
}

function isCustomAttribute(attribute) {
    return ['number', 'boolean', 'text'].includes(
        attribute.type
    );
}

function customAttributeIndex(attributeId) {
    return form.value.custom_attribute_values.findIndex(
        item => item.attribute_id === attributeId
    );
}

function customAttributeValue(attribute) {
    const index = customAttributeIndex(attribute.id);

    if (index === -1) {
        return attribute.type === 'boolean' ? false : '';
    }

    return form.value.custom_attribute_values[index].value;
}

function setCustomAttributeValue(attribute, value) {
    const index = customAttributeIndex(attribute.id);

    if (
        value === '' ||
        value === null ||
        value === undefined
    ) {
        if (index !== -1) {
            form.value.custom_attribute_values.splice(index, 1);
        }

        return;
    }

    if (index === -1) {
        form.value.custom_attribute_values.push({
            attribute_id: attribute.id,
            value,
        });

        return;
    }

    form.value.custom_attribute_values[index].value = value;
}

function isVariantValueSelected(
    variant,
    valueId
) {
    return variant.attribute_value_ids.includes(
        valueId
    );
}

function toggleVariantValue(
    variant,
    valueId
) {
    const index =
        variant.attribute_value_ids.indexOf(
            valueId
        );

    if (index === -1) {
        variant.attribute_value_ids.push(
            valueId
        );
    } else {
        variant.attribute_value_ids.splice(
            index,
            1
        );
    }
}
const selectedImageFiles = ref([]);
const imagePreviews = ref([]);
const imageAltText = ref('');
const imageUploading = ref(false);
const draggingImageId = ref(null);

function startImageDrag(imageId) {
    draggingImageId.value = imageId;
}

async function dropImage(targetImageId) {
    if (
        !draggingImageId.value ||
        draggingImageId.value === targetImageId
    ) {
        draggingImageId.value = null;
        return;
    }

    const images = [...(form.value.images || [])];

    const fromIndex = images.findIndex(
        image => image.id === draggingImageId.value
    );

    const toIndex = images.findIndex(
        image => image.id === targetImageId
    );

    if (fromIndex === -1 || toIndex === -1) {
        draggingImageId.value = null;
        return;
    }

    const [movedImage] = images.splice(
        fromIndex,
        1
    );

    images.splice(
        toIndex,
        0,
        movedImage
    );

    form.value.images = images.map(
        (image, index) => ({
            ...image,
            sort_order: index + 1,
        })
    );

    const imageIds = form.value.images.map(
        image => image.id
    );

    draggingImageId.value = null;

    try {
        await reorderProductImages(
            editingProductId.value,
            imageIds
        );
    } catch (error) {
        console.error(
            'Failed to reorder images:',
            error
        );

        alert('مرتب‌سازی تصاویر انجام نشد.');

        await loadProduct(
            editingProductId.value
        );
    }
}

function onImagesSelected(event) {
    const files = Array.from(
        event.target.files || []
    );

    if (!files.length) {
        return;
    }

    const validFiles = files.filter(file => {
        const validType = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ].includes(file.type);

        const validSize =
            file.size <= 5 * 1024 * 1024;

        return validType && validSize;
    });

    selectedImageFiles.value = [
        ...selectedImageFiles.value,
        ...validFiles,
    ];

    imagePreviews.value = [
        ...imagePreviews.value,
        ...validFiles.map(file => ({
            file,
            url: URL.createObjectURL(file),
        })),
    ];

    event.target.value = '';
}
function clearSelectedImages() {
    imagePreviews.value.forEach(
        preview => {
            if (preview.url) {
                URL.revokeObjectURL(
                    preview.url
                );
            }
        }
    );

    selectedImageFiles.value = [];
    imagePreviews.value = [];
    imageAltText.value = '';
}
function removeSelectedImage(index) {
    const preview =
        imagePreviews.value[index];

    if (preview?.url) {
        URL.revokeObjectURL(preview.url);
    }

    selectedImageFiles.value.splice(
        index,
        1
    );

    imagePreviews.value.splice(
        index,
        1
    );
}


async function deleteImage(imageId) {
    if (!editingProductId.value || !imageId) {
        return;
    }

    if (!confirm('این تصویر حذف شود؟')) {
        return;
    }

    try {
        await removeProductImage(
            editingProductId.value,
            imageId
        );
    } catch (error) {
        console.error(
            'Failed to delete image:',
            error
        );

        alert('حذف تصویر انجام نشد.');
    }
}

function imageUrl(image) {
    if (!image?.path) {
        return '';
    }

    return `/storage/${image.path}`;
}

async function setPrimaryImage(imageId) {
    if (!editingProductId.value || !imageId) {
        return;
    }

    try {
        await setPrimaryProductImage(
            editingProductId.value,
            imageId
        );
    } catch (error) {
        console.error(
            'Failed to set primary image:',
            error
        );

        alert('تعیین تصویر اصلی انجام نشد.');
    }
}
function openProductModal() {
    resetForm();

    errorMessage.value = '';
    successMessage.value = '';

    showProductModal.value = true;
}

function closeProductModal() {
    if (saving.value) {
        return;
    }

    showProductModal.value = false;
}

function changeSection(value) {
    section.value = value;
}

async function submitProduct() {
    errorMessage.value = '';
    successMessage.value = '';

    try {
        let product;

        if (editingProductId.value) {
            product = await updateProduct();
        } else {
            product = await save();

            const productId = product?.id;

            if (
                productId &&
                selectedImageFiles.value.length
            ) {
                imageUploading.value = true;

                for (
                    const file of selectedImageFiles.value
                ) {
                    await uploadProductImage(
                        productId,
                        file,
                        imageAltText.value
                    );
                }

                imageUploading.value = false;
            }
        }

        successMessage.value =
            'محصول با موفقیت ذخیره شد.';

        clearSelectedImages();

        resetForm();

        showProductModal.value = false;

    } catch (error) {
        imageUploading.value = false;

        console.error(
            'Failed to save product:',
            error
        );

        errorMessage.value =
            error.response?.data?.message ||
            'ذخیره محصول انجام نشد.';
    }
}

async function deleteProduct(id) {
    try {
        await removeProduct(id);
    } catch (error) {
        alert(
            error.response?.data?.message ||
            'حذف محصول انجام نشد.'
        );
    }
}

async function submitLogin() {
    loginLoading.value = true;
    loginError.value = '';

    try {
        await login({
            email: loginEmail.value,
            password: loginPassword.value,
        });
        await load();
    } catch (error) {
        loginError.value =
            error.response?.data?.errors?.email?.[0] ||
            'ورود به پنل مدیریت ناموفق بود.';
    } finally {
        loginLoading.value = false;
    }
}

async function signOut() {
    await logout();
    resetForm();
    errorMessage.value = '';
    successMessage.value = '';
}

onMounted(async () => {
    const restored = await restoreAdminSession();

    if (!restored) {
        return;
    }

    try {
        await load();
    } catch (error) {
        console.error(error);

        errorMessage.value =
            'دریافت اطلاعات پنل مدیریت انجام نشد.';
    }
});
</script>

<template>
    <div
        v-if="!isAuthenticated"
        dir="rtl"
        class="admin-login"
    >
        <form
            class="login-card"
            @submit.prevent="submitLogin"
        >
            <p class="section-label">CAR ADMIN</p>
            <h1>ورود مدیر</h1>
            <p>برای مدیریت محصولات و دسته‌بندی‌ها وارد شوید.</p>

            <label>
                ایمیل
                <input
                    v-model="loginEmail"
                    type="email"
                    autocomplete="email"
                    required
                />
            </label>

            <label>
                رمز عبور
                <input
                    v-model="loginPassword"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </label>

            <p
                v-if="loginError"
                class="login-error"
            >
                {{ loginError }}
            </p>

            <button
                type="submit"
                :disabled="loginLoading"
            >
                {{ loginLoading ? 'در حال ورود…' : 'ورود به پنل' }}
            </button>
        </form>
    </div>

    <div
        v-else
        dir="rtl"
        class="admin-shell"
    >
        <!-- ================================================= -->
        <!-- SIDEBAR -->
        <!-- ================================================= -->

        <aside class="sidebar">
            <div class="brand">
                ROYA
                <span>ADMIN</span>
            </div>

            <nav class="sidebar-nav">
                <button
                    v-for="item in nav"
                    :key="item.key"
                    type="button"
                    class="nav-item"
                    :class="{
                        active:
                            section === item.key
                    }"
                    @click="
                        changeSection(item.key)
                    "
                >
                    <b>{{ item.icon }}</b>

                    <span>
                        {{ item.label }}
                    </span>
                </button>
            </nav>

            <div class="side-foot">
                پنل مدیریت فروشگاه

                <small>
                    ROYA ADMIN · v1.0
                </small>
            </div>
        </aside>

        <!-- ================================================= -->
        <!-- MAIN -->
        <!-- ================================================= -->

        <main class="main">
            <!-- HEADER -->
  <div
        v-if="successMessage"
        class="alert success"
    >
        {{ successMessage }}
    </div>
            <header class="topbar">
                <div>
                    <p class="eyebrow">
                        مدیریت فروشگاه
                    </p>

                    <h1>
                        {{
                            currentSection?.label
                        }}
                    </h1>
                </div>

                <div class="admin-user">
                    <span>{{ adminUser?.name || 'مدیر' }}</span>

                    <button
                        type="button"
                        class="logout-button"
                        @click="signOut"
                    >
                        خروج
                    </button>
                </div>
            </header>

            <!-- GLOBAL ERROR -->

            <div
                v-if="errorMessage && !showProductModal"
                class="alert error"
            >
                {{ errorMessage }}
            </div>

            <!-- ================================================= -->
            <!-- LOADING -->
            <!-- ================================================= -->

            <div
                v-if="loading"
                class="loading"
            >
                <div class="spinner"></div>

                <span>
                    در حال بارگذاری اطلاعات...
                </span>
            </div>

            <template v-else>
                <!-- ================================================= -->
                <!-- DASHBOARD -->
                <!-- ================================================= -->

                <section
                    v-if="section === 'dashboard'"
                >
                    <div class="cards">
                        <div class="stat">
                            <span>
                                کل محصولات
                            </span>

                            <strong>
                                {{
                                    products.length
                                }}
                            </strong>

                            <i>◈</i>
                        </div>

                        <div class="stat">
                            <span>
                                دسته‌بندی‌ها
                            </span>

                            <strong>
                                {{
                                    categories.length
                                }}
                            </strong>

                            <i>◫</i>
                        </div>

                        <div class="stat">
                            <span>
                                موجود در انبار
                            </span>

                            <strong>
                                {{
                                    stockProducts
                                }}
                            </strong>

                            <i>✓</i>
                        </div>

                        <div class="stat">
                            <span>
                                ناموجود
                            </span>

                            <strong>
                                {{
                                    outOfStockProducts
                                }}
                            </strong>

                            <i>!</i>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    آخرین محصولات
                                </p>

                                <h2>
                                    محصولات اخیر
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="text-button"
                                @click="
                                    section =
                                        'products'
                                "
                            >
                                مشاهده همه
                            </button>
                        </div>

                        <div
                            v-if="
                                products.length
                            "
                            class="rows"
                        >
                            <div
                                v-for="product in products.slice(
                                    0,
                                    6
                                )"
                                :key="product.id"
                                class="product-row"
                            >
                                <div class="product-thumb">
                                    {{
                                        product.name?.charAt(
                                            0
                                        )
                                    }}
                                </div>

                                <div class="grow">
                                    <b>
                                        {{
                                            product.name
                                        }}
                                    </b>

                                    <small>
                                        {{
                                            product.slug
                                        }}
                                    </small>
                                </div>

                                <strong>
                                    {{
                                        formatPrice(
                                            product.price
                                        )
                                    }}
                                    تومان
                                </strong>

                                <span
                                    class="status"
                                    :class="
                                        product.in_stock
                                            ? 'ok'
                                            : 'bad'
                                    "
                                >
                                    {{
                                        product.in_stock
                                            ? 'موجود'
                                            : 'ناموجود'
                                    }}
                                </span>
<div class="product-actions">

    <button
        class="btn-edit"
        @click="openEditProduct(product.id)"
    >
        ویرایش
    </button>

    <button
        class="btn-delete"
        @click="removeProduct(product.id)"
    >
        حذف
    </button>

</div>

                            </div>
                        </div>

                        <div
                            v-else
                            class="empty"
                        >
                            هنوز محصولی ثبت نشده است.
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- PRODUCTS -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'products'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    کاتالوگ فروشگاه
                                </p>

                                <h2>
                                    مدیریت محصولات
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="
                                    openProductModal()
                                "
                            >
                                + افزودن محصول
                            </button>
                        </div>

                        <div
                            v-if="
                                products.length
                            "
                            class="products-table"
                        >
                            <div
                                v-for="product in products"
                                :key="product.id"
                                class="product-row"
                            >
                                <div class="product-thumb">
                                    {{
                                        product.name?.charAt(
                                            0
                                        )
                                    }}
                                </div>

                                <div class="grow">
                                    <b>
                                        {{
                                            product.name
                                        }}
                                    </b>

                                    <small>
                                        {{
                                            product.slug
                                        }}
                                    </small>
                                </div>

                                <strong class="price">
                                    {{
                                        formatPrice(
                                            product.price
                                        )
                                    }}
                                    تومان
                                </strong>

                                <span
                                    class="status"
                                    :class="
                                        product.in_stock
                                            ? 'ok'
                                            : 'bad'
                                    "
                                >
                                    {{
                                        product.in_stock
                                            ? 'فعال'
                                            : 'ناموجود'
                                    }}
                                </span>

                                <button
                                    type="button"
                                    class="delete-button"
                                    @click="
                                        deleteProduct(
                                            product.id
                                        )
                                    "
                                >
                                    حذف
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="empty"
                        >
                            <div class="empty-icon">
                                ◈
                            </div>

                            <h3>
                                هنوز محصولی ندارید
                            </h3>

                            <p>
                                اولین محصول فروشگاه
                                ROYA را اضافه کنید.
                            </p>

                            <button
                                type="button"
                                class="primary"
                                @click="
                                    openProductModal()
                                "
                            >
                                افزودن اولین محصول
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- CATEGORIES -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'categories'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    ساختار فروشگاه
                                </p>

                                <h2>
                                    دسته‌بندی‌ها
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="openCreateCategory"
                            >
                                + دسته جدید
                            </button>
                        </div>

                        <form
                            class="category-form"
                            @submit.prevent="submitCategory"
                        >
                            <div class="category-form-head">
                                <h3>
                                    {{ categoryEditingId ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید' }}
                                </h3>

                                <button
                                    v-if="categoryEditingId"
                                    type="button"
                                    class="text-button"
                                    @click="openCreateCategory"
                                >
                                    انصراف
                                </button>
                            </div>

                            <div class="category-form-grid">
                                <label class="form-field">
                                    <span>نام</span>
                                    <input v-model.trim="categoryForm.name" required>
                                </label>

                                <label class="form-field">
                                    <span>Slug</span>
                                    <input v-model.trim="categoryForm.slug" required dir="ltr">
                                </label>

                                <label class="form-field">
                                    <span>دسته والد</span>
                                    <select v-model="categoryForm.parent_id">
                                        <option :value="null">دسته اصلی</option>
                                        <option
                                            v-for="category in categoryConfigurationOptions"
                                            :key="category.id"
                                            :value="category.id"
                                            :disabled="category.id === categoryEditingId"
                                        >
                                            {{ '— '.repeat(category.depth) }}{{ category.name }}
                                        </option>
                                    </select>
                                </label>

                                <label class="form-field">
                                    <span>ترتیب</span>
                                    <input
                                        v-model.number="categoryForm.sort_order"
                                        type="number"
                                        min="0"
                                    >
                                </label>

                                <label class="form-field full-width">
                                    <span>توضیحات</span>
                                    <textarea v-model.trim="categoryForm.description" rows="2"></textarea>
                                </label>

                                <label class="form-field full-width">
                                    <span>آدرس تصویر</span>
                                    <input v-model.trim="categoryForm.image" type="url" dir="ltr">
                                </label>
                            </div>

                            <label class="category-active-toggle">
                                <input v-model="categoryForm.is_active" type="checkbox">
                                دسته فعال باشد
                            </label>

                            <div class="category-form-actions">
                                <button
                                    type="submit"
                                    class="primary"
                                    :disabled="categorySaving"
                                >
                                    {{ categorySaving ? 'در حال ذخیره…' : 'ذخیره دسته‌بندی' }}
                                </button>
                            </div>
                        </form>

                        <div class="category-list">
                            <div
                                v-for="category in categoryConfigurationOptions"
                                :key="category.id"
                                class="category-item"
                            >
                                <div>
                                    <strong>
                                        {{ '— '.repeat(category.depth) }}{{ category.name }}
                                    </strong>

                                    <small>
                                        /
                                        {{
                                            category.slug
                                        }}
                                    </small>
                                </div>

                                <div class="category-row-actions">
                                    <span :class="category.is_active ? 'status ok' : 'status bad'">
                                        {{ category.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>

                                    <button type="button" class="text-button" @click="openEditCategory(category)">
                                        ویرایش
                                    </button>

                                    <button type="button" class="text-button danger" @click="deleteCategory(category)">
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="category-attribute-editor">
                            <div>
                                <p class="section-label">پیکربندی مستقیم</p>
                                <h3>ویژگی‌های دسته‌بندی</h3>
                                <p class="editor-help">
                                    تنظیمات این بخش فقط روی دسته انتخاب‌شده اعمال می‌شود و می‌تواند تنظیمات والد را Override کند.
                                </p>
                            </div>

                            <label class="form-field">
                                <span>دسته‌بندی</span>
                                <select
                                    :value="configuredCategoryId || ''"
                                    @change="selectCategoryForAttributes($event.target.value)"
                                >
                                    <option value="">یک دسته را انتخاب کنید</option>
                                    <option
                                        v-for="category in categoryConfigurationOptions"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ '— '.repeat(category.depth) }}{{ category.name }}
                                    </option>
                                </select>
                            </label>

                            <p v-if="categoryAttributeLoading" class="editor-help">
                                در حال دریافت تنظیمات…
                            </p>

                            <template v-else-if="configuredCategory">
                                <div class="configuration-heading">
                                    تنظیم ویژگی‌ها برای «{{ configuredCategory.name }}»
                                </div>

                                <div class="category-attribute-config-list">
                                    <div
                                        v-for="attribute in attributes"
                                        :key="attribute.id"
                                        class="category-attribute-config"
                                        :class="{ enabled: isCategoryAttributeConfigured(attribute.id) }"
                                    >
                                        <label class="config-toggle">
                                            <input
                                                type="checkbox"
                                                :checked="isCategoryAttributeConfigured(attribute.id)"
                                                @change="setCategoryAttributeConfigured(attribute, $event.target.checked)"
                                            >
                                            <span>
                                                <strong>{{ attribute.name }}</strong>
                                                <small>{{ attribute.type }}</small>
                                            </span>
                                        </label>

                                        <div
                                            v-if="isCategoryAttributeConfigured(attribute.id)"
                                            class="config-fields"
                                        >
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    :checked="categoryAttributeConfiguration(attribute.id).is_required"
                                                    @change="updateCategoryAttributeConfig(attribute.id, 'is_required', $event.target.checked)"
                                                >
                                                اجباری
                                            </label>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    :checked="categoryAttributeConfiguration(attribute.id).is_filterable"
                                                    @change="updateCategoryAttributeConfig(attribute.id, 'is_filterable', $event.target.checked)"
                                                >
                                                فیلتر
                                            </label>
                                            <label :class="{ disabled: !supportsVariantAxis(attribute) }">
                                                <input
                                                    type="checkbox"
                                                    :disabled="!supportsVariantAxis(attribute)"
                                                    :checked="categoryAttributeConfiguration(attribute.id).is_variant_axis"
                                                    @change="updateCategoryAttributeConfig(attribute.id, 'is_variant_axis', $event.target.checked)"
                                                >
                                                محور تنوع
                                            </label>
                                            <label class="sort-input">
                                                ترتیب
                                                <input
                                                    type="number"
                                                    min="0"
                                                    :value="categoryAttributeConfiguration(attribute.id).sort_order"
                                                    @input="updateCategoryAttributeConfig(attribute.id, 'sort_order', Number($event.target.value) || 0)"
                                                >
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="config-actions">
                                    <button
                                        type="button"
                                        class="primary"
                                        :disabled="categoryAttributeSaving"
                                        @click="saveCategoryAttributes"
                                    >
                                        {{ categoryAttributeSaving ? 'در حال ذخیره…' : 'ذخیره تنظیمات' }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- ATTRIBUTES -->
                <!-- ================================================= -->

                <section
                    v-else-if="
                        section === 'attributes'
                    "
                >
                    <div class="panel">
                        <div class="panel-head">
                            <div>
                                <p class="section-label">
                                    ویژگی‌های قابل استفاده
                                </p>

                                <h2>
                                    ویژگی‌ها
                                </h2>
                            </div>

                            <button
                                type="button"
                                class="primary"
                                @click="openCreateAttribute"
                            >
                                + ویژگی جدید
                            </button>
                        </div>

                        <form
                            class="attribute-form"
                            @submit.prevent="submitAttribute"
                        >
                            <div class="category-form-head">
                                <h3>
                                    {{ attributeEditingId ? 'ویرایش ویژگی' : 'ویژگی جدید' }}
                                </h3>

                                <button
                                    v-if="attributeEditingId"
                                    type="button"
                                    class="text-button"
                                    @click="openCreateAttribute"
                                >
                                    انصراف
                                </button>
                            </div>

                            <div class="category-form-grid">
                                <label class="form-field">
                                    <span>نام</span>
                                    <input v-model.trim="attributeForm.name" required>
                                </label>

                                <label class="form-field">
                                    <span>Slug</span>
                                    <input v-model.trim="attributeForm.slug" required dir="ltr">
                                </label>

                                <label class="form-field">
                                    <span>نوع</span>
                                    <select v-model="attributeForm.type">
                                        <option value="select">select</option>
                                        <option value="multiselect">multiselect</option>
                                        <option value="color">color</option>
                                        <option value="number">number</option>
                                        <option value="boolean">boolean</option>
                                        <option value="text">text</option>
                                    </select>
                                </label>

                                <label class="form-field">
                                    <span>ترتیب</span>
                                    <input v-model.number="attributeForm.sort_order" type="number" min="0">
                                </label>
                            </div>

                            <div class="category-form-actions">
                                <button type="submit" class="primary" :disabled="attributeSaving">
                                    {{ attributeSaving ? 'در حال ذخیره…' : 'ذخیره ویژگی' }}
                                </button>
                            </div>
                        </form>

                        <div class="attribute-list">
                            <div
                                v-for="attribute in attributes"
                                :key="attribute.id"
                                class="attribute-item"
                            >
                                <div>
                                    <strong>
                                        {{
                                            attribute.name
                                        }}
                                    </strong>

                                    <small>
                                        {{
                                            attribute.type
                                        }}
                                    </small>
                                </div>

                                <div class="category-row-actions">
                                    <span>
                                        {{ attribute.values?.length || 0 }} مقدار
                                    </span>
                                    <button type="button" class="text-button" @click="openEditAttribute(attribute)">
                                        ویرایش
                                    </button>
                                    <button type="button" class="text-button danger" @click="deleteAttribute(attribute)">
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="attributeEditingId && currentEditingAttribute()"
                            class="attribute-values-editor"
                        >
                            <div>
                                <p class="section-label">مقدارهای ازپیش‌تعریف‌شده</p>
                                <h3>مقدارهای «{{ currentEditingAttribute().name }}»</h3>
                            </div>

                            <p
                                v-if="!isOptionAttribute(currentEditingAttribute())"
                                class="editor-help"
                            >
                                نوع {{ currentEditingAttribute().type }} مقدار ثابت ندارد؛ مقدار آن هنگام ثبت محصول وارد می‌شود.
                            </p>

                            <template v-else>
                                <form
                                    class="attribute-value-form"
                                    @submit.prevent="submitAttributeValue"
                                >
                                    <div class="attribute-value-grid">
                                        <label class="form-field">
                                            <span>برچسب</span>
                                            <input v-model.trim="attributeValueForm.label" required>
                                        </label>
                                        <label class="form-field">
                                            <span>مقدار</span>
                                            <input v-model.trim="attributeValueForm.value" required dir="ltr">
                                        </label>
                                        <label
                                            v-if="currentEditingAttribute().type === 'color'"
                                            class="form-field"
                                        >
                                            <span>کد رنگ</span>
                                            <input v-model.trim="attributeValueForm.hex_color" placeholder="#000000" dir="ltr">
                                        </label>
                                        <label class="form-field">
                                            <span>ترتیب</span>
                                            <input v-model.number="attributeValueForm.sort_order" type="number" min="0">
                                        </label>
                                    </div>

                                    <div class="category-form-actions">
                                        <button
                                            v-if="attributeValueEditingId"
                                            type="button"
                                            class="text-button"
                                            @click="resetAttributeValueForm"
                                        >
                                            انصراف
                                        </button>
                                        <button type="submit" class="primary" :disabled="attributeValueSaving">
                                            {{ attributeValueSaving ? 'در حال ذخیره…' : attributeValueEditingId ? 'به‌روزرسانی مقدار' : 'افزودن مقدار' }}
                                        </button>
                                    </div>
                                </form>

                                <div class="attribute-value-list">
                                    <div
                                        v-for="value in currentEditingAttribute().values || []"
                                        :key="value.id"
                                        class="attribute-value-item"
                                    >
                                        <span
                                            v-if="value.hex_color"
                                            class="color-swatch"
                                            :style="{ background: value.hex_color }"
                                        ></span>
                                        <div class="grow">
                                            <strong>{{ value.label }}</strong>
                                            <small>{{ value.value }}</small>
                                        </div>
                                        <span>{{ value.sort_order }}</span>
                                        <button type="button" class="text-button" @click="openEditAttributeValue(value)">ویرایش</button>
                                        <button type="button" class="text-button danger" @click="deleteAttributeValue(value)">حذف</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <!-- ================================================= -->
                <!-- OTHER -->
                <!-- ================================================= -->

                <section
                    v-else
                    class="panel"
                >
                    <div class="empty">
                        این بخش در مرحله بعدی
                        تکمیل می‌شود.
                    </div>
                </section>
            </template>
        </main>

        <!-- ================================================= -->
        <!-- PRODUCT MODAL -->
        <!-- ================================================= -->

        <Teleport to="body">
            <div
                v-if="showProductModal"
                class="modal-backdrop"
                @click.self="
                    closeProductModal()
                "
            >
                <div class="modal">
                    <!-- MODAL HEADER -->

                    <div class="modal-header">
                        <div>
                            <p class="section-label">
                                کاتالوگ فروشگاه
                            </p>

                            <h2>
                                   {{ editingProductId ? 'ویرایش محصول' : 'افزودن محصول جدید' }}

                            </h2>
                        </div>

                        <button
                            type="button"
                            class="close-button"
                            @click="
                                closeProductModal()
                            "
                        >
                            ×
                        </button>
                    </div>

                    <!-- ERROR -->

                    <div
                        v-if="errorMessage"
                        class="alert error"
                    >
                        {{ errorMessage }}
                    </div>

                    <!-- FORM -->

                    <form
                        class="product-form"
                        @submit.prevent="
                            submitProduct()
                        "
                    >
                        <!-- BASIC -->

                        <div class="form-section">
                            <div class="form-section-title">
                                اطلاعات اصلی
                            </div>

                            <div class="form-grid">
                                <label class="field full">
                                    <span>
                                        نام محصول
                                    </span>

                                    <input
                                        v-model="
                                            form.name
                                        "
                                        type="text"
                                        placeholder="مثلاً کت زنانه کلاسیک"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        Slug
                                    </span>

                                    <input
                                        v-model="
                                            form.slug
                                        "
                                        type="text"
                                        dir="ltr"
                                        placeholder="classic-women-coat"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        SKU
                                    </span>

                                    <input
                                        v-model="
                                            form.sku
                                        "
                                        type="text"
                                        dir="ltr"
                                        placeholder="ROY-001"
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        قیمت
                                    </span>

                                    <input
                                        v-model.number="
                                            form.price
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        required
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        قیمت قبل
                                    </span>

                                    <input
                                        v-model.number="
                                            form.compare_at_price
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="اختیاری"
                                    />
                                </label>

                                <label class="field">
                                    <span>
                                        موجودی
                                    </span>

                                    <input
                                        v-model.number="
                                            form.stock
                                        "
                                        type="number"
                                        min="0"
                                    />
                                </label>
                            </div>
                        </div>
<!-- ================================================= -->
<!-- PRODUCT IMAGES -->
<!-- ================================================= -->

<div class="form-section">

    <div class="form-section-title">
        تصاویر محصول
    </div>

    <p
        v-if="!editingProductId"
        class="hint"
    >
        تصاویر را همین‌جا انتخاب کنید؛ پس از ذخیره محصول،
        تصاویر به‌صورت خودکار آپلود می‌شوند.
    </p>

    <p
        v-else
        class="hint"
    >
        تصاویر جدید را انتخاب کنید یا تصاویر موجود را مدیریت کنید.
    </p>

    <!-- IMAGE UPLOAD -->

    <div class="image-upload-box">

        <label
            for="product-image-input"
            class="image-file-label"
        >
            <span class="image-upload-icon">
                📷
            </span>

            <span>
                انتخاب تصویر
            </span>

            <small>
                JPG، PNG یا WebP — هر تصویر حداکثر 5MB
            </small>
        </label>

        <input
            id="product-image-input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="image-file-input"
            multiple
            @change="onImagesSelected"
        />

    </div>

    <!-- NEW IMAGE PREVIEWS -->

    <div
        v-if="imagePreviews.length"
        class="selected-images-grid"
    >

        <div
            v-for="(preview, index) in imagePreviews"
            :key="preview.url"
            class="selected-image-card"
        >

            <div class="selected-image-card-preview">

                <img
                    :src="preview.url"
                    alt="پیش‌نمایش تصویر"
                />

                <span
                    v-if="index === 0"
                    class="new-primary-badge"
                >
                    ⭐ تصویر اصلی
                </span>

                <button
                    type="button"
                    class="remove-selected-image"
                    @click="removeSelectedImage(index)"
                >
                    ×
                </button>

            </div>

            <div class="selected-image-card-name">
                {{ preview.file.name }}
            </div>

        </div>

    </div>

    <!-- ALT TEXT -->

    <div
        v-if="imagePreviews.length"
        class="selected-images-info"
    >

        <label class="field">

            <span>
                متن جایگزین تصاویر
            </span>

            <input
                v-model="imageAltText"
                type="text"
                placeholder="مثلاً مانتو مشکی ROYA"
            />

        </label>

        <p class="hint">
            اولین تصویر به‌عنوان تصویر اصلی محصول ثبت می‌شود.
        </p>

    </div>

    <!-- EXISTING IMAGES -->

    <div
        v-if="editingProductId && form.images?.length"
        class="product-images-grid"
    >

        <div
            v-for="image in form.images"
            :key="image.id"
            class="product-image-card"
            :class="{
                'is-dragging':
                    draggingImageId === image.id
            }"
            draggable="true"
            @dragstart="startImageDrag(image.id)"
            @dragover.prevent
            @drop="dropImage(image.id)"
        >

            <div class="product-image-preview">

                <img
                    :src="imageUrl(image)"
                    :alt="
                        image.alt_text ||
                        form.name
                    "
                />

                <span
                    v-if="image.is_primary"
                    class="primary-image-badge"
                >
                    تصویر اصلی
                </span>

            </div>

            <div class="product-image-footer">

                <div class="product-image-meta">

                    <span>
                        {{
                            image.alt_text ||
                            'بدون متن جایگزین'
                        }}
                    </span>

                    <button
                        v-if="!image.is_primary"
                        type="button"
                        class="btn-primary-image"
                        @click="setPrimaryImage(image.id)"
                    >
                        ⭐ تصویر اصلی
                    </button>

                    <span
                        v-else
                        class="current-primary-label"
                    >
                        ⭐ تصویر اصلی
                    </span>

                </div>

                <button
                    type="button"
                    class="btn-delete-image"
                    @click="deleteImage(image.id)"
                >
                    حذف
                </button>

            </div>

        </div>

    </div>

    <!-- NO EXISTING IMAGES -->

    <div
        v-else-if="editingProductId"
        class="empty-images"
    >
        هنوز تصویری برای این محصول ثبت نشده است.
    </div>

</div>

                        <!-- DESCRIPTION -->

                        <div class="form-section">
                            <div class="form-section-title">
                                توضیحات
                            </div>

                            <div class="form-grid">
                                <label class="field full">
                                    <span>
                                        توضیح کوتاه
                                    </span>

                                    <textarea
                                        v-model="
                                            form.short_description
                                        "
                                        rows="3"
                                        placeholder="توضیح کوتاه محصول..."
                                    ></textarea>
                                </label>

                                <label class="field full">
                                    <span>
                                        توضیحات کامل
                                    </span>

                                    <textarea
                                        v-model="
                                            form.description
                                        "
                                        rows="5"
                                        placeholder="توضیحات کامل محصول..."
                                    ></textarea>
                                </label>
                            </div>
                        </div>

                        <!-- CATEGORIES -->

                        <div class="form-section">
                            <div class="form-section-title">
                                دسته‌بندی محصول
                            </div>

                            <p class="hint">
                                با انتخاب دسته‌بندی،
                                ویژگی‌های اختصاصی همان
                                دسته نمایش داده می‌شوند.
                            </p>

                            <div
                                v-if="
                                    categories.length
                                "
                                class="category-select"
                            >
                                <div
                                    v-for="category in categories"
                                    :key="
                                        category.id
                                    "
                                    class="category-group"
                                >
                                    <label
                                        class="check-card"
                                    >
                                        <input
                                            v-model="
                                                form.category_ids
                                            "
                                            type="checkbox"
                                            :value="
                                                category.id
                                            "
                                        />

                                        <span>
                                            {{
                                                category.name
                                            }}
                                        </span>
                                    </label>

                                    <div
                                        v-if="
                                            category.children
                                                ?.length
                                        "
                                        class="children"
                                    >
                                        <label
                                            v-for="child in category.children"
                                            :key="
                                                child.id
                                            "
                                            class="check-card child"
                                        >
                                            <input
                                                v-model="
                                                    form.category_ids
                                                "
                                                type="checkbox"
                                                :value="
                                                    child.id
                                                "
                                            />

                                            <span>
                                                {{
                                                    child.name
                                                }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="
                                    form.category_ids
                                        .length
                                "
                                class="selected-categories"
                            >
                                <span
                                    v-for="name in selectedCategoryNames()"
                                    :key="name"
                                >
                                    {{ name }}
                                </span>
                            </div>
                        </div>

                        <!-- ATTRIBUTES -->

                        <div class="form-section">
                            <div class="form-section-title">
                                ویژگی‌های محصول
                            </div>

                            <p
                                v-if="
                                    !form.category_ids
                                        .length
                                "
                                class="hint warning-text"
                            >
                                ابتدا یک دسته‌بندی انتخاب
                                کنید.
                            </p>

                            <p
                                v-else-if="
                                    !selectedAttributes.length
                                "
                                class="hint"
                            >
                                برای دسته‌بندی انتخاب‌شده
                                هنوز ویژگی‌ای تعریف نشده
                                است.
                            </p>

                            <div
                                v-else
                                class="attributes-form"
                            >
                                <div
                                    v-for="attribute in selectedAttributes"
                                    :key="
                                        attribute.id
                                    "
                                    class="attribute-box"
                                >
                                    <div class="attribute-heading">
                                        <strong>
                                            {{
                                                attribute.name
                                            }}
                                        </strong>

                                        <small>
                                            {{
                                                attribute.type
                                            }}
                                        </small>
                                    </div>

                                    <div
                                        v-if="isCustomAttribute(attribute)"
                                        class="custom-attribute-input"
                                    >
                                        <label
                                            v-if="attribute.type === 'boolean'"
                                            class="boolean-value"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="customAttributeValue(attribute)"
                                                @change="setCustomAttributeValue(attribute, $event.target.checked)"
                                            />

                                            مقدار فعال است
                                        </label>

                                        <input
                                            v-else
                                            :type="attribute.type === 'number' ? 'number' : 'text'"
                                            :step="attribute.type === 'number' ? 'any' : undefined"
                                            :value="customAttributeValue(attribute)"
                                            :placeholder="attribute.type === 'number' ? 'مقدار عددی' : 'مقدار متنی'"
                                            @input="setCustomAttributeValue(attribute, $event.target.value)"
                                        />
                                    </div>

                                    <div
                                        v-else-if="attribute.values?.length"
                                        class="values-grid"
                                    >
                                        <button
                                            v-for="value in attribute.values"
                                            :key="
                                                value.id
                                            "
                                            type="button"
                                            class="value-chip"
                                            :class="{
                                                selected:
                                                    isValueSelected(
                                                        value.id
                                                    ),
                                            }"
                                            @click="
                                                toggleAttributeValue(
                                                    value.id
                                                )
                                            "
                                        >
                                            <span
                                                v-if="
                                                    value.hex_color
                                                "
                                                class="color-dot"
                                                :style="{
                                                    backgroundColor:
                                                        value.hex_color,
                                                }"
                                            ></span>

                                            {{
                                                value.label
                                            }}
                                        </button>
                                    </div>

                                    <div
                                        v-else
                                        class="hint"
                                    >
                                        برای این ویژگی هنوز
                                        مقداری تعریف نشده.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VARIANTS -->

                        <div class="form-section">
                            <div class="variant-header">
                                <div>
                                    <div
                                        class="form-section-title"
                                    >
                                        Variantها
                                    </div>

                                    <p class="hint">
                                        برای محصولاتی مثل لباس
                                        که سایز یا رنگ متفاوت
                                        دارند.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="secondary"
                                    @click="
                                        addVariant()
                                    "
                                >
                                    + افزودن Variant
                                </button>
                            </div>

                            <div
                                v-if="
                                    form.variants.length
                                "
                                class="variants"
                            >
                                <div
                                    v-for="(
                                        variant,
                                        index
                                    ) in form.variants"
                                    :key="index"
                                    class="variant-box"
                                >
                                    <div class="variant-top">
                                        <strong>
                                            Variant
                                            {{
                                                index + 1
                                            }}
                                        </strong>

                                        <button
                                            type="button"
                                            class="remove-variant"
                                            @click="
                                                removeVariant(
                                                    index
                                                )
                                            "
                                        >
                                            حذف
                                        </button>
                                    </div>

                                    <div class="form-grid">
                                        <label class="field">
                                            <span>
                                                SKU
                                            </span>

                                            <input
                                                v-model="
                                                    variant.sku
                                                "
                                                type="text"
                                                dir="ltr"
                                                placeholder="ROY-001-BLK-M"
                                            />
                                        </label>

                                        <label class="field">
                                            <span>
                                                قیمت
                                            </span>

                                            <input
                                                v-model.number="
                                                    variant.price
                                                "
                                                type="number"
                                                min="0"
                                                placeholder="قیمت اصلی"
                                            />
                                        </label>

                                        <label class="field">
                                            <span>
                                                موجودی
                                            </span>

                                            <input
                                                v-model.number="
                                                    variant.stock
                                                "
                                                type="number"
                                                min="0"
                                            />
                                        </label>
                                    </div>

                                    <div
                                        v-if="variantAttributes.length"
                                        class="variant-attributes"
                                    >
                                        <div
                                            v-for="attribute in variantAttributes"
                                            :key="
                                                attribute.id
                                            "
                                            class="variant-attribute"
                                        >
                                            <span>
                                                {{
                                                    attribute.name
                                                }}
                                            </span>

                                            <div
                                                class="values-grid"
                                            >
                                                <button
                                                    v-for="value in attribute.values"
                                                    :key="
                                                        value.id
                                                    "
                                                    type="button"
                                                    class="value-chip"
                                                    :class="{
                                                        selected:
                                                            isVariantValueSelected(
                                                                variant,
                                                                value.id
                                                            ),
                                                    }"
                                                    @click="
                                                        toggleVariantValue(
                                                            variant,
                                                            value.id
                                                        )
                                                    "
                                                >
                                                    {{
                                                        value.label
                                                    }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="variant-empty"
                            >
                                <span>＋</span>

                                <div>
                                    <strong>
                                        Variant ندارد
                                    </strong>

                                    <small>
                                        اگر محصول سایز، رنگ یا
                                        ترکیب متفاوت دارد، Variant
                                        اضافه کنید.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- SETTINGS -->

                        <div class="form-section">
                            <div class="form-section-title">
                                وضعیت محصول
                            </div>

                            <div class="toggle-row">
                                <label class="toggle-card">
                                    <input
                                        v-model="
                                            form.is_active
                                        "
                                        type="checkbox"
                                    />

                                    <span class="toggle">
                                    </span>

                                    <div>
                                        <strong>
                                            محصول فعال
                                        </strong>

                                        <small>
                                            در فروشگاه نمایش داده
                                            شود.
                                        </small>
                                    </div>
                                </label>

                                <label class="toggle-card">
                                    <input
                                        v-model="
                                            form.is_featured
                                        "
                                        type="checkbox"
                                    />

                                    <span class="toggle">
                                    </span>

                                    <div>
                                        <strong>
                                            محصول ویژه
                                        </strong>

                                        <small>
                                            در بخش محصولات ویژه
                                            نمایش داده شود.
                                        </small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- FOOTER -->

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="cancel"
                                :disabled="saving"
                                @click="
                                    closeProductModal()
                                "
                            >
                                انصراف
                            </button>

<button
    type="submit"
    class="primary save-button"
    :disabled="saving"
>
    <span
        v-if="saving"
        class="button-spinner"
    ></span>

    {{
        saving
            ? 'در حال ذخیره...'
            : (
                editingProductId
                    ? 'ذخیره تغییرات'
                    : 'ذخیره محصول'
            )
    }}
</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* =========================================================
   PRODUCT IMAGES
========================================================= */

.image-upload-box {
    border: 1.5px dashed #d9d4c9;
    border-radius: 14px;
    background: #fafbfc;
    padding: 24px;
    text-align: center;
    transition: .2s ease;
}

.image-upload-box:hover {
    border-color: #6563d9;
    background: #fafaff;
}

.image-file-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    cursor: pointer;
}

.image-upload-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-size: 22px;
}

.image-file-label span:not(.image-upload-icon) {
    font-size: 13px;
    font-weight: 700;
    color: #36384b;
}

.image-file-label small {
    color: #999aae;
    font-size: 11px;
}

.image-file-input {
    display: none;
}


/* Selected image */

.selected-image-wrapper {
    margin-top: 16px;
    padding: 15px;
    border: 1px solid #eeeeF4;
    border-radius: 14px;
    display: flex;
    gap: 16px;
    background: #fff;
}

.selected-image-preview {
    width: 130px;
    height: 160px;
    flex-shrink: 0;
    border-radius: 11px;
    overflow: hidden;
    background: #f1f1f5;
}

.selected-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.selected-image-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.selected-image-info > strong {
    font-size: 13px;
    word-break: break-word;
}

.selected-image-info > span {
    color: #999aae;
    font-size: 11px;
}

.selected-image-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
}


/* Existing images */

.product-images-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.product-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
}

.product-image-preview {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #f1f1f5;
}

.product-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.primary-image-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 5px 8px;
    border-radius: 7px;
    background: #6563d9;
    color: #fff;
    font-size: 10px;
}

.product-image-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px;
}

.product-image-footer span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #888a9b;
    font-size: 10px;
}

.btn-delete-image {
    flex-shrink: 0;
    border: 0;
    background: #fff0f1;
    color: #d25f68;
    border-radius: 7px;
    padding: 6px 9px;
    font-family: inherit;
    font-size: 10px;
    cursor: pointer;
}

.btn-delete-image:hover {
    background: #ffe0e3;
}

.empty-images {
    margin-top: 16px;
    padding: 25px;
    border: 1px dashed #dddde7;
    border-radius: 12px;
    text-align: center;
    color: #999aae;
    font-size: 12px;
}


/* Mobile */

@media (max-width: 700px) {

    .product-images-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .selected-image-wrapper {
        flex-direction: column;
    }

    .selected-image-preview {
        width: 100%;
        height: 240px;
    }

}
/* =========================================================
   BASE
========================================================= */

.admin-shell {
    min-height: 100vh;
    background: #f5f6fa;
    color: #202235;
    display: flex;
    font-family:
        Vazirmatn,
        Tahoma,
        Arial,
        sans-serif;
}

.admin-login {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background: #f6f6fb;
}

.login-card {
    width: min(100%, 380px);
    display: grid;
    gap: 16px;
    padding: 30px;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 18px 50px rgba(42, 43, 72, .12);
}

.login-card h1,
.login-card p {
    margin: 0;
}

.login-card > p:not(.section-label):not(.login-error) {
    color: #7c7d91;
    font-size: 13px;
    line-height: 1.8;
}

.login-card label {
    display: grid;
    gap: 7px;
    color: #505266;
    font-size: 12px;
    font-weight: 600;
}

.login-card input {
    border: 1px solid #dedfea;
    border-radius: 9px;
    padding: 10px 12px;
    font: inherit;
}

.login-card button,
.logout-button {
    border: 0;
    border-radius: 9px;
    padding: 10px 14px;
    background: #6563d9;
    color: #fff;
    font: inherit;
    cursor: pointer;
}

.login-card button:disabled {
    opacity: .65;
    cursor: wait;
}

.login-error {
    color: #c5394f;
    font-size: 12px;
}

.logout-button {
    padding: 6px 10px;
    background: transparent;
    color: #6563d9;
    border: 1px solid #d8d8ea;
    font-size: 11px;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 260px;
    min-height: 100vh;
    background: #171827;
    color: #fff;
    padding: 28px 18px;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.brand {
    margin: 0 12px 42px;
    font-family: Georgia, serif;
    font-size: 28px;
    letter-spacing: 1px;
}

.brand span {
    display: block;
    margin-top: 4px;
    color: #aaaac0;
    font-family: Vazirmatn, Tahoma, sans-serif;
    font-size: 10px;
    letter-spacing: 3px;
}

.sidebar-nav {
    display: grid;
    gap: 7px;
}

.nav-item {
    width: 100%;
    border: 0;
    background: transparent;
    color: #a8a9bd;
    text-align: right;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 13px;
    cursor: pointer;
    transition: .2s ease;
}

.nav-item:hover,
.nav-item.active {
    background: #6563d9;
    color: #fff;
}

.nav-item b {
    width: 22px;
    font-size: 18px;
    text-align: center;
}

.side-foot {
    margin-top: auto;
    color: #85869c;
    font-size: 12px;
    line-height: 2;
}

.side-foot small {
    display: block;
    color: #66677c;
}

/* =========================================================
   MAIN
========================================================= */

.main {
    flex: 1;
    min-width: 0;
    padding: 34px 42px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.eyebrow,
.section-label {
    margin: 0 0 6px;
    color: #777895;
    font-size: 12px;
}

.topbar h1 {
    margin: 0;
    font-size: 28px;
}

.admin-user {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e9e8ff;
    color: #5b59c7;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
}

/* =========================================================
   CARDS
========================================================= */

.cards {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.stat,
.panel {
    background: #fff;
    border-radius: 18px;
    box-shadow:
        0 8px 30px rgba(35, 35, 77, .05);
}

.stat {
    position: relative;
    padding: 23px;
}

.stat span {
    color: #7b7d94;
    font-size: 13px;
}

.stat strong {
    display: block;
    margin-top: 13px;
    font-size: 30px;
}

.stat i {
    position: absolute;
    left: 22px;
    top: 22px;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-style: normal;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
    padding: 23px;
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
}

.panel-head h2 {
    margin: 0;
    font-size: 19px;
}

.text-button {
    border: 0;
    background: transparent;
    color: #6563d9;
    cursor: pointer;
    font-family: inherit;
}

.primary,
.secondary,
.cancel {
    border: 0;
    border-radius: 10px;
    padding: 10px 16px;
    font-family: inherit;
    cursor: pointer;
    transition: .2s ease;
}

.primary {
    background: #6563d9;
    color: #fff;
}

.primary:hover {
    background: #5553c8;
}

.primary:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.secondary {
    background: #efefff;
    color: #5b59c7;
}

.secondary:hover {
    background: #e4e3ff;
}

.cancel {
    background: #f1f2f6;
    color: #5e6073;
}

/* =========================================================
   PRODUCTS
========================================================= */

.rows,
.products-table {
    display: grid;
}

.product-row {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 14px 4px;
    border-bottom: 1px solid #f0f0f5;
}

.product-row:last-child {
    border-bottom: 0;
}

.product-thumb {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #eeeefe;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 18px;
}

.grow {
    flex: 1;
    min-width: 0;
}

.grow b,
.grow small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.grow small {
    margin-top: 4px;
    color: #999aae;
    font-size: 11px;
}

.price {
    white-space: nowrap;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    white-space: nowrap;
}

.status.ok {
    background: #e7faf3;
    color: #27a37a;
}

.status.bad {
    background: #fff0f1;
    color: #dd6870;
}

.delete-button {
    border: 0;
    background: #fff0f1;
    color: #d85f68;
    border-radius: 8px;
    padding: 7px 10px;
    font-family: inherit;
    cursor: pointer;
}

.delete-button:hover {
    background: #ffe2e5;
}

/* =========================================================
   EMPTY / LOADING
========================================================= */

.empty {
    padding: 70px 20px;
    text-align: center;
    color: #999aae;
}

.empty-icon {
    font-size: 40px;
    color: #6563d9;
    margin-bottom: 10px;
}

.empty h3 {
    margin: 0 0 7px;
    color: #303247;
}

.empty p {
    margin: 0 0 20px;
}

.loading {
    min-height: 400px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 14px;
    color: #85869c;
}

.spinner,
.button-spinner {
    border: 3px solid #e5e5f8;
    border-top-color: #6563d9;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}

.spinner {
    width: 35px;
    height: 35px;
}

.button-spinner {
    width: 15px;
    height: 15px;
    display: inline-block;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* =========================================================
   ALERT
========================================================= */

.alert {
    margin-bottom: 18px;
    border-radius: 12px;
    padding: 12px 15px;
    font-size: 13px;
}
.btn-edit,
.btn-delete {
    border: none;
    border-radius: 8px;
    padding: 7px 12px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #f5efe2;
    color: #8a6b2f;
}

.btn-edit:hover {
    background: #e9ddc3;
}

.btn-delete {
    background: #fff0f1;
    color: #c9545e;
}

.btn-delete:hover {
    background: #ffe0e3;
}
.product-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
}
.alert.error {
    background: #fff0f1;
    color: #c9545e;
    border: 1px solid #ffd9dc;
}
.alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

/* =========================================================
   CATEGORIES / ATTRIBUTES
========================================================= */

.category-list,
.attribute-list {
    display: grid;
    gap: 10px;
}

.category-form {
    margin-bottom: 22px;
    padding: 18px;
    border: 1px solid #e6e6ef;
    border-radius: 14px;
    background: #fafaff;
}

.attribute-form,
.attribute-values-editor {
    margin-bottom: 22px;
    padding: 18px;
    border: 1px solid #e6e6ef;
    border-radius: 14px;
    background: #fafaff;
}

.attribute-values-editor {
    margin-top: 26px;
    margin-bottom: 0;
}

.attribute-values-editor h3 {
    margin: 4px 0 0;
    font-size: 16px;
}

.attribute-value-form {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #eeeeF4;
}

.attribute-value-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.attribute-value-list {
    display: grid;
    gap: 8px;
    margin-top: 18px;
}

.attribute-value-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid #eeeeF4;
    border-radius: 10px;
    font-size: 12px;
}

.attribute-value-item strong,
.attribute-value-item small {
    display: block;
}

.attribute-value-item small {
    margin-top: 3px;
    color: #85869c;
}

.color-swatch {
    width: 18px;
    height: 18px;
    border: 1px solid #d4d4dd;
    border-radius: 50%;
    flex-shrink: 0;
}

.category-form-head,
.category-form-actions,
.category-row-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.category-form-head {
    justify-content: space-between;
    margin-bottom: 16px;
}

.category-form-head h3 {
    margin: 0;
    font-size: 15px;
}

.category-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.form-field {
    display: grid;
    gap: 6px;
}

.form-field > span {
    color: #5c5e71;
    font-size: 12px;
    font-weight: 600;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 11px;
    border: 1px solid #e3e4eb;
    border-radius: 9px;
    background: #fff;
    color: #25273a;
    font-family: inherit;
}

.form-field textarea {
    resize: vertical;
}

.form-field.full-width {
    grid-column: 1 / -1;
}

.category-active-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    font-size: 12px;
}

.category-form-actions {
    justify-content: flex-end;
    margin-top: 16px;
}

.category-row-actions {
    justify-content: flex-end;
}

.text-button.danger {
    color: #c9545e;
}

.category-item,
.attribute-item {
    padding: 16px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-item strong,
.attribute-item strong {
    display: block;
}

.category-item small,
.attribute-item small {
    display: block;
    margin-top: 5px;
    color: #999aae;
    font-size: 11px;
}

.category-item > span,
.attribute-item > span {
    color: #85869c;
    font-size: 12px;
}

.category-attribute-editor {
    margin-top: 26px;
    padding-top: 24px;
    border-top: 1px solid #eeeeF4;
}

.category-attribute-editor h3 {
    margin: 4px 0;
    font-size: 17px;
}

.editor-help {
    margin: 6px 0 18px;
    color: #85869c;
    font-size: 12px;
    line-height: 1.8;
}

.category-attribute-editor .form-field {
    max-width: 420px;
    margin: 18px 0;
}

.configuration-heading {
    margin: 20px 0 12px;
    font-weight: 700;
    font-size: 13px;
}

.category-attribute-config-list {
    display: grid;
    gap: 10px;
}

.category-attribute-config {
    padding: 14px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
}

.category-attribute-config.enabled {
    border-color: #b9b8f1;
    background: #fafaff;
}

.config-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.config-toggle strong,
.config-toggle small {
    display: block;
}

.config-toggle small {
    margin-top: 3px;
    color: #85869c;
    font-size: 11px;
}

.config-fields {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #eeeeF4;
    font-size: 12px;
}

.config-fields label {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.config-fields .disabled {
    color: #b3b4c1;
}

.sort-input input {
    width: 58px;
    padding: 5px 7px;
    border: 1px solid #dedee8;
    border-radius: 7px;
}

.config-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

/* =========================================================
   MODAL
========================================================= */

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    padding: 30px;
    background: rgba(17, 18, 32, .62);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal {
    width: min(950px, 100%);
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    background: #fff;
    border-radius: 22px;
    box-shadow:
        0 30px 80px rgba(0, 0, 0, .2);
}

.modal-header {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    padding: 24px;
    border-bottom: 1px solid #eeeeF4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px;
}

.close-button {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 10px;
    background: #f2f3f7;
    color: #55576a;
    font-size: 25px;
    cursor: pointer;
}

/* =========================================================
   FORM
========================================================= */

.product-form {
    padding: 24px;
}

.form-section {
    padding: 0 0 25px;
    margin-bottom: 25px;
    border-bottom: 1px solid #eeeeF4;
}

.form-section:last-of-type {
    border-bottom: 0;
}

.form-section-title {
    margin-bottom: 5px;
    font-size: 16px;
    font-weight: 700;
}

.hint {
    margin: 0 0 15px;
    color: #8b8d9f;
    font-size: 12px;
    line-height: 1.8;
}

.warning-text {
    color: #c88a32;
}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.field {
    display: grid;
    gap: 7px;
}

.field.full {
    grid-column: 1 / -1;
}

.field > span {
    color: #5c5e71;
    font-size: 12px;
    font-weight: 600;
}

.field input,
.field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e3e4eb;
    background: #fafbfc;
    border-radius: 10px;
    padding: 11px 12px;
    outline: 0;
    color: #25273a;
    font-family: inherit;
    font-size: 13px;
    transition: .2s ease;
}

.field textarea {
    resize: vertical;
}

.field input:focus,
.field textarea:focus {
    border-color: #7775df;
    background: #fff;
    box-shadow:
        0 0 0 3px rgba(101, 99, 217, .08);
}

/* =========================================================
   CATEGORY SELECT
========================================================= */

.category-select {
    display: grid;
    gap: 10px;
}

.category-group {
    padding: 13px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
}

.check-card {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #36384b;
    font-size: 13px;
    cursor: pointer;
}

.check-card input {
    accent-color: #6563d9;
}

.children {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding-right: 25px;
}

.check-card.child {
    padding: 7px 10px;
    border-radius: 8px;
    background: #f7f7fb;
}

.selected-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 13px;
}

.selected-categories span {
    padding: 6px 10px;
    border-radius: 20px;
    background: #efefff;
    color: #5c5ac9;
    font-size: 11px;
}

/* =========================================================
   ATTRIBUTES
========================================================= */

.attributes-form {
    display: grid;
    gap: 14px;
}

.attribute-box {
    padding: 16px;
    border: 1px solid #eeeeF4;
    border-radius: 14px;
}

.attribute-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.attribute-heading small {
    color: #999aae;
    font-size: 10px;
}

.values-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.custom-attribute-input input[type="number"],
.custom-attribute-input input[type="text"] {
    width: 100%;
    border: 1px solid #e2e3eb;
    border-radius: 9px;
    padding: 9px 11px;
    font: inherit;
}

.boolean-value {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #505266;
    font-size: 12px;
}

.value-chip {
    border: 1px solid #e2e3eb;
    background: #fff;
    color: #505266;
    border-radius: 9px;
    padding: 7px 11px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.value-chip:hover {
    border-color: #a3a1ed;
}

.value-chip.selected {
    background: #6563d9;
    border-color: #6563d9;
    color: #fff;
}

.color-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0,0,0,.12);
}

/* =========================================================
   VARIANTS
========================================================= */

.variant-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.variants {
    display: grid;
    gap: 13px;
}

.variant-box {
    padding: 16px;
    background: #fafbfc;
    border: 1px solid #e8e9ef;
    border-radius: 14px;
}

.variant-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.remove-variant {
    border: 0;
    background: #fff0f1;
    color: #d25f68;
    padding: 6px 9px;
    border-radius: 7px;
    font-family: inherit;
    cursor: pointer;
}

.variant-attributes {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e6e7ed;
    display: grid;
    gap: 13px;
}

.variant-attribute > span {
    display: block;
    margin-bottom: 7px;
    color: #656779;
    font-size: 12px;
    font-weight: 600;
}

.variant-empty {
    padding: 20px;
    background: #fafbfc;
    border: 1px dashed #dcdde6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 13px;
}

.variant-empty > span {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #efefff;
    color: #6563d9;
    display: grid;
    place-items: center;
    font-size: 20px;
}

.variant-empty strong,
.variant-empty small {
    display: block;
}

.variant-empty small {
    margin-top: 4px;
    color: #999aae;
    font-size: 11px;
}

/* =========================================================
   TOGGLES
========================================================= */

.toggle-row {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.toggle-card {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 14px;
    border: 1px solid #eeeeF4;
    border-radius: 12px;
    cursor: pointer;
}

.toggle-card input {
    display: none;
}

.toggle {
    position: relative;
    width: 40px;
    height: 22px;
    flex-shrink: 0;
    border-radius: 30px;
    background: #d9dae3;
    transition: .2s ease;
}

.toggle::after {
    content: '';
    position: absolute;
    top: 3px;
    right: 3px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    transition: .2s ease;
}

.toggle-card input:checked + .toggle {
    background: #6563d9;
}

.toggle-card input:checked + .toggle::after {
    right: 21px;
}

.toggle-card strong,
.toggle-card small {
    display: block;
}

.toggle-card strong {
    font-size: 12px;
}

.toggle-card small {
    margin-top: 3px;
    color: #999aae;
    font-size: 10px;
}

/* =========================================================
   MODAL FOOTER
========================================================= */

.modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    margin: 0 -24px -24px;
    padding: 17px 24px;
    background: #fff;
    border-top: 1px solid #eeeeF4;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.save-button {
    min-width: 135px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1000px) {
    .cards {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .sidebar {
        width: 70px;
        padding: 20px 7px;
    }

    .brand {
        margin: 0 0 30px;
        text-align: center;
        font-size: 0;
    }

    .brand::first-letter {
        font-size: 27px;
    }

    .brand span {
        display: none;
    }

    .nav-item {
        justify-content: center;
        padding: 13px 5px;
    }

    .nav-item span {
        display: none;
    }

    .side-foot {
        display: none;
    }

    .main {
        padding: 22px 15px;
    }

    .topbar {
        margin-bottom: 22px;
    }

    .topbar h1 {
        font-size: 23px;
    }

    .product-row {
        flex-wrap: wrap;
    }

    .price {
        margin-right: auto;
    }

    .modal-backdrop {
        padding: 10px;
        align-items: flex-end;
    }

    .modal {
        max-height: calc(100vh - 20px);
        border-radius: 20px 20px 0 0;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .toggle-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .cards {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat {
        padding: 16px;
    }

    .stat strong {
        font-size: 24px;
    }

    .stat i {
        left: 12px;
        top: 12px;
    }

    .panel {
        padding: 16px;
    }

    .panel-head {
        align-items: flex-start;
    }

    .panel-head .primary {
        padding: 8px 11px;
        font-size: 11px;
    }

    .product-row > strong,
    .product-row > .status {
        font-size: 10px;
    }

    .modal-header,
    .product-form {
        padding: 18px;
    }

    .modal-footer {
        margin: 0 -18px -18px;
        padding: 14px 18px;
    }

    .variant-header {
        flex-direction: column;
    }
    .product-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
    cursor: grab;
    transition: .2s ease;
}

.product-image-card:active {
    cursor: grabbing;
}

.product-image-card.is-dragging {
    opacity: .45;
    transform: scale(.98);
}

.product-image-card:hover {
    border-color: #6563d9;
    box-shadow: 0 8px 24px rgba(30, 30, 60, .08);
}

.product-image-meta {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
}

.btn-primary-image {
    border: 0;
    background: #f1f0ff;
    color: #6563d9;
    border-radius: 7px;
    padding: 5px 8px;
    font-family: inherit;
    font-size: 10px;
    cursor: pointer;
}

.btn-primary-image:hover {
    background: #e7e5ff;
}

.current-primary-label {
    color: #6563d9;
    font-size: 10px;
    font-weight: 700;
}
.selected-images-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}

.selected-image-card {
    overflow: hidden;
    border: 1px solid #eeeeF4;
    border-radius: 13px;
    background: #fff;
}

.selected-image-card-preview {
    position: relative;
    aspect-ratio: 3 / 4;
    overflow: hidden;
    background: #f1f1f5;
}

.selected-image-card-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.new-primary-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 5px 8px;
    border-radius: 7px;
    background: #6563d9;
    color: #fff;
    font-size: 10px;
}

.remove-selected-image {
    position: absolute;
    top: 8px;
    left: 8px;

    width: 27px;
    height: 27px;

    border: 0;
    border-radius: 50%;

    background: rgba(0, 0, 0, .65);
    color: #fff;

    font-size: 18px;
    line-height: 1;

    cursor: pointer;
}

.remove-selected-image:hover {
    background: #d25f68;
}

.selected-image-card-name {
    padding: 8px 9px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    color: #888a9b;
    font-size: 10px;
}

@media (max-width: 700px) {
    .selected-images-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

}
</style>
```

### بعد از جایگزینی

در ترمینال:

```powershell
npm run dev
```

اگر Vite از قبل در حال اجراست، اول:

```powershell
Ctrl + C
```

و دوباره:

```powershell
npm run dev
```

بعد برو:

```text
http://127.0.0.1:8000/admin
```

### یک نکته مهم

در این مرحله اگر صفحه باز شد، **هنوز هیچ محصولی ایجاد نکن** تا اول خود پنل را بررسی کنیم.

باید بتوانی:

**محصولات → افزودن محصول**

را بزنی و فرم را ببینی.

همچنین قسمت ویژگی‌ها باید با انتخاب دسته‌بندی، ویژگی‌های مربوط به همان دسته را نشان دهد؛ این همان معماری‌ای است که برای مثال باعث می‌شود:

* لباس → سایز، رنگ، جنس
* کفش → سایز کفش، رنگ، جنس
* عینک → نوع عینک، جنس فریم، رنگ
* کیف → نوع کیف، جنس، رنگ

از هم تفکیک شوند.

اگر این نسخه **بدون خطا بالا آمد**، مرحله بعدی را روی همین کد می‌سازیم: **ویرایش محصول + API `show/update`**؛ بعد می‌رویم سراغ مدیریت کامل دسته‌بندی و Attributeها.
