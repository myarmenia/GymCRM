<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Index from '@/Layouts/Index.vue';
import InputError from '@/Components/InputError.vue';
import { useTrans } from '/resources/js/trans';

const props = defineProps({
    gyms: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const trans = useTrans;
const currentLocale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');
const form = useForm({
    gym_id: props.gyms.length === 1 ? props.gyms[0].id : '',
    name: '',
    ip: '',
    port: 6000,
    password: '',
    status: true,
});

const submit = () => {
    form.post(route('hdm-configurations.store', { locale: currentLocale.value }));
};
</script>

<template>
    <Head :title="trans('page.create_title')" />

    <Index>
        <template #header>
            <div class="d-flex align-items-center gap-2">
                <Link :href="route('hdm-configurations.index', { locale: currentLocale })" class="btn btn-sm btn-icon btn-label-secondary">
                    <i class="ti tabler-arrow-left"></i>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-0">{{ trans('page.create_title') }}</h2>
            </div>
        </template>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ trans('page.config_section') }}</h5></div>
            <form class="card-body" @submit.prevent="submit">
                <div class="row g-4">
                    <div class="col-md-12">
                        <label class="form-label" for="gym_id">{{ trans('page.fields.gym') }} *</label>
                        <select id="gym_id" v-model="form.gym_id" class="form-select">
                            <option value="" disabled>{{ trans('page.placeholders.select_gym') }}</option>
                            <option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.gym_id" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name">{{ trans('page.fields.name') }} *</label>
                        <input id="name" v-model="form.name" type="text" class="form-control" :placeholder="trans('page.placeholders.name')" />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="port">{{ trans('page.fields.port') }} *</label>
                        <input id="port" v-model="form.port" type="number" min="1" max="65535" class="form-control" />
                        <InputError class="mt-1" :message="form.errors.port" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ip">{{ trans('page.fields.ip') }} *</label>
                        <input id="ip" v-model="form.ip" type="text" class="form-control" placeholder="192.168.1.10" />
                        <InputError class="mt-1" :message="form.errors.ip" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">{{ trans('page.fields.password') }} *</label>
                        <input id="password" v-model="form.password" type="password" class="form-control" autocomplete="new-password" />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input id="status" v-model="form.status" class="form-check-input" type="checkbox" />
                            <label class="form-check-label" for="status">{{ trans('page.fields.active') }}</label>
                        </div>
                        <InputError class="mt-1" :message="form.errors.status" />
                    </div>
                </div>

                <div class="d-flex gap-2 mt-5">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        {{ trans('page.actions.save') }}
                    </button>
                    <Link :href="route('hdm-configurations.index', { locale: currentLocale })" class="btn btn-label-secondary">
                        {{ trans('page.actions.cancel') }}
                    </Link>
                </div>
            </form>
        </div>
    </Index>
</template>
