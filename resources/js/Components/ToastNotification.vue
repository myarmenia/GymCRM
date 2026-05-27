<!-- Resources/js/Components/ToastNotification.vue -->
<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    duration: {
        type: Number,
        default: 3000
    }
});

const toast = ref({
    show: false,
    message: '',
    type: 'success'
});

let timeoutId = null;

const show = (message, type = 'success') => {
    if (timeoutId) clearTimeout(timeoutId);

    toast.value = {
        show: true,
        message,
        type
    };

    timeoutId = setTimeout(() => {
        toast.value.show = false;
    }, props.duration);
};

const hide = () => {
    if (timeoutId) clearTimeout(timeoutId);
    toast.value.show = false;
};

defineExpose({
    show,
    hide
});
</script>

<template>
    <transition name="toast">
        <div v-if="toast.show" class="toast-notification" :class="toast.type">
            <div class="toast-content">
                <i class="ti" :class="{
                    'tabler-check-circle': toast.type === 'success',
                    'tabler-alert-circle': toast.type === 'error',
                    'tabler-alert-triangle': toast.type === 'warning',
                    'tabler-info-circle': toast.type === 'info'
                }"></i>
                <span>{{ toast.message }}</span>
                <button class="toast-close" @click="hide">×</button>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.toast-notification.success {
    background-color: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

.toast-notification.error {
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
}

.toast-notification.warning {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

.toast-notification.info {
    background-color: #d1ecf1;
    border-left: 4px solid #17a2b8;
    color: #0c5460;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.toast-content i {
    font-size: 20px;
}

.toast-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0 5px;
    color: inherit;
    opacity: 0.7;
}

.toast-close:hover {
    opacity: 1;
}

.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.toast-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
