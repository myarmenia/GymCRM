<script setup>
import Index from "@/Layouts/Index.vue";
import { Head } from "@inertiajs/vue3";
import { useTrans } from "/resources/js/trans";
import { useAuth } from "@/composables/useAuth";

const { user } = useAuth();
</script>

<template>
    <Head title="Dashboard" />

    <Index>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard - {{ useTrans("app.sidebar.profile") }}
            </h2>
        </template>

        <div class="row g-6">
            <div class="col">
                <div class="card">
                    <h5 class="card-header">Welcome - {{ user.name }}</h5>
                </div>
            </div>
        </div>
    </Index>
</template>
