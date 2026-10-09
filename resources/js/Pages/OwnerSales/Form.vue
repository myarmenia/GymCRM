<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Index from '@/Layouts/Index.vue';
import { useTrans } from '/resources/js/trans';

const props = defineProps({ gyms: Array, sale: Object });
const page = usePage();
const trans = useTrans;
const locale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');
const editing = computed(() => Boolean(props.sale));
const dateValue = value => value ? String(value).slice(0, 10) : '';
const form = useForm({
    gym_id: props.sale?.gym_id ?? '', amount: props.sale?.amount ?? '',
    payment_type: props.sale?.payment_type ?? 'cash', payment_status: props.sale?.payment_status ?? 'paid',
    status: ['active', 'inactive'].includes(props.sale?.status) ? props.sale.status : 'active',
    starts_at: dateValue(props.sale?.starts_at), ends_at: dateValue(props.sale?.ends_at),
    notes: props.sale?.notes ?? '',
});

const submit = () => {
    const options = { preserveScroll: true };
    if (editing.value) {
        form.transform(data => ({ ...data, _method: 'put' }))
            .post(route('owner-sales.update', { locale: locale.value, ownerSale: props.sale.id }), options);
    } else {
        form.post(route('owner-sales.store', { locale: locale.value }), options);
    }
};
</script>

<template>
    <Head :title="trans(editing ? 'page.edit' : 'page.add')" />
    <Index>
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ trans(editing ? 'page.edit' : 'page.add') }}</h5></div>
            <form class="card-body" @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">{{ trans('page.gym') }}</label><select v-model="form.gym_id" class="form-select" :class="{ 'is-invalid': form.errors.gym_id }" required><option value="" disabled>—</option><option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option></select><div class="invalid-feedback">{{ form.errors.gym_id }}</div></div>
                    <div class="col-md-6"><label class="form-label">{{ trans('page.amount') }}</label><input v-model="form.amount" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': form.errors.amount }" required><div class="invalid-feedback">{{ form.errors.amount }}</div></div>
                    <div class="col-md-6"><label class="form-label">{{ trans('page.payment_type') }}</label><select v-model="form.payment_type" class="form-select"><option v-for="type in ['cash','transfer']" :key="type" :value="type">{{ trans(`page.${type}`) }}</option></select></div>
                    <div class="col-md-6"><label class="form-label">{{ trans('page.payment_status') }}</label><select v-model="form.payment_status" class="form-select"><option v-for="status in ['paid','unpaid']" :key="status" :value="status">{{ trans(`page.${status}`) }}</option></select></div>
                    <div class="col-12"><label class="form-label d-block">{{ trans('page.access_status') }}</label><div class="d-flex gap-4"><div class="form-check"><input id="status-active" v-model="form.status" class="form-check-input" type="radio" value="active"><label class="form-check-label" for="status-active">{{ trans('page.status_active') }}</label></div><div class="form-check"><input id="status-inactive" v-model="form.status" class="form-check-input" type="radio" value="inactive"><label class="form-check-label" for="status-inactive">{{ trans('page.status_inactive') }}</label></div></div><div v-if="form.errors.status" class="text-danger small mt-1">{{ form.errors.status }}</div><div class="form-text">{{ trans('page.access_status_help') }}</div></div>
                    <div class="col-md-6"><label class="form-label">{{ trans('page.starts_at') }}</label><input v-model="form.starts_at" type="date" class="form-control" :class="{ 'is-invalid': form.errors.starts_at }" required><div class="invalid-feedback">{{ form.errors.starts_at }}</div></div>
                    <div class="col-md-6"><label class="form-label">{{ trans('page.ends_at') }}</label><input v-model="form.ends_at" type="date" class="form-control" :class="{ 'is-invalid': form.errors.ends_at }" required><div class="invalid-feedback">{{ form.errors.ends_at }}</div></div>
                    <div class="col-12"><label class="form-label">{{ trans('page.notes') }}</label><textarea v-model="form.notes" rows="3" class="form-control" :class="{ 'is-invalid': form.errors.notes }"></textarea><div class="invalid-feedback">{{ form.errors.notes }}</div></div>
                </div>
                <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary" :disabled="form.processing">{{ trans('page.save') }}</button><Link :href="route('owner-sales.index', { locale })" class="btn btn-outline-secondary">{{ trans('page.cancel') }}</Link></div>
            </form>
        </div>
    </Index>
</template>
