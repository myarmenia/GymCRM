<script setup>
import { computed } from 'vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Ընտրեք հաճախորդին',
    },
})

const emit = defineEmits(['update:modelValue'])

const selected = computed({
    get() {
        return props.options.find(option => String(option.value) === String(props.modelValue)) ?? null
    },
    set(value) {
        emit('update:modelValue', value?.value ?? '')
    },
})
</script>

<template>
    <Multiselect
        v-model="selected"
        :options="options"
        :multiple="false"
        :close-on-select="true"
        :clear-on-select="false"
        :allow-empty="true"
        :preserve-search="true"
        :placeholder="placeholder"
        label="label"
        track-by="value"
    >
        <template #noResult>
            Արդյունքներ չկան
        </template>
        <template #noOptions>
            Հաճախորդներ չկան
        </template>
    </Multiselect>
</template>
