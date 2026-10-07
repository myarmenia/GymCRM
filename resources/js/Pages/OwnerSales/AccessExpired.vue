<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useTrans } from '/resources/js/trans';
const page = usePage();
const trans = useTrans;
const locale = computed(() => page.props.locale ?? page.props.lang ?? 'hy');
</script>

<template>
    <Head :title="trans('page.access_expired_title')" />
    <main class="min-vh-100 d-flex align-items-center justify-content-center bg-body-tertiary p-4">
        <div class="card shadow-sm text-center" style="max-width: 560px">
            <div class="card-body p-5">
                <i class="ti tabler-lock-dollar text-warning mb-3" style="font-size: 4rem"></i>
                <h3>{{ trans('page.access_expired_title') }}</h3>
                <p class="text-muted my-3">{{ trans('page.access_expired_text') }}</p>
                <Link :href="route('logout', { locale })" method="post" as="button" class="btn btn-primary">{{ trans('page.logout') }}</Link>
            </div>
        </div>
    </main>
</template>
