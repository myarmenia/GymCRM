<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";
import { Head } from "@inertiajs/vue3";
import Index from "@/Layouts/Index.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    schedules: {
        type: Array,
        default: () => [],
    },
});

const weekdays = {
    Monday: t('staff_reports.monday'),
    Tuesday: t('staff_reports.tuesday'),
    Wednesday: t('staff_reports.wednesday'),
    Thursday: t('staff_reports.thursday'),
    Friday: t('staff_reports.friday'),
    Saturday: t('staff_reports.saturday'),
    Sunday: t('staff_reports.sunday'),
};

const normalizedSchedules = computed(() => props.schedules ?? []);

const time = (value) => (value ? String(value).slice(0, 5) : "-");
const weekday = (value) => weekdays[value] ?? value ?? "-";
const sessionType = (value) =>
    ({ individual: t('staff_reports.individual'), group: t('staff_reports.group') })[value] ?? value ?? "-";
</script>

<template>
    <Head :title="t('sidebar.my_schedules')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('sidebar.my_schedules') }}
            </h2>
        </template>

        <div v-if="!normalizedSchedules.length" class="alert alert-info">
            {{ t('staff_reports.no_hourly_schedule_has_been_assigned_to_you_yet') }}
        </div>

        <div
            v-for="trainerSchedule in normalizedSchedules"
            :key="trainerSchedule.id"
            class="card mb-4"
        >
            <div class="card-header">
                <h5 class="mb-0">
                    {{ trainerSchedule.schedule?.name ?? t('staff_reports.unnamed_schedule') }}
                </h5>
            </div>

            <div class="card-body">
                <h6 class="mb-3">{{ t('staff_reports.working_hours') }}</h6>
                <div
                    v-if="trainerSchedule.schedule?.schedule_details?.length"
                    class="table-responsive mb-4"
                >
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>{{ t('membership.day') }}</th>
                                <th>{{ t('staff_reports.hours') }}</th>
                                <th>{{ t('staff_reports.break') }}</th>
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
                    {{ t('staff_reports.no_hours_are_specified_in_this_schedule') }}
                </p>

                <h6 class="mb-3">{{ t('staff_reports.training_types') }}</h6>
                <div v-if="trainerSchedule.session_durations?.length" class="row g-3">
                    <div
                        v-for="duration in trainerSchedule.session_durations"
                        :key="duration.id"
                        class="col-md-6"
                    >
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold">
                                {{ duration.title || t('staff_reports.untitled') }}
                            </div>
                            <div class="text-muted small mb-2">
                                {{ t('staff_reports.minutes_and_type', { minutes: duration.minutes, type: sessionType(duration.type) }) }}
                            </div>
                            <div v-if="duration.slots?.length" class="d-flex flex-wrap gap-2">
                                <span
                                    v-for="slot in duration.slots"
                                    :key="slot.id"
                                    class="badge bg-label-primary"
                                >
                                    {{ weekday(slot.week_day) }}:
                                    {{ time(slot.start_time) }} - {{ time(slot.end_time) }}
                                </span>
                            </div>
                            <span v-else class="text-muted small">
                                {{ t('staff_reports.no_individual_hours_are_specified') }}
                            </span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-muted mb-0">
                    {{ t('staff_reports.no_training_types_have_been_added') }}
                </p>
            </div>
        </div>
    </Index>
</template>
