<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { ref } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { Link, usePage } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import ToggleStatus from "@/Components/ToggleStatus.vue";
import DeleteButton from "@/Components/DeleteButton.vue";
import Pagination from "@/Components/Pagination.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    gyms: Object,
});

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const gymsList = ref(props.gyms?.data ?? props.gyms ?? []);
const pagination = ref(props.gyms?.links ? props.gyms : null);
</script>

<template>
    <Head :title="t('ui.gyms_list')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('ui.gyms_list') }}
            </h2>
        </template>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ t('ui.gyms_list') }}</h5>
                <Link
                    class="btn create-new btn-primary"
                    tabindex="0"
                    type="button"
                    :href="route('gym.create', { locale: currentLocale })"
                >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i>
                            <span class="d-none d-sm-inline-block">{{ t('ui.add_gym') }}</span>
                        </span>
                    </span>
                </Link>
            </div>

            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered align-middle"> <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 70px;">{{ t('ui.logo') }}</th> <th>{{ t('filter.name') }}</th>
                                <th>{{ t('inventory.address') }}</th>
                                <th>{{ t('filter.phone') }}</th>
                                <th>{{ t('auth.email') }}</th>
                                <th style="width: 80px;">{{ t('people.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="gym in gymsList" :key="gym.id">
                                <td>{{ gym.id }}</td>

                                <td class="text-center">
                                    <img
                                        v-if="gym.logo"
                                        :src="`/storage/${gym.logo}`"
                                        :alt="t('ui.gym_logo')"
                                        class="rounded-circle object-fit-cover"
                                        style="width: 40px; height: 40px; border: 1px solid #e5e7eb;"
                                    />
                                    <div
                                        v-else
                                        class="rounded-circle bg-label-secondary d-flex align-items-center justify-content-center mx-auto"
                                        style="width: 40px; height: 40px;"
                                    >
                                        <i class="ti tabler-building icon-base text-secondary fs-4"></i>
                                    </div>
                                </td>

                                <td>{{ gym.name }}</td>
                                <td>{{ gym.address }}</td>
                                <td>{{ gym.phone }}</td>
                                <td>{{ gym.email }}</td>

                                <td>
                                    <div class="dropdown">
                                        <button
                                            type="button"
                                            class="btn p-0 dropdown-toggle hide-arrow"
                                            data-bs-toggle="dropdown"
                                        >
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="route('gym.edit', { locale: currentLocale, id: gym.id })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                {{ t('people.edit') }}
                                            </Link>

                                            <a class="dropdown-item waves-effect" href="javascript:void(0);">
                                                <DeleteButton
                                                    :model="'gyms'"
                                                    :prefix="'gym'"
                                                    :locale="currentLocale"
                                                    :model-id="gym.id"
                                                    @deleted="
                                                        gymsList = gymsList.filter((h) => h.id !== $event)
                                                    "
                                                />
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="gymsList.length === 0">
                                <td colspan="7" class="text-center text-muted">{{ t('ui.no_gyms') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="pagination && pagination.links" class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
