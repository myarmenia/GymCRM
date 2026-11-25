<script setup>
import { translate } from '/resources/js/trans'
import { usePage as useTranslationPage } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const translationPage = useTranslationPage()
const t = (key, replacements = {}) => translate(translationPage.props.translations, `app.${key}`, replacements)

const props = defineProps({
    ownerType: String, // user / guest
    ownerId: Number,
    gymId: Number,
    gyms: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: true },
});

console.log(props.ownerType, props.ownerId, '=========');
const documentForm = useForm({
    documents: [],
    type: ''
});

const selectedGymId = ref(props.gymId ?? props.gyms[0]?.id ?? null);
const previews = ref([]);
const uploaded = ref([]);

const isImage = (url) => {
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
};

const getFileName = (url) => {
    return url.split('/').pop();
};

const loadDocuments = async () => {
    const res = await axios.get(
        `/documents/${props.ownerType}/${props.ownerId}`, { params: { gym_id: selectedGymId.value } }
    );
    uploaded.value = res.data;
};

watch(selectedGymId, loadDocuments);
watch(() => props.gymId, value => { selectedGymId.value = value; });
onMounted(() => {
    loadDocuments();
});

const handleUpload = (e) => {
    const files = Array.from(e.target.files);
    files.forEach(file => {
        documentForm.documents.push(file);
        previews.value.push({
            file,
            url: URL.createObjectURL(file)
        });
    });
    e.target.value = '';
};

const removePreview = (index) => {
    documentForm.documents.splice(index, 1);
    previews.value.splice(index, 1);
};

const submitDocuments = async () => {
    if (!documentForm.type) return alert(t('operations.select_type_2'));

    const formData = new FormData();
    formData.append('type', documentForm.type);
    if (selectedGymId.value) formData.append('gym_id', String(selectedGymId.value));
    documentForm.documents.forEach(file => formData.append('documents[]', file));

    const res = await axios.post(`/documents/${props.ownerType}/${props.ownerId}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    });

    uploaded.value = [...uploaded.value, ...res.data];
    documentForm.documents = [];
    previews.value = [];
};

// Ջնջել վերբեռնված ֆայլը
const removeUploaded = async (index) => {
    const doc = uploaded.value[index];
    console.log(index, doc.id, '=========');
    await axios.delete(`/documents/${doc.id}`);
    uploaded.value.splice(index, 1);
};
</script>

<template>
<div class="card mb-6">
    <h5 class="card-header">{{ t('operations.documents') }}</h5>
    <div class="card-body">

        <select v-if="gyms.length" class="form-select mb-3" v-model="selectedGymId">
            <option v-for="gym in gyms" :key="gym.id" :value="gym.id">{{ gym.name }}</option>
        </select>
        <!-- ՎԵՐԲԵՌՆՈՒՄ -->
        <div v-if="canManage" class="mb-3">
            <label class="form-label">{{ t('people.type') }}</label>
            <select v-model="documentForm.type" class="form-select">
                <option value="" disabled>{{ t('operations.select_type') }}</option>
                <option value="passport">{{ t('operations.passport') }}</option>
                <option value="id_card">{{ t('operations.identity_card') }}</option>
                <option value="contract">{{ t('operations.contract') }}</option>
            </select>
        </div>

        <div v-if="canManage" class="mb-3">
            <label class="btn btn-primary">
                {{ t('operations.upload_files') }}
                <input type="file" hidden multiple @change="handleUpload" />
            </label>
        </div>

        <!-- ՆԱԽԱԴԻՏՈՒՄ -->
        <div v-if="canManage && previews.length" class="row g-3 mb-3">
            <div v-for="(item, index) in previews" :key="index" class="col-md-3">
                <div class="card p-2 text-center">
                    <img v-if="item.file.type.startsWith('image/')" :src="item.url" class="rounded mb-2" style="width: 100%; height: 120px; object-fit: cover;">
                    <div v-else>📄 PDF</div>
                    <small class="d-block text-truncate">{{ item.file.name }}</small>
                    <button class="btn btn-danger btn-sm mt-2" @click="removePreview(index)">{{ t('operations.remove') }}</button>
                </div>
            </div>
        </div>

        <button v-if="canManage && previews.length" class="btn btn-success" @click="submitDocuments">
            {{ t('operations.upload_file_count', { count: previews.length }) }}
        </button>

        <!-- ՎԵՐԲԵՌՆՎԱԾ ՖԱՅԼԵՐ -->
        <div v-if="uploaded.length" class="mt-4">
            <h6>{{ t('operations.uploads') }}</h6>

            <div class="row g-3">
                <div
                    v-for="(doc, index) in uploaded"
                    :key="doc.id"
                    class="col-md-3"
                >
                    <div class="card p-2 text-center">

                        <!-- ՆԿԱՐ -->
                        <div v-if="isImage(doc.file_url)">
                            <img
                                :src="doc.file_url"
                                class="rounded mb-2"
                                style="width: 100%; height: 120px; object-fit: cover;"
                            />

                            <div class="d-flex gap-2 justify-content-center">
                                <a :href="doc.file_url" target="_blank" class="btn btn-sm btn-primary">
                                    {{ t('staff_reports.view') }}
                                </a>
                                <a :href="doc.file_url" download class="btn btn-sm btn-success">
                                    {{ t('operations.download') }}
                                </a>
                            </div>
                        </div>

                        <!-- PDF / ՖԱՅԼ -->
                        <div v-else class="mb-2">
                            📄
                            <a :href="doc.file_url" target="_blank">
                                {{ t('operations.open_file') }}
                            </a>
                        </div>

                        <button
                            class="btn btn-danger btn-sm mt-2"
                            v-if="canManage" @click="removeUploaded(index)"
                        >
                            {{ t('action.delete') }}
                        </button>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</template>
