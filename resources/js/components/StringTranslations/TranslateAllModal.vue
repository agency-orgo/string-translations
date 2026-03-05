<template>
    <Modal v-model:open="isOpen" title="Translate All">
        <div class="space-y-6 pb-3">
            <p class="text-sm font-normal text-gray-600/90 dark:text-gray-400">
                Translate all untranslated keys using DeepL.
            </p>
            <Field label="From Language">
                <Select
                    v-model="fromLang"
                    :options="languageOptions"
                />
            </Field>
            <Field label="To Language">
                <Select
                    v-model="toLang"
                    :options="languageOptions"
                />
            </Field>
        </div>
        <template #footer>
            <div class="flex items-center justify-end space-x-3 pt-3 pb-1">
                <ModalClose>
                    <Button text="Cancel" variant="ghost" />
                </ModalClose>
                <Button
                    text="Translate All"
                    variant="primary"
                    :disabled="isTranslating || !fromLang || !toLang || fromLang === toLang"
                    @click="translate"
                />
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import { router } from '@statamic/cms/inertia';
import { Button, Select, Modal, ModalClose, Field } from '@statamic/cms/ui';

const props = defineProps({
    sites: { type: Array, required: true },
    translateUrl: { type: String, required: true },
});

const { $axios } = getCurrentInstance().appContext.config.globalProperties;

const isOpen = defineModel('open', { type: Boolean, default: false });

const fromLang = ref(props.sites[0]?.handle ?? '');
const toLang = ref('');
const isTranslating = ref(false);

const languageOptions = computed(() =>
    props.sites.map(site => ({ label: site.name, value: site.handle }))
);

async function translate() {
    isTranslating.value = true;

    try {
        const { data } = await $axios.post(props.translateUrl, {
            from_lang: fromLang.value,
            to_lang: toLang.value,
        });

        Statamic.$toast.success(data.message);
        isOpen.value = false;
        router.reload();
    } catch (e) {
        const message = e.response?.data?.error || 'Translation failed. Please try again.';
        Statamic.$toast.error(message);
    } finally {
        isTranslating.value = false;
    }
}
</script>
