<script setup>
import { onMounted, nextTick, computed } from 'vue'; // import computed
import Index from '@/Layouts/Index.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const props = defineProps({
    gyms: Array,
    defaultGymId: { type: Number, default: null },
});

const form = useForm({
    gym_id: props.defaultGymId,
    token: '',
    type: 'rfId',
    activation: false,
});
// Determine default gym_id
const isOwner = computed(() => {
    return page.props.auth.user.roles?.some(r => r.name === 'owner') ?? false;
});


// Now place console logs (optional)
console.log('Gyms:', props.gyms);
console.log('Form:', form);
console.log('page.props.auth.user', page.props.auth.user);

// onMounted(async () => {
//     await nextTick();
//     const $gyms = window.$('#gyms');
//     if ($gyms.length && isOwner.value) {
//         $gyms.select2({ width: '100%', placeholder: 'Ընտրել մարզասրահը' });
//         $gyms.on('change', function () {
//             form.gym_id = window.$(this).val();
//         });
//         if (form.gym_id) {
//             $gyms.val(form.gym_id).trigger('change');
//         }
//     }
// });

const submit = () => {
    form.post(route('entry-code.store', { locale: currentLocale }));
};
</script>

<template>
    <Head title="Ստեղծել մուտքի կոդ" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold">Մուտքի կոդ / Ստեղծել</h2>
        </template>
        <div class="card">
            <h5 class="card-header">Ստեղծել նոր կոդ</h5>
            <form @submit.prevent="submit" class="card-body">
                <!-- Gym dropdown – visible only for owner -->
                <div class="row mb-3" v-if="isOwner">
                    <label class="col-sm-3 col-form-label">Մարզասրահ</label>
                    <div class="col-sm-9 select2-primary">
                        <!-- <select id="gyms" class="select2 form-select">
                            <option disabled selected>Ընտրել մարզասրահը</option>
                            <option v-for="gym in gyms" :key="gym.id" :value="gym.id">
                                {{ gym.name }}
                            </option>
                        </select> -->
                        <select v-model="form.gym_id" class="form-select">
                            <option value="" disabled>Ընտրել մարզասրահը</option>


                            <option
                                v-for="gym in gyms"
                                :key="gym.id"
                                :value="gym.id"
                            >
                                {{ gym.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.gym_id" />
                    </div>
                </div>

                <!-- Token -->
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Թոքեն</label>
                    <div class="col-sm-9">
                        <TextInput type="text" class="form-control" v-model="form.token" />
                        <InputError :message="form.errors.token" />
                    </div>
                </div>

                <!-- Type (only for owner – but you removed v-if, keep as is) -->
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Տեսակ</label>
                    <div class="col-sm-9">
                        <select class="form-select" v-model="form.type">
                            <option value="rfId">rfId</option>
                            <option value="FaceId">FaceID</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                </div>



                <div class="row mb-3">
                    <div class="col-sm-9 offset-sm-3">
                        <PrimaryButton :disabled="form.processing">Ստեղծել</PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </Index>
</template>
