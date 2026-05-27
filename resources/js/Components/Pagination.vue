<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="d-flex justify-content-end mt-4 gap-1 flex-wrap">

        <template v-for="(link, index) in links" :key="index">

            <!-- ACTIVE / NORMAL -->
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="px-4 py-2 border rounded text-sm"
                :class="[
                    link.active
                        ? 'bg-primary text-white border-primary'
                        : 'bg-white text-dark'
                ]"
                v-html="link.label"
            />

            <!-- DISABLED -->
            <span
                v-else
                class="px-4 py-2 border rounded text-sm text-muted bg-light"
                v-html="link.label"
            />

        </template>

    </div>
</template>
