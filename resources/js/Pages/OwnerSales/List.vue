<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Index from '@/Layouts/Index.vue';
import Pagination from '@/Components/Pagination.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useTrans } from '/resources/js/trans';

const props = defineProps({ sales: Object, summary: Object, gyms: Array, filters: Object });
const page = usePage();
const trans = useTrans;
const { confirm } = useConfirm();
const locale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');
const filter = reactive({
    tab: props.filters?.tab ?? 'all',
    gym_id: props.filters?.gym_id ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});
const tabs = ['all', 'paid', 'unpaid', 'ending', 'expired', 'inactive', 'cancelled', 'archived'];
const browserLocale = computed(() => ({ hy: 'hy-AM', ru: 'ru-RU', en: 'en-US' }[locale.value] ?? 'hy-AM'));
const formatAmount = value => Number(value || 0).toLocaleString(browserLocale.value, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const formatDate = value => value
    ? new Intl.DateTimeFormat(browserLocale.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    : '—';
const summaryCards = computed(() => [
    { label: trans('page.total_count'), value: props.summary?.total_count ?? 0, icon: 'tabler-list-numbers', class: 'bg-label-primary text-primary' },
    { label: trans('page.paid_amount'), value: formatAmount(props.summary?.paid_amount), icon: 'tabler-circle-check', class: 'bg-label-success text-success' },
    { label: trans('page.unpaid_amount'), value: formatAmount(props.summary?.unpaid_amount), icon: 'tabler-cash-off', class: 'bg-label-danger text-danger' },
    { label: trans('page.ending_count'), value: props.summary?.ending_count ?? 0, icon: 'tabler-clock', class: 'bg-label-warning text-warning' },
    { label: trans('page.expired_count'), value: props.summary?.expired_count ?? 0, icon: 'tabler-calendar-x', class: 'bg-label-danger text-danger' },
    { label: trans('page.inactive_count'), value: props.summary?.inactive_count ?? 0, icon: 'tabler-player-pause', class: 'bg-label-secondary text-secondary' },
    { label: trans('page.cancelled_count'), value: props.summary?.cancelled_count ?? 0, icon: 'tabler-ban', class: 'bg-label-secondary text-secondary' },
    { label: trans('page.archived_count'), value: props.summary?.archived_count ?? 0, icon: 'tabler-archive', class: 'bg-label-secondary text-secondary' },
]);

const applyFilters = () => router.get(route('owner-sales.index', { locale: locale.value }), filter, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const setTab = (tab) => {
    filter.tab = tab;
    applyFilters();
};

const cancelSale = (sale) => {
    if (!window.confirm(trans('page.confirm_cancel'))) return;
    router.patch(route('owner-sales.cancel', { locale: locale.value, ownerSale: sale.id }), {}, { preserveScroll: true });
};

const deleteSale = async (sale) => {
    const accepted = await confirm(trans('page.confirm_delete'));
    if (!accepted) return;
    router.delete(route('owner-sales.destroy', { locale: locale.value, ownerSale: sale.id }), { preserveScroll: true });
};

const archiveSale = async (sale) => {
    const accepted = await confirm(trans('page.confirm_archive'));
    if (!accepted) return;
    router.patch(route('owner-sales.archive', { locale: locale.value, ownerSale: sale.id }), {}, { preserveScroll: true });
};

const resetDates = () => {
    filter.date_from = '';
    filter.date_to = '';
    applyFilters();
};

const badgeClass = (status) => ({
    active: 'bg-label-success', ending: 'bg-label-warning', expired: 'bg-label-danger',
    future: 'bg-label-info', cancelled: 'bg-label-secondary',
}[status] ?? 'bg-label-secondary');
</script>

<template>
    <Head :title="trans('page.title')" />
    <Index>
        <div v-if="page.props.flash?.success" class="alert alert-success">{{ page.props.flash.success }}</div>
        <div class="row g-4 mb-4">
            <div v-for="card in summaryCards" :key="card.label" class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div class="avatar flex-shrink-0">
                            <span class="avatar-initial rounded" :class="card.class">
                                <i :class="['icon-base ti', card.icon]"></i>
                            </span>
                        </div>
                        <div>
                            <div class="text-muted small">{{ card.label }}</div>
                            <div class="h5 mb-0">{{ card.value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">{{ trans('page.title') }}</h5>
                    <div class="text-muted small">{{ trans('page.subtitle') }}</div>
                </div>
                <Link :href="route('owner-sales.create', { locale })" class="btn btn-primary">
                    <i class="ti tabler-plus me-1"></i>{{ trans('page.add') }}
                </Link>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button v-for="tab in tabs" :key="tab" type="button" class="btn btn-sm"
                        :class="filter.tab === tab ? 'btn-primary' : 'btn-outline-secondary'" @click="setTab(tab)">
                        {{ trans(`page.${tab}`) }}
                    </button>
                    <select v-model="filter.gym_id" class="form-select form-select-sm ms-auto" style="max-width: 260px" @change="applyFilters">
                        <option value="">{{ trans('page.all_gyms') }}</option>
                        <option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option>
                    </select>
                </div>
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-sm-6 col-md-3"><label class="form-label small">{{ trans('page.date_from') }}</label><input v-model="filter.date_from" type="date" class="form-control form-control-sm" @change="applyFilters"></div>
                    <div class="col-sm-6 col-md-3"><label class="form-label small">{{ trans('page.date_to') }}</label><input v-model="filter.date_to" type="date" class="form-control form-control-sm" @change="applyFilters"></div>
                    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!filter.date_from && !filter.date_to" @click="resetDates">{{ trans('page.reset_dates') }}</button></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead><tr>
                            <th style="width: 70px">№</th>
                            <th>{{ trans('page.gym') }}</th><th>{{ trans('page.amount') }}</th>
                            <th>{{ trans('page.payment_type') }}</th><th>{{ trans('page.payment_status') }}</th>
                            <th>{{ trans('page.access_status') }}</th>
                            <th>{{ trans('page.period') }}</th><th>{{ trans('page.period_status') }}</th>
                            <th>{{ trans('page.actions') }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="(sale, index) in sales.data" :key="sale.id">
                                <td>{{ ((sales.current_page ?? 1) - 1) * (sales.per_page ?? 10) + index + 1 }}</td>
                                <td class="fw-semibold">{{ sale.gym?.name ?? '—' }}</td>
                                <td>{{ formatAmount(sale.amount) }}</td>
                                <td>{{ ['cash', 'transfer'].includes(sale.payment_type) ? trans(`page.${sale.payment_type}`) : '—' }}</td>
                                <td><span class="badge" :class="sale.payment_status === 'paid' ? 'bg-label-success' : 'bg-label-danger'">{{ trans(`page.${sale.payment_status}`) }}</span></td>
                                <td><span class="badge" :class="sale.status === 'active' ? 'bg-label-success' : 'bg-label-secondary'">{{ trans(sale.status === 'active' ? 'page.status_active' : sale.status === 'inactive' ? 'page.status_inactive' : sale.status === 'archived' ? 'page.archived' : 'page.cancelled') }}</span></td>
                                <td>{{ formatDate(sale.starts_at) }} – {{ formatDate(sale.ends_at) }}</td>
                                <td><span class="badge" :class="badgeClass(sale.period_status)">{{ trans(`page.${sale.period_status}`) }}</span></td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                            <i class="icon-base ti tabler-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <Link v-if="sale.status !== 'archived'" :href="route('owner-sales.edit', { locale, ownerSale: sale.id })" class="dropdown-item waves-effect">
                                                <i class="icon-base ti tabler-pencil me-1"></i>{{ trans('page.edit_action') }}
                                            </Link>
                                            <button v-if="!['cancelled', 'archived'].includes(sale.status)" type="button" class="dropdown-item waves-effect" @click="cancelSale(sale)">
                                                <i class="icon-base ti tabler-ban me-1"></i>{{ trans('page.cancel_action') }}
                                            </button>
                                            <button v-if="sale.status !== 'archived'" type="button" class="dropdown-item waves-effect" @click="archiveSale(sale)">
                                                <i class="icon-base ti tabler-archive me-1"></i>{{ trans('page.archive_action') }}
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item waves-effect text-danger" @click="deleteSale(sale)">
                                                <i class="icon-base ti tabler-trash me-1"></i>{{ trans('page.delete_action') }}
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!sales.data?.length"><td colspan="9" class="text-center text-muted py-5">{{ trans('page.no_records') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <Pagination v-if="sales.links?.length > 3" :links="sales.links" />
            </div>
        </div>
    </Index>
</template>
