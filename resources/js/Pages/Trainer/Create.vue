<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { computed } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage();
const currentLocale = page.props.locale ?? "en";
const weekDayLabels = {
    monday: t('staff_reports.monday'),
    tuesday: t('staff_reports.tuesday'),
    wednesday: t('staff_reports.wednesday'),
    thursday: t('staff_reports.thursday'),
    friday: t('staff_reports.friday'),
    saturday: t('staff_reports.saturday'),
    sunday: t('staff_reports.sunday'),
};

const getWeekDayLabel = (weekDay) => {
    return weekDayLabels[String(weekDay).toLowerCase()] ?? weekDay;
};
const props = defineProps({
    scheduleNames: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    schedule_names: [],
    session_durations: [],
});

const selectedScheduleNames = computed(() => {
    return props.scheduleNames.filter((schedule) =>
        form.schedule_names.includes(schedule.id),
    );
});

const addScheduleName = () => {
    form.schedule_names.push(null);
};

const removeScheduleName = (index) => {
    const scheduleId = form.schedule_names[index];

    form.schedule_names.splice(index, 1);

    form.session_durations = form.session_durations.filter((item) => {
        return item.schedule_name_id !== scheduleId;
    });
};

const addSessionDuration = () => {
    form.session_durations.push({
        schedule_name_id: null,
        title: "",
        minutes: 60,
        type: "individual",
        price: null,
        slots: [],
    });
};

const removeSessionDuration = (index) => {
    form.session_durations.splice(index, 1);
};

const getScheduleDetails = (scheduleNameId) => {
    const schedule = props.scheduleNames.find(
        (item) => item.id === scheduleNameId,
    );

    return (
        schedule?.schedule_details ??
        schedule?.schedule_name?.schedule_details ??
        []
    );
};

const addSlot = (durationIndex, detail) => {
    const duration = form.session_durations[durationIndex];
    const minutes = Number(duration.minutes || 0);

    duration.slots.push({
        week_day: detail.week_day,
        start_time: detail.day_start_time,
        end_time: addMinutes(detail.day_start_time, minutes),
    });
};

const removeSlot = (durationIndex, slotIndex) => {
    form.session_durations[durationIndex].slots.splice(slotIndex, 1);
};

const addMinutes = (time, minutes) => {
    if (!time || !minutes) return "";

    const [h, m] = time.split(":").map(Number);

    const date = new Date();
    date.setHours(h);
    date.setMinutes(m + minutes);

    return (
        String(date.getHours()).padStart(2, "0") +
        ":" +
        String(date.getMinutes()).padStart(2, "0")
    );
};

const updateSlotEndTime = (durationIndex, slotIndex) => {
    const duration = form.session_durations[durationIndex];
    const slot = duration.slots[slotIndex];

    slot.end_time = addMinutes(slot.start_time, Number(duration.minutes));
};

const submit = () => {
    form.post(route("trainer-schedule.store", { locale: currentLocale }));
};
</script>

<template>
    <Head :title="t('staff_reports.trainer_schedule_management_2')" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ t('staff_reports.trainer_schedule_management') }}
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">{{ t('staff_reports.schedules_and_training_types') }}</h5>

            <form @submit.prevent="submit" class="card-body">
                <h6>{{ t('staff_reports.1_hourly_schedules') }}</h6>

                <div class="mb-4">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="addScheduleName"
                    >
                        {{ t('staff_reports.add_schedule') }}
                    </button>
                </div>

                <div
                    v-for="(scheduleId, index) in form.schedule_names"
                    :key="index"
                    class="row g-3 mb-3 align-items-end"
                >
                    <div class="col-md-10">
                        <InputLabel class="form-label" :value="t('sidebar.schedule')" />

                        <select
                            class="form-select"
                            v-model="form.schedule_names[index]"
                        >
                            <option :value="null" disabled>
                                {{ t('membership.choose_schedule') }}
                            </option>

                            <option
                                v-for="schedule in scheduleNames"
                                :key="schedule.id"
                                :value="schedule.id"
                            >
                                {{
                                    schedule.name ??
                                    schedule.schedule_name?.name
                                }}
                            </option>
                        </select>

                        <InputError
                            class="mt-2"
                            :message="form.errors[`schedule_names.${index}`]"
                        />
                    </div>

                    <div class="col-md-2">
                        <button
                            type="button"
                            class="btn btn-danger w-100"
                            @click="removeScheduleName(index)"
                        >
                            {{ t('action.delete') }}
                        </button>
                    </div>
                </div>

                <InputError
                    class="mt-2"
                    :message="form.errors.schedule_names"
                />

                <hr class="my-6 mx-n6" />

                <div
                    class="d-flex justify-content-between align-items-center mb-3"
                >
                    <h6 class="mb-0">{{ t('staff_reports.2_training_types') }}</h6>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="addSessionDuration"
                    >
                        {{ t('staff_reports.add_type') }}
                    </button>
                </div>

                <div
                    v-for="(duration, durationIndex) in form.session_durations"
                    :key="durationIndex"
                    class="border rounded p-3 mb-4"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <strong
                            >{{ t('staff_reports.training_type_number', { number: durationIndex + 1 }) }}</strong
                        >

                        <button
                            type="button"
                            class="btn btn-danger btn-sm"
                            @click="removeSessionDuration(durationIndex)"
                        >
                            {{ t('action.delete') }}
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <InputLabel
                                class="form-label"
                                :value="t('staff_reports.assigned_schedule')"
                            />

                            <select
                                class="form-select"
                                v-model="duration.schedule_name_id"
                            >
                                <option :value="null" disabled>
                                    {{ t('membership.choose_schedule') }}
                                </option>

                                <option
                                    v-for="schedule in selectedScheduleNames"
                                    :key="schedule.id"
                                    :value="schedule.id"
                                >
                                    {{
                                        schedule.name ??
                                        schedule.schedule_name?.name
                                    }}
                                </option>
                            </select>

                            <InputError
                                class="mt-2"
                                :message="
                                    form.errors[
                                        `session_durations.${durationIndex}.schedule_name_id`
                                    ]
                                "
                            />
                        </div>

                        <div class="col-md-3">
                            <InputLabel class="form-label" :value="t('membership.title')" />

                            <input
                                type="text"
                                class="form-control"
                                v-model="duration.title"
                                :placeholder="t('staff_reports.e_g_60_minutes')"
                            />

                            <InputError
                                class="mt-2"
                                :message="
                                    form.errors[
                                        `session_durations.${durationIndex}.title`
                                    ]
                                "
                            />
                        </div>

                        <div class="col-md-2">
                            <InputLabel class="form-label" :value="t('staff_reports.minutes')" />

                            <input
                                type="number"
                                class="form-control"
                                v-model="duration.minutes"
                                min="1"
                            />

                            <InputError
                                class="mt-2"
                                :message="
                                    form.errors[
                                        `session_durations.${durationIndex}.minutes`
                                    ]
                                "
                            />
                        </div>

                        <div class="col-md-2">
                            <InputLabel class="form-label" :value="t('people.type')" />

                            <select class="form-select" v-model="duration.type">
                                <option value="individual">{{ t('staff_reports.individual') }}</option>
                                <option value="group">{{ t('staff_reports.group') }}</option>
                            </select>

                            <InputError
                                class="mt-2"
                                :message="
                                    form.errors[
                                        `session_durations.${durationIndex}.type`
                                    ]
                                "
                            />
                        </div>

                        <div class="col-md-1">
                            <InputLabel class="form-label" :value="t('membership.price')" />

                            <input
                                type="number"
                                class="form-control"
                                v-model="duration.price"
                                min="0"
                            />
                        </div>
                    </div>

                    <hr />

                    <h6>{{ t('staff_reports.hours') }}</h6>

                    <div
                        v-if="!duration.schedule_name_id"
                        class="alert alert-warning"
                    >
                        {{ t('staff_reports.select_training_schedule_first') }}
                    </div>

                    <div
                        v-for="detail in getScheduleDetails(
                            duration.schedule_name_id,
                        )"
                        :key="detail.id"
                        class="mb-4"
                    >
                        <div
                            class="d-flex justify-content-between align-items-center mb-2"
                        >
                            <strong>
                                {{ getWeekDayLabel(detail.week_day) }}
                                {{ detail.day_start_time }} -
                                {{ detail.day_end_time }}
                            </strong>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                @click="addSlot(durationIndex, detail)"
                            >
                                {{ t('staff_reports.add_time') }}
                            </button>
                        </div>

                        <div
                            v-for="slot in duration.slots.filter(
                                (s) => s.week_day === detail.week_day,
                            )"
                            :key="duration.slots.indexOf(slot)"
                            class="row g-2 mb-2 align-items-end"
                        >
                            <div class="col-md-4">
                                <label class="form-label">{{ t('people.start') }}</label>

                                <input
                                    type="time"
                                    class="form-control"
                                    v-model="slot.start_time"
                                    :min="detail.day_start_time"
                                    :max="detail.day_end_time"
                                    @change="
                                        updateSlotEndTime(
                                            durationIndex,
                                            duration.slots.indexOf(slot),
                                        )
                                    "
                                />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">{{ t('people.end') }}</label>

                                <input
                                    type="time"
                                    class="form-control"
                                    v-model="slot.end_time"
                                    readonly
                                />
                            </div>

                            <div class="col-md-4">
                                <button
                                    type="button"
                                    class="btn btn-danger"
                                    @click="
                                        removeSlot(
                                            durationIndex,
                                            duration.slots.indexOf(slot),
                                        )
                                    "
                                >
                                    {{ t('action.delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <InputError
                    class="mt-2"
                    :message="form.errors.session_durations"
                />

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        {{ t('common.save') }}
                    </PrimaryButton>

                    <button
                        type="reset"
                        class="btn btn-label-secondary waves-effect"
                    >
                        {{ t('confirm.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </Index>
</template>
