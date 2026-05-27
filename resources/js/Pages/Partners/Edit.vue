<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DocumentsUploader from '@/Components/DocumentsUploader.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    partner: Object
});

// 💡 Լրացնում ենք գործընկերոջ բոլոր 7 դաշտերը
const form = useForm({
    name: props.partner.name,
    account_number: props.partner.account_number,
    contract_number: props.partner.contract_number,
    address: props.partner.address,
    phone: props.partner.phone,
    email: props.partner.email,
    contact_full_name: props.partner.contact_full_name,
    contact_phone: props.partner.contact_phone ?? ''
});

const submit = () => {
    // 💡 Ուղարկում ենք PATCH հարցում համապատասխան ռոութին
    form.patch(route('partner.update', { locale: currentLocale, id: props.partner.id }), {
        onError: () => {
            console.log('Update failed');
        }
    });
};

const cancel = () => {
    router.get(route('partner.list', { locale: currentLocale }));
};
</script>

<template>
    <Head :title="`Edit Partner - ${props.partner.name}`" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Partner / Edit Partner
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Edit Partner: {{ props.partner.name }}</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>Partner Details</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Partner / Company Name" />
                        <TextInput
                            id="name"
                            type="text"
                            class="form-control"
                            v-model="form.name"
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="account_number" class="form-label" value="Account Number" />
                        <TextInput
                            id="account_number"
                            type="text"
                            class="form-control"
                            v-model="form.account_number"
                        />
                        <InputError class="mt-2" :message="form.errors.account_number" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="contract_number" class="form-label" value="Contract Number" />
                        <TextInput
                            id="contract_number"
                            type="text"
                            class="form-control"
                            v-model="form.contract_number"
                            tabindex="3"
                            placeholder="Enter contract number"
                        />
                        <InputError class="mt-2" :message="form.errors.contract_number" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Phone Number" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="form-control"
                            v-model="form.phone"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email Address" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control"
                            v-model="form.email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="address" class="form-label" value="Address" />
                        <TextInput
                            id="address"
                            class="form-control"
                            type="text"
                            v-model="form.address"
                        ></TextInput>
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-0">Contact Person Details</h6>

                    <div class="col-md-6">
                        <InputLabel for="contact_full_name" class="form-label" value="Contact Full Name" />
                        <TextInput
                            id="contact_full_name"
                            type="text"
                            class="form-control"
                            v-model="form.contact_full_name"
                        />
                        <InputError class="mt-2" :message="form.errors.contact_full_name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="contact_phone" class="form-label" value="Contact Phone (Optional)" />
                        <TextInput
                            id="contact_phone"
                            type="text"
                            class="form-control"
                            v-model="form.contact_phone"
                        />
                        <InputError class="mt-2" :message="form.errors.contact_phone" />
                    </div>
                </div>

                <div class="pt-6">
                    <PrimaryButton
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        class="me-4"
                    >
                        Save Changes
                    </PrimaryButton>
                    <button type="button" @click="cancel" class="btn btn-label-secondary waves-effect">Cancel</button>
                </div>
            </form>
        </div>

        <DocumentsUploader
            :ownerType="'partner'"
            :ownerId="props.partner.id"
        />
    </Index>
</template>