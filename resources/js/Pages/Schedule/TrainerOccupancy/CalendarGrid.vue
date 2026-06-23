<script setup>
import { computed, ref, watch } from 'vue'
import CalendarEventItem from './CalendarEventItem.vue'

const props = defineProps({
    weekDates: {
        type: Array,
        default: () => [],
    },
    monthNames: {
        type: Array,
        default: () => [],
    },
    timeSlots: {
        type: Array,
        default: () => [],
    },
    events: {
        type: Array,
        default: () => [],
    },
})

const isDrawerOpen = ref(false)
const selectedDate = ref(null)
const selectedTimeSlot = ref(null)
const selectedRecords = ref([])

const cellKey = (date, slot) => `${date}|${slot.start_time}|${slot.end_time}`

const eventGroups = computed(() => {
    return props.events.reduce((groups, event) => {
        const key = cellKey(event.date, event)
        groups[key] = groups[key] || []
        groups[key].push(event)

        return groups
    }, {})
})

const selectedDateLabel = computed(() => selectedDate.value ? formatShortDate(selectedDate.value) : '-')
const selectedTimeRange = computed(() => {
    if (!selectedTimeSlot.value) {
        return '-'
    }

    return `${selectedTimeSlot.value.start_time} - ${selectedTimeSlot.value.end_time}`
})

const parseLocalDate = value => {
    if (!value) {
        return new Date()
    }

    return new Date(`${String(value).slice(0, 10)}T00:00:00`)
}

const formatShortDate = value => {
    const date = parseLocalDate(value)

    return `${date.getDate()} ${props.monthNames[date.getMonth()] ?? ''}`
}

const slotEvents = (day, slot) => eventGroups.value[cellKey(day.date, slot)] ?? []

const recordCountText = () => 'գրառում'

const openDrawer = (day, slot) => {
    const records = slotEvents(day, slot)

    if (!records.length) {
        return
    }

    selectedDate.value = day.date
    selectedTimeSlot.value = { ...slot }
    selectedRecords.value = [...records]
    isDrawerOpen.value = true
}

const closeDrawer = () => {
    isDrawerOpen.value = false
    selectedDate.value = null
    selectedTimeSlot.value = null
    selectedRecords.value = []
}

watch(
    () => [props.events, props.weekDates, props.timeSlots],
    () => closeDrawer(),
)
</script>

<template>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Շաբաթական օրացույց</h5>
        </div>
        <div class="card-body p-0">
            <div class="calendar-scroll">
                <div class="calendar-table">
                    <div class="calendar-header calendar-time-cell">Ժամ</div>
                    <div
                        v-for="day in weekDates"
                        :key="day.key"
                        class="calendar-header"
                    >
                        <div class="fw-semibold">{{ day.label }}</div>
                        <div class="small text-muted">{{ formatShortDate(day.date) }}</div>
                    </div>

                    <template
                        v-for="slot in timeSlots"
                        :key="slot.key"
                    >
                        <div class="calendar-time-cell">
                            {{ slot.start_time }} - {{ slot.end_time }}
                        </div>
                        <div
                            v-for="day in weekDates"
                            :key="`${slot.key}-${day.key}`"
                            class="calendar-cell"
                        >
                            <button
                                v-if="slotEvents(day, slot).length"
                                type="button"
                                class="cell-count-button"
                                @click="openDrawer(day, slot)"
                            >
                                <span class="count-number">{{ slotEvents(day, slot).length }}</span>
                                <span>{{ recordCountText() }}</span>
                            </button>
                            <div
                                v-else
                                class="empty-cell"
                            >
                                -
                            </div>
                        </div>
                    </template>

                    <div
                        v-if="!timeSlots.length"
                        class="empty-calendar"
                    >
                        Օրացույցում ցուցադրվող ժամեր չկան։
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="isDrawerOpen"
        class="offcanvas-backdrop fade show"
        @click="closeDrawer"
    ></div>
    <div
        class="offcanvas offcanvas-end workload-drawer"
        :class="{ show: isDrawerOpen }"
        :style="{ visibility: isDrawerOpen ? 'visible' : 'hidden' }"
        tabindex="-1"
        aria-labelledby="workload-drawer-title"
    >
        <div class="offcanvas-header border-bottom">
            <div>
                <h5
                    id="workload-drawer-title"
                    class="offcanvas-title"
                >
                    Զբաղվածության մանրամասներ
                </h5>
                <div class="text-muted small">
                    {{ selectedDateLabel }} · {{ selectedTimeRange }} · {{ selectedRecords.length }} գրառում
                </div>
            </div>
            <button
                type="button"
                class="btn-close"
                aria-label="Փակել"
                @click="closeDrawer"
            ></button>
        </div>
        <div class="offcanvas-body">
            <div class="drawer-records">
                <CalendarEventItem
                    v-for="event in selectedRecords"
                    :key="event.id"
                    :event="event"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.calendar-scroll {
    overflow-x: auto;
}

.calendar-table {
    display: grid;
    grid-template-columns: 150px repeat(7, minmax(160px, 1fr));
    min-width: 1270px;
    position: relative;
}

.calendar-header,
.calendar-time-cell,
.calendar-cell {
    border-bottom: 1px solid var(--bs-border-color);
    border-right: 1px solid var(--bs-border-color);
    padding: .85rem;
}

.calendar-header {
    background: var(--bs-light);
    position: sticky;
    top: 0;
    z-index: 2;
}

.calendar-time-cell {
    background: var(--bs-light);
    font-weight: 600;
    left: 0;
    position: sticky;
    z-index: 3;
}

.calendar-cell {
    align-items: center;
    display: flex;
    justify-content: center;
    min-height: 84px;
}

.cell-count-button {
    align-items: center;
    background: rgba(var(--bs-primary-rgb), .08);
    border: 1px solid rgba(var(--bs-primary-rgb), .22);
    border-radius: 8px;
    color: var(--bs-primary);
    display: flex;
    flex-direction: column;
    font-weight: 600;
    gap: .2rem;
    min-height: 56px;
    padding: .55rem .75rem;
    transition: background-color .15s ease, border-color .15s ease;
    width: 100%;
}

.cell-count-button:hover {
    background: rgba(var(--bs-primary-rgb), .14);
    border-color: rgba(var(--bs-primary-rgb), .35);
}

.count-number {
    font-size: 1.25rem;
    line-height: 1;
}

.empty-cell {
    color: var(--bs-secondary-color);
    text-align: center;
}

.empty-calendar {
    color: var(--bs-secondary-color);
    grid-column: 1 / -1;
    padding: 2rem;
    text-align: center;
}

.workload-drawer {
    width: min(520px, 100vw);
    z-index: 1090;
}

.drawer-records {
    display: grid;
    gap: .85rem;
}
</style>
