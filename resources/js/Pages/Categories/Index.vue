<script setup>
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import DeleteButton from "@/Components/DeleteButton.vue";

const props = defineProps({
    categories: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
            from: 0,
            to: 0,
        }),
    },
});

const categories = ref([...(props.categories.data ?? [])]);
console.log("categories", props.categories.data);
const openedCategory = ref(null);
const errorModalMessage = ref("");
const showDeleteError = (message) => {
    errorModalMessage.value = message;

    const modal = new bootstrap.Modal(
        document.getElementById("deleteErrorModal"),
    );

    modal.show();
};
const toggleCategory = (id) => {
    openedCategory.value = openedCategory.value === id ? null : id;
};
const page = usePage();

const currentLocale = page.props.locale ?? "en";

const removeCategory = (id) => {
    categories.value = categories.value.filter(
        (category) => category.id !== id,
    );

    if (openedCategory.value === id) {
        openedCategory.value = null;
    }
};

const removeSubCategory = (categoryId, subId) => {
    const category = categories.value.find((c) => c.id === categoryId);

    if (!category) return;

    category.subcategories = category.subcategories.filter(
        (sub) => sub.id !== subId,
    );
};
</script>

<template>
    <Head title="Կատեգորիաներ" />

    <Index>
        <div class="container-xxl py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Կատեգորիաներ</h3>
                    <p class="text-muted mb-0">
                        Կառավարել կատեգորիաներ և ենթակատեգորիաներ
                    </p>
                </div>

                <Link
                    :href="
                        route('categories.create', { locale: currentLocale })
                    "
                    class="btn btn-primary add-product-btn"
                >
                    <i class="icon-base ti tabler-plus me-1"></i>
                    Ավելացնել կատեգորիա
                </Link>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="60"></th>
                                <th>Կատեգորիա</th>
                                <th>Ենթակատեգորիա</th>
                                <!-- <th>Status</th> -->
                                <th class="text-end">Գործողություն</th>
                            </tr>
                        </thead>

                        <tbody>
                            <template
                                v-for="category in categories"
                                :key="category.id"
                            >
                                <tr
                                    class="category-row"
                                    style="cursor: pointer"
                                    @click="toggleCategory(category.id)"
                                >
                                    <td>
                                        <button class="btn btn-sm btn-icon">
                                            <i
                                                class="icon-base ti tabler-chevron-down transition-icon"
                                                :class="{
                                                    rotated:
                                                        openedCategory ===
                                                        category.id,
                                                }"
                                            ></i>
                                        </button>
                                    </td>

                                    <td>
                                        <div
                                            class="d-flex align-items-center gap-3"
                                        >
                                            <div>
                                                <h6 class="mb-0 fw-semibold">
                                                    {{ category.name }}
                                                </h6>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-label-primary">
                                            {{
                                                category.subcategories
                                                    ?.length ?? 0
                                            }}
                                            հատ
                                        </span>
                                    </td>

                                    <!-- <td>
                                        <span class="badge bg-label-success">
                                            Active
                                        </span>
                                    </td> -->

                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button
                                                class="btn btn-sm btn-icon"
                                                data-bs-toggle="dropdown"
                                                @click.stop
                                            >
                                                <i
                                                    class="icon-base ti tabler-dots-vertical"
                                                ></i>
                                            </button>

                                            <div
                                                class="dropdown-menu dropdown-menu-end"
                                            >
                                                <Link
                                                    class="product-action-item"
                                                    :href="
                                                        route(
                                                            'categories.edit',
                                                            {
                                                                locale: currentLocale,
                                                                id: category.id,
                                                            },
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="icon-base ti tabler-pencil me-1"
                                                    ></i>
                                                    <span class="edit-span"
                                                        >Խմբագրել</span
                                                    >
                                                </Link>
                                                <!-- <Link
                                                    class="dropdown-item"
                                                    :href="
                                                        route(
                                                            'categories.edit',
                                                            {
                                                                locale: currentLocale,
                                                                id: category.id,
                                                            },
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="icon-base ti tabler-pencil me-2"
                                                    ></i>
                                                    Edit
                                                </Link> -->
                                                <div @click.stop>
                                                    <DeleteButton
                                                        prefix="tables"
                                                        :model="'inventorycategory'"
                                                        :model-id="category.id"
                                                        @deleted="
                                                            removeCategory
                                                        "
                                                        @delete-error="
                                                            showDeleteError
                                                        "
                                                    />
                                                </div>
                                                <!-- <Link
                                                    class="dropdown-item text-danger"
                                                    :href="
                                                        route(
                                                            'categories.destroy',
                                                            {
                                                                locale: currentLocale,
                                                                id: category.id,
                                                            },
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="icon-base ti tabler-trash me-2"
                                                    ></i>
                                                    Delete
                                                </Link> -->
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <!-- SUBCATEGORIES -->
                                <tr class="collapse-row">
                                    <td colspan="5" class="p-0 border-0">
                                        <div
                                            v-show="
                                                openedCategory === category.id
                                            "
                                        >
                                            <div class="subcategory-wrapper">
                                                <div
                                                    v-if="
                                                        !category.subcategories
                                                            ?.length
                                                    "
                                                    class="empty-subcategory"
                                                >
                                                    Չկա ենթակատեգորիա
                                                </div>

                                                <div
                                                    v-for="sub in category.subcategories ??
                                                    []"
                                                    :key="sub.id"
                                                    class="subcategory-item"
                                                >
                                                    <div
                                                        class="d-flex justify-content-between align-items-center"
                                                    >
                                                        <div
                                                            class="d-flex align-items-center gap-3"
                                                        >
                                                            <span
                                                                class="subcategory-dot"
                                                            ></span>

                                                            <div>
                                                                <div
                                                                    class="fw-medium"
                                                                >
                                                                    {{
                                                                        sub
                                                                            .translations[0]
                                                                            ?.name ??
                                                                        "Unnamed"
                                                                    }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-center gap-2"
                                                        >
                                                            <Link
                                                                class="dropdown-item sub-edit-btn"
                                                                :href="
                                                                    route(
                                                                        'categories.edit',
                                                                        {
                                                                            locale: currentLocale,
                                                                            id: sub.id,
                                                                        },
                                                                    )
                                                                "
                                                            >
                                                                <i
                                                                    class="icon-base ti tabler-pencil"
                                                                ></i>
                                                                Խմբագրել
                                                            </Link>

                                                            <div @click.stop>
                                                                <DeleteButton
                                                                    prefix="tables"
                                                                    :model="'inventorycategory'"
                                                                    :model-id="
                                                                        sub.id
                                                                    "
                                                                    @deleted="
                                                                        removeSubCategory(
                                                                            category.id,
                                                                            $event,
                                                                        )
                                                                    "
                                                                    @delete-error="
                                                                        showDeleteError
                                                                    "
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div
            class="modal fade"
            id="deleteErrorModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Ջնջել չհաջողվեց</h5>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        {{ errorModalMessage }}
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.add-product-btn {
    padding: 6px 12px;
    gap: 6px;
}
.dropdown-menu {
    /* min-width: 120px; */
    padding: 12px 12px;
}
.product-action-item {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    /* width: 100%; */
    padding-bottom: 8px;
    padding-top: 3px;
    margin-right: 0;
    font-size: 15px;
    font-weight: 2;
    color: rgb(71, 62, 62);
    text-decoration: none;
    /* line-height: 24px; */
}

/* .product-action-item:hover {
    background-color: rgb(211, 207, 207);
} */

.product-action-item i {
    font-size: 20px;
    color: rgb(83, 74, 74);
    /* width: 20px; */
}

.edit-span {
    color: rgb(71, 62, 62);
}

.category-card {
    border-radius: 24px;
    transition: 0.25s ease;
    background: #fff;
}

.category-card:hover {
    transform: translateY(-5px);
}

.add-subcategory-btn {
    background: linear-gradient(
        270deg,
        rgba(var(--bs-primary-rgb), 0.7) 0%,
        var(--bs-primary) 100%
    );
    text-align: center;
    padding: 14px 0;
    color: #fff;
    border-radius: 222px;
}

.category-icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    background: rgba(115, 103, 240, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-icon i {
    font-size: 26px;
    color: #7367f0;
}

.subcategory-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.subcategory-item {
    background: #f8f7fa;
    border-radius: 16px;
    padding: 14px 16px;
    transition: 0.2s;
}

.subcategory-item:hover {
    background: #f1f0f5;
}

.subcategory-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #7367f0;
    margin-right: 12px;
}

.subcategory-name {
    font-weight: 500;
    color: #444050;
}

.btn-light-primary {
    background: rgba(115, 103, 240, 0.12);
    color: #7367f0;
    border: none;
}

.btn-light-primary:hover {
    background: #7367f0;
    color: white;
}

.category-row {
    transition: 0.2s;
}

.category-row:hover {
    background: #f8f7fa;
}

.category-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(115, 103, 240, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-icon i {
    color: #7367f0;
    font-size: 20px;
}

.subcategory-wrapper {
    padding: 18px;
    background: #f8f7fa;
}

.subcategory-item {
    background: white;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 12px;
    border: 1px solid #ebe9f1;
}

.subcategory-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #7367f0;
}

.empty-subcategory {
    text-align: center;
    padding: 20px;
    color: #999;
}

.btn-light-danger {
    background: rgba(234, 84, 85, 0.12);
    color: #ea5455;
}

.transition-icon {
    transition: 0.3s ease;
}

.rotated {
    transform: rotate(180deg);
}

.sub-edit-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
}
</style>
