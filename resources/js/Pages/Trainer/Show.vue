<script setup>
import Index from '@/Layouts/Index.vue';
import { Head, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const page = usePage();
const currentLocale = page.props.locale ?? "hy";

const props = defineProps({
    user: Object,
    roles: Array,
    selectedEntryCodeId: Number,
});
</script>

<template>
    <Head title="Օգտատիրոջ տվյալներ" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Օգտատեր / Տվյալներ
            </h2>
        </template>

        <div class="card">
            <h5 class="card-header">Օգտատիրոջ մանրամասներ</h5>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Անուն</div>
                    <div class="col-md-9">{{ user.name }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Ազգանուն</div>
                    <div class="col-md-9">{{ user.surname }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Էլ. հասցե</div>
                    <div class="col-md-9">{{ user.email }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Հեռախոս</div>
                    <div class="col-md-9">{{ user.phone }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Դերեր</div>
                    <div class="col-md-9">
                        <span v-for="role in roles" :key="role" class="badge bg-primary me-1">
                            {{ role }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 fw-bold">Կարգավիճակ</div>
                    <div class="col-md-9">
                        <span :class="user.active ? 'text-success' : 'text-danger'">
                            {{ user.active ? 'Ակտիվ' : 'Ոչ ակտիվ' }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3" v-if="selectedEntryCodeId">
                    <div class="col-md-3 fw-bold">Մուտքի կոդ (Entry Code)</div>
                    <div class="col-md-9">{{ selectedEntryCodeId }}</div>
                </div>
            </div>
            <div class="card-footer">
                
                <Link :href="route('user.list', { locale: currentLocale })" class="btn btn-secondary">
                    Վերադառնալ ցուցակ
                </Link>
            </div>
        </div>
    </Index>
</template>