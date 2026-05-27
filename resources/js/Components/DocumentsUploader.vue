<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    ownerType: String, // user / guest
    ownerId: Number
});

const documentForm = useForm({
    documents: [],
    type: ''
});

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
        `/documents/${props.ownerType}/${props.ownerId}`
    );

    uploaded.value = res.data;
};

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
    if (!documentForm.type) return alert('Select type');

    const formData = new FormData();

    formData.append('type', documentForm.type);
    documentForm.documents.forEach(file => formData.append('documents[]', file));

    const res = await axios.post(`/documents/${props.ownerType}/${props.ownerId}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    });


    uploaded.value = [...uploaded.value, ...res.data];
    documentForm.documents = [];
    previews.value = [];
};

// Удаление загруженного файла
const removeUploaded = async (index) => {
    const doc = uploaded.value[index];

    console.log(index,doc.id, '=========' )
    await axios.delete(`/documents/${doc.id}`);
    uploaded.value.splice(index, 1);
};
</script>

<template>
<div class="card mb-6">
    <h5 class="card-header">Documents</h5>
    <div class="card-body">

        <!-- UPLOAD -->
        <div class="mb-3">
            <label class="form-label">Type</label>
            <select v-model="documentForm.type" class="form-select">
                <option value="" disabled>Select type</option>
                <option value="passport">Passport</option>
                <option value="id_card">ID Card</option>
                <option value="contract">Contract</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="btn btn-primary">
                Upload files
                <input type="file" hidden multiple @change="handleUpload" />
            </label>
        </div>

        <!-- PREVIEWS -->
        <div v-if="previews.length" class="row g-3 mb-3">
            <div v-for="(item, index) in previews" :key="index" class="col-md-3">
                <div class="card p-2 text-center">
                    <img v-if="item.file.type.startsWith('image/')" :src="item.url" class="rounded mb-2" style="width: 100%; height: 120px; object-fit: cover;">
                    <div v-else>📄 PDF</div>
                    <small class="d-block text-truncate">{{ item.file.name }}</small>
                    <button class="btn btn-danger btn-sm mt-2" @click="removePreview(index)">Remove</button>
                </div>
            </div>
        </div>

        <button v-if="previews.length" class="btn btn-success" @click="submitDocuments">
            Upload {{ previews.length }} file(s)
        </button>

        <!-- UPLOADED FILES -->
        <div v-if="uploaded.length" class="mt-4">
            <h6>Uploaded</h6>

            <div class="row g-3">
                <div
                    v-for="(doc, index) in uploaded"
                    :key="doc.id"
                    class="col-md-3"
                >
                    <div class="card p-2 text-center">

                        <!-- IMAGE -->
                        <div v-if="isImage(doc.file_url)">
                            <img
                                :src="doc.file_url"
                                class="rounded mb-2"
                                style="width: 100%; height: 120px; object-fit: cover;"
                            />

                            <div class="d-flex gap-2 justify-content-center">
                                <a :href="doc.file_url" target="_blank" class="btn btn-sm btn-primary">
                                    View
                                </a>
                                <a :href="doc.file_url" download class="btn btn-sm btn-success">
                                    Download
                                </a>
                            </div>
                        </div>

                        <!-- PDF / FILE -->
                        <div v-else class="mb-2">
                            📄
                            <a :href="doc.file_url" target="_blank">
                                Open file
                            </a>
                        </div>

                        <!-- <small class="d-block text-truncate">
                            {{ getFileName(doc.file_url) }}
                        </small> -->

                        <button
                            class="btn btn-danger btn-sm mt-2"
                            @click="removeUploaded(index)"
                        >
                            Delete
                        </button>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</template>
