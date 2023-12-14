<template>
  <ShaplaModal :active="true" @close="emitClose" v-if="active" :title="title">
    <table class="form-table" v-if="state.settings && Object.keys(state.settings).length">
      <tr>
        <th scope="row"><label :for="`site_url-${index}`">Site URL</label></th>
        <td>
          <input type="text" v-model="state.settings.site_url" class="regular-text p-2">
        </td>
      </tr>
      <tr>
        <th scope="row"><label :for="`site_url-${index}`">Authorization</label></th>
        <td>
          <div class="mb-2">
            <ShaplaSelect
                label="Auth Type"
                :options="authTypes"
                v-model="state.settings.auth_type"
            />
          </div>
          <template v-if="state.settings.auth_type === 'Basic'">
            <div class="mb-2">
              <ShaplaInput
                  label="Username"
                  v-model="state.settings.auth_username"
              />
            </div>
            <ShaplaInput
                label="Password"
                v-model="state.settings.auth_password"
            />
          </template>
          <ShaplaInput
              v-if="['Get-Args','Append-to-Url'].includes(state.settings.auth_type)"
              label="Argument Name"
              v-model="state.settings.auth_get_args"
          />
          <ShaplaInput
              v-if="state.settings.auth_type === 'Post-Params'"
              label="Parameter Name"
              v-model="state.settings.auth_post_params"
          />
          <ShaplaInput
              v-if="!['None','Basic'].includes(state.settings.auth_type)"
              label="Credentials"
              type="textarea"
              v-model="state.settings.auth_credentials"
              rows="2"
              class="w-full"
          />
        </td>
      </tr>
    </table>
    <template v-slot:foot>
      <ShaplaButton theme="primary" @click="emitSave">Save</ShaplaButton>
    </template>
  </ShaplaModal>
</template>

<script lang="ts" setup>
import {ShaplaButton, ShaplaInput, ShaplaModal, ShaplaSelect} from "@shapla/vue-components";
import {onMounted, PropType, reactive} from "vue";
import {SiteSettingInterface} from "../../../utils/interfaces";

const siteDefault = () => ({
  site_url: '', auth_credentials: '', auth_username: '', auth_password: '',
  auth_type: 'Bearer', auth_get_args: '', auth_post_params: ''
})

const props = defineProps({
  active: {type: Boolean, default: false},
  index: {type: Number, default: 0},
  site: {type: Object as PropType<SiteSettingInterface>, required: true},
  title: {type: String, default: 'Add new Site'}
});
const state = reactive<{
  settings: SiteSettingInterface
}>({
  settings: null,
})
const emit = defineEmits(['close', 'save'])

const emitClose = () => {
  emit('close');
}

const emitSave = () => {
  emit('save', state.settings)
}

const authTypes = [
  {label: 'No Auth Required', value: 'None'},
  {label: 'Basic', value: 'Basic'},
  {label: 'Bearer', value: 'Bearer'},
  {label: 'POST parameter', value: 'Post-Params'},
  {label: 'GET argument', value: 'Get-Args'},
  {label: 'Append to URL', value: 'Append-to-Url'},
];

onMounted(() => {
  state.settings = props.site;
})
</script>
