<script setup>
const props = defineProps({
    selectedPeriod: {
        type: String,
        default: 'monthly',
    },
    startDate: {
        type: String,
        default: '',
    },
    endDate: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'update:selectedPeriod',
    'update:startDate',
    'update:endDate',
    'period-change',
    'apply',
])

const periodOptions = [
    { value: 'monthly', label: 'Ամսական' },
    { value: 'quarterly', label: 'Եռամսյակային' },
    { value: 'yearly', label: 'Տարեկան' },
]

const changePeriod = period => {
    emit('update:selectedPeriod', period)
    emit('period-change', period)
}
</script>

<template>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap mb-4">
                <button
                    v-for="option in periodOptions"
                    :key="option.value"
                    type="button"
                    class="btn"
                    :class="props.selectedPeriod === option.value ? 'btn-primary' : 'btn-outline-primary'"
                    @click="changePeriod(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label
                        class="form-label"
                        for="report_start_date"
                    >
                        Սկիզբ
                    </label>
                    <input
                        id="report_start_date"
                        :value="props.startDate"
                        type="date"
                        class="form-control"
                        @input="emit('update:startDate', $event.target.value)"
                    >
                </div>
                <div class="col-md-4">
                    <label
                        class="form-label"
                        for="report_end_date"
                    >
                        Ավարտ
                    </label>
                    <input
                        id="report_end_date"
                        :value="props.endDate"
                        type="date"
                        class="form-control"
                        @input="emit('update:endDate', $event.target.value)"
                    >
                </div>
                <div class="col-md-4">
                    <button
                        type="button"
                        class="btn btn-primary w-100"
                        @click="emit('apply')"
                    >
                        Կիրառել
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
