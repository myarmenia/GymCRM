<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },

    parentCategories: {
        type: Array,
        default: () => [],
    },
    langs: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const availableLangs = computed(() =>
    Array.isArray(props.langs) && props.langs.length ? props.langs : ["hy"],
);

const form = useForm({
    type: props.category.parent_id ? "subcategory" : "category",
    parent_id: props.category.parent_id ?? null,
    // status: Boolean(props.category.status),
    status:true,
    translations: Object.fromEntries(
        availableLangs.value.map((code) => [
            code,
            {
                id: props.category.translations?.[code]?.id ?? null,
                name: props.category.translations?.[code]?.name ?? "",
            },
        ]),
    ),
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
    <Head :title="t('inventory.edit_category')" />

    <Index>
        <div class="container-xxl py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">
                        {{
                            form.type === "category"
                                ? t('inventory.edit_category')
                                : t('inventory.edit_subcategory')
                        }}
                    </h3>

                    <p class="text-muted mb-0">
                        {{ t('inventory.edit_category_or_subcategory') }}
                    </p>
                </div>

                <Link
                    class="btn btn-outline-secondary rounded-pill px-4"
                    :href="route('categories.index', { locale: currentLocale })"
                >
                    <i class="icon-base ti tabler-arrow-left me-1"></i>
                    {{ t('inventory.back') }}
                </Link>
            </div>

            <div class="card border-0 shadow-sm category-form-card">
                <div class="card-body p-4">
                    <form @submit.prevent="submit">
                        <div class="row g-4">
                            <div class="col-md-6" v-if = "typelog === 'subcategory'">
                                <label class="form-label">{{ t('inventory.create_type') }}</label>

                                <select
                                    v-model="form.type"
                                    class="form-select rounded-pill"
                                    @change="changeType"
                                >
                                    <option value="category">
                                        {{ t('people.category') }}
                                    </option>

                                    <option value="subcategory">
                                        {{ t('inventory.subcategory') }}
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="form.type === 'subcategory'"
                                class="col-md-6"
                            >
                                <label class="form-label">
                                    {{ t('inventory.parent_category') }}
                                </label>

                                <select
                                    v-model="form.parent_id"
                                    class="form-select rounded-pill"
                                >
                                    <option :value="null" disabled>
                                        {{ t('inventory.select_parent_category_2') }}
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
                                <h5 class="fw-bold mb-3">
                                    {{ t('inventory.translations') }}
                                </h5>
                            </div>

                            <div
                                v-for="code in availableLangs"
                                :key="code"
                                class="col-md-4"
                            >
                                <label class="form-label">
                                    {{ t('inventory.name') }}
                                    ({{ code.toUpperCase() }})
                                </label>

                                <input
                                    v-model="form.translations[code].name"
                                    type="text"
                                    class="form-control rounded-pill"
                                    :placeholder="t('inventory.name')"
                                />

                                <div
                                    v-if="
                                        form.errors[
                                            `translations.${code}.name`
                                        ]
                                    "
                                    class="text-danger small mt-1"
                                >
                                    {{
                                        form.errors[
                                            `translations.${code}.name`
                                        ]
                                    }}
                                </div>
                            </div>

                            <!-- <div class="col-md-6">
                                <label class="form-label">{{ t('ui.icon') }}</label>

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
                                <label class="form-label">{{ t('status.status') }}</label>

                                <select
                                    v-model="form.status"
                                    class="form-select rounded-pill"
                                >
                                    <option :value="true">{{ t('status.active') }}</option>
                                    <option :value="false">{{ t('status.inactive') }}</option>
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
                                {{ t('confirm.cancel') }}
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
                                        ? t('inventory.update_category')
                                        : t('inventory.update_subcategory')
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