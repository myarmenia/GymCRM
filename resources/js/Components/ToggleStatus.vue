<script setup>
import { router } from '@inertiajs/vue3';
import { useTrans } from '/resources/js/trans';
import axios from 'axios';

const props = defineProps({
  modelId: [Number, String],
  model: String,
  active: Boolean,
  label: String,
  prefix: String,
  locale: String | null,
  column: {
    type: String,
    default: 'active'
  }
});

const emit = defineEmits(['update']);

const toggle = async () => {
    try {

        let $ulr = `/${props.prefix}/${props.model}/${props.modelId}/toggle-active`;

        if (props.locale) {
            $ulr =  `/${props.locale}/${props.prefix}/${props.model}/${props.modelId}/toggle-active`;
        }

        const res = await axios.patch($ulr, {
            column: props.column
        });

        emit('update', res.data.active);
    } catch (e) {
        console.error(e);
    }
};
</script>

<template>
    <div class="form-check form-switch mb-0">
        <input
          class="form-check-input"
          type="checkbox"
          id="flexSwitchCheckChecked"

          :checked="active"
          @change="toggle"
        >{{props.label}}
    </div>
</template>

