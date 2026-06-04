<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTrans } from '/resources/js/trans';
import axios from 'axios';
import MultiSelect from '@/Components/MultiSelect.vue';


const page = usePage();
const currentLocale = page.props.locale ?? "en";

const props = defineProps({
  roles: Array,
  gyms: Array,
  canSelectGym: Boolean,
  entryCodes: Array,
});


// const selectedGymId = ref(null);
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
    gym_id: null,
    entry_code_id: null,
});

// Watch for gym change to load entry codes dynamically
// watch(selectedGymId, async (newGymId) => {
//     if (newGymId) {
//         try {
//             const response = await axios.get(route('entry-code.by-gym', {
//                 locale: currentLocale,
//                 gymId: newGymId
//             }));
//             entryCodes.value = response.data;
//             // Reset entry_code_id when gym changes
//             form.entry_code_id = null;
//         } catch (error) {
//             console.error('Failed to load entry codes', error);
//             entryCodes.value = [];
//         }
//     } else {
//         entryCodes.value = [];
//         form.entry_code_id = null;
//     }
// });

watch(() => form.gym_id, async (newGymId) => {
    if (newGymId) {
        try {
            const response = await axios.get(route('entry-code.by-gym', {
                locale: currentLocale,
                gymId: newGymId
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

onMounted(async () => {
    // If user can select gym and there's an initial gym, load entry codes for that gym
    console.log(props.entryCodes, 'initial entry codes');
    if (props.entryCodes.length) {
        entryCodes.value = props.entryCodes;
    } else {
        entryCodes.value = [];
    }
})

// Handle gym selection change
// const onGymChange = (event) => {
//     const gymId = event.target.value;
//     selectedGymId.value = gymId;
//     form.gym_id = gymId;
// };

const onGymChange = (event) => {
    const gymId = Number(event.target.value) || null;

    // selectedGymId.value = gymId;
    form.gym_id = gymId;
};

// Handle entry code selection change
const onEntryCodeChange = (event) => {
    form.entry_code_id = event.target.value;
};


// onMounted(async () => {
//     await nextTick();

//     const $roles = window.$('#roles');
//     const $gyms = window.$('#gyms');

//     if ($gyms.length && props.canSelectGym) {
//         $gyms.select2({
//             width: '100%',
//             placeholder: 'Choose gym'
//         });
//         $gyms.on('change', onGymChange);
//     }

//     if ($roles.length) {
//         $roles.select2({
//             width: '100%',
//             placeholder: 'Choose roles'
//         });
//         $roles.on('change', function () {
//             form.roles = window.$(this).val();
//         });
//     }
// });


const roleOptions = computed(() => {
    return props.roles.map(role => ({
        label: useTrans(`page.roles.${role.name}`),
        value: role.name,
    }));
});

const gymOptions = computed(() => {
    return props.gyms.map(gym => ({
        label: gym.name,
        value: gym.id,
    }));
});




const submit = () => {
    form.post(route('user.store', { locale: currentLocale }), {
        onError: (errors) => {
            if (errors.password || errors.password_confirmation) {
                form.reset('password', 'password_confirmation');
            }
        }
    });
};
</script>

<template>
    <Head title="Ավելացնել նոր աշխատակից" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Օգտատեր / Ավելացնել նոր աշխատակից</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Ավելացնել նոր աշխատակից</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>1. Հաշվի տվյալներ</h6>
                <div class="row g-6">
                    <!-- Gym selection (only for owner) -->
                    <div v-if="canSelectGym" class="col-md-12 select2-primary">
                        <InputLabel for="gyms" class="form-label" value="Մարզադահլիճ" />

                        <select class="form-select" v-model="form.gym_id" @change="onGymChange">
                            <option value="" disabled>Ընտրել մարզադահլիճ</option>


                            <option
                                v-for="gym in gymOptions"
                                :key="gym.value"
                                :value="gym.value"
                            >
                                {{ gym.label }}
                            </option>
                        </select>

                        <!-- <select id="gyms" class="select2 form-select">
                            <option disabled selected>Choose gym</option>
                            <option
                                v-for="gym in gymOptions"
                                :key="gym.value"
                                :value="gym.value"
                            >
                                {{ gym.text }}
                            </option>
                        </select> -->
                        <InputError class="mt-2" :message="form.errors.gym_id" />
                    </div>

                    <!-- Entry Code dropdown (appears after gym is selected) -->
                    <div v-if="entryCodes.length" class="col-md-12 select2-primary">
                        <InputLabel for="entry_codes" class="form-label" value="Մուտքի կոդ" />
                        <select id="entry_codes" class="form-select" @change="onEntryCodeChange">
                            <option :value="null">Ոչինչ</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id">
                                {{ code.token }} ({{ code.gym?.name || 'Առանց մարզադահլիճի' }}) {{ code.type }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.entry_code_id" />
                    </div>

                    <!-- Rest of the fields (name, surname, password, etc.) remain unchanged -->
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Անուն" />
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus tabindex="1" placeholder="Մուտքագրել անունը" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" value="Ազգանուն" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" tabindex="2" placeholder="Մուտքագրել ազգանունը" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>
                    <div class="col-md-6">
                        <div class="form-password-toggle">
                            <label class="form-label">Գաղտնաբառ</label>
                            <div class="input-group input-group-merge">
                                <input type="password" v-model="form.password" tabindex="3" class="form-control" placeholder="············">
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Էլ. հասցե" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" tabindex="5" placeholder="Մուտքագրել էլ. հասցեն" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="col-md-6">
                        <div class="form-password-toggle">
                            <label class="form-label">Հաստատել գաղտնաբառը</label>
                            <div class="input-group input-group-merge">
                                <input type="password" v-model="form.password_confirmation" tabindex="4" class="form-control" placeholder="············">
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Հեռախոսահամար" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" tabindex="6" placeholder="+374 58 79 98 94" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="col-md-6 select2-primary">
                        <InputLabel for="roles" class="form-label" value="Դերեր" />
                        <!-- <select id="roles" class="select2 form-select" multiple tabindex="7">
                            <option
                                v-for="(role, index) in roleOptions"
                                :key="index"
                                :value="role.value"
                            >
                                {{ useTrans(`page.roles.${role.value}`) }}
                            </option>
                        </select> -->
                        <MultiSelect
                            v-model="form.roles"
                            :options="roleOptions"
                            placeholder="Ընտրել դերերը"
                        />
                        <InputError class="mt-2" :message="form.errors.roles" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ակտիվ</label>
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="flexSwitchCheckChecked"
                                v-model="form.active"
                            >
                        </div>
                    </div>
                </div>

                <hr class="my-6 mx-n6">
                <h6>2. Անձնական տվյալներ</h6>
                <div class="row g-6">
                    <div class="col-md-6">
                        <InputLabel for="passport_number" class="form-label" value="Անձնագրի համար / ID" />
                        <TextInput id="passport_number" type="text" class="form-control" v-model="form.passport_number" tabindex="9" placeholder="AB547896 / 005423587" />
                        <InputError :message="form.errors.passport_number" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="passport_expire_at" class="form-label" value="Անձնագրի ժամկետը (մինչև)" />
                        <TextInput id="passport_expire_at" type="date" class="form-control" v-model="form.passport_expire_at" tabindex="10" />
                        <InputError :message="form.errors.passport_expire_at" />
                    </div>
                    <div class="col-md-6">
                        <InputLabel for="birth_date" class="form-label" value="Ծննդյան ամսաթիվ" />
                        <TextInput id="birth_date" type="date" class="form-control" v-model="form.birth_date" tabindex="12" />
                        <InputError :message="form.errors.birth_date" />
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Պահպանել
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">Չեղարկել</button>
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