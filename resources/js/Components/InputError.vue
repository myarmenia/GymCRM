<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600 mb-0">
            {{ message }}
        </p>
    </div>
</template>
