<script setup>
import AppLayout from "@/Layouts/Index.vue";
import { useForm, usePage, Link } from "@inertiajs/vue3";

defineProps({
    locales: Array,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const form = useForm({
    // gym_id: "",
    slug: "",
    price: "",
    price_per_hour: "",
    translations: {
        en: {
            name: "",
        },
        ru: {
            name: "",
        },
        hy: {
            name: "",
        },
    },
});

const submit = () => {
    form.post(route("room-types.store", { locale: currentLocale }));
};
</script>

<template>
    <AppLayout>
        <div class="card">
            <div
                class="card-header d-flex justify-content-between align-items-center"
            >
                <h5 class="mb-0">Create Room Type</h5>

                <Link
                    :href="route('room-types.index', { locale: currentLocale })"
                    class="btn btn-secondary"
                >
                    Back
                </Link>
            </div>

            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row g-4">
                        <div class="flex-row-justify-between g-25">
                            <div class="col-md-6 mb-7">
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
                            <div class="col-md-6 mb-7">
                                <label class="form-label">Price</label>
                                <input
                                    v-model="form.price"
                                    type="text"
                                    class="form-control"
                                    placeholder="Enter price"
                                />
                                <div
                                    v-if="form.errors.price"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors.price }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Price Per Hour</label>
                                <input
                                    v-model="form.price_per_hour"
                                    type="text"
                                    class="form-control"
                                    placeholder="Enter price per hour"
                                />
                                <div
                                    v-if="form.errors.price_per_hour"
                                    class="text-danger small mt-1"
                                >
                                    {{ form.errors.price_per_hour }}
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">English</h6>
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

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">Russian</h6>
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

                        <div class="col-12">
                            <hr />
                            <h6 class="mb-3">Armenian</h6>
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

                        <div class="col-12 d-flex gap-2 mt-4">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? "Saving..." : "Create" }}
                            </button>

                            <Link
                                :href="
                                    route('room-types.index', {
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
