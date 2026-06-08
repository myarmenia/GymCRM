<script setup>
import { computed } from "vue";
import Index from "@/Layouts/Index.vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    trainer: {
        type: Object,
        required: true,
    },
    scheduleNames: {
        type: Array,
        default: () => [],
    },
    trainerSessionDuration: {
        type: [Object, Array, null],
        default: null,
    },
    trainerScheduleIds: {
        type: Array,
        default: () => [],
    },
});

const normalizeTime = (time) => {
    if (!time) return "";
    return String(time).slice(0, 5);
};

const timeToMinutes = (time) => {
    if (!time) return null;

    const [hours, minutes] = normalizeTime(time).split(":").map(Number);

    return hours * 60 + minutes;
};

const addMinutes = (time, minutes) => {
    if (!time || !minutes) return "";

    const [h, m] = time.split(":").map(Number);

    const date = new Date();
    date.setHours(h);
    date.setMinutes(m + Number(minutes));

    return (
        String(date.getHours()).padStart(2, "0") +
        ":" +
        String(date.getMinutes()).padStart(2, "0")
    );
};

const rangesOverlap = (start1, end1, start2, end2) => {
    return start1 < end2 && end1 > start2;
};

const slotOverlapsBreak = (slot, detail) => {
    const breakStart = normalizeTime(detail.break_start_time);
    const breakEnd = normalizeTime(detail.break_end_time);

    if (!breakStart || !breakEnd) {
        return false;
    }

    const slotStart = timeToMinutes(slot.start_time);
    const slotEnd = timeToMinutes(slot.end_time);
    const breakStartMinutes = timeToMinutes(breakStart);
    const breakEndMinutes = timeToMinutes(breakEnd);

    return rangesOverlap(
        slotStart,
        slotEnd,
        breakStartMinutes,
        breakEndMinutes,
    );
};

const slotInsideSchedule = (slot, detail) => {
    const slotStart = timeToMinutes(slot.start_time);
    const slotEnd = timeToMinutes(slot.end_time);
    const dayStart = timeToMinutes(detail.day_start_time);
    const dayEnd = timeToMinutes(detail.day_end_time);

    return slotStart >= dayStart && slotEnd <= dayEnd;
};
const modal = {
    show(message) {
        const oldModal = document.getElementById("slotWarningModal");

        if (oldModal) {
            oldModal.remove();
        }

        const modalHtml = `
            <div class="modal fade" id="slotWarningModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-0 pb-0">
                            <div class="d-flex align-items-center gap-2">
                                <div
                                    class="bg-warning bg-opacity-25 text-warning rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 42px; height: 42px;"
                                >
                                    <i class="bi bi-exclamation-triangle fs-4"></i>
                                </div>

                                <h5 class="modal-title fw-bold mb-0">
                                    Զգուշացում
                                </h5>
                            </div>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body pt-3">
                            <p class="mb-0 text-muted">
                                ${message}
                            </p>
                        </div>

                        <div class="modal-footer border-0 pt-0">
                            <button
                                type="button"
                                class="btn btn-warning px-4"
                                data-bs-dismiss="modal"
                            >
                                Հասկացա
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML("beforeend", modalHtml);

        const modalElement = document.getElementById("slotWarningModal");
        const bootstrapModal = new window.bootstrap.Modal(modalElement);

        bootstrapModal.show();
    },
};
const validateSlot = (slot, detail) => {
    if (!slotInsideSchedule(slot, detail)) {
        modal.show("Ընտրված ժամը պետք է լինի գրաֆիկի աշխատանքային ժամերի մեջ։");
        return false;
    }

    if (slotOverlapsBreak(slot, detail)) {
        modal.show("Ընտրված ժամը համընկնում է ընդմիջման ժամին։");
        return false;
    }

    return true;
};

const normalizeExistingDurations = () => {
    const trainerSchedules = props.trainerSessionDuration;

    if (
        !trainerSchedules ||
        !Array.isArray(trainerSchedules) ||
        !trainerSchedules.length
    ) {
        return [];
    }

    const result = [];

    trainerSchedules.forEach((schedule) => {
        const sessionDurations =
            schedule.sessionDurations ?? schedule.session_durations ?? [];

        sessionDurations.forEach((duration) => {
            result.push({
                id: duration.id ?? null,
                schedule_name_id: schedule.schedule_name_id ?? null,
                title: duration.title ?? "",
                minutes: duration.minutes ?? 60,
                type: duration.type ?? "individual",
                price: duration.price ?? null,

                slots: (duration.slots ?? []).map((slot) => ({
                    id: slot.id ?? null,
                    week_day: slot.week_day,
                    start_time: normalizeTime(slot.start_time),
                    end_time: normalizeTime(slot.end_time),
                })),
            });
        });
    });

    return result;
};

const form = useForm({
    schedule_names: props.trainerScheduleIds.length
        ? props.trainerScheduleIds
        : props.trainerSessionDuration?.schedule_name_id
          ? [props.trainerSessionDuration.schedule_name_id]
          : [],

    session_durations: normalizeExistingDurations(),
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
        id: null,
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

const findScheduleDetailByWeekDay = (scheduleNameId, weekDay) => {
    return getScheduleDetails(scheduleNameId).find(
        (detail) => detail.week_day === weekDay,
    );
};

const addSlot = (durationIndex, detail) => {
    const duration = form.session_durations[durationIndex];
    const minutes = Number(duration.minutes || 0);

    const slot = {
        id: null,
        week_day: detail.week_day,
        start_time: normalizeTime(detail.day_start_time),
        end_time: addMinutes(normalizeTime(detail.day_start_time), minutes),
    };

    if (!validateSlot(slot, detail)) {
        return;
    }

    duration.slots.push(slot);
};

const removeSlot = (durationIndex, slotIndex) => {
    form.session_durations[durationIndex].slots.splice(slotIndex, 1);
};

const updateSlotEndTime = (durationIndex, slotIndex) => {
    const duration = form.session_durations[durationIndex];
    const slot = duration.slots[slotIndex];

    slot.end_time = addMinutes(slot.start_time, Number(duration.minutes));

    const detail = findScheduleDetailByWeekDay(
        duration.schedule_name_id,
        slot.week_day,
    );

    if (!detail) return;

    if (!validateSlot(slot, detail)) {
        slot.start_time = normalizeTime(detail.day_start_time);
        slot.end_time = addMinutes(
            normalizeTime(detail.day_start_time),
            Number(duration.minutes),
        );
    }
};

const updateDurationMinutes = (durationIndex) => {
    const duration = form.session_durations[durationIndex];

    duration.slots.forEach((slot, slotIndex) => {
        updateSlotEndTime(durationIndex, slotIndex);
    });
};

const submit = () => {
    const hasExistingData = Boolean(props.trainerSessionDuration);

    if (hasExistingData) {
        form.put(
            route("trainer.update", {
                locale: currentLocale,
                id: props.trainer.id,
            }),
        );
    } else {
        form.post(
            route("trainer.store", {
                locale: currentLocale,
                id: props.trainer.id,
            }),
        );
    }
};
</script>

<template>
    <Head title="Մարզիչի գրաֆիկների կառավարում" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Մարզիչ / Գրաֆիկների կառավարում
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">
                {{ trainer.name }} {{ trainer.surname }} - գրաֆիկներ և
                պարապունքի տեսակներ
            </h5>

            <form @submit.prevent="submit" class="card-body">
                <h6>1. Ժամային գրաֆիկներ</h6>

                <div class="mb-4">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="addScheduleName"
                    >
                        + Ավելացնել գրաֆիկ
                    </button>
                </div>

                <div
                    v-if="!form.schedule_names.length"
                    class="alert alert-warning"
                >
                    Դեռ գրաֆիկ ընտրված չէ։
                </div>

                <div
                    v-for="(scheduleId, index) in form.schedule_names"
                    :key="index"
                    class="row g-3 mb-3 align-items-end"
                >
                    <div class="col-md-10">
                        <InputLabel class="form-label" value="Ժամային գրաֆիկ" />

                        <select
                            class="form-select"
                            v-model="form.schedule_names[index]"
                        >
                            <option :value="null" disabled>
                                Ընտրել գրաֆիկ
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
                            Ջնջել
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
                    <h6 class="mb-0">2. Պարապունքի տեսակներ</h6>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="addSessionDuration"
                    >
                        + Ավելացնել տեսակ
                    </button>
                </div>

                <div
                    v-if="!form.session_durations.length"
                    class="alert alert-warning"
                >
                    Դեռ պարապունքի տեսակ ավելացված չէ։
                </div>

                <div
                    v-for="(duration, durationIndex) in form.session_durations"
                    :key="durationIndex"
                    class="border rounded p-3 mb-4"
                >
                    <div
                        class="d-flex justify-content-between align-items-center mb-3"
                    >
                        <strong>
                            Պարապունքի տեսակ #{{ durationIndex + 1 }}
                        </strong>

                        <button
                            type="button"
                            class="btn btn-danger btn-sm"
                            @click="removeSessionDuration(durationIndex)"
                        >
                            Ջնջել
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <InputLabel
                                class="form-label"
                                value="Որ գրաֆիկին է կպնում"
                            />

                            <select
                                class="form-select"
                                v-model="duration.schedule_name_id"
                            >
                                <option :value="null" disabled>
                                    Ընտրել գրաֆիկ
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

                            <div class="mt-2">
                                <InputLabel class="form-label" value="Գին" />

                                <input
                                    type="number"
                                    class="form-control"
                                    v-model="duration.price"
                                    min="0"
                                    @wheel.prevent
                                />

                                <InputError
                                    class="mt-2"
                                    :message="
                                        form.errors[
                                            `session_durations.${durationIndex}.price`
                                        ]
                                    "
                                />
                            </div>
                        </div>

                        <div class="col-md-3">
                            <InputLabel class="form-label" value="Անվանում" />

                            <input
                                type="text"
                                class="form-control"
                                v-model="duration.title"
                                placeholder="Օր․ 60 րոպե"
                                @wheel.prevent
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
                            <InputLabel class="form-label" value="Րոպե" />

                            <input
                                type="number"
                                class="form-control"
                                v-model="duration.minutes"
                                min="1"
                                @input="updateDurationMinutes(durationIndex)"
                                @wheel.prevent
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

                        <div class="col-md-3">
                            <InputLabel class="form-label" value="Տեսակ" />

                            <select class="form-select" v-model="duration.type">
                                <option value="individual">Անհատական</option>
                                <option value="group">Խմբային</option>
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
                    </div>

                    <hr />

                    <h6>Ժամեր</h6>

                    <div
                        v-if="!duration.schedule_name_id"
                        class="alert alert-warning"
                    >
                        Նախ ընտրիր, թե այս պարապունքի տեսակը որ գրաֆիկին է
                        կպնում։
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
                                {{ detail.week_day }}
                                {{ normalizeTime(detail.day_start_time) }}
                                -
                                {{ normalizeTime(detail.day_end_time) }}

                                <span
                                    v-if="
                                        detail.break_start_time &&
                                        detail.break_end_time
                                    "
                                    class="text-danger ms-2"
                                >
                                    Ընդմիջում՝
                                    {{ normalizeTime(detail.break_start_time) }}
                                    -
                                    {{ normalizeTime(detail.break_end_time) }}
                                </span>
                            </strong>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                @click="addSlot(durationIndex, detail)"
                            >
                                + Ավելացնել ժամ
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
                                <label class="form-label">Սկիզբ</label>

                                <input
                                    type="time"
                                    class="form-control"
                                    v-model="slot.start_time"
                                    :min="normalizeTime(detail.day_start_time)"
                                    :max="normalizeTime(detail.day_end_time)"
                                    @change="
                                        updateSlotEndTime(
                                            durationIndex,
                                            duration.slots.indexOf(slot),
                                        )
                                    "
                                    @wheel.prevent
                                />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Ավարտ</label>

                                <input
                                    type="time"
                                    class="form-control"
                                    v-model="slot.end_time"
                                    readonly
                                    @wheel.prevent
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
                                    Ջնջել
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
                        Պահպանել
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Index>
</template>
