<script setup>
import { computed } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const page = usePage();
const currentLocale = page.props.locale ?? 'en';

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
    return props.scheduleNames.filter(schedule =>
        form.schedule_names.includes(schedule.id)
    );
});

const addScheduleName = () => {
    form.schedule_names.push(null);
};

const removeScheduleName = (index) => {
    const scheduleId = form.schedule_names[index];

    form.schedule_names.splice(index, 1);

    form.session_durations = form.session_durations.filter(item => {
        return item.schedule_name_id !== scheduleId;
    });
};

const addSessionDuration = () => {
    form.session_durations.push({
        schedule_name_id: null,
        title: '',
        minutes: 60,
        type: 'individual',
        price: null,
        slots: [],
    });
};

const removeSessionDuration = (index) => {
    form.session_durations.splice(index, 1);
};

const getScheduleDetails = (scheduleNameId) => {
    const schedule = props.scheduleNames.find(item => item.id === scheduleNameId);

    return schedule?.schedule_details
        ?? schedule?.schedule_name?.schedule_details
        ?? [];
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
    if (!time || !minutes) return '';

    const [h, m] = time.split(':').map(Number);

    const date = new Date();
    date.setHours(h);
    date.setMinutes(m + minutes);

    return String(date.getHours()).padStart(2, '0') + ':' +
        String(date.getMinutes()).padStart(2, '0');
};

const updateSlotEndTime = (durationIndex, slotIndex) => {
    const duration = form.session_durations[durationIndex];
    const slot = duration.slots[slotIndex];

    slot.end_time = addMinutes(slot.start_time, Number(duration.minutes));
};

const submit = () => {
    form.post(route('trainer-schedule.store', { locale: currentLocale }));
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
            <h5 class="card-header">Գրաֆիկներ և պարապունքի տեսակներ</h5>

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
                    v-for="(scheduleId, index) in form.schedule_names"
                    :key="index"
                    class="row g-3 mb-3 align-items-end"
                >
                    <div class="col-md-10">
                        <InputLabel class="form-label" value="Ժամային գրաֆիկ" />

                        <select class="form-select" v-model="form.schedule_names[index]">
                            <option :value="null" disabled>Ընտրել գրաֆիկ</option>

                            <option
                                v-for="schedule in scheduleNames"
                                :key="schedule.id"
                                :value="schedule.id"
                            >
                                {{ schedule.name ?? schedule.schedule_name?.name }}
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

                <InputError class="mt-2" :message="form.errors.schedule_names" />

                <hr class="my-6 mx-n6">

                <div class="d-flex justify-content-between align-items-center mb-3">
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
                    v-for="(duration, durationIndex) in form.session_durations"
                    :key="durationIndex"
                    class="border rounded p-3 mb-4"
                >
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>Պարապունքի տեսակ #{{ durationIndex + 1 }}</strong>

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
                            <InputLabel class="form-label" value="Որ գրաֆիկին է կպնում" />

                            <select class="form-select" v-model="duration.schedule_name_id">
                                <option :value="null" disabled>Ընտրել գրաֆիկ</option>

                                <option
                                    v-for="schedule in selectedScheduleNames"
                                    :key="schedule.id"
                                    :value="schedule.id"
                                >
                                    {{ schedule.name ?? schedule.schedule_name?.name }}
                                </option>
                            </select>

                            <InputError
                                class="mt-2"
                                :message="form.errors[`session_durations.${durationIndex}.schedule_name_id`]"
                            />
                        </div>

                        <div class="col-md-3">
                            <InputLabel class="form-label" value="Անվանում" />

                            <input
                                type="text"
                                class="form-control"
                                v-model="duration.title"
                                placeholder="Օր․ 60 րոպե"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors[`session_durations.${durationIndex}.title`]"
                            />
                        </div>

                        <div class="col-md-2">
                            <InputLabel class="form-label" value="Րոպե" />

                            <input
                                type="number"
                                class="form-control"
                                v-model="duration.minutes"
                                min="1"
                            />

                            <InputError
                                class="mt-2"
                                :message="form.errors[`session_durations.${durationIndex}.minutes`]"
                            />
                        </div>

                        <div class="col-md-2">
                            <InputLabel class="form-label" value="Տեսակ" />

                            <select class="form-select" v-model="duration.type">
                                <option value="individual">Անհատական</option>
                                <option value="group">Խմբային</option>
                            </select>

                            <InputError
                                class="mt-2"
                                :message="form.errors[`session_durations.${durationIndex}.type`]"
                            />
                        </div>

                        <div class="col-md-1">
                            <InputLabel class="form-label" value="Գին" />

                            <input
                                type="number"
                                class="form-control"
                                v-model="duration.price"
                                min="0"
                            />
                        </div>
                    </div>

                    <hr>

                    <h6>Ժամեր</h6>

                    <div v-if="!duration.schedule_name_id" class="alert alert-warning">
                        Նախ ընտրիր, թե այս պարապունքի տեսակը որ գրաֆիկին է կպնում։
                    </div>

                    <div
                        v-for="detail in getScheduleDetails(duration.schedule_name_id)"
                        :key="detail.id"
                        class="mb-4"
                    >
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>
                                {{ detail.week_day }}
                                {{ detail.day_start_time }} - {{ detail.day_end_time }}
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
                            v-for="slot in duration.slots.filter(s => s.week_day === detail.week_day)"
                            :key="duration.slots.indexOf(slot)"
                            class="row g-2 mb-2 align-items-end"
                        >
                            <div class="col-md-4">
                                <label class="form-label">Սկիզբ</label>

                                <input
                                    type="time"
                                    class="form-control"
                                    v-model="slot.start_time"
                                    :min="detail.day_start_time"
                                    :max="detail.day_end_time"
                                    @change="updateSlotEndTime(durationIndex, duration.slots.indexOf(slot))"
                                />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Ավարտ</label>

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
                                    @click="removeSlot(durationIndex, duration.slots.indexOf(slot))"
                                >
                                    Ջնջել
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <InputError class="mt-2" :message="form.errors.session_durations" />

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                    >
                        Պահպանել
                    </PrimaryButton>

                    <button type="reset" class="btn btn-label-secondary waves-effect">
                        Չեղարկել
                    </button>
                </div>
            </form>
        </div>
    </Index>
</template>