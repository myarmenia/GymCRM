<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

// 💡 Գործընկերոջ ստեղծման սկզբնական դատարկ դաշտերը
const form = useForm({
    name: '',
    account_number: '',
    contract_number: '',
    address: '',
    phone: '',
    email: '',
    contact_full_name: '',
    contact_phone: '',
});

const submit = () => {
    form.post(route('partner.store', { locale: currentLocale }), {
        preserveState: false, 
        onSuccess: () => {
            console.log('Redirecting to edit page...');
        },
        onError: (errors) => {
            if (Object.keys(errors).length === 0) {
                alert('Something went wrong on the server. Please check logs.');
            }
        }
    });
};

const cancel = () => {
    router.get(route('partner.list', { locale: currentLocale }));
};
</script>

<template>
    <Head title="Add New Partner" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Partner / Add New Partner
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Add New Partner</h5>
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
                            tabindex="1"
                            placeholder="Enter partner name"
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
                            tabindex="2"
                            placeholder="Enter account number"
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
                        <InputLabel for="phone" class="form-label" value="Phone number" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="form-control"
                            v-model="form.phone"
                            tabindex="3"
                            placeholder="Enter phone number ex. +374 58 79 98 94"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control"
                            v-model="form.email"
                            tabindex="4"
                            placeholder="Enter partner email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="col-6">
                        <InputLabel for="address" class="form-label" value="Address" />
                      
                        <TextInput
                            id="address"
                            type="text"
                            class="form-control"
                            v-model="form.address"
                            tabindex="5"
                            placeholder="Enter partner address"
                        />
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
                            tabindex="6"
                            placeholder="Enter contact person's full name"
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
                            tabindex="7"
                            placeholder="Enter contact phone number"
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
                        Submit
                    </PrimaryButton>
                    <button 
                        type="button" 
                        @click="cancel" 
                        class="btn btn-label-secondary waves-effect"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </Index>
</template>