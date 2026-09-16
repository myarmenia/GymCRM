<script setup>
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";

const props = defineProps({
    schedules: {
        type: Array,
        default: () => [],
    },
});

const weekdays = {
    Monday: "Երկուշաբթի",
    Tuesday: "Երեքշաբթի",
    Wednesday: "Չորեքշաբթի",
    Thursday: "Հինգշաբթի",
    Friday: "Ուրբաթ",
    Saturday: "Շաբաթ",
    Sunday: "Կիրակի",
};

const normalizedSchedules = computed(() => props.schedules ?? []);

const time = (value) => (value ? String(value).slice(0, 5) : "-");
const weekday = (value) => weekdays[value] ?? value ?? "-";
const sessionType = (value) =>
    ({ individual: "Անհատական", group: "Խմբային" })[value] ?? value ?? "-";
</script>

<template>
    <Head title="Իմ գրաֆիկները" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Իմ գրաֆիկները
            </h2>
        </template>

        <div v-if="!normalizedSchedules.length" class="alert alert-info">
            Ձեզ դեռ ժամային գրաֆիկ կցված չէ։
        </div>

        <div
            v-for="trainerSchedule in normalizedSchedules"
            :key="trainerSchedule.id"
            class="card mb-4"
        >
            <div class="card-header">
                <h5 class="mb-0">
                    {{ trainerSchedule.schedule?.name ?? "Անանուն գրաֆիկ" }}
                </h5>
            </div>

            <div class="card-body">
                <h6 class="mb-3">Աշխատանքային ժամեր</h6>
                <div
                    v-if="trainerSchedule.schedule?.schedule_details?.length"
                    class="table-responsive mb-4"
                >
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Օր</th>
                                <th>Ժամեր</th>
                                <th>Ընդմիջում</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="detail in trainerSchedule.schedule.schedule_details"
                                :key="detail.id"
                            >
                                <td>{{ weekday(detail.week_day) }}</td>
                                <td>
                                    {{ time(detail.day_start_time) }} -
                                    {{ time(detail.day_end_time) }}
                                </td>
                                <td>
                                    <template
                                        v-if="detail.break_start_time && detail.break_end_time"
                                    >
                                        {{ time(detail.break_start_time) }} -
                                        {{ time(detail.break_end_time) }}
                                    </template>
                                    <template v-else>-</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-muted mb-4">
                    Այս գրաֆիկում ժամեր նշված չեն։
                </p>

                <h6 class="mb-3">Պարապունքի տեսակներ</h6>
                <div v-if="trainerSchedule.session_durations?.length" class="row g-3">
                    <div
                        v-for="duration in trainerSchedule.session_durations"
                        :key="duration.id"
                        class="col-md-6"
                    >
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold">
                                {{ duration.title || "Անվերնագիր" }}
                            </div>
                            <div class="text-muted small mb-2">
                                {{ duration.minutes }} րոպե · {{ sessionType(duration.type) }}
                            </div>
                            <div v-if="duration.slots?.length" class="d-flex flex-wrap gap-2">
                                <span
                                    v-for="slot in duration.slots"
                                    :key="slot.id"
                                    class="badge bg-label-primary"
                                >
                                    {{ weekday(slot.week_day) }}՝
                                    {{ time(slot.start_time) }} - {{ time(slot.end_time) }}
                                </span>
                            </div>
                            <span v-else class="text-muted small">
                                Առանձին ժամեր նշված չեն։
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-muted mb-0">
                    Պարապունքի տեսակներ ավելացված չեն։
                </p>
            </div>
        </div>
    </Index>
</template>
