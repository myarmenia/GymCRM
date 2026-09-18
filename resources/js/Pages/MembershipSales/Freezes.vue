<script setup>
import { translate } from '/resources/js/trans'
import { computed } from 'vue'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const t = (key, replacements = {}) => translate(page.props.translations, `app.${key}`, replacements)
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSale: Object,
    personMembership: Object,
    freezes: {
        type: Array,
        default: () => [],
    },
    allowedFreezeCount: {
        type: [Number, String],
        default: 0,
    },
    usedFreezeCount: {
        type: [Number, String],
        default: 0,
    },
    remainingFreezeCount: {
        type: [Number, String],
        default: 0,
    },
})

const form = useForm({
    start_date: '',
    end_date: '',
    notes: '',
})

const remaining = computed(() => Number(props.remainingFreezeCount || 0))
const hasFreezableStatus = computed(() => ['waiting', 'active', 'frozen'].includes(props.personMembership?.status))
const canAddFreeze = computed(() => remaining.value > 0 && hasFreezableStatus.value)
const freezeOverlapMessage = t('sales.freeze_cannot_start_during_an_existing_freeze_period')

const personName = person => `${person?.name ?? ''} ${person?.surname ?? ''}`.trim() || '-'
const translatedName = item => {
    return item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
        ?? item?.name
        ?? item?.slug
        ?? (item?.id ? `#${item.id}` : '-')
}
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const statusLabel = status => ({
    waiting: t('people.waiting'),
    active: t('membership.active'),
    frozen: t('people.frozen'),
    expired: t('people.expired'),
    deleted: t('people.deleted'),
    cancelled: t('people.cancelled'),
}[status] ?? status ?? '-')
const dateInsideFreezePeriod = value => {
    if (!value) {
        return false
    }

    const selectedDate = String(value).slice(0, 10)

    return props.freezes.some(freeze => {
        const startDate = freeze.start_date ? String(freeze.start_date).slice(0, 10) : null
        const endDate = freeze.end_date ? String(freeze.end_date).slice(0, 10) : null

        return startDate && endDate && selectedDate >= startDate && selectedDate <= endDate
    })
}

const submit = () => {
    if (dateInsideFreezePeriod(form.start_date)) {
        form.setError('start_date', freezeOverlapMessage)
        return
    }

    form.post(route('membership_sale.freezes.store', {
        locale: currentLocale.value,
        id: props.membershipSale.id,
    }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head :title="t('people.freeze_membership')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">
                {{ t('people.freeze_membership') }}
            </h2>
        </template>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.membership_details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.client') }}</span>
                            <strong>{{ personName(personMembership.person) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('people.membership') }}</span>
                            <strong>{{ translatedName(personMembership.membership_plan) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('membership.start') }}</span>
                            <strong>{{ formatDate(personMembership.start_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('membership.end') }}</span>
                            <strong>{{ formatDate(personMembership.end_date) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('people.valid_until') }}</span>
                            <strong>{{ formatDate(personMembership.valid_at) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ t('membership.status') }}</span>
                            <span class="badge bg-label-success">{{ statusLabel(personMembership.status) }}</span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.allowed_freezes') }}</span>
                            <strong>{{ allowedFreezeCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ t('sales.used_freezes') }}</span>
                            <strong>{{ usedFreezeCount }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-muted">{{ t('sales.remaining_freezes') }}</span>
                            <strong class="text-primary">{{ remainingFreezeCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('sales.previous_freezes') }}</h5>
                    </div>
                    <div class="card-body">
                        <div
                            v-if="freezes.length"
                            class="table-responsive text-nowrap"
                        >
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ t('membership.start') }}</th>
                                        <th>{{ t('membership.end') }}</th>
                                        <th>{{ t('people.notes') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="freeze in freezes"
                                        :key="freeze.id"
                                    >
                                        <td>{{ formatDate(freeze.start_date) }}</td>
                                        <td>{{ formatDate(freeze.end_date) }}</td>
                                        <td>{{ freeze.notes ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="text-muted"
                        >
                            {{ t('sales.no_freezes_yet') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="canAddFreeze"
            class="card mb-4"
        >
            <div class="card-header">
                <h5 class="mb-0">{{ t('sales.new_freeze') }}</h5>
            </div>
            <form
                class="card-body"
                @submit.prevent="submit"
            >
                <div class="row g-4">
                    <div class="col-md-6">
                        <InputLabel
                            for="start_date"
                            :value="t('sales.freeze_start')"
                        />
                        <TextInput
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            class="form-control"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel
                            for="end_date"
                            :value="t('sales.freeze_end')"
                        />
                        <TextInput
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            class="form-control"
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <div class="col-md-12">
                        <InputLabel
                            for="notes"
                            :value="t('people.notes')"
                        />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="form-control"
                            rows="3"
                            :placeholder="t('sales.enter_notes')"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="pt-4 d-flex justify-content-end gap-2">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('membership_sale.list', { locale: currentLocale })"
                    >
                        {{ t('people.cancel') }}
                    </Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ t('people.freeze_membership') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <div
            v-else
            class="alert alert-warning"
        >
            {{ remaining > 0 && !hasFreezableStatus ? t('sales.a_membership_with_this_status_cannot_be_frozen') : t('sales.no_freezes_remaining') }}
        </div>
    </Index>
</template>
