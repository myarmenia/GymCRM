<script setup>
import { ref, watch } from 'vue';
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
  gyms: Array,
  canSelectGym: Boolean,
});

const entryCodes = ref([]);

const form = useForm({
    gym_id: null,
    name: '',
    surname: '',
    email: '',
    phone: '',
    type: 'visitor',   // 'visitor' or 'employee'
    entry_code_id: null,
});

// When gym changes, load entry codes for that gym
watch(() => form.gym_id, async (newGymId) => {
    if (newGymId) {
        try {
            const response = await axios.get(route('entry-code.by-gym', {
                locale: currentLocale,
                gymId: newGymId,
            }));
            entryCodes.value = response.data;
            form.entry_code_id = null;
        } catch (error) {
            console.error('Failed to load entry codes', error);
            entryCodes.value = [];
        }
    } else {
        entryCodes.value = [];
        form.entry_code_id = null;
    }
});

const onGymChange = (event) => {
    form.gym_id = Number(event.target.value) || null;
};

const onEntryCodeChange = (event) => {
    form.entry_code_id = event.target.value;
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
                    <!-- Gym selection (only for owner) -->
                    <div v-if="canSelectGym" class="col-md-12 select2-primary">
                        <InputLabel for="gyms" class="form-label" value="Gym" />
                        <select class="form-select" v-model="form.gym_id" @change="onGymChange">
                            <option value="" disabled>Choose gym</option>
                            <option
                                v-for="gym in gyms"
                                :key="gym.id"
                                :value="gym.id"
                            >
                                {{ gym.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.gym_id" />
                    </div>

                    <!-- Entry Code dropdown -->
                    <div v-if="entryCodes.length" class="col-md-12 select2-primary">
                        <InputLabel for="entry_codes" class="form-label" value="Entry Code" />
                        <select id="entry_codes" class="form-select" @change="onEntryCodeChange">
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
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus tabindex="1" placeholder="Enter name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Surname -->
                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" value="Surname" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" tabindex="2" placeholder="Enter surname" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" tabindex="3" placeholder="Enter email" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Phone number" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" tabindex="4" placeholder="+374 58 79 98 94" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <!-- Type (visitor/employee) -->
                    <div class="col-md-6">
                        <InputLabel for="type" class="form-label" value="Type" />
                        <select id="type" class="form-select" v-model="form.type" tabindex="5">
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

<style scoped>
.select2-container {
    width: 100% !important;
}
</style>