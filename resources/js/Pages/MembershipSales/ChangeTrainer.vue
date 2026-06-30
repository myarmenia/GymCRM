<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const page = usePage()
const currentLocale = computed(() => page.props.lang ?? page.props.locale ?? 'hy')

const props = defineProps({
    membershipSale: Object,
    personMembership: Object,
    currentTrainer: Object,
    trainers: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    trainer_id: '',
})

const translatedName = item => item?.translations?.find(translation => translation.locale === currentLocale.value)?.name
    ?? item?.name
    ?? item?.slug
    ?? (item?.id ? `#${item.id}` : '-')
const fullName = user => `${user?.name ?? ''} ${user?.surname ?? ''}`.trim() || '-'
const formatDate = value => value ? String(value).slice(0, 10) : '-'
const formatAmount = value => Number(value || 0).toFixed(2)
const availableTrainers = computed(() => props.trainers.filter(trainer => Number(trainer.id) !== Number(props.currentTrainer?.id)))

const selectTrainer = trainerId => {
    form.trainer_id = Number(form.trainer_id) === Number(trainerId) ? '' : trainerId
}

const submit = () => {
    form.patch(route('membership_sale.change_trainer.update', {
        locale: currentLocale.value,
        id: props.membershipSale.id,
    }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Փոխել մարզիչին" />

    <Index>
        <template #header>
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <h2 class="text-xl font-semibold mb-0">
                    Փոխել մարզիչին
                </h2>
                <Link
                    class="btn btn-secondary"
                    :href="route('membership_sale.list', { locale: currentLocale })"
                >
                    Վերադառնալ
                </Link>
            </div>
        </template>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Վաճառքի տվյալներ</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span>Հաճախորդ</span>
                            <strong>{{ fullName(membershipSale.person) }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Աբոնեմենտ</span>
                            <strong>{{ translatedName(membershipSale.membership_plan) }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Վաճառքի համար</span>
                            <strong>#{{ membershipSale.id }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Ընդհանուր գին</span>
                            <strong>{{ formatAmount(membershipSale.total_price) }}</strong>
                        </div>
                        <div class="detail-row mb-0">
                            <span>Վերջնական գին</span>
                            <strong>{{ formatAmount(membershipSale.final_price) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Աբոնեմենտի տվյալներ</h5>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span>Սկիզբ</span>
                            <strong>{{ formatDate(personMembership.start_date) }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Ավարտ</span>
                            <strong>{{ formatDate(personMembership.valid_at || personMembership.end_date) }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Կարգավիճակ</span>
                            <strong>{{ personMembership.status ?? '-' }}</strong>
                        </div>
                        <div class="detail-row mb-0">
                            <span>Գործող մարզիչ</span>
                            <strong>{{ fullName(currentTrainer) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form
            class="card"
            @submit.prevent="submit"
        >
            <div class="card-header">
                <h5 class="mb-0">Ընտրել նոր մարզիչ</h5>
            </div>
            <div class="card-body">
                <div
                    v-if="availableTrainers.length"
                    class="trainer-grid"
                >
                    <button
                        v-for="trainer in availableTrainers"
                        :key="trainer.id"
                        type="button"
                        class="trainer-card"
                        :class="{ selected: Number(form.trainer_id) === Number(trainer.id) }"
                        @click="selectTrainer(trainer.id)"
                    >
                        <span class="trainer-icon">
                            <i class="icon-base ti tabler-user-star"></i>
                        </span>
                        <span class="trainer-info">
                            <span class="fw-semibold">{{ fullName(trainer) }}</span>
                            <span class="text-muted small">{{ trainer.phone ?? trainer.email ?? '-' }}</span>
                        </span>
                        <span
                            v-if="Number(form.trainer_id) === Number(trainer.id)"
                            class="selected-mark"
                        >
                            <i class="icon-base ti tabler-check"></i>
                        </span>
                    </button>
                </div>
                <div
                    v-else
                    class="alert alert-warning mb-0"
                >
                    Այս աբոնեմենտի համար այլ մարզիչ կցված չէ։
                </div>

                <InputError :message="form.errors.trainer_id" />
                <InputError :message="form.errors.membership_sale_id" />

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <Link
                        class="btn btn-label-secondary"
                        :href="route('membership_sale.list', { locale: currentLocale })"
                    >
                        Չեղարկել
                    </Link>
                    <PrimaryButton
                        :disabled="form.processing || !form.trainer_id"
                    >
                        Պահպանել
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </Index>
</template>

<style scoped>
.detail-row {
    align-items: center;
    border-bottom: 1px solid var(--bs-border-color);
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: .625rem 0;
}

.detail-row:last-child {
    border-bottom: 0;
}

.detail-row span {
    color: var(--bs-secondary-color);
}

.trainer-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}

.trainer-card {
    align-items: center;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    display: grid;
    gap: .85rem;
    grid-template-columns: auto 1fr auto;
    min-height: 82px;
    padding: 1rem;
    position: relative;
    text-align: left;
    transition: .15s ease-in-out;
}

.trainer-card:hover {
    border-color: rgba(var(--bs-primary-rgb), .55);
    box-shadow: 0 .25rem .75rem rgba(var(--bs-primary-rgb), .08);
    transform: translateY(-1px);
}

.trainer-card.selected {
    background: rgba(var(--bs-primary-rgb), .08);
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 1px var(--bs-primary);
}

.trainer-icon,
.selected-mark {
    align-items: center;
    border-radius: 50%;
    display: inline-flex;
    flex: 0 0 auto;
    justify-content: center;
}

.trainer-icon {
    background: rgba(var(--bs-primary-rgb), .1);
    color: var(--bs-primary);
    font-size: 1.25rem;
    height: 44px;
    width: 44px;
}

.trainer-info {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
}

.selected-mark {
    background: var(--bs-primary);
    color: var(--bs-white);
    font-size: .95rem;
    height: 28px;
    width: 28px;
}
</style>
