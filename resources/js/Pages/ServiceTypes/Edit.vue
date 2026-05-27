<script setup>
import AppLayout from "@/Layouts/Index.vue";
import { useForm, usePage, Link } from "@inertiajs/vue3";

const props = defineProps({
    serviceType: Object,
    locales: Array,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";
console.log("Current Locale:", page.props.langs);
const form = useForm({
    gym_id: props.serviceType.gym_id ?? "",
    slug: props.serviceType.slug ?? "",
    translations: {
        en: {
            id: props.serviceType.translations?.en?.id ?? "",
            name: props.serviceType.translations?.en?.name ?? "",
            description: props.serviceType.translations?.en?.description ?? "",
        },
        ru: {
            id: props.serviceType.translations?.ru?.id ?? "",
            name: props.serviceType.translations?.ru?.name ?? "",
            description: props.serviceType.translations?.ru?.description ?? "",
        },
        hy: {
            id: props.serviceType.translations?.hy?.id ?? "",
            name: props.serviceType.translations?.hy?.name ?? "",
            description: props.serviceType.translations?.hy?.description ?? "",
        },
    },
});

const submit = () => {
    form.put(
        route("service-types.update", {
            locale: currentLocale,
            id: props.serviceType.id,
        }),
    );
};
</script>

<template>
    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Edit Service Type</h5>

                <Link
                    :href="
                        route('service-types.index', { locale: currentLocale })
                    "
                    class="btn btn-secondary"
                >
                    Back
                </Link>
            </div>

            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row g-4">
                        <!-- <div class="col-md-6">
                            <label class="form-label">Gym ID</label>
                            <input
                                v-model="form.gym_id"
                                type="number"
                                class="form-control"
                                placeholder="Enter gym id"
                            />
                            <div v-if="form.errors.gym_id" class="text-danger small mt-1">
                                {{ form.errors.gym_id }}
                            </div>
                        </div> -->

                        <div class="col-md-6">
                            <label class="form-label">Slug</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="form-control"
                                placeholder="Enter slug"
                            />
                            <div
                                v-if="form.errors.slug"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors.slug }}
                            </div>
                        </div>

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">English</h6>
                        </div>

                        <div class="col-md-6 hidden">
                            <input
                                v-model="form.translations.en.id"
                                type="text"
                                class="form-control"
                                placeholder="Enter English id"
                            />
                            <div
                                v-if="form.errors['translations.en.id']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.en.id"] }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Name (EN)</label>
                            <input
                                v-model="form.translations.en.name"
                                type="text"
                                class="form-control"
                                placeholder="Enter English name"
                            />
                            <div
                                v-if="form.errors['translations.en.name']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.en.name"] }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description (EN)</label>
                            <input
                                v-model="form.translations.en.description"
                                type="text"
                                class="form-control"
                                placeholder="Enter English description"
                            />
                            <div
                                v-if="
                                    form.errors['translations.en.description']
                                "
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.en.description"] }}
                            </div>
                        </div>

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">Russian</h6>
                        </div>
                        <div class="col-md-6 hidden">
                            <input
                                v-model="form.translations.ru.id"
                                type="text"
                                class="form-control"
                                placeholder="Enter Russian id"
                            />
                            <div
                                v-if="form.errors['translations.ru.id']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.ru.id"] }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name (RU)</label>
                            <input
                                v-model="form.translations.ru.name"
                                type="text"
                                class="form-control"
                                placeholder="Enter Russian name"
                            />
                            <div
                                v-if="form.errors['translations.ru.name']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.ru.name"] }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description (RU)</label>
                            <input
                                v-model="form.translations.ru.description"
                                type="text"
                                class="form-control"
                                placeholder="Enter Russian description"
                            />
                            <div
                                v-if="
                                    form.errors['translations.ru.description']
                                "
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.ru.description"] }}
                            </div>
                        </div>

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">Armenian</h6>
                        </div>
                        <div class="col-md-6 hidden">
                            <input
                                v-model="form.translations.hy.id"
                                type="text"
                                class="form-control"
                                placeholder="Enter Armenian id"
                            />
                            <div
                                v-if="form.errors['translations.hy.id']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.hy.id"] }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name (HY)</label>
                            <input
                                v-model="form.translations.hy.name"
                                type="text"
                                class="form-control"
                                placeholder="Enter Armenian name"
                            />
                            <div
                                v-if="form.errors['translations.hy.name']"
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.hy.name"] }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description (HY)</label>
                            <input
                                v-model="form.translations.hy.description"
                                type="text"
                                class="form-control"
                                placeholder="Enter Armenian description"
                            />
                            <div
                                v-if="
                                    form.errors['translations.hy.description']
                                "
                                class="text-danger small mt-1"
                            >
                                {{ form.errors["translations.hy.description"] }}
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 mt-4">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? "Updating..." : "Update" }}
                            </button>

                            <Link
                                :href="
                                    route('service-types.index', {
                                        locale: currentLocale,
                                    })
                                "
                                class="btn btn-label-secondary"
                            >
                                Cancel
                            </Link>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
