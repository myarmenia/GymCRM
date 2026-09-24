<script setup>
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

defineProps({
    showLanguageSwitcher: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6">
                <div v-if="showLanguageSwitcher" class="d-flex justify-content-end mb-3">
                    <LanguageSwitcher />
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
