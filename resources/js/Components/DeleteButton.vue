<script setup>
import axios from 'axios';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    modelId: [Number, String],
    model: String,
    prefix: String,
    locale: String | null
});

const emit = defineEmits(['deleted']);

const { confirm } = useConfirm();

const destroy = async () => {
    const ok = await confirm('Ջնջել այս տարրը?');

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
        Ջնջել
    </button>
</template>
