<script setup lang="ts">
import {
  Dialog,
  Notify,
  ShaplaButton,
  ShaplaModal,
  ShaplaTable,
  ShaplaTablePagination,
  ShaplaTableStatusList,
  Spinner
} from "@shapla/vue-components";
import NewsSyncSettings from "../components/NewsSyncSettings.vue";
import {onMounted, reactive} from "vue";
import {NewsSyncSettingsInterface, SelectOptionsInterface} from "../../../utils/interfaces";
import CrudOperation, {PaginationDataInterface, StatusDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";

const crud = new CrudOperation('newsapi-sync-settings', http);

interface ServerResponseInterface {
  settings: NewsSyncSettingsInterface[];
  pagination: PaginationDataInterface;
  statuses: StatusDataInterface[];
  categories: SelectOptionsInterface[];
  countries: SelectOptionsInterface[];
  languages: SelectOptionsInterface[];
  news_sync_fields: SelectOptionsInterface[];
}

interface NewsApiSyncSettingsStateInterface extends ServerResponseInterface {
  activeSetting: NewsSyncSettingsInterface | null;
  showEditModal: boolean;
  openQueryInfoModal: boolean;
  status: 'publish' | 'draft' | string;
}

const state = reactive<NewsApiSyncSettingsStateInterface>({
  settings: [],
  pagination: {per_page: 100, current_page: 1, total_items: 0, total_pages: 1},
  statuses: [],
  status: 'publish',
  categories: [],
  countries: [],
  languages: [],
  news_sync_fields: [],
  activeSetting: null,
  showEditModal: false,
  openQueryInfoModal: false,
})

const createUUID = (): string => {
  const pattern = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx";
  return pattern.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
const addSyncSetting = () => {
  const index = state.settings.length + 1;
  const newSettings: NewsSyncSettingsInterface = {
    id: 0,
    option_id: createUUID(),
    title: `Setting ${index}`,
    fields: [],
    locations: [],
    categories: [],
    concepts: [],
    sources: [],
    conceptUri: [],
    locationUri: [],
    categoryUri: [],
    sourceUri: [],
    lang: [],
    keyword: '',
    keywordLoc: '',
    primary_category: '',
    copy_news_image: false,
    enable_news_filtering: false,
    use_actual_news: true,
    enable_live_news: false,
    news_filtering_instruction: '',
    query_info: null,
    service_provider: 'newsapi.ai'
  }
  state.settings.unshift(newSettings);
  state.activeSetting = newSettings;
  state.showEditModal = true;
}

const getSettingSubtext = (setting: NewsSyncSettingsInterface) => {
  let html = '';
  if (setting.locationUri.length) {
    html += `Location: ${setting.locationUri}; `;
  }
  if (setting.categoryUri.length) {
    html += `Category: ${setting.categoryUri}; `;
  }
  if (setting.conceptUri.length) {
    html += `Concept: ${setting.conceptUri}; `;
  }
  if (setting.keyword && setting.keyword.length) {
    html += `Keyword: ${setting.keyword}; `;
  }
  if (setting.to_sites) {
    html += '<br><strong>Sites:</strong> ' + (setting.to_sites.length ? setting.to_sites.join(', ') : '-');
  }
  return html;
}

const duplicateSyncSetting = (settings: NewsSyncSettingsInterface) => {
  Dialog.confirm({
    message: 'New setting will append at top of current settings list.',
    title: 'Are you sure?'
  }).then(confirmed => {
    if (confirmed) {
      const _settings = JSON.parse(JSON.stringify(settings)) as NewsSyncSettingsInterface;
      _settings.id = 0;
      _settings.option_id = createUUID();
      _settings.title = `${settings.title} duplicate`
      state.settings.unshift(_settings);
      state.showEditModal = true;
      state.activeSetting = _settings;
    }
  })
}

const getSettings = (status: string = 'publish') => {
  crud.getItems({status}).then((data) => {
    state.settings = data.settings as NewsSyncSettingsInterface[];
    state.pagination = data.pagination as PaginationDataInterface;
    state.statuses = (data.statuses as StatusDataInterface[]).filter(status => status.key !== 'all');
    state.categories = data.categories as SelectOptionsInterface[];
    state.languages = data.languages as SelectOptionsInterface[];
    state.countries = data.countries as SelectOptionsInterface[];
    state.news_sync_fields = data.news_sync_fields as SelectOptionsInterface[];
  })
}

const removeSyncSetting = (id: number) => {
  Dialog.confirm('Are you sure to delete?').then((confirmed) => {
    if (confirmed) {
      Spinner.show();
      crud.deleteItem(id).then(() => {
        getSettings();
      }).finally(() => {
        Spinner.hide();
      })
    }
  })
}

const saveActiveSetting = () => {
  crud.createItem(state.activeSetting).then(() => {
    getSettings();
    state.activeSetting = null;
    state.showEditModal = false;
  })
}

const syncNow = (setting: NewsSyncSettingsInterface) => {
  Spinner.show();
  http
      .post('settings/sync', {option_id: setting.option_id})
      .then((response) => {
        const data = response.data.data;
        const existing_records_ids = data.existing_records_ids.length;
        const new_records_ids = data.new_records_ids.length;
        const records_ids = data.records_ids.length;
        if (existing_records_ids || new_records_ids) {
          let message = `${records_ids} new articles from news api. ${existing_records_ids} records are already exists. ${new_records_ids} records are synced.`;
          Notify.success(message, 'Success!');
        } else {
          let message = `No new articles found from news api.`;
          Notify.success(message, 'Success!');
        }
        getSettings();
      })
      .catch((error) => {
        const responseData = error.response.data;
        if (responseData.message) {
          Notify.error(responseData.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.hide();
      })
}

const onActionClick = (action: string, setting: NewsSyncSettingsInterface) => {
  if ('edit' === action) {
    state.activeSetting = setting;
    state.showEditModal = true;
  }
  if ('query-info' === action) {
    state.activeSetting = setting;
    state.openQueryInfoModal = true;
  }
  if ('duplicate' === action) {
    duplicateSyncSetting(setting);
  }
  if ('sync-now' === action) {
    syncNow(setting);
  }
  if ('delete' === action) {
    if (setting.id) {
      removeSyncSetting(setting.id);
    } else {
      state.settings.splice(state.settings.indexOf(setting), 1);
    }
  }
}

const onStatusChange = (status: StatusDataInterface) => {
  if (state.status !== status.key) {
    state.status = status.key;
    getSettings(status.key);
  }
}

onMounted(() => {
  getSettings();
})
</script>

<template>
  <div class="mb-4 flex justify-end space-x-2">
    <ShaplaButton theme="primary" size="small" @click.prevent="getSettings" outline>Refresh</ShaplaButton>
    <ShaplaButton theme="primary" size="small" @click.prevent="addSyncSetting">Add Sync Setting</ShaplaButton>
  </div>
  <div class="flex">
    <ShaplaTableStatusList :statuses="state.statuses" @change="onStatusChange"/>
    <div class="flex-grow"></div>
    <ShaplaTablePagination
        :current-page="state.pagination.current_page"
        :per-page="state.pagination.per_page"
        :total-items="state.pagination.total_items"
    />
  </div>
  <div class="my-4">
    <ShaplaTable
        :items="state.settings"
        :columns="[
          {label:'Title',key:'title'},
          {label:'Category',key:'primary_category'},
          {label:'Service Provider',key:'service_provider'},
          {label:'Usage',key:'usage'},
          {label:'Fields',key:'fields'},
          {label:'Deliver to',key:'to_sites'},
          {label:'Last Sync Info',key:'synced_at',numeric:true},
      ]"
        :actions="[
          {label:'Edit',key:'edit'},
          {label:'Duplicate',key:'duplicate'},
          {label:'Query Info',key:'query-info'},
          {label:'Sync Now',key:'sync-now'},
          {label:'Delete',key:'delete'},
      ]"
        @click:action="onActionClick"
    >
      <template v-slot:usage="data">
        <div class="flex flex-col">
          <span v-if="data.row.use_actual_news">Use Actual News: yes</span>
          <span v-if="data.row.copy_news_image">Copy Image: yes</span>
          <span v-if="data.row.enable_news_filtering">Filter News: yes</span>
        </div>
      </template>
      <template v-slot:to_sites="data">{{ data.row.to_sites.join(', ') }}</template>
      <template v-slot:fields="data">
        <div class="flex flex-col space-y-1">
          <template v-for="_field in state.news_sync_fields">
          <span v-if="data.row.fields.includes(_field.value) && 'enable_news_filtering' !== _field.value"
          >{{ _field.label }}</span>
          </template>
        </div>
      </template>
      <template v-slot:synced_at="data">
        <div class="flex flex-col">
          <div>{{ formatISO8601DateTime(data.row.synced_at) }}</div>
          <div><span class="text-gray-400">Total Found: </span> {{ data.row.total_found_items }}</div>
          <div v-if="data.row.total_existing_items"><span class="text-gray-400">Existing: </span>
            {{ data.row.total_existing_items }}
          </div>
          <div v-if="data.row.total_omitted_items"><span class="text-gray-400">Omitted: </span>
            {{ data.row.total_omitted_items }}
          </div>
          <div v-if="data.row.total_new_items"><span class="text-gray-400">New: </span> {{ data.row.total_new_items }}
          </div>
        </div>
      </template>
    </ShaplaTable>
  </div>
  <ShaplaTablePagination
      :current-page="state.pagination.current_page"
      :per-page="state.pagination.per_page"
      :total-items="state.pagination.total_items"
  />
  <ShaplaModal :title="`Edit Setting: ${state.activeSetting.title}`" v-if="state.showEditModal" :active="true"
               @close="state.showEditModal = false" content-size="large">
    <NewsSyncSettings
        :countries="state.countries"
        :categories="state.categories"
        :news_sync_fields="state.news_sync_fields"
        :languages="state.languages"
        :setting="state.activeSetting"
        @change:setting="(_setting) => state.activeSetting = _setting"
    />
    <template v-slot:foot>
      <ShaplaButton theme="default" @click="state.showEditModal = false">Cancel</ShaplaButton>
      <ShaplaButton theme="primary" @click="saveActiveSetting">Save</ShaplaButton>
    </template>
  </ShaplaModal>
  <ShaplaModal v-if="state.openQueryInfoModal" :active="true" @close="state.openQueryInfoModal = false"
               :show-card-footer="false" :title="`Query Info: ${state.activeSetting.title}`">
    <div v-if="state.activeSetting.query_info && Object.keys(state.activeSetting.query_info).length">
      <div>
        <h2>GET Request</h2>
        <table class="form-table">
          <tr>
            <th>URL</th>
            <td>
              <div>
                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
                    state.activeSetting.query_info.get.url
                  }}</code></pre>
              </div>
              <a :href="state.activeSetting.query_info.get.url" target="_blank">Open</a>
            </td>
          </tr>
        </table>
      </div>
      <div>
        <h2>POST request</h2>
        <table class="form-table">
          <tr>
            <th>URL</th>
            <td>
              <div>
                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
                    state.activeSetting.query_info.post.url
                  }}</code></pre>
              </div>
            </td>
          </tr>
          <tr>
            <th>Body</th>
            <td>
              <div>
                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
                    state.activeSetting.query_info.post.body
                  }}</code></pre>
              </div>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </ShaplaModal>
</template>
