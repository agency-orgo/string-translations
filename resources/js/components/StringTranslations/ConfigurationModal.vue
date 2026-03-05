<template>
    <Modal v-model:open="isOpen" title="Configuration">
        <div class="space-y-6 py-3">
            <Field label="DeepL API Key" instructions="Enter your DeepL API key to enable auto-translation.">
                <Input
                    v-model="apiKey"
                    type="password"
                    viewable
                    focus
                />
            </Field>
        </div>
        <template #footer>
            <div class="flex items-center justify-end space-x-3 pt-3 pb-1">
                <ModalClose>
                    <Button text="Cancel" variant="ghost" />
                </ModalClose>
                <Button
                    text="Save"
                    variant="primary"
                    :disabled="isSaving || !apiKey.trim()"
                    @click="save"
                />
            </div>
        </template>
    </Modal>
</template>

<script setup>
import { ref, getCurrentInstance } from 'vue';
import { Button, Input, Modal, ModalClose, Field } from '@statamic/cms/ui';

const props = defineProps({
    settingsUrl: { type: String, required: true },
    hasDeeplKey: { type: Boolean, default: false },
});

const emit = defineEmits(['saved']);

const isOpen = defineModel('open', { type: Boolean, default: false });

const { $axios } = getCurrentInstance().appContext.config.globalProperties;

const apiKey = ref('');
const hasKey = ref(props.hasDeeplKey);
const isSaving = ref(false);

async function save() {
    isSaving.value = true;

    try {
        const { data } = await $axios.post(props.settingsUrl, {
            deepl_api_key: apiKey.value || null,
        });

        hasKey.value = data.has_deepl_key;
        apiKey.value = '';
        isOpen.value = false;
        Statamic.$toast.success('Settings saved.');
        emit('saved', data.has_deepl_key);
    } catch (e) {
        Statamic.$toast.error('Failed to save settings.');
    } finally {
        isSaving.value = false;
    }
}
</script>
