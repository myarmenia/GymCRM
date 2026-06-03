<script setup>
import { computed, ref, watch } from 'vue';
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
  membershipCategories: Array,

});


console.log('Membership Categories:', props.membershipCategories);
const form = useForm({
    name: '',
    category_id: '',

});








const membershipCategoryOptions = computed(() => {
    return props.membershipCategories.map(category => ({
        label: useTrans(`page.membership_categories.${category.name}`),
        value: category.id,
        name: category.name,
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
    <Head title="Add New Employee" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">User / Add New employee</h2>
        </template>

        <div class="card mb-6">
            <h5 class="card-header">Add New Employee</h5>
            <form @submit.prevent="submit" class="card-body">
                <h6>1. Account Details</h6>
                <div class="row g-6">

                    <div  class="col-md-12 select2-primary">
                        <InputLabel for="gyms" class="form-label" value="Gyms" />

                        <select class="form-select" v-model="form.category_id" >
                            <option value="" disabled>Ընտրել կատեգորիա</option>


                            <option
                                v-for="category in membershipCategoryOptions"
                                :key="category.value"
                                :value="category.value"
                            >
                                {{ category.name }}
                            </option>
                        </select>


                        <InputError class="mt-2" :message="form.errors.category_id" />
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
