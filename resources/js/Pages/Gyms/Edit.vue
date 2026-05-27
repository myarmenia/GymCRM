<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    gym: Object
});

const form = useForm({
    name: props.gym.name,
    address: props.gym.address,
    phone: props.gym.phone,
    email: props.gym.email,
    logo: null
});

const submit = () => {

    form.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(route('gym.update', { locale: currentLocale, id: props.gym.id }), {
        onError: () => {
            console.log('Update failed');
        }
    });
};

const cancel = () => {
    router.get(route('gym.list', { locale: currentLocale }));
};
</script>

<template>
    <Head :title="`Edit Gym - ${props.gym.name}`" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Gym / Edit Gym
            </h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Edit Gym: {{ props.gym.name }}</h5>
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
                        />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="col-md-6">
                        <InputLabel for="logo" class="form-label" value="Gym Logo" />
                        <div class="d-flex align-items-center gap-3">
                            <div v-if="props.gym.logo" class="mb-2">
                                <img
                                    :src="`/storage/${props.gym.logo}`"
                                    alt="Current Logo"
                                    class="rounded object-fit-cover"
                                    style="width: 50px; height: 50px; border: 1px solid #e5e7eb;"
                                />
                            </div>
                            <div class="flex-grow-1">
                                <input
                                    id="logo"
                                    type="file"
                                    class="form-control"
                                    @input="form.logo = $event.target.files[0]"
                                />
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.logo" />
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
    </Index>
</template>
