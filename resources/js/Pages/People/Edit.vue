<script setup>
import { ref, onMounted } from 'vue';
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
    initialGymId: Number,
    selectedEntryCodeId: Number,
    entryCodes: Array,
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
    birth_date: '',
    gender: '',
   
});

if (props.person) {
    form.name = props.person.name || '';
    form.surname = props.person.surname || '';
    form.email = props.person.email || '';
    form.phone = props.person.phone || '';
    form.type = props.person.type || 'visitor';
    form.entry_code_id = props.selectedEntryCodeId ?? null;
    form.birth_date = props.person.birth_date || ''; 
    form.gender = props.person.gender || ''; 
}

onMounted(() => {
    entryCodes.value = props.entryCodes || [];
});

const submit = () => {
    form.patch(route('person.update', { 
        locale: currentLocale, 
        id: props.person.id 
    }));
};
</script>

<template>
    <Head title="Խմբագրել անձը" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Անձինք / Խմբագրել անձը</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Խմբագրել անձը</h5>
            <form @submit.prevent="submit" class="card-body">
                <div class="row g-6">
                    <!-- Entry Code dropdown -->
                    <div v-if="entryCodes.length" class="col-md-12">
                        <InputLabel for="entry_codes" class="form-label" value="Մուտքի կոդ" />
                        <select id="entry_codes" class="form-select" v-model="form.entry_code_id">
                            <option :value="null">Ոչինչ</option>
                            <option v-for="code in entryCodes" :key="code.id" :value="code.id">
                                {{ code.token }} ({{ code.gym?.name || 'Առանց մարզադահլիճի' }}) {{ code.type }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.entry_code_id" />
                    </div>

                    <!-- Name -->
                    <div class="col-md-6">
                        <InputLabel for="name" class="form-label" value="Անուն" />
                        <TextInput id="name" type="text" class="form-control" v-model="form.name" autofocus placeholder="Մուտքագրել անունը" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Surname -->
                    <div class="col-md-6">
                        <InputLabel for="surname" class="form-label" value="Ազգանուն" />
                        <TextInput id="surname" type="text" class="form-control" v-model="form.surname" placeholder="Մուտքագրել ազգանունը" />
                        <InputError class="mt-2" :message="form.errors.surname" />
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <InputLabel for="email" class="form-label" value="Էլ. հասցե" />
                        <TextInput id="email" type="email" class="form-control" v-model="form.email" placeholder="Մուտքագրել էլ. հասցեն" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="col-md-6">
                        <InputLabel for="password" class="form-label" value="Գաղտնաբառ" />
                        <TextInput id="password" type="password" class="form-control" v-model="form.password" placeholder="Թողնել դատարկ, եթե չեք ցանկանում փոխել" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <InputLabel for="phone" class="form-label" value="Հեռախոսահամար" />
                        <TextInput id="phone" type="text" class="form-control" v-model="form.phone" placeholder="+374 58 79 98 94" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <!-- Type -->
                    <div class="col-md-6">
                        <InputLabel for="type" class="form-label" value="Տեսակ" />
                        <select id="type" class="form-select" v-model="form.type">
                            <option value="visitor">Այցելու</option>
                            <option value="guest">Հյուր</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <!-- 🔹 Birth Date -->
                    <div class="col-md-6">
                        <InputLabel for="birth_date" class="form-label" value="Ծննդյան ամսաթիվ" />
                        <TextInput id="birth_date" type="date" class="form-control" v-model="form.birth_date" />
                        <InputError :message="form.errors.birth_date" />
                    </div>

                    <!-- 🔹 Gender -->
                    <div class="col-md-6">
                        <InputLabel for="gender" class="form-label" value="Սեռ" />
                        <select id="gender" class="form-select" v-model="form.gender">
                            <option value="" disabled>Ընտրել</option>
                            <option value="male">Արական</option>
                            <option value="female">Իգական</option>
                        </select>
                        <InputError :message="form.errors.gender" />
                    </div>
                </div>

                <div class="pt-6 d-flex justify-content-end gap-2">
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Թարմացնել
                    </PrimaryButton>
                    <button type="reset" class="btn btn-label-secondary waves-effect">Չեղարկել</button>
                </div>
            </form>
        </div>

        <DocumentsUploader :ownerType="'person'" :ownerId="props.person.id" />
    </Index>
</template>