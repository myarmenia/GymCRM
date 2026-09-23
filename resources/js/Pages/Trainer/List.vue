<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import Pagination from "@/Components/Pagination.vue";
import TableFilter from "@/Components/TableFilter.vue";
import { useAuth } from "@/composables/useAuth";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    users: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? "hy");

const usersList = ref(props.users.data);
const pagination = ref(props.users);
const { hasAnyRole } = useAuth();
const filters = ref({
    ...Object.fromEntries(new URLSearchParams(window.location.search)),
    ...props.filters,
});

watch(
    () => props.users,
    (users) => {
        usersList.value = users.data;
        pagination.value = users;
    },
);

const applyFilters = (payload) => {
    router.get(
        route("trainer.index", { locale: currentLocale.value }),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    filters.value = {};

    router.get(
        route("trainer.index", { locale: currentLocale.value }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head :title="t('staff_reports.trainer_list')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('staff_reports.trainer_list') }}
            </h2>
        </template>

        <TableFilter
            v-model="filters"
            name-mode="full"
            :date-fields="[]"
            @filter="applyFilters"
            @reset="resetFilters"
        />

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ t('staff_reports.trainer_list') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ t('auth.name') }}</th>
                                <th>{{ t('filter.surname') }}</th>
                                <th>{{ t('filter.phone') }}</th>
                                <th>{{ t('staff_reports.email_address') }}</th>
                                <th>{{ t('action.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in usersList"
                                :key="user.id"
                            >
                                <td>{{ user.id }}</td>
                                <td>{{ user.name }}</td>
                                <td>{{ user.surname }}</td>
                                <td>{{ user.phone }}</td>
                                <td>{{ user.email }}</td>
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
                                                :href="route('trainer.profile', {
                                                    locale: currentLocale,
                                                    id: user.id,
                                                })"
                                            >
                                                <i class="icon-base ti tabler-eye me-1"></i>
                                                {{ t('people.view_profile') }}
                                            </Link>
                                            <Link
                                                class="dropdown-item waves-effect"
                                                :href="route('trainer.salary', {
                                                    locale: currentLocale,
                                                    id: user.id,
                                                })"
                                            >
                                                <i class="icon-base ti tabler-cash me-1"></i>
                                                {{ t('staff_reports.pay_salary') }}
                                            </Link>
                                            <Link
                                                v-if="hasAnyRole(['owner', 'super_admin'])"
                                                class="dropdown-item waves-effect"
                                                :href="route('trainer.edit', {
                                                    locale: currentLocale,
                                                    id: user.id,
                                                })"
                                            >
                                                <i class="icon-base ti tabler-pencil me-1"></i>
                                                {{ t('action.edit') }}
                                            </Link>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>
