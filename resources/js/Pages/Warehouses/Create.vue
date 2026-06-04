<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const form = useForm({
    name: '',
    type: '',
    phone: '',
    address: '',
    status: true
});

const submit = () => {
    form.post(route('warehouse.store', { locale: currentLocale }));
};

</script>

<template>
    <Head title="Ավելացնել նոր պահեստ" />
    <Index>
        <div class="card mb-6">
            <h5 class="card-header">Ավելացնել նոր պահեստ</h5>
            <form @submit.prevent="submit" class="card-body row g-6">
                
                <div class="col-md-6">
                    <InputLabel for="name" value="Պահեստի անուն" />
                    <TextInput id="name" type="text" class="form-control" v-model="form.name" />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="type" value="Տիպ" />
    
                    <select id="type" class="form-select" v-model="form.type">
                        <option value="">ընտրել տիպը</option>
                        <option value="main">Հիմնական</option>
                    </select>

                    <InputError class="mt-2" :message="form.errors.type" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="phone" value="Հեռախոս" />
                    <TextInput id="phone" type="text" class="form-control" v-model="form.phone" />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div class="col-md-6">
                    <InputLabel for="address" value="Հասցե" />
                    <TextInput id="address" type="text" class="form-control" v-model="form.address" />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>

                <div class="col-md-6">
                    <label class="form-label d-block">Կարգավիճակ (Ակտիվ)</label>
                    <div class="form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" v-model="form.status">
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end">
                    <PrimaryButton :disabled="form.processing" class="me-4">Հաստատել</PrimaryButton>
                    <button type="button" @click="router.get(route('warehouse.list', { locale: currentLocale }))" class="btn btn-label-secondary">Չեղարկել</button>
                </div>
            </form>
        </div>
    </Index>
</template>