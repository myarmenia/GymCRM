<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTrans } from '/resources/js/trans';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const form = useForm({
    name: '',
    address: '',
    phone: '',
    email: '',
    logo: null,
});

const submit = () => {
    form.post(route('gym.store', { locale: currentLocale }), {
        onError: (errors) => {
            if (Object.keys(errors).length === 0) {
                alert('Something went wrong on the server. Please check logs.');
            }
        }
    });
};

const cancel = () => {
    router.get(route('gym.list', { locale: currentLocale }));
};

</script>

<template>
    <Head title="Add New Gym" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Gym / Add New Gym
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Add New Gym</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>Gym Details</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Gym Name" />
                        <TextInput
                            id="name"
                            type="text"
                            class="form-control"
                            v-model="form.name"
                            autofocus
                            tabindex="1"
                            placeholder="Enter gym name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="form-control"
                            v-model="form.email"
                            tabindex="2"
                            placeholder="Enter gym email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
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
                        <InputLabel for="address" class="form-label" value="Address" />
                        <TextInput
                            id="address"
                            type="text"
                            class="form-control"
                            v-model="form.address"
                            tabindex="4"
                            placeholder="Enter gym address"
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="logo" class="form-label" value="Gym Logo" />
                        <input
                            id="logo"
                            type="file"
                            class="form-control"
                            @input="form.logo = $event.target.files[0]"
                        />
                        <InputError class="mt-2" :message="form.errors.logo" />
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
