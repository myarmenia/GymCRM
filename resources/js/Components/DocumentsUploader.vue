<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    ownerType: String, // user / guest
    ownerId: Number
});

console.log(props.ownerType, props.ownerId, '=========');
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
    if (!documentForm.type) return alert('Ընտրեք տեսակը');

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
    <h5 class="card-header">Փաստաթղթեր</h5>
    <div class="card-body">

        <!-- ՎԵՐԲԵՌՆՈՒՄ -->
        <div class="mb-3">
            <label class="form-label">Տեսակ</label>
            <select v-model="documentForm.type" class="form-select">
                <option value="" disabled>Ընտրել տեսակը</option>
                <option value="passport">Անձնագիր</option>
                <option value="id_card">Նույնականացման քարտ</option>
                <option value="contract">Պայմանագիր</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="btn btn-primary">
                Վերբեռնել ֆայլեր
                <input type="file" hidden multiple @change="handleUpload" />
            </label>
        </div>

        <!-- ՆԱԽԱԴԻՏՈՒՄ -->
        <div v-if="previews.length" class="row g-3 mb-3">
            <div v-for="(item, index) in previews" :key="index" class="col-md-3">
                <div class="card p-2 text-center">
                    <img v-if="item.file.type.startsWith('image/')" :src="item.url" class="rounded mb-2" style="width: 100%; height: 120px; object-fit: cover;">
                    <div v-else>📄 PDF</div>
                    <small class="d-block text-truncate">{{ item.file.name }}</small>
                    <button class="btn btn-danger btn-sm mt-2" @click="removePreview(index)">Հեռացնել</button>
                </div>
            </div>
        </div>

        <button v-if="previews.length" class="btn btn-success" @click="submitDocuments">
            Վերբեռնել {{ previews.length }} ֆայլ(եր)
        </button>

        <!-- ՎԵՐԲԵՌՆՎԱԾ ՖԱՅԼԵՐ -->
        <div v-if="uploaded.length" class="mt-4">
            <h6>Վերբեռնվածներ</h6>

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
                                    Դիտել
                                </a>
                                <a :href="doc.file_url" download class="btn btn-sm btn-success">
                                    Ներբեռնել
                                </a>
                            </div>
                        </div>

                        <!-- PDF / ՖԱՅԼ -->
                        <div v-else class="mb-2">
                            📄
                            <a :href="doc.file_url" target="_blank">
                                Բացել ֆայլը
                            </a>
                        </div>

                        <button
                            class="btn btn-danger btn-sm mt-2"
                            @click="removeUploaded(index)"
                        >
                            Ջնջել
                        </button>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
</template>