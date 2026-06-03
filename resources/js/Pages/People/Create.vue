<script setup>
import { ref, onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
    initialGymId: Number,  // gym ID-ն entry codes-ը բեռնելու համար
});

const entryCodes = ref([]);

const form = useForm({
    name: '',
    surname: '',
    email: '',
    password: '',
    phone: '',
    type: 'visitor',
    entry_code_id: null,
});

onMounted(() => {
    if (props.initialGymId) {
        loadEntryCodes(props.initialGymId);
    }
});

const loadEntryCodes = async (gymId) => {
    try {
        const response = await axios.get(route('entry-code.by-gym', {
            locale: currentLocale,
            gymId: gymId,
        }));
        entryCodes.value = response.data;
    } catch (error) {
        console.error('Failed to load entry codes', error);
        entryCodes.value = [];
    }
};

const submit = () => {
    form.post(route('person.store', { locale: currentLocale }));
};
</script>

<template>
    <Head title="Add New Person" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">People / Add New Person</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Add New Person</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>General Information</h6>
                <div class="row g-6">
                    <!-- Entry Code dropdown (appears only if entry codes exist for the given gym) -->
                    <div v-if="entryCodes.length" class="col-md-12">
                        <InputLabel for="entry_codes" class="form-label" value="Entry Code" />
                        <select id="entry_codes" class="form-select" v-model="form.entry_code_id">
                            <option :value="null">None</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id">
                                {{ code.token }} ({{ code.gym?.name || 'No gym' }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.entry_code_id" />
                    </div>

                    <!-- Name -->
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Name" />
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus placeholder="Enter name" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Surname -->
                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" value="Surname" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" placeholder="Enter surname" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" placeholder="Enter email" required />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="col-md-6">
                        <InputLabel for="password" class="form-label" value="Password" />
                        <TextInput id="password" type="password" class="form-control" v-model="form.password" placeholder="Enter password (optional)" required />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Phone number" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" placeholder="+374 58 79 98 94" required />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <!-- Type -->
                    <div class="col-md-6">
                        <InputLabel for="type" class="form-label" value="Type" />
                        <select id="type" class="form-select" v-model="form.type">
                            <option value="visitor">Visitor</option>
                            <option value="employee">Employee</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>

                <div class="pt-6">
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Submit
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                </div>
            </form>
        </div>
    </Index>
</template>