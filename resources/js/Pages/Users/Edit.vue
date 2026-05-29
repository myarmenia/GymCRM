<script setup>
import { onMounted, computed, nextTick, ref, watch } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTrans } from '/resources/js/trans';
import DocumentsUploader from '@/Components/DocumentsUploader.vue';
import axios from 'axios';

const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
  roles: Array,
  user: Object,
  gyms: Array,
  canSelectGym: Boolean,
  selectedEntryCodeId: Number, // passed from controller
});

const selectedGymId = ref(null);
const entryCodes = ref([]);

const form = useForm({
    name: '',
    surname: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
    passport_number: '',
    passport_expire_at: '',
    birth_date: '',
    roles: [],
    active: true,
    gym_id: '',
    entry_code_id: null,
});

// Watch for gym change to load entry codes dynamically
watch(selectedGymId, async (newGymId) => {
    if (newGymId) {
        try {
            const response = await axios.get(route('entry-code.by-gym', {
                locale: currentLocale,
                gymId: newGymId,
                current_id: form.entry_code_id || props.selectedEntryCodeId || undefined

            }));
            console.log('Entry codes loaded for gym', newGymId, response.data);
            entryCodes.value = response.data;
            // Only reset if current entry_code_id doesn't belong to new gym
            if (form.entry_code_id && !entryCodes.value.some(c => c.id === form.entry_code_id)) {
                form.entry_code_id = null;
            }
        } catch (error) {
            console.error('Failed to load entry codes', error);
            entryCodes.value = [];
        }
    } else {
        entryCodes.value = [];
        form.entry_code_id = null;
    }
});

// Handle gym selection change (for owner)
const onGymChange = (event) => {
    selectedGymId.value = event.target.value;
    form.gym_id = selectedGymId.value;
};

// Handle entry code selection
const onEntryCodeChange = (event) => {
    form.entry_code_id = event.target.value;
};

onMounted(async () => {
    await nextTick();

    // Fill form with user data
    if (props.user) {
        form.name = props.user.name || '';
        form.surname = props.user.surname || '';
        form.phone = props.user.phone || '';
        form.email = props.user.email || '';
        form.passport_number = props.user.passport_number || '';
        form.passport_expire_at = props.user.passport_expire_at || '';
        form.birth_date = props.user.birth_date || '';
        form.roles = props.user.roles ? props.user.roles.map(r => r.name) : [];
        form.active = props.user.active ?? true;
        form.gym_id = props.user.gym_id || '';
        form.entry_code_id = props.selectedEntryCodeId ?? null;
    }

    // Set selectedGymId for watch
    selectedGymId.value = form.gym_id || null;

    // Initialize Select2
    const $roles = window.$('#roles');
    const $gyms = window.$('#gyms');

    if ($roles.length) {
        $roles.select2({ width: '100%', placeholder: 'Choose roles' });
        $roles.val(form.roles).trigger('change');
        $roles.on('change', function () {
            form.roles = window.$(this).val();
        });
    }

    if ($gyms.length && props.canSelectGym) {
        $gyms.select2({ width: '100%', placeholder: 'Choose gym' });
        if (form.gym_id) {
            $gyms.val(form.gym_id).trigger('change');
        }
        $gyms.on('change', onGymChange);
    }

    // Load entry codes for initial gym (if any)
    if (selectedGymId.value) {
        try {
            const response = await axios.get(route('entry-code.by-gym', {
                locale: currentLocale,
                gymId: selectedGymId.value,
                current_id: props.selectedEntryCodeId || undefined

            }));
            entryCodes.value = response.data;
        } catch (error) {
            console.error('Failed to load entry codes', error);
        }
    }
});

const roleOptions = computed(() => {
    return props.roles.map(role => ({ value: role.name }));
});

const gymOptions = computed(() => {
    return props.gyms.map(gym => ({ value: gym.id, text: gym.name }));
});

const submit = () => {
    form.patch(route('user.update', { id: props.user.id, locale: currentLocale }), {
        onError: () => {
            if (form.errors.password || form.errors.password_confirmation) {
                form.reset('password', 'password_confirmation');
            }
        }
    });
};
</script>

<template>
    <Head title="Edit Employee" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">User / Edit employee</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Edit Employee</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>1. Account Details</h6>
                <div class="row g-6">
                    <!-- Gym selection (only for owner) -->
                    <div v-if="canSelectGym" class="col-md-12 select2-primary">
                        <InputLabel for="gyms" class="form-label" value="Gyms" />
                        <select id="gyms" class="select2 form-select">
                            <option disabled>Choose gym</option>
                            <option v-for="gym in gymOptions" :key="gym.value" :value="gym.value">
                                {{ gym.text }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.gym_id" />
                    </div>

                    <!-- Entry Code dropdown (appears if entry codes exist) -->
                    <div v-if="entryCodes.length" class="col-md-12 select2-primary">
                        <InputLabel for="entry_codes" class="form-label" value="Entry Code" />
                        <select id="entry_codes" class="form-select" @change="onEntryCodeChange">
                            <option :value="null">None</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id" :selected="code.id === form.entry_code_id">
                                {{ code.token }} ({{ code.gym?.name || 'No gym' }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.entry_code_id" />
                    </div>

                    <!-- Name, Surname, etc. (existing fields) -->
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Name" />
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus tabindex="1" placeholder="Enter name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" value="Surname" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" tabindex="2" placeholder="Enter surname" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>
                    <div class="col-md-6">
                        <div class="form-password-toggle">
                            <label class="form-label">Password</label>
                            <div class="input-group input-group-merge">
                                <input type="password" v-model="form.password" tabindex="3" id="multicol-password" class="form-control" placeholder="············">
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Email" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" tabindex="5" placeholder="Enter email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="col-md-6">
                        <div class="form-password-toggle">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group input-group-merge">
                                <input type="password" v-model="form.password_confirmation" tabindex="4" id="multicol-confirm-password" class="form-control" placeholder="············">
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Phone number" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" tabindex="6" placeholder="+374 58 79 98 94" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="col-md-6 select2-primary">
                        <InputLabel for="roles" class="form-label" value="Roles" />
                        <select id="roles" class="select2 form-select" multiple tabindex="7">
                            <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                                {{ useTrans(`page.roles.${role.value}`) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.roles" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Active</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" v-model="form.active" :checked="form.active">
                        </div>
                    </div>
                </div>

                <hr class="my-6 mx-n6" />
                <h6>2. Personal Info</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="passport_number" class="form-label" value="Passport number / ID" />
                        <TextInput id="passport_number" type="text" class="form-control" v-model="form.passport_number" tabindex="9" placeholder="AB547896 / 005423587" />
                        <InputError :message="form.errors.passport_number" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="passport_expire_at" class="form-label" value="Passport expire at" />
                        <TextInput id="passport_expire_at" type="date" class="form-control" v-model="form.passport_expire_at" tabindex="10" />
                        <InputError :message="form.errors.passport_expire_at" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="birth_date" class="form-label" value="Birth date" />
                        <TextInput id="birth_date" type="date" class="form-control" v-model="form.birth_date" tabindex="12" />
                        <InputError :message="form.errors.birth_date" />
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

        <DocumentsUploader :ownerType="'user'" :ownerId="props.user.id" />
    </Index>
</template>