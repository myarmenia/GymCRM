<script setup>
defineProps({
    trainers: {
        type: Array,
        default: () => [],
    },
    selectedTrainer: {
        type: [Number, String],
        default: '',
    },
})

const emit = defineEmits(['change'])

const trainerName = trainer => {
    return trainer?.name || `${trainer?.first_name ?? ''} ${trainer?.last_name ?? ''}`.trim() || trainer?.email || `#${trainer?.id}`
}

const handleChange = event => {
    emit('change', event.target.value)
}
</script>

<template>
    <div class="card mb-4">
        <div class="card-body">
            <label
                class="form-label"
                for="trainer-filter"
            >
                Մարզիչ
            </label>
            <select
                id="trainer-filter"
                class="form-select filter-select"
                :value="selectedTrainer"
                @change="handleChange"
            >
                <option value="">Բոլոր մարզիչները</option>
                <option
                    v-for="trainer in trainers"
                    :key="trainer.id"
                    :value="trainer.id"
                >
                    {{ trainerName(trainer) }}
                </option>
            </select>
        </div>
    </div>
</template>

<style scoped>
.filter-select {
    max-width: 360px;
}
</style>
