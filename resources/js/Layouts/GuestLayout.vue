<script setup>
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
</script>

<template>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner py-6">
                <div class="d-flex justify-content-end mb-3">
                    <LanguageSwitcher />
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
