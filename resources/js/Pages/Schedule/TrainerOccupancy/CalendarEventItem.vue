<script setup>
defineProps({
    event: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <div class="calendar-event">
        <div class="d-flex justify-content-between gap-3 align-items-start mb-2">
            <div>
                <div class="fw-semibold">{{ event.customer_name }}</div>
                <div class="small text-muted">{{ event.membership_plan_name }}</div>
            </div>
            <span
                v-if="event.membership_status"
                class="badge bg-label-primary"
            >
                {{ event.membership_status }}
            </span>
        </div>

        <div class="event-details">
            <div>
                <span class="text-muted">Մարզիչ՝</span>
                {{ event.trainer_name }}
            </div>
            <div>
                <span class="text-muted">Ժամ՝</span>
                {{ event.start_time }} - {{ event.end_time }}
            </div>
            <div>
                <span class="text-muted">Գրաֆիկ՝</span>
                {{ event.schedule_name }}
            </div>
        </div>
    </div>
</template>

<style scoped>
.calendar-event {
    background: rgba(var(--bs-primary-rgb), .06);
    border: 1px solid rgba(var(--bs-primary-rgb), .18);
    border-left: 4px solid var(--bs-primary);
    border-radius: 8px;
    padding: .85rem;
}

.event-details {
    display: grid;
    gap: .35rem;
    font-size: .875rem;
}
</style>
