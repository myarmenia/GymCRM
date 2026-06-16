<script setup>
import Index from "@/Layouts/Index.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },

    parentCategories: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const form = useForm({
    type: props.category.parent_id ? "subcategory" : "category",
    parent_id: props.category.parent_id ?? null,
    // status: Boolean(props.category.status),
    status:true,
    translations: {
        en: {
            id: props.category.translations?.en?.id ?? null,
            name: props.category.translations?.en?.name ?? "",
        },
        ru: {
            id: props.category.translations?.ru?.id ?? null,
            name: props.category.translations?.ru?.name ?? "",
        },
        hy: {
            id: props.category.translations?.hy?.id ?? null,
            name: props.category.translations?.hy?.name ?? "",
        },
    },
});


const typelog = form.type;
console.log("typelog",typelog);

const changeType = () => {
    if (form.type === "category") {
        form.parent_id = null;
    }
};

const submit = () => {
    form.put(
        route("categories.update", {
            locale: currentLocale,
            id: props.category.id,
        }),
    );
};
</script>

<template>
    <Head title="Խմբագրել կատեգորիա" />

    <Index>
        <div class="container-xxl py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">
                        {{
                            form.type === "category"
                                ? "Խմբագրել կատեգորիա"
                                : "Խմբագրել ենթակատեգորիա"
                        }}
                    </h3>

                    <p class="text-muted mb-0">
                        Խմբագրել կատեգորիա կամ ենթակատեգորիա
                    </p>
                </div>

                <Link
                    class="btn btn-outline-secondary rounded-pill px-4"
                    :href="route('categories.index', { locale: currentLocale })"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    Հետ գնալ
                </Link>
            </div>

            <div class="card border-0 shadow-sm category-form-card">
                <div class="card-body p-4">
                    <form @submit.prevent="submit">
                        <div class="row g-4">
                            <div class="col-md-6" v-if = "typelog === 'subcategory'">
                                <label class="form-label">Ստեղծել տիպ</label>

                                <select
                                    v-model="form.type"
                                    class="form-select rounded-pill"
                                    @change="changeType"
                                >
                                    <option value="category">
                                        Կատեգորիա
                                    </option>

                                    <option value="subcategory">
                                        Ենթակատեգորիա
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="form.type === 'subcategory'"
                                class="col-md-6"
                            >
                                <label class="form-label">
                                    Ծնող կատեգորիա
                                </label>

                                <select
                                    v-model="form.parent_id"
                                    class="form-select rounded-pill"
                                >
                                    <option :value="null" disabled>
                                        ընտրել ծնող կատեգորիա
                                    </option>

                                    <option
                                        v-for="parentCategory in parentCategories"
                                        :key="parentCategory.id"
                                        :value="parentCategory.id"
                                        :disabled="parentCategory.id === category.id"
                                    >
                                        {{ parentCategory.name }}
                                    </option>
                                </select>

                                <div
                                    v-if="form.errors.parent_id"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors.parent_id }}
                                </div>
                            </div>

                            <div class="col-12">
                                <h5 class="fw-bold mb-3">Թարգմանություններ</h5>
                            </div>

                            <!-- <div class="col-md-4">
                                <label class="form-label">Name EN</label>

                                <input
                                    v-model="form.translations.en.name"
                                    type="text"
                                    class="form-control rounded-pill"
                                    placeholder="Example: Food"
                                />

                                <div
                                    v-if="form.errors['translations.en.name']"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors["translations.en.name"] }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Name RU</label>

                                <input
                                    v-model="form.translations.ru.name"
                                    type="text"
                                    class="form-control rounded-pill"
                                    placeholder="Например: Еда"
                                />

                                <div
                                    v-if="form.errors['translations.ru.name']"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors["translations.ru.name"] }}
                                </div>
                            </div> -->

                            <div class="col-md-4">
                                <label class="form-label">Անուն HY</label>

                                <input
                                    v-model="form.translations.hy.name"
                                    type="text"
                                    class="form-control rounded-pill"
                                    placeholder="Օրինակ՝ Սնունդ"
                                />

                                <div
                                    v-if="form.errors['translations.hy.name']"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors["translations.hy.name"] }}
                                </div>
                            </div>

                            <!-- <div class="col-md-6">
                                <label class="form-label">Icon</label>

                                <div class="input-group">
                                    <span
                                        class="input-group-text rounded-start-pill"
                                    >
                                        <i
                                            class="icon-base ti"
                                            :class="
                                                form.image || 'tabler-category'
                                            "
                                        ></i>
                                    </span>

                                    <input
                                        v-model="form.image"
                                        type="text"
                                        class="form-control rounded-end-pill"
                                        placeholder="tabler-chef-hat"
                                    />
                                </div>

                                <div
                                    v-if="form.errors.image"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors.image }}
                                </div>
                            </div> -->

                            <!-- <div class="col-md-6">
                                <label class="form-label">Status</label>

                                <select
                                    v-model="form.status"
                                    class="form-select rounded-pill"
                                >
                                    <option :value="true">Active</option>
                                    <option :value="false">Inactive</option>
                                </select>

                                <div
                                    v-if="form.errors.status"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors.status }}
                                </div>
                            </div> -->
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <Link
                                class="btn btn-light rounded-pill px-4"
                                :href="
                                    route('categories.index', {
                                        locale: currentLocale,
                                    })
                                "
                            >
                                Չեղարկել
                            </Link>

                            <button
                                type="submit"
                                class="btn btn-primary rounded-pill px-4"
                                :disabled="form.processing"
                            >
                                <i
                                    class="icon-base ti tabler-device-floppy me-1"
                                ></i>

                                {{
                                    form.type === "category"
                                        ? "Թարմացնել կատեգորիան"
                                        : "Թարմացնել ենթակատեգորիան"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Index>
</template>

<style scoped>
.category-form-card {
    border-radius: 24px;
}

.form-control,
.form-select,
.input-group-text {
    border-color: #e6e6ef;
}
</style>