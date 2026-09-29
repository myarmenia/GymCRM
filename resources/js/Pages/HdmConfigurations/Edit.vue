<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Index from '@/Layouts/Index.vue';
import InputError from '@/Components/InputError.vue';
import { useTrans } from '/resources/js/trans';

const props = defineProps({
    config: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const page = usePage();
const trans = useTrans;
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');
const showCashierModal = ref(false);
const editingCashierId = ref(null);
const configForm = useForm({
    name: props.config.name,
    ip: props.config.ip,
    port: props.config.port,
    password: '',
    status: Boolean(props.config.status),
});
const cashierForm = useForm({
    user_id: '',
    name: '',
    login: '',
    pin: '',
    status: true,
});

const saveConfig = () => {
    configForm.put(route('hdm-configurations.update', {
        locale: currentLocale.value,
        hdmConfig: props.config.id,
    }), {
        preserveScroll: true,
        onSuccess: () => { configForm.password = ''; },
    });
};

const openAddCashier = () => {
    editingCashierId.value = null;
    cashierForm.reset();
    cashierForm.clearErrors();
    cashierForm.status = true;
    showCashierModal.value = true;
};

const openEditCashier = (cashier) => {
    editingCashierId.value = cashier.id;
    cashierForm.clearErrors();
    cashierForm.user_id = cashier.user_id;
    cashierForm.name = cashier.name;
    cashierForm.login = cashier.login;
    cashierForm.pin = '';
    cashierForm.status = Boolean(cashier.status);
    showCashierModal.value = true;
};

const closeCashierModal = () => {
    if (cashierForm.processing) return;
    showCashierModal.value = false;
    editingCashierId.value = null;
    cashierForm.reset();
    cashierForm.clearErrors();
};

const saveCashier = () => {
    const options = { preserveScroll: true, onSuccess: closeCashierModal };
    if (editingCashierId.value) {
        cashierForm.put(route('hdm-configurations.cashiers.update', {
            locale: currentLocale.value,
            hdmConfig: props.config.id,
            cashier: editingCashierId.value,
        }), options);
        return;
    }
    cashierForm.post(route('hdm-configurations.cashiers.store', {
        locale: currentLocale.value,
        hdmConfig: props.config.id,
    }), options);
};

const removeCashier = (cashier) => {
    if (!window.confirm(trans('page.messages.confirm_delete_cashier'))) return;
    router.delete(route('hdm-configurations.cashiers.destroy', {
        locale: currentLocale.value,
        hdmConfig: props.config.id,
        cashier: cashier.id,
    }), { preserveScroll: true });
};

const userName = (user) => {
    if (!user) return '—';
    return [user.name, user.surname].filter(Boolean).join(' ') || user.email;
};

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(currentLocale.value, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
    : '—';
</script>

<template>
    <Head :title="trans('page.edit_title')" />
    <Index>
        <template #header>
            <div class="d-flex align-items-center gap-2">
                <Link :href="route('hdm-configurations.index', { locale: currentLocale })" class="btn btn-sm btn-icon btn-label-secondary">
                    <i class="ti tabler-arrow-left"></i>
                </Link>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-0">{{ trans('page.edit_title') }}</h2>
                    <div class="small text-muted">{{ config.gym?.name }} · {{ config.name }}</div>
                </div>
            </div>
        </template>

        <div v-if="page.props.flash?.success" class="alert alert-success" role="alert">
            {{ page.props.flash.success }}
        </div>
        <div v-if="page.props.flash?.error" class="alert alert-danger" role="alert">
            {{ page.props.flash.error }}
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ trans('page.config_section') }}</h5></div>
            <form class="card-body" @submit.prevent="saveConfig">
                <div class="row g-4">
                    <div class="col-md-12">
                        <label class="form-label">{{ trans('page.fields.gym') }}</label>
                        <input type="text" class="form-control" :value="config.gym?.name" disabled />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name">{{ trans('page.fields.name') }} *</label>
                        <input id="name" v-model="configForm.name" type="text" class="form-control" />
                        <InputError class="mt-1" :message="configForm.errors.name" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="port">{{ trans('page.fields.port') }} *</label>
                        <input id="port" v-model="configForm.port" type="number" min="1" max="65535" class="form-control" />
                        <InputError class="mt-1" :message="configForm.errors.port" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ip">{{ trans('page.fields.ip') }} *</label>
                        <input id="ip" v-model="configForm.ip" type="text" class="form-control" />
                        <InputError class="mt-1" :message="configForm.errors.ip" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">{{ trans('page.fields.password') }}</label>
                        <input id="password" v-model="configForm.password" type="password" class="form-control" autocomplete="new-password" :placeholder="trans('page.placeholders.keep_password')" />
                        <InputError class="mt-1" :message="configForm.errors.password" />
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input id="config_status" v-model="configForm.status" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="config_status">{{ trans('page.fields.active') }}</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4" :disabled="configForm.processing">
                    <span v-if="configForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                    {{ trans('page.actions.save_changes') }}
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1">{{ trans('page.cashiers_section') }}</h5>
                    <div class="small text-muted">{{ trans('page.cashiers_hint') }}</div>
                </div>
                <button type="button" class="btn btn-primary" @click="openAddCashier">
                    <i class="ti tabler-plus me-1"></i>{{ trans('page.actions.add_cashier') }}
                </button>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead><tr>
                            <th>{{ trans('page.fields.id') }}</th>
                            <th>{{ trans('page.fields.cashier_name') }}</th>
                            <th>{{ trans('page.fields.login') }}</th>
                            <th>{{ trans('page.fields.user') }}</th>
                            <th>{{ trans('page.fields.status') }}</th>
                            <th>{{ trans('page.fields.last_login') }}</th>
                            <th class="text-end">{{ trans('page.fields.actions') }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="cashier in config.cashiers" :key="cashier.id">
                                <td>{{ cashier.id }}</td>
                                <td class="fw-semibold">{{ cashier.name }}</td>
                                <td class="font-monospace">{{ cashier.login }}</td>
                                <td><div>{{ userName(cashier.user) }}</div><small class="text-muted">{{ cashier.user?.email }}</small></td>
                                <td><span class="badge" :class="cashier.status ? 'bg-label-success' : 'bg-label-danger'">{{ cashier.status ? trans('page.status.active') : trans('page.status.inactive') }}</span></td>
                                <td>{{ formatDate(cashier.last_login_at) }}</td>
                                <td><div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-primary" @click="openEditCashier(cashier)"><i class="ti tabler-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger" @click="removeCashier(cashier)"><i class="ti tabler-trash"></i></button>
                                </div></td>
                            </tr>
                            <tr v-if="!config.cashiers?.length"><td colspan="7" class="text-center text-muted py-4">{{ trans('page.messages.no_cashiers') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="showCashierModal" class="modal fade show" style="display: block" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" @submit.prevent="saveCashier">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingCashierId ? trans('page.edit_cashier_title') : trans('page.add_cashier_title') }}</h5>
                        <button type="button" class="btn-close" @click="closeCashierModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="cashier_user">{{ trans('page.fields.user') }} *</label>
                            <select id="cashier_user" v-model="cashierForm.user_id" class="form-select">
                                <option value="" disabled>{{ trans('page.placeholders.select_user') }}</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ userName(user) }} — {{ user.email }}</option>
                            </select>
                            <InputError class="mt-1" :message="cashierForm.errors.user_id" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cashier_name">{{ trans('page.fields.cashier_name') }} *</label>
                            <input id="cashier_name" v-model="cashierForm.name" type="text" class="form-control" />
                            <InputError class="mt-1" :message="cashierForm.errors.name" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cashier_login">{{ trans('page.fields.login') }} *</label>
                            <input id="cashier_login" v-model="cashierForm.login" type="text" class="form-control" autocomplete="off" />
                            <InputError class="mt-1" :message="cashierForm.errors.login" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cashier_pin">{{ trans('page.fields.pin') }} {{ editingCashierId ? '' : '*' }}</label>
                            <input id="cashier_pin" v-model="cashierForm.pin" type="password" inputmode="numeric" class="form-control" autocomplete="new-password" :placeholder="editingCashierId ? trans('page.placeholders.keep_pin') : '••••'" />
                            <InputError class="mt-1" :message="cashierForm.errors.pin" />
                        </div>
                        <div class="form-check form-switch">
                            <input id="cashier_status" v-model="cashierForm.status" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="cashier_status">{{ trans('page.fields.active') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" @click="closeCashierModal">{{ trans('page.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-primary" :disabled="cashierForm.processing">
                            <span v-if="cashierForm.processing" class="spinner-border spinner-border-sm me-1"></span>{{ trans('page.actions.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div v-if="showCashierModal" class="modal-backdrop fade show" @click="closeCashierModal"></div>
    </Index>
</template>
