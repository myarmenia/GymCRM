<script setup>
import axios from 'axios';
import { useConfirm } from '@/composables/useConfirm';
import { usePage } from '@inertiajs/vue3';
import { translate, useTrans } from '/resources/js/trans';

const props = defineProps({
    modelId: [Number, String],
    model: String,
    prefix: String,
    locale: String | null
});

const emit = defineEmits(['deleted']);

const { confirm } = useConfirm();
const page = usePage();

const destroy = async () => {
    const ok = await confirm(translate(page.props.translations, 'app.action.confirm_delete'));

    if (!ok) return;

    let $ulr = `/${props.prefix}/${props.model}/${props.modelId}`;

    if (props.locale) {
        $ulr =  `/${props.locale}/${props.prefix}/${props.model}/${props.modelId}`;
    }

    await axios.delete($ulr);

    emit('deleted', props.modelId);

};
</script>

<template>
    <button  @click="destroy">
        <i class="icon-base ti tabler-trash me-1"></i>
        {{ useTrans("app.action.delete") }}
    </button>
</template>
