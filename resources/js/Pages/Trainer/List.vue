<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed, ref, watch } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
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
const freezingTrainer = ref(null)
const freezeForm = useForm({ start_date: '', end_date: '' })
const cancellingFreeze = ref(null)
const cancelForm = useForm({})
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const openFreeze = trainer => {
    freezingTrainer.value = trainer
    freezeForm.reset()
    freezeForm.clearErrors()
}
const submitFreeze = () => {
    freezeForm.post(route('trainer.freeze', { locale: currentLocale.value, id: freezingTrainer.value.id }), {
        onSuccess: () => { freezingTrainer.value = null },
    })
}
const openCancel = (trainer, freeze) => {
    cancellingFreeze.value = { trainer, freeze }
    cancelForm.clearErrors()
}
const submitCancel = () => {
    const { trainer, freeze } = cancellingFreeze.value
    cancelForm.post(route('trainer.freeze.cancel', {
        locale: currentLocale.value, id: trainer.id, freezeId: freeze.id,
    }), { onSuccess: () => { cancellingFreeze.value = null } })
}
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
                                <th>{{ t('status.status') }}</th>
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
                                <td>{{ user.trainer_status === 'frozen' ? t('trainer_freeze.frozen') : (user.active ? t('status.active') : t('people.inactive')) }}</td>
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
                                            <button
                                                v-if="hasAnyRole(['owner', 'super_admin', 'admin']) && user.active && user.trainer_status !== 'frozen'"
                                                type="button"
                                                class="dropdown-item"
                                                @click="openFreeze(user)"
                                            >
                                                <i class="icon-base ti tabler-snowflake me-1"></i>
                                                {{ t('trainer_freeze.freeze_trainer') }}
                                            </button>
                                            <template v-if="hasAnyRole(['owner', 'super_admin', 'admin'])">
                                                <button
                                                    v-for="freeze in (user.trainer_freezes ?? [])"
                                                    :key="freeze.id"
                                                    type="button"
                                                    class="dropdown-item"
                                                    @click="openCancel(user, freeze)"
                                                >
                                                    <i class="icon-base ti tabler-snowflake-off me-1"></i>
                                                    {{ t('trainer_freeze.cancel_freeze') }} ({{ formatDate(freeze.start_date) }} – {{ formatDate(freeze.end_date) }})
                                                </button>
                                            </template>
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
                                                v-if="!hasAnyRole(['founder'])"
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
                                                v-if="hasAnyRole(['owner', 'super_admin', 'admin'])"
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
        <div v-if="freezingTrainer" class="trainer-freeze-backdrop" @click.self="freezingTrainer = null">
            <form class="card trainer-freeze-dialog" @submit.prevent="submitFreeze">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ t('trainer_freeze.freeze_trainer') }}: {{ freezingTrainer.name }} {{ freezingTrainer.surname }}</h5>
                    <button type="button" class="btn-close" @click="freezingTrainer = null"></button>
                </div>
                <div class="card-body">
                    <label class="form-label" for="trainer-freeze-start">{{ t('trainer_freeze.start_date') }}</label>
                    <input id="trainer-freeze-start" v-model="freezeForm.start_date" type="date" class="form-control mb-1" required />
                    <div v-if="freezeForm.errors.start_date" class="text-danger small mb-3">{{ freezeForm.errors.start_date }}</div>
                    <label class="form-label mt-3" for="trainer-freeze-end">{{ t('trainer_freeze.end_date') }}</label>
                    <input id="trainer-freeze-end" v-model="freezeForm.end_date" type="date" :min="freezeForm.start_date" class="form-control mb-1" required />
                    <div v-if="freezeForm.errors.end_date" class="text-danger small">{{ freezeForm.errors.end_date }}</div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="freezingTrainer = null">{{ t('people.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" :disabled="freezeForm.processing">{{ t('trainer_freeze.confirm') }}</button>
                </div>
            </form>
        </div>
        <div v-if="cancellingFreeze" class="trainer-freeze-backdrop" @click.self="cancellingFreeze = null">
            <form class="card trainer-freeze-dialog" @submit.prevent="submitCancel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ t('trainer_freeze.cancel_freeze') }}</h5>
                    <button type="button" class="btn-close" @click="cancellingFreeze = null"></button>
                </div>
                <div class="card-body">
                    <p class="mb-2">{{ t('trainer_freeze.cancel_question') }}</p>
                    <strong>{{ cancellingFreeze.trainer.name }} {{ cancellingFreeze.trainer.surname }}</strong>
                    <div>{{ formatDate(cancellingFreeze.freeze.start_date) }} – {{ formatDate(cancellingFreeze.freeze.end_date) }}</div>
                    <div v-if="cancelForm.errors.freeze" class="text-danger small mt-2">{{ cancelForm.errors.freeze }}</div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="cancellingFreeze = null">{{ t('people.cancel') }}</button>
                    <button type="submit" class="btn btn-danger" :disabled="cancelForm.processing">{{ t('trainer_freeze.cancel_confirm') }}</button>
                </div>
            </form>
        </div>
    </Index>
</template>

<style scoped>
.trainer-freeze-backdrop { position: fixed; inset: 0; z-index: 1090; display: grid; place-items: center; background: rgba(0, 0, 0, .45); padding: 1rem; }
.trainer-freeze-dialog { width: min(100%, 480px); }
</style>
