<script setup>
import { useConfirm } from '@/composables/useConfirm';

const { isOpen, modalData, confirmYes, confirmNo } = useConfirm();
</script>

<template>
    <div
        class="modal fade"
        :class="{ show: isOpen }"
        :style="isOpen ? 'display:block;' : ''"
        tabindex="-1"
        role="dialog"
    >
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ modalData.title }}</h5>
                    <button type="button" class="btn-close" @click="confirmNo"></button>
                </div>
                <div class="modal-body">
                    <p>{{ modalData.message }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" @click="confirmNo">
                        {{ modalData.cancelText }}
                    </button>
                    <button type="button" :class="['btn', modalData.confirmClass]" @click="confirmYes">
                        {{ modalData.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="isOpen" class="modal-backdrop fade show"></div>
</template>
