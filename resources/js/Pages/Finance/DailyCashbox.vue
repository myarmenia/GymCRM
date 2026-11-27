<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import Pagination from '@/Components/Pagination.vue'
import { translate } from '/resources/js/trans'

const props = defineProps({
    date: { type: String, required: true },
    summary: { type: Object, required: true },
    entries: { type: Object, required: true },
})

const page = usePage()
const locale = computed(() => page.props.locale ?? page.props.lang ?? 'hy')
const t = key => translate(page.props.translations, `app.daily_cashbox.${key}`)
const amount = value => Number(value || 0).toLocaleString(locale.value === 'hy' ? 'hy-AM' : locale.value, {
    maximumFractionDigits: 2,
})
</script>

<template>
    <Head :title="t('title')" />
    <Index>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mb-1">{{ t('title') }}</h2>
                <p class="text-muted mb-1">{{ t('description') }}</p>
                <span class="text-muted">{{ date }} · {{ t('timezone') }}</span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted mb-2">{{ t('received_total') }}</div>
                    <h3 class="mb-0">{{ amount(summary.received_amount) }} ֏</h3>
                </div></div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted mb-2">{{ t('payments_count') }}</div>
                    <h3 class="mb-0">{{ summary.payments_count }}</h3>
                </div></div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted mb-2">{{ t('today_sales_count') }}</div>
                    <h3 class="mb-0">{{ summary.today_sales_count }}</h3>
                </div></div>
            </div>
        </div>

        <div v-if="summary.payment_methods.length" class="row g-3 mb-4">
            <div v-for="method in summary.payment_methods" :key="method.id" class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100"><div class="card-body d-flex justify-content-between gap-3">
                    <span>{{ method.name }}</span>
                    <strong class="text-nowrap">{{ amount(method.amount) }} ֏</strong>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ t('entries') }}</h5></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr>
                        <th>{{ t('time') }}</th>
                        <th>{{ t('customer') }}</th>
                        <th>{{ t('membership') }}</th>
                        <th>{{ t('sale') }}</th>
                        <th>{{ t('payment_method') }}</th>
                        <th class="text-end">{{ t('received_amount') }}</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="entry in entries.data" :key="entry.id">
                            <td class="text-nowrap">{{ entry.occurred_at.slice(11, 16) }}</td>
                            <td>{{ entry.customer }}</td>
                            <td>{{ entry.membership }}</td>
                            <td><span class="badge" :class="entry.previous_sale ? 'bg-label-secondary' : 'bg-label-primary'">
                                {{ t(entry.previous_sale ? 'previous_sale' : 'today_sale') }}
                            </span></td>
                            <td>
                                {{ entry.payment_method }}
                                <span v-if="entry.card_type" class="text-muted"> · {{ entry.card_type }}</span>
                            </td>
                            <td class="text-end text-nowrap fw-semibold">{{ amount(entry.amount) }} ֏</td>
                        </tr>
                        <tr v-if="!entries.data.length">
                            <td colspan="6" class="text-center text-muted py-5">{{ t('empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="entries.last_page > 1" class="card-body pt-0">
                <Pagination :links="entries.links" />
            </div>
        </div>
    </Index>
</template>
