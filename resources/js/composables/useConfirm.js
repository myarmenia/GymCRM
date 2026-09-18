import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { translate } from '/resources/js/trans';

const isOpen = ref(false);
const modalData = ref({
    message: '',
    title: '',
    confirmText: '',
    cancelText: '',
    confirmClass: 'btn-danger'
});
let resolver = null;

export function useConfirm() {
    const page = usePage();
    const translated = key => translate(page.props.translations, `app.confirm.${key}`);
    const confirm = (msg, options = {}) => {
        modalData.value = {
            message: msg,
            title: options.title ?? translated('title'),
            confirmText: options.confirmText ?? translated('accept'),
            cancelText: options.cancelText ?? translated('cancel'),
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
