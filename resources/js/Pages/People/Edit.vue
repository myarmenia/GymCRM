<script setup>
import { ref, watch, onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DocumentsUploader from '@/Components/DocumentsUploader.vue';
import axios from 'axios';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
  person: Object,
  gyms: Array,
  canSelectGym: Boolean,
  selectedEntryCodeId: Number,
});

const selectedGymId = ref(null);
const entryCodes = ref([]);

const form = useForm({
    name: '',
    surname: '',
    email: '',
    phone: '',
    type: 'visitor',
    gym_id: null,
    entry_code_id: null,
});

// Populate form from person data
if (props.person) {
    form.name = props.person.name || '';
    form.surname = props.person.surname || '';
    form.email = props.person.email || '';
    form.phone = props.person.phone || '';
    form.type = props.person.type || 'visitor';
    form.gym_id = props.person.gym_id ?? null;
    form.entry_code_id = props.selectedEntryCodeId ?? null;
}

// Set initial selected gym
if (props.person?.gym_id) {
    selectedGymId.value = props.person.gym_id;
}

// Load entry codes on mount
onMounted(() => {
    if (selectedGymId.value) {
        loadEntryCodes(selectedGymId.value);
    }
});

// Watch for gym change
watch(() => form.gym_id, async (newGymId) => {
    if (newGymId) {
        await loadEntryCodes(newGymId);
        // Reset entry code if it doesn't belong to new gym
        if (form.entry_code_id && !entryCodes.value.some(c => c.id == form.entry_code_id)) {
            form.entry_code_id = null;
        }
    } else {
        entryCodes.value = [];
        form.entry_code_id = null;
    }
});

const loadEntryCodes = async (gymId) => {
    try {
        const response = await axios.get(route('entry-code.by-gym', {
            locale: currentLocale,
            gymId: gymId,
            current_id: form.entry_code_id || props.selectedEntryCodeId || undefined,
        }));
        entryCodes.value = response.data;
    } catch (error) {
        console.error('Failed to load entry codes', error);
        entryCodes.value = [];
    }
};

const onGymChange = (event) => {
    form.gym_id = Number(event.target.value) || null;
};

const submit = () => {
    form.patch(route('person.update', { id: props.person.id, locale: currentLocale }), {
        onSuccess: () => {
            // optional: reset or redirect
        }
    });
};
</script>

<template>
    <Head title="Edit Person" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">People / Edit Person</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Edit Person</h5>
            <form @submit.prevent="submit" class="card-body">
                <div class="row g-6">
                    <!-- Gym selection (only for owner) -->
                    <div v-if="canSelectGym" class="col-md-12 select2-primary">
                        <InputLabel for="gyms" class="form-label" value="Gym" />
                        <select v-model="form.gym_id" class="form-select" @change="onGymChange">
                            <option :value="null">No gym</option>
                            <option v-for="gym in gyms" :key="gym.id" :value="gym.id">
                                {{ gym.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.gym_id" />
                    </div>

                    <!-- Entry Code dropdown -->
                    <div v-if="entryCodes.length" class="col-md-12 select2-primary">
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
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus placeholder="Enter name" />
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
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" placeholder="Enter email" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Phone number" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" placeholder="+374 58 79 98 94" />
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
                        Update
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                </div>
            </form>
        </div>

        <DocumentsUploader :ownerType="'person'" :ownerId="props.person.id" />
    </Index>
</template>