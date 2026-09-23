<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)
defineProps({
    weekRangeLabel: {
        type: String,
        default: '-',
    },
})

defineEmits(['previous', 'today', 'next'])
</script>

<template>
    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">
                    {{ t('operations.trainer_occupancy_calendar') }}
                </h2>
                <div class="text-muted">{{ weekRangeLabel }}</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="$emit('previous')"
                >
                    {{ t('operations.previous_week') }}
                </button>
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="$emit('today')"
                >
                    {{ t('staff_reports.today') }}
                </button>
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="$emit('next')"
                >
                    {{ t('operations.next_week') }}
                </button>
            </div>
        </div>
    </div>
</template>
