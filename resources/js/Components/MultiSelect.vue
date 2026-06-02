<template>
    <div>
        <Multiselect
            v-model="selected"
            :options="options"
            :multiple="multiple"
            :close-on-select="false"
            :clear-on-select="false"
            :preserve-search="true"
            :placeholder="placeholder"
            label="label"
            track-by="value"
            @update:modelValue="updateValue"
        />
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';

import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => []
    },

    options: {
        type: Array,
        required: true
    },

    placeholder: {
        type: String,
        default: 'Select options'
    },

    multiple: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['update:modelValue']);

const selected = ref([]);

// при загрузке
watch(
    () => props.modelValue,
    (value) => {
        selected.value = props.options.filter(option =>
            value.includes(option.value)
        );
    },
    { immediate: true }
);

const updateValue = (value) => {
    emit(
        'update:modelValue',
        value.map(item => item.value)
    );
};
</script>
<style>

.multiselect {
    min-height: 38px;
}

.multiselect__tags {
    border: 1px solid #d9dee3;
    border-radius: 0.375rem;
    min-height: 38px;
    padding: 4px 40px 4px 8px;
    background: white;
}

.multiselect__tags-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.multiselect__tag {
    background: rgba(13, 147, 148, 0.08) !important;
    color: #0d9394 !important;
    border: 1px solid rgba(13, 147, 148, 0.25);
    border-radius: 4px;
    padding: 4px 26px 4px 10px;
    margin-bottom: 0;
    font-size: 13px;
}

.multiselect__tag-icon {
    border-radius: 0 4px 4px 0;
}

.multiselect__tag-icon:after {
    color: #0d9394 !important;
    font-size: 14px;
}

.multiselect__tag-icon:hover {
    background: transparent !important;
}

.multiselect__tag-icon:hover:after {
    color: #0b7d7e !important;
}

.multiselect__option--highlight {
    background: #0d9394 !important;
}

.multiselect__option--selected {
    background: rgba(13, 147, 148, 0.08) !important;
    color: #0d9394 !important;
    font-weight: 500;
}

.multiselect__input,
.multiselect__single {
    font-size: 14px;
    margin-bottom: 0;
    background: transparent !important;
}

.multiselect__input:focus {
    outline: none !important;
    box-shadow: none !important;
}

.multiselect--active .multiselect__tags {
    border-color: #0d9394 !important;
    box-shadow: 0 0 0 0.2rem rgba(13, 147, 148, 0.12);
}

.multiselect__placeholder {
    margin-bottom: 0;
    padding-top: 2px;
}

.multiselect__content-wrapper {
    border-color: #d9dee3;
}

.multiselect__option {
    font-size: 14px;
}

.multiselect__option:after {
    display: none !important;
}

</style>
