<template>
    <Modal v-model:open="isOpen" title="Translate All">
        <div class="space-y-6 pb-3">
            <p class="text-sm font-normal text-gray-600/90 dark:text-gray-400">
                Translate keys using DeepL.
            </p>
            <Field label="From Language">
                <Select
                    v-model="fromLang"
                    :options="fromLanguageOptions"
                />
            </Field>
            <Field label="To Language">
                <Select
                    v-model="toLang"
                    :options="toLanguageOptions"
                />
            </Field>
            <div>
                <div class="flex items-center gap-2">
                    <Switch v-model="overwrite" />
                    <label class="text-sm font-medium">Overwrite existing translations</label>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 ml-9">
                    When enabled, all keys will be re-translated via DeepL, replacing existing translations. When disabled, only untranslated keys will be translated.
                </p>
            </div>
        </div>
        <template #footer>
            <div class="flex items-center justify-end space-x-3 pt-3 pb-1">
                <ModalClose>
                    <Button text="Close" variant="ghost" />
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
import { ref, computed, watch, getCurrentInstance } from 'vue';
import { Button, Select, Switch, Modal, ModalClose, Field } from '@statamic/cms/ui';

const props = defineProps({
    sites: { type: Array, required: true },
    translateUrl: { type: String, required: true },
});

const emit = defineEmits(['changed']);

const { $axios } = getCurrentInstance().appContext.config.globalProperties;

const isOpen = defineModel('open', { type: Boolean, default: false });

const fromLang = ref(props.sites[0]?.handle ?? '');
const toLang = ref('');
const overwrite = ref(false);
const isTranslating = ref(false);

const toSites = (exclude) => props.sites
    .filter(site => site.handle !== exclude)
    .map(site => ({ label: site.name, value: site.handle }));

const fromLanguageOptions = computed(() => toSites(toLang.value));
const toLanguageOptions = computed(() => toSites(fromLang.value));

watch(fromLang, (val) => { if (toLang.value === val) toLang.value = ''; });
watch(toLang, (val) => { if (fromLang.value === val) fromLang.value = ''; });

async function translate() {
    isTranslating.value = true;

    try {
        const { data } = await $axios.post(props.translateUrl, {
            from_lang: fromLang.value,
            to_lang: toLang.value,
            overwrite: overwrite.value,
        });

        Statamic.$toast.success(data.message);
        emit('changed');
    } catch (e) {
        const message = e.response?.data?.error || 'Translation failed. Please try again.';
        Statamic.$toast.error(message);
    } finally {
        isTranslating.value = false;
    }
}
</script>
