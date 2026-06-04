import { ref } from 'vue';

const isOpen = ref(false);
const modalData = ref({
    message: '',
    title: 'Հաստատեք գործողությունը',
    confirmText: 'Հաստատել',
    cancelText: 'Չեղարկել',
    confirmClass: 'btn-danger'
});
let resolver = null;

export function useConfirm() {
    const confirm = (msg, options = {}) => {
        modalData.value = {
            message: msg,
            title: options.title || 'Հաստատեք գործողությունը',
            confirmText: options.confirmText || 'Հաստատել',
            cancelText: options.cancelText || 'Չեղարկել',
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
