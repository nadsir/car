import { ref } from 'vue';
import axios from 'axios';

export const products = ref([]);
export const categories = ref([]);
export const adminCategories = ref([]);
export const attributes = ref([]);
export const categoryAttributes = ref({});

export const loading = ref(false);
export const saving = ref(false);
export const editingProductId = ref(null);
export const adminUser = ref(null);
export const isAuthenticated = ref(false);

export const productSearch = ref('');
export const productPage = ref(1);
export const productTotalPages = ref(1);
export const productTotal = ref(0);

const adminTokenStorageKey = 'car.admin_token';

function applyAdminToken(token) {
    axios.defaults.headers.common.Authorization =
        `Bearer ${token}`;
}

function clearAdminToken() {
    delete axios.defaults.headers.common.Authorization;
    localStorage.removeItem(adminTokenStorageKey);
    adminUser.value = null;
    isAuthenticated.value = false;
}

export async function restoreAdminSession() {
    const token = localStorage.getItem(adminTokenStorageKey);

    if (!token) {
        return false;
    }

    applyAdminToken(token);

    try {
        const response = await axios.get('/api/admin/me');

        adminUser.value = response.data.user;
        isAuthenticated.value = true;

        return true;
    } catch {
        clearAdminToken();

        return false;
    }
}

export async function login(credentials) {
    const response = await axios.post('/api/admin/login', credentials);

    localStorage.setItem(adminTokenStorageKey, response.data.token);
    applyAdminToken(response.data.token);
    adminUser.value = response.data.user;
    isAuthenticated.value = true;
}

export async function logout() {
    try {
        await axios.post('/api/admin/logout');
    } finally {
        clearAdminToken();
    }
}

export function createEmptyForm() {
    return {
        name: '',
        slug: '',
        sku: '',
        short_description: '',
        description: '',
        price: 0,
        compare_at_price: null,
        stock: 0,
        is_active: true,
        is_featured: false,
        category_ids: [],
        attribute_value_ids: [],
        custom_attribute_values: [],
        variants: [],
        images: [],
    };
}

export const form = ref(createEmptyForm());

export function resetForm() {
    form.value = createEmptyForm();
    editingProductId.value = null;
}

export async function loadAdminProducts() {
    const params = {
        page: productPage.value,
    };

    if (productSearch.value) {
        params.search = productSearch.value;
    }

    const response = await axios.get('/api/admin/products', { params });

    products.value = response.data.data || [];
    productTotalPages.value = response.data.last_page || 1;
    productTotal.value = response.data.total || 0;

    if (productPage.value > productTotalPages.value) {
        productPage.value = productTotalPages.value;
    }
}

export async function loadProducts() {
    const response = await axios.get('/api/products');

    products.value = response.data.data || [];
}

export async function loadMeta() {
    const response = await axios.get('/api/admin/products/meta');

    categories.value =
        response.data.categories || [];

    attributes.value =
        response.data.attributes || [];

    categoryAttributes.value =
        response.data.category_attributes || {};
}

export async function loadAdminCategories() {
    const response = await axios.get('/api/admin/categories');

    adminCategories.value = response.data.data || [];
}

export async function load() {
    loading.value = true;

    try {
        await Promise.all([
            loadAdminProducts(),
            loadMeta(),
            loadAdminCategories(),
        ]);
    } finally {
        loading.value = false;
    }
}

export async function loadCategoryAttributeConfig(categoryId) {
    const response = await axios.get(
        `/api/admin/categories/${categoryId}/attributes`
    );

    return response.data.data || [];
}

export async function saveCategoryAttributeConfig(
    categoryId,
    configurations
) {
    const response = await axios.put(
        `/api/admin/categories/${categoryId}/attributes`,
        { attributes: configurations }
    );

    return response.data.data || [];
}

export async function createCategory(data) {
    const response = await axios.post('/api/admin/categories', data);

    return response.data.data;
}

export async function updateCategory(categoryId, data) {
    const response = await axios.put(
        `/api/admin/categories/${categoryId}`,
        data
    );

    return response.data.data;
}

export async function removeCategory(categoryId) {
    await axios.delete(`/api/admin/categories/${categoryId}`);
}

export async function createAttribute(data) {
    const response = await axios.post('/api/admin/attributes', data);

    return response.data.data;
}

export async function updateAttribute(attributeId, data) {
    const response = await axios.put(
        `/api/admin/attributes/${attributeId}`,
        data
    );

    return response.data.data;
}

export async function removeAttribute(attributeId) {
    await axios.delete(`/api/admin/attributes/${attributeId}`);
}

export async function createAttributeValue(attributeId, data) {
    const response = await axios.post(
        `/api/admin/attributes/${attributeId}/values`,
        data
    );

    return response.data.data;
}

export async function updateAttributeValue(
    attributeId,
    valueId,
    data
) {
    const response = await axios.put(
        `/api/admin/attributes/${attributeId}/values/${valueId}`,
        data
    );

    return response.data.data;
}

export async function removeAttributeValue(attributeId, valueId) {
    await axios.delete(
        `/api/admin/attributes/${attributeId}/values/${valueId}`
    );
}

export function addVariant() {
    form.value.variants.push({
        sku: '',
        price: null,
        compare_at_price: null,
        stock: 0,
        is_active: true,
        attribute_value_ids: [],
    });
}

export function removeVariant(index) {
    form.value.variants.splice(index, 1);
}

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

export async function save() {
    saving.value = true;

    try {
        const response = await axios.post(
            '/api/admin/products',
            form.value
        );

        await loadAdminProducts();

        return response.data;
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Load Product For Edit
|--------------------------------------------------------------------------
*/

export async function loadProduct(id) {
    loading.value = true;

    try {
        const response = await axios.get(
            `/api/admin/products/${id}`
        );

        const product = response.data;

        editingProductId.value = product.id;

        form.value = {
            name: product.name || '',
            slug: product.slug || '',
            sku: product.sku || '',

            short_description:
                product.short_description || '',

            description:
                product.description || '',

            price:
                product.price ?? 0,

            compare_at_price:
                product.compare_at_price ?? null,

            stock:
                product.stock ?? 0,

            is_active:
                Boolean(product.is_active),

            is_featured:
                Boolean(product.is_featured),

            category_ids:
                (product.categories || [])
                    .map(category => category.id),

            attribute_value_ids:
                (product.attribute_values || [])
                    .map(value => value.id),

            custom_attribute_values:
                (product.custom_attribute_values || [])
                    .map(value => ({
                        attribute_id: value.attribute_id,
                        value: value.value_type === 'number'
                            ? value.value_number
                            : value.value_type === 'boolean'
                                ? Boolean(value.value_boolean)
                                : value.value_text,
                    })),

            variants:
                (product.variants || [])
                    .map(variant => ({
                        id: variant.id,

                        sku: variant.sku || '',

                        price:
                            variant.price ?? null,

                        compare_at_price:
                            variant.compare_at_price ?? null,

                        stock:
                            variant.stock ?? 0,

                        is_active:
                            Boolean(variant.is_active),

                        attribute_value_ids:
                            (variant.attribute_values || [])
                                .map(value => value.id),
                    })),
                    images:
    (product.images || []).map(image => ({
        id: image.id,
        path: image.path,
        alt_text: image.alt_text || '',
        is_primary: Boolean(image.is_primary),
        sort_order: image.sort_order ?? 0,
    })),
        };

        return product;

    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

export async function updateProduct() {
    if (!editingProductId.value) {
        return;
    }

    saving.value = true;

    try {
        const response = await axios.put(
            `/api/admin/products/${editingProductId.value}`,
            form.value
        );

        await loadAdminProducts();

        return response.data;

    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Product Images
|--------------------------------------------------------------------------
*/

export async function uploadProductImage(
    productId,
    file,
    altText = ''
) {
    if (!productId || !file) {
        return;
    }

    const formData = new FormData();

    formData.append('image', file);

    if (altText) {
        formData.append(
            'alt_text',
            altText
        );
    }

    const response = await axios.post(
        `/api/admin/products/${productId}/images`,
        formData,
        {
            headers: {
                'Content-Type':
                    'multipart/form-data',
            },
        }
    );

    if (!form.value.images) {
        form.value.images = [];
    }

    form.value.images.push(
        response.data
    );

    return response.data;
}
export async function setPrimaryProductImage(
    productId,
    imageId
) {
    if (!productId || !imageId) return;

    const response = await axios.patch(
        `/api/admin/products/${productId}/images/${imageId}/primary`
    );

    form.value.images = response.data;

    return response.data;
}




export async function reorderProductImages(
    productId,
    imageIds
) {
    if (!productId || !Array.isArray(imageIds)) {
        return;
    }

    const response = await axios.put(
        `/api/admin/products/${productId}/images/reorder`,
        {
            image_ids: imageIds,
        }
    );

    form.value.images = response.data;

    return response.data;
}
export async function removeProductImage(
    productId,
    imageId
) {
    if (!productId || !imageId) return;

    const response = await axios.delete(
        `/api/admin/products/${productId}/images/${imageId}`
    );

    form.value.images = response.data;

    return response.data;
}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

export async function removeProduct(id) {
    if (!confirm('این محصول حذف شود؟')) {
        return;
    }

    await axios.delete(
        `/api/admin/products/${id}`
    );

    await loadAdminProducts();
}

/*
|--------------------------------------------------------------------------
| Vehicle CRUD (Admin)
|--------------------------------------------------------------------------
*/

export async function loadAdminBrands() {
    const response = await axios.get('/api/admin/vehicles/brands');
    return response.data.data || [];
}

export async function createBrand(data) {
    const response = await axios.post('/api/admin/vehicles/brands', data);
    return response.data;
}

export async function updateBrand(id, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${id}`, data);
    return response.data;
}

export async function deleteBrand(id) {
    await axios.delete(`/api/admin/vehicles/brands/${id}`);
}

export async function loadAdminModels(brandId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models`);
    return response.data.data || [];
}

export async function createModel(brandId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models`, data);
    return response.data;
}

export async function updateModel(brandId, modelId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}`, data);
    return response.data;
}

export async function deleteModel(brandId, modelId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}`);
}

export async function loadAdminGenerations(brandId, modelId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations`);
    return response.data.data || [];
}

export async function createGeneration(brandId, modelId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations`, data);
    return response.data;
}

export async function updateGeneration(brandId, modelId, genId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}`, data);
    return response.data;
}

export async function deleteGeneration(brandId, modelId, genId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}`);
}

export async function loadAdminTrims(brandId, modelId, genId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims`);
    return response.data.data || [];
}

export async function createTrim(brandId, modelId, genId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims`, data);
    return response.data;
}

export async function updateTrim(brandId, modelId, genId, trimId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}`, data);
    return response.data;
}

export async function deleteTrim(brandId, modelId, genId, trimId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}`);
}

export async function loadAdminEngines(brandId, modelId, genId, trimId) {
    const response = await axios.get(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines`);
    return response.data.data || [];
}

export async function createEngine(brandId, modelId, genId, trimId, data) {
    const response = await axios.post(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines`, data);
    return response.data;
}

export async function updateEngine(brandId, modelId, genId, trimId, engineId, data) {
    const response = await axios.put(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines/${engineId}`, data);
    return response.data;
}

export async function deleteEngine(brandId, modelId, genId, trimId, engineId) {
    await axios.delete(`/api/admin/vehicles/brands/${brandId}/models/${modelId}/generations/${genId}/trims/${trimId}/engines/${engineId}`);
}

/*
|--------------------------------------------------------------------------
| Vehicle Hierarchy (Public API - for selectors)
|--------------------------------------------------------------------------
*/

export async function loadBrands() {
    const response = await axios.get('/api/vehicles/brands');
    return response.data.data || [];
}

export async function loadBrandModels(brandId) {
    const response = await axios.get(`/api/vehicles/brands/${brandId}/models`);
    return response.data.data || [];
}

export async function loadModelGenerations(modelId) {
    const response = await axios.get(`/api/vehicles/models/${modelId}/generations`);
    return response.data.data || [];
}

export async function loadGenerationTrims(genId) {
    const response = await axios.get(`/api/vehicles/generations/${genId}/trims`);
    return response.data.data || [];
}

export async function loadTrimEngines(trimId) {
    const response = await axios.get(`/api/vehicles/trims/${trimId}/engines`);
    return response.data.data || [];
}

/*
|--------------------------------------------------------------------------
| Product ↔ Vehicle Compatibility
|--------------------------------------------------------------------------
*/

export async function loadProductCompatibility(productId) {
    const response = await axios.get(`/api/admin/products/${productId}/vehicle-compat`);
    return response.data.data || [];
}

export async function attachProductCompatibility(productId, engineIds) {
    const response = await axios.post(`/api/admin/products/${productId}/vehicle-compat`, {
        vehicle_engine_ids: engineIds,
    });
    return response.data;
}

export async function detachProductCompatibility(productId, engineId) {
    await axios.delete(`/api/admin/products/${productId}/vehicle-compat/${engineId}`);
}

/*
|--------------------------------------------------------------------------
| Orders (Admin)
|--------------------------------------------------------------------------
*/

export const adminOrders = ref([]);
export const adminOrder = ref(null);
export const adminOrderLoading = ref(false);
export const adminOrderError = ref('');
export const adminOrderSuccess = ref('');

export const adminOrderSearch = ref('');
export const adminOrderStatusFilter = ref('');
export const adminOrderPage = ref(1);
export const adminOrderTotalPages = ref(1);
export const adminOrderTotal = ref(0);

export async function loadAdminOrders() {
    adminOrderLoading.value = true;
    adminOrderError.value = '';
    adminOrderSuccess.value = '';

    try {
        const params = {
            page: adminOrderPage.value,
        };

        if (adminOrderSearch.value) {
            params.search = adminOrderSearch.value;
        }

        if (adminOrderStatusFilter.value) {
            params.status = adminOrderStatusFilter.value;
        }

        const response = await axios.get('/api/admin/orders', { params });

        adminOrders.value = response.data.data || [];
        adminOrderTotalPages.value = response.data.last_page || 1;
        adminOrderTotal.value = response.data.total || 0;

        if (adminOrderPage.value > adminOrderTotalPages.value) {
            adminOrderPage.value = adminOrderTotalPages.value;
        }
    } catch (error) {
        adminOrderError.value = error.response?.data?.message || 'دریافت سفارش‌ها انجام نشد.';
    } finally {
        adminOrderLoading.value = false;
    }
}

export async function loadAdminOrder(id) {
    adminOrderLoading.value = true;
    adminOrderError.value = '';
    adminOrderSuccess.value = '';
    adminOrder.value = null;

    try {
        const response = await axios.get(`/api/admin/orders/${id}`);
        adminOrder.value = response.data.order || null;
        return adminOrder.value;
    } catch (error) {
        adminOrderError.value = error.response?.data?.message || 'دریافت سفارش انجام نشد.';
    } finally {
        adminOrderLoading.value = false;
    }
}

export async function updateAdminOrderStatus(id, status, cancelledReason = null) {
    adminOrderError.value = '';
    adminOrderSuccess.value = '';

    try {
        const response = await axios.patch(`/api/admin/orders/${id}/status`, {
            status,
            cancelled_reason: cancelledReason,
        });

        adminOrderSuccess.value = 'وضعیت سفارش با موفقیت تغییر یافت.';
        return response.data;
    } catch (error) {
        adminOrderError.value = error.response?.data?.errors?.status?.[0] || error.response?.data?.message || 'تغییر وضعیت انجام نشد.';
        throw error;
    }
}

export function getAllowedTransitions(currentStatus) {
    const transitions = {
        pending: ['confirmed', 'cancelled'],
        confirmed: ['processing', 'cancelled'],
        processing: ['shipped'],
        shipped: ['delivered'],
        delivered: [],
        cancelled: [],
    };
    return transitions[currentStatus] || [];
}

export function getStatusLabel(status) {
    const labels = {
        pending: 'در انتظار پرداخت',
        confirmed: 'تأیید شده',
        processing: 'در حال پردازش',
        shipped: 'ارسال شده',
        delivered: 'تحویل شده',
        cancelled: 'لغو شده',
    };
    return labels[status] || status;
}
