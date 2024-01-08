<template>
  <table class="form-table">
    <tr>
      <th scope="row"><label>Site URL</label></th>
      <td>
        {{ setting.site_url }}
      </td>
    </tr>
    <tr>
      <th scope="row"><label>Authorization</label></th>
      <td>
        <div class="flex flex-col mb-2">
          <div class="flex space-x-4 mb-1">
            <div>Auth Type</div>
            <div class="font-bold">{{ setting.auth_type }}</div>
          </div>
          <div v-if="setting.auth_type === 'Basic'" class="flex flex-col">
            <div class="flex space-x-4 mb-1">
              <div>Username</div>
              <div class="font-bold">{{ setting.auth_username }}</div>
            </div>
            <div class="flex space-x-4 mb-1">
              <div>Password</div>
              <div class="font-bold">{{ setting.auth_password }}</div>
            </div>
          </div>
          <div v-if="['Get-Args','Append-to-Url'].includes(setting.auth_type)" class="flex space-x-4 mb-1">
            <div>Argument Name</div>
            <div class="font-bold">{{ setting.auth_get_args }}</div>
          </div>
          <div v-if="setting.auth_type === 'Post-Params'" class="flex space-x-4 mb-1">
            <div>Argument Name</div>
            <div class="font-bold">{{ setting.auth_post_params }}</div>
          </div>
        </div>
        <template v-if="!['None','Basic'].includes(setting.auth_type)">
          Credentials
          {{ setting.auth_credentials }}
        </template>
      </td>
    </tr>
    <tr>
      <th>Last Sync</th>
      <td>
        <template v-if="setting.last_sync_datetime">
          {{ formatISO8601Date(setting.last_sync_datetime) }}
        </template>
        <template v-else>Unknown</template>
      </td>
    </tr>
    <tr>
      <th>Sync Settings (Remote)</th>
      <td>
        <ShaplaToggles v-if="setting.sync_settings">
          <ShaplaToggle v-for="(sync_setting, index) in setting.sync_settings" :name="`Setting ${index + 1}`"
                        :subtext="getSubtext(sync_setting)">
            <table class="form-table">
              <tbody>
              <tr v-if="sync_setting.concept">
                <th>Concept</th>
                <td>{{ sync_setting.concept }}</td>
              </tr>
              <tr v-if="sync_setting.category">
                <th>Category</th>
                <td>{{ sync_setting.category }}</td>
              </tr>
              <tr v-if="sync_setting.language">
                <th>language</th>
                <td>{{ sync_setting.language }}</td>
              </tr>
              <tr v-if="sync_setting.location">
                <th>location</th>
                <td>{{ sync_setting.location }}</td>
              </tr>
              <tr v-if="sync_setting.source">
                <th>source</th>
                <td>{{ sync_setting.source }}</td>
              </tr>
              <tr v-if="sync_setting.primaryCategory">
                <th>Primary Category</th>
                <td>{{ sync_setting.primaryCategory }}</td>
              </tr>
              </tbody>
            </table>
          </ShaplaToggle>
        </ShaplaToggles>
        <div v-else>
          No settings found
        </div>
      </td>
    </tr>
  </table>
</template>

<script lang="ts" setup>
import {PropType} from "vue";
import {SiteSettingInterface} from "../../../utils/interfaces";
import {formatISO8601Date} from "../../../utils/humanTimeDiff";
import {ShaplaToggle, ShaplaToggles} from "@shapla/vue-components";

const props = defineProps({
  setting: {type: Object as PropType<SiteSettingInterface>, required: true}
});

const getSubtext = (sync_setting) => {
  let html = '';
  if (sync_setting.concept) {
    html += `Concept: ${sync_setting.concept}; `;
  } else if (sync_setting.primaryCategory) {
    html += `Category: ${sync_setting.primaryCategory}; `;
  }
  return html;
}
</script>
