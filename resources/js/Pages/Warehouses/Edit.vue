<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    warehouse: Object,
});

const form = useForm({
    gym_id: props.warehouse.gym_id,
    name: props.warehouse.name,
    type: props.warehouse.type,
    phone: props.warehouse.phone,
    address: props.warehouse.address,
    status: Boolean(props.warehouse.status)
});

const submit = () => {
    form.patch(route('warehouse.update', { locale: currentLocale, id: props.warehouse.id }));
};

</script>

<template>
    <Head :title="t('inventory.edit_named_warehouse', { name: props.warehouse.name })" />
    <Index>
        <div class="card mb-6">
            <h5 class="card-header">{{ t('inventory.edit_warehouse') }}</h5>
            <form @submit.prevent="submit" class="card-body row g-6">

                <div class="col-md-6">
                    <InputLabel for="name" :value="t('inventory.warehouse_name')" />
                    <TextInput id="name" type="text" class="form-control" v-model="form.name" />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="type" :value="t('inventory.type')" />

                    <select id="type" class="form-select" v-model="form.type">
                        <option value="">{{ t('inventory.select_type') }}</option>
                        <option value="main">{{ t('inventory.main') }}</option>
                    </select>

                    <InputError class="mt-2" :message="form.errors.type" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="phone" :value="t('filter.phone')" />
                    <TextInput id="phone" type="text" class="form-control" v-model="form.phone" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="address" :value="t('inventory.address')" />
                    <TextInput id="address" type="text" class="form-control" v-model="form.address" />
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block">{{ t('inventory.status_active') }}</label>
                    <div class="form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" v-model="form.status">
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end">
                    <PrimaryButton :disabled="form.processing" class="me-4">{{ t('inventory.save_changes') }}</PrimaryButton>
                    <button type="button" @click="router.get(route('warehouse.list', { locale: currentLocale }))" class="btn btn-label-secondary">{{ t('confirm.cancel') }}</button>
                </div>
            </form>
        </div>
    </Index>
</template>
