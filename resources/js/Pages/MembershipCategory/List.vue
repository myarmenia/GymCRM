<script setup>
import { ref } from 'vue';
import Index from '@/Layouts/Index.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';

const page = usePage();
const currentLocale = page.props.locale ?? 'hy';
const props = defineProps({ categories: Object });
const categoriesList = ref(props.categories.data);
const pagination = ref(props.categories);
</script>

<template>
    <Head title="Անդամակցության կատեգորիաներ" />
    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Անդամակցության կատեգորիաներ
            </h2>
        </template>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5>Կատեգորիաներ</h5>
                <Link :href="route('membership-category.create', { locale: currentLocale })" class="btn btn-primary">
                    + Ավելացնել
                </Link>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>ID</th><th>Slug</th><th>Անվանում ({{ currentLocale }})</th><th>Ակտիվ</th><th>Գործողություններ</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="cat in categoriesList" :key="cat.id">
                            <td>{{ cat.id }}</td>
                            <td>{{ cat.slug }}</td>
                            <td>{{ cat.translations.find(t => t.locale === currentLocale)?.name || '-' }}</td>
                            <td><span :class="cat.active ? 'badge bg-success' : 'badge bg-danger'">{{ cat.active ? 'Ակտիվ' : 'Ոչ ակտիվ' }}</span></td>
                            <td>
                                <Link :href="route('membership-category.edit', { locale: currentLocale, id: cat.id })" class="btn btn-sm btn-primary">Խմբագրել</Link>
                                <!-- Delete button component could be added -->
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :links="pagination.links" />
            </div>
        </div>
    </Index>
</template>