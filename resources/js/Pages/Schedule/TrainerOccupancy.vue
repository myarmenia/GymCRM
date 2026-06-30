<script setup>
import { computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue'
import CalendarGrid from './TrainerOccupancy/CalendarGrid.vue'
import CalendarHeader from './TrainerOccupancy/CalendarHeader.vue'
import TrainerCards from './TrainerOccupancy/TrainerCards.vue'
import TrainerFilter from './TrainerOccupancy/TrainerFilter.vue'
import {
    formatUtcDateToYmd,
    parseYmdAsUtcDate,
    todayInYerevan,
} from '@/utils/yerevanDate'

const page = usePage()
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy')

const props = defineProps({
    weekStart: String,
    weekEnd: String,
    selectedTrainerId: {
        type: [Number, String, null],
        default: null,
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    summaries: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
    timeSlots: {
        type: Array,
        default: () => [],
    },
})

const weekDays = [
    { key: 'Monday', label: 'Երկուշաբթի' },
    { key: 'Tuesday', label: 'Երեքշաբթի' },
    { key: 'Wednesday', label: 'Չորեքշաբթի' },
    { key: 'Thursday', label: 'Հինգշաբթի' },
    { key: 'Friday', label: 'Ուրբաթ' },
    { key: 'Saturday', label: 'Շաբաթ' },
    { key: 'Sunday', label: 'Կիրակի' },
]

const monthNames = [
    'հունվար',
    'փետրվար',
    'մարտ',
    'ապրիլ',
    'մայիս',
    'հունիս',
    'հուլիս',
    'օգոստոս',
    'սեպտեմբեր',
    'հոկտեմբեր',
    'նոյեմբեր',
    'դեկտեմբեր',
]

const selectedTrainer = computed(() => props.selectedTrainerId ? Number(props.selectedTrainerId) : '')
const weekDates = computed(() => {
    const start = parseLocalDate(props.weekStart)

    return weekDays.map((day, index) => {
        const date = new Date(start)
        date.setUTCDate(start.getUTCDate() + index)

        return {
            ...day,
            date: formatLocalDate(date),
        }
    })
})

const weekRangeLabel = computed(() => {
    const start = parseLocalDate(props.weekStart)
    const end = parseLocalDate(props.weekEnd)

    if (!start || !end) {
        return '-'
    }

    const startMonth = monthNames[start.getMonth()]
    const endMonth = monthNames[end.getMonth()]

    if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
        return `${start.getDate()} - ${end.getDate()} ${endMonth} ${end.getFullYear()}`
    }

    if (start.getFullYear() === end.getFullYear()) {
        return `${start.getDate()} ${startMonth} - ${end.getDate()} ${endMonth} ${end.getFullYear()}`
    }

    return `${start.getDate()} ${startMonth} ${start.getFullYear()} - ${end.getDate()} ${endMonth} ${end.getFullYear()}`
})

const parseLocalDate = value => {
    if (!value) {
        return parseYmdAsUtcDate(todayInYerevan())
    }

    return parseYmdAsUtcDate(String(value).slice(0, 10))
}

const formatLocalDate = date => {
    return formatUtcDateToYmd(date)
}

const changeWeek = offsetDays => {
    const date = parseLocalDate(props.weekStart)
    date.setDate(date.getDate() + offsetDays)

    loadCalendar(formatLocalDate(date), selectedTrainer.value)
}

const goToday = () => {
    loadCalendar(todayInYerevan(), selectedTrainer.value)
}

const changeTrainer = trainerId => {
    loadCalendar(props.weekStart, trainerId)
}

const loadCalendar = (week, trainerId = '') => {
    router.get(
        route('schedule.trainer_occupancy', { locale: currentLocale.value }),
        {
            week,
            trainer_id: trainerId || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}
</script>

<template>
    <Head title="Մարզիչների զբաղվածության օրացույց" />

    <Index>
        <CalendarHeader
            class="mb-4"
            :week-range-label="weekRangeLabel"
            @previous="changeWeek(-7)"
            @today="goToday"
            @next="changeWeek(7)"
        />

        <TrainerFilter
            :trainers="trainers"
            :selected-trainer="selectedTrainer"
            @change="changeTrainer"
        />

        <TrainerCards :summaries="summaries" />

        <CalendarGrid
            :week-dates="weekDates"
            :month-names="monthNames"
            :time-slots="timeSlots"
            :events="events"
        />
    </Index>
</template>
