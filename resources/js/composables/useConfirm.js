import { ref } from 'vue';

const isOpen = ref(false);
const modalData = ref({
    message: '',
    title: 'Confirm action',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    confirmClass: 'btn-danger'
});
let resolver = null;

export function useConfirm() {
    const confirm = (msg, options = {}) => {
        modalData.value = {
            message: msg,
            title: options.title || 'Confirm action',
            confirmText: options.confirmText || 'Confirm',
            cancelText: options.cancelText || 'Cancel',
            confirmClass: options.confirmClass || 'btn-danger'
        };
        isOpen.value = true;

        return new Promise((resolve) => {
            resolver = resolve;
        });
    };

    const confirmYes = () => {
        isOpen.value = false;
        resolver(true);
    };

    const confirmNo = () => {
        isOpen.value = false;
        resolver(false);
    };

    return {
        isOpen,
        modalData,
        confirm,
        confirmYes,
        confirmNo
    };
}
