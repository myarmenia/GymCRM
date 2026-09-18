<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)
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
                {{ t('roles.trainer') }}
            </label>
            <select
                id="trainer-filter"
                class="form-select filter-select"
                :value="selectedTrainer"
                @change="handleChange"
            >
                <option value="">{{ t('sales.all_trainers') }}</option>
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
