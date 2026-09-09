<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const props = defineProps({
    user: Object,
    roles: Array,
    selectedEntryCodeId: Number,
    lastAttendance: Object,
});

const localDateTime = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());

    return date.toISOString().slice(0, 16);
};

const staffAttendanceForm = useForm({
    action: 'entry',
    manual_datetime: localDateTime(),
});

const staffInside = () => props.lastAttendance?.direction === 'entry';

const recordAttendance = (action) => {
    staffAttendanceForm.action = action;
    staffAttendanceForm.post(route('user.attendance.store', {
        locale: currentLocale,
        id: props.user.id,
    }), { preserveScroll: true });
};
</script>

<template>
    <Head title="Օգտատիրոջ տվյալներ" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Օգտատեր / Տվյալներ
            </h2>
        </template>

        <div class="card">
            <h5 class="card-header">Օգտատիրոջ մանրամասներ</h5>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Անուն</div>
                    <div class="col-md-9">{{ user.name }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Ազգանուն</div>
                    <div class="col-md-9">{{ user.surname }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Էլ. հասցե</div>
                    <div class="col-md-9">{{ user.email }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Հեռախոս</div>
                    <div class="col-md-9">{{ user.phone }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Դերեր</div>
                    <div class="col-md-9">
                        <span v-for="role in roles" :key="role" class="badge bg-primary me-1">
                            {{ role }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Կարգավիճակ</div>
                    <div class="col-md-9">
                        <span :class="user.active ? 'text-success' : 'text-danger'">
                            {{ user.active ? 'Ակտիվ' : 'Ոչ ակտիվ' }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3" v-if="selectedEntryCodeId">
                    <div class="col-md-3 fw-bold">Մուտքի կոդ (Entry Code)</div>
                    <div class="col-md-9">{{ selectedEntryCodeId }}</div>
                </div>
            </div>
            <div class="card-footer">
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2">Անձնակազմի մուտք / ելք</div>
                    <input
                        v-model="staffAttendanceForm.manual_datetime"
                        type="datetime-local"
                        class="form-control mb-2"
                    >
                    <div v-if="staffAttendanceForm.errors.action" class="text-danger small mb-2">
                        {{ staffAttendanceForm.errors.action }}
                    </div>
                    <div class="d-flex gap-2">
                        <button
                            type="button"
                            class="btn btn-success"
                            :disabled="staffAttendanceForm.processing || staffInside()"
                            @click="recordAttendance('entry')"
                        >
                            Ֆիքսել մուտք
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="staffAttendanceForm.processing || !staffInside()"
                            @click="recordAttendance('exit')"
                        >
                            Ֆիքսել ելք
                        </button>
                    </div>
                </div>

                <Link :href="route('user.list', { locale: currentLocale })" class="btn btn-secondary">
                    Վերադառնալ ցուցակ
                </Link>
            </div>
        </div>
    </Index>
</template>
