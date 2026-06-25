<script setup>
defineProps({
    summaries: {
        type: Array,
        default: () => [],
    },
})

const formatHours = value => Number(value || 0).toFixed(2)
</script>

<template>
    <div class="row g-4 mb-4">
        <div
            v-for="summary in summaries"
            :key="summary.id"
            class="col-12 col-md-6 col-xl-3"
        >
            <div class="card h-100 trainer-summary-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h5 class="mb-1">{{ summary.first_name }} {{ summary.last_name }}</h5>
                            <div class="text-muted small">
                                {{ summary.email || summary.phone || 'Կապի տվյալներ չկան' }}
                            </div>
                        </div>
                        <span class="badge bg-label-primary align-self-start">
                            {{ formatHours(summary.weekly_occupied_hours) }} ժ
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="text-muted small mb-2">Կցված աբոնեմենտներ</div>
                        <div class="plan-list">
                            <span
                                v-for="plan in summary.membership_plans"
                                :key="plan"
                                class="badge bg-label-secondary"
                            >
                                {{ plan }}
                            </span>
                            <span
                                v-if="!summary.membership_plans.length"
                                class="text-muted small"
                            >
                                Աբոնեմենտներ չկան
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="!summaries.length"
            class="col-12"
        >
            <div class="alert alert-warning mb-0">
                Ընտրված շաբաթվա համար վաճառված աբոնեմենտներով մարզիչների գրաֆիկներ չկան։
            </div>
        </div>
    </div>
</template>

<style scoped>
.trainer-summary-card {
    border-left: 4px solid var(--bs-primary);
}

.plan-list {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
</style>
