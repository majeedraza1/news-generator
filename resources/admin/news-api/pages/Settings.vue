<template>
  <div>
    <h1 class="wp-heading-inline">Settings</h1>
    <hr class="wp-header-end">

    <div>
      <ShaplaTabs alignment="center">
        <ShaplaTab name="General" selected>
          <table class="form-table">
            <tr>
              <th scope="row"><label>Google Vision Api Key</label></th>
              <td>
                <input type="text" v-model="state.google_vision_secret_key" class="regular-text"/>
                <p>
                  <a class="button" :href="state.google_vision_test_url" target="_blank">Test Config</a>
                </p>
              </td>
            </tr>
            <tr>
              <th scope="row"><label>Instagram Important News</label></th>
              <td>
                <a class="button" :href="state.important_news_for_instagram" target="_blank">Sync now</a>
                <p class="description">
                  Click to sync important news for instagram
                </p>
              </td>
            </tr>
          </table>
        </ShaplaTab>
        <ShaplaTab name="News API">
          <ShaplaTabs alignment="center" tab-style="rounded">
            <ShaplaTab selected name="General Settings">
              <table class="form-table">
                <tr>
                  <th scope="row"><label>News API Keys</label></th>
                  <td>
                    <ShaplaToggles>
                      <ShaplaToggle v-for="(api_key, index) in state.news_api" :key="index"
                                    :name="`Key ${index + 1}`"
                                    :subtext="getSubtext(api_key)">
                        <table class="form-table">
                          <tr>
                            <th scope="row"><label :for="`api_key-${index}`">Key</label>
                            </th>
                            <td><input type="text" :id="`api_key-${index}`"
                                       v-model="api_key.api_key"
                                       class="regular-text"></td>
                          </tr>
                          <tr>
                            <th scope="row"><label :for="`limit_per_day-${index}`">Limit
                              (per
                              day)</label>
                            </th>
                            <td>
                              <input type="text" :id="`limit_per_day-${index}`"
                                     class="regular-text"
                                     v-model="api_key.limit_per_day">
                            </td>
                          </tr>
                        </table>
                        <p>
                          <shapla-button outline theme="error" size="small"
                                         @click.prevent="removeApiKey(index)">Remove
                          </shapla-button>
                        </p>
                      </ShaplaToggle>
                    </ShaplaToggles>
                    <p>
                      <shapla-button outline theme="primary" size="small"
                                     @click.prevent="addNewKey">Add
                        New Key
                      </shapla-button>
                    </p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Enable Automatic News Sync</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.newsapi_auto_sync_enabled"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="">News Sync Interval</label>
                  </th>
                  <td>
                    <input type="number" v-model="state.news_sync_interval" min="15" max="360"
                           step="5"/>
                    <p class="description">News sync interval in minutes. Minimum value is
                      15(minutes) and maximum value is 360(minutes).</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="">News not before (in minutes)</label>
                  </th>
                  <td>
                    <input type="number" v-model="state.news_not_before_in_minutes" min="15"/>
                    <p class="description">If you set 45, it means that news more than 45 minutes ago
                      should not sync. Minimum value 15.</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Enable duplicate news checking</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.news_duplicate_checking_enabled"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Remove news images containing text</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.should_remove_image_with_text"
                    />
                    <p>Make sure Google Vision key are set properly.</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Sync image copy setting from source</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.sync_image_copy_setting_from_source"
                    />
                    <p>Uncheck this to use google vision api to add/remove image.</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="">Min similarity (in percent)</label>
                  </th>
                  <td>
                    <input type="number" v-model="state.similarity_in_percent" min="30" max="90"/>
                    <p class="description">Set minimum similarity in percentage. Min value is 30,
                      max value
                      is 90. Recommended value is 60.</p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="">Days to check similarity</label>
                  </th>
                  <td>
                    <input type="number" v-model="state.num_of_days_for_similarity" min="1"
                           max="7"/>
                    <p class="description">Number of hours to check similar news. Minimum value is
                      1,
                      maximum
                      value is 168. Recommended value is 72.</p>
                  </td>
                </tr>
              </table>
            </ShaplaTab>
            <ShaplaTab name="Sync settings">
              <div class="mb-4 flex justify-end">
                <ShaplaButton theme="primary" size="small" @click.prevent="addSyncSetting">
                  Add Sync Setting
                </ShaplaButton>
              </div>
              <ShaplaToggles>
                <ShaplaToggle v-for="(setting, index) in state.news_sync" :key="index"
                              :name="`Setting ${index + 1}`"
                              :subtext="getSettingSubtext(setting)">
                  <div>
                    <div class="flex justify-between">
                      <div class="space-x-2 p-2 rounded">
                        <strong>ID:</strong>
                        <span>{{ setting.option_id }}</span>
                      </div>
                      <div>
                        <ShaplaButton size="small" theme="secondary" @click="()=>duplicateSyncSetting(setting)">
                          Duplicate
                        </ShaplaButton>
                      </div>
                    </div>
                    <div class="space-x-2 bg-yellow-200 p-2 rounded">
                      <strong>Note:</strong>
                      <span>Only choose one value from each setting for best sync result.</span>
                    </div>
                  </div>
                  <NewsSyncSettings
                      :countries="countries"
                      :categories="state.primary_categories"
                      :news_sync_fields="state.news_sync_fields"
                      :languages="languages"
                      :setting="setting"
                      @change:setting="(_setting) => setting = _setting"
                      @remove="()=>removeSyncSetting(index)"
                  />
                </ShaplaToggle>
              </ShaplaToggles>
            </ShaplaTab>
          </ShaplaTabs>
        </ShaplaTab>
        <ShaplaTab name="OpenAI API">
          <ShaplaTabs alignment="center" tab-style="rounded">
            <ShaplaTab selected name="General Settings">
              <table class="form-table">
                <tr>
                  <th scope="row"><label>OpenAI API settings</label></th>
                  <td>
                    <ShaplaToggles>
                      <ShaplaToggle v-for="(api_key, index) in state.openai_api" :key="index"
                                    :name="`Key ${index + 1}`"
                                    :subtext="getSubtext(api_key)">
                        <table class="form-table">
                          <tr>
                            <th scope="row"><label :for="`api_key-${index}`">Key</label>
                            </th>
                            <td><input type="text" :id="`api_key-${index}`"
                                       v-model="api_key.api_key"
                                       class="regular-text"></td>
                          </tr>
                          <tr>
                            <th scope="row"><label
                                :for="`organization-${index}`">Organization</label></th>
                            <td><input type="text" :id="`organization-${index}`"
                                       v-model="api_key.organization"
                                       class="regular-text"></td>
                          </tr>
                          <tr>
                            <th scope="row"><label :for="`limit_per_day-${index}`">Limit
                              (per
                              day)</label>
                            </th>
                            <td>
                              <input type="text" :id="`limit_per_day-${index}`"
                                     class="regular-text"
                                     v-model="api_key.limit_per_day">
                            </td>
                          </tr>
                        </table>
                        <p>
                          <shapla-button outline theme="error" size="small"
                                         @click.prevent="removeOpenAiApiKey(index)">Remove
                          </shapla-button>
                        </p>
                      </ShaplaToggle>
                    </ShaplaToggles>
                    <p>
                      <shapla-button outline theme="primary" size="small"
                                     @click.prevent="addNewOpenAiApiKey">Add New
                        Key
                      </shapla-button>
                    </p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>OpenAI Auto Rewrite</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.openai_auto_sync_enabled"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Enable news country search</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.openai_news_country_enabled"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Enable External link</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.external_link_enabled"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Use linkedin data for instagram</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.use_linkedin_data_for_instagram"
                    />
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Enable important news for tweet filter</label></th>
                  <td>
                    <ShaplaSwitch
                        v-model="state.important_news_for_tweets_enabled"
                    />
                  </td>
                </tr>
                <tr v-if="state.important_news_for_tweets_enabled">
                  <th scope="row"><label>Minimum news count for important tweet</label></th>
                  <td>
                    <div class="max-w-xs">
                      <ShaplaInput
                          v-model="state.min_news_count_for_important_tweets"
                      />
                    </div>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>News Sync method</label></th>
                  <td>
                    <div class="max-w-xs">
                      <ShaplaSelect
                          v-model="state.openai_news_sync_method"
                          :options="openai_news_sync_methods"
                          :clearable="false"
                      />
                    </div>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><label>Instagram news interval</label></th>
                  <td>
                    <div class="max-w-xs">
                      <input type="number" v-model="state.instagram_new_news_interval" min="15">
                    </div>
                    <p class="description">Interval (in minutes) to sync important news for instagram. Minimum value is
                      15</p>
                  </td>
                </tr>
              </table>
            </ShaplaTab>
            <ShaplaTab name="OpenAI Instructions">
              <OpenAiInstructions
                  :instructions="state.instructions"
                  @change:instruction="value => state.instructions = value"
              />
            </ShaplaTab>
            <ShaplaTab name="OpenAI Blacklist Words">
              <OpenAiBlacklistWords :words="state.blacklist_words"/>
            </ShaplaTab>
          </ShaplaTabs>
        </ShaplaTab>
        <ShaplaTab name="News Categories">
          <table class="form-table">
            <tr>
              <th>Default Category</th>
              <td>
                <ShaplaSelect
                    :options="state.primary_categories"
                    v-model="state.default_news_category"
                    :clearable="false"
                />
              </td>
            </tr>
            <tr>
              <th>Categories</th>
              <td>
                <PrimaryCategories
                    :categories="state.primary_categories"
                    :default-category="state.default_news_category"
                    @change:categories="newValue => state.primary_categories = newValue"
                />
              </td>
            </tr>
          </table>
        </ShaplaTab>
      </ShaplaTabs>
    </div>
    <div class="fixed bottom-8 right-8">
      <ShaplaButton fab theme="primary" size="large" @click="saveSettings">
        <ShaplaIcon>
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"
               fill="#000000">
            <path d="M0 0h24v24H0V0z" fill="none"/>
            <path
                d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm2 16H5V5h11.17L19 7.83V19zm-7-7c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zM6 6h9v4H6z"/>
          </svg>
        </ShaplaIcon>
      </ShaplaButton>
    </div>
  </div>
</template>

<script lang="ts" setup>
import {
  Dialog,
  ShaplaButton,
  ShaplaIcon,
  ShaplaInput,
  ShaplaSelect,
  ShaplaSwitch,
  ShaplaTab,
  ShaplaTabs,
  ShaplaToggle,
  ShaplaToggles
} from '@shapla/vue-components';
import {computed, onMounted, reactive} from "vue";
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {
  CategoryInterface,
  LocationInterfaces,
  NewsSyncQueryInfoInterface,
  NewsSyncSettingsInterface,
  OpenAiSettingsInterface
} from "../../../utils/interfaces";
import NewsSyncSettings from "../components/NewsSyncSettings.vue";
import OpenAiInstructions from "../components/OpenAiInstructions.vue";
import PrimaryCategories from "../components/PrimaryCategories.vue";
import OpenAiBlacklistWords from "../components/OpenAiBlacklistWords.vue";

interface RemoteSettingInterface {
  api_key: string;
  limit_per_day: number;
  request_sent: number;
}

const crud = new CrudOperation('settings', http)

const state = reactive<{
  openLocationModal: boolean,
  loadingLocation: boolean,
  active_news_sync_index: number,
  locations: LocationInterfaces[],
  location: LocationInterfaces | {},
  news_api: RemoteSettingInterface[],
  news_sync: NewsSyncSettingsInterface[],
  openai_api: OpenAiSettingsInterface[],
  google_maps_api: RemoteSettingInterface[],
  active_news_sync: NewsSyncSettingsInterface | {},
  openCategoryModal: boolean,
  openai_auto_sync_enabled: boolean,
  openai_news_country_enabled: boolean,
  external_link_enabled: boolean,
  use_linkedin_data_for_instagram: boolean,
  min_news_count_for_important_tweets: number,
  important_news_for_tweets_enabled: boolean,
  newsapi_auto_sync_enabled: boolean,
  news_duplicate_checking_enabled: boolean,
  openai_news_sync_method: 'full_news' | 'individual_field',
  instructions: Record<string, string>,
  news_sync_query_info: NewsSyncQueryInfoInterface[],
  categories: CategoryInterface[],
  category: CategoryInterface | {},
  primary_categories: ({
    label: string;
    value: string
  })[];
  news_sync_fields: ({
    label: string;
    value: string
  })[];
  blacklist_words: ({
    id: number;
    phrase: string
  })[];
  similarity_in_percent: number;
  num_of_days_for_similarity: number;
  news_sync_interval: number;
  news_not_before_in_minutes: number;
  default_news_category: string;
  should_remove_image_with_text: boolean,
  sync_image_copy_setting_from_source: boolean,
  google_vision_secret_key: string;
  google_vision_test_url: string;
  important_news_for_instagram: string;
  instagram_new_news_interval: number;
}>({
  blacklist_words: [],
  news_sync_query_info: [],
  google_maps_api: [],
  news_api: [],
  news_sync: [],
  openai_api: [],
  openLocationModal: false,
  loadingLocation: false,
  important_news_for_tweets_enabled: false,
  external_link_enabled: false,
  use_linkedin_data_for_instagram: false,
  active_news_sync_index: -1,
  active_news_sync: {},
  locations: [],
  location: {},
  openCategoryModal: false,
  openai_auto_sync_enabled: false,
  newsapi_auto_sync_enabled: false,
  news_duplicate_checking_enabled: false,
  openai_news_country_enabled: false,
  min_news_count_for_important_tweets: 4,
  categories: [],
  category: {},
  instructions: {},
  primary_categories: [],
  news_sync_fields: [],
  similarity_in_percent: 40,
  num_of_days_for_similarity: 3,
  news_sync_interval: 30,
  instagram_new_news_interval: 30,
  news_not_before_in_minutes: 3,
  default_news_category: '',
  openai_news_sync_method: 'full_news',
  should_remove_image_with_text: false,
  sync_image_copy_setting_from_source: false,
  google_vision_secret_key: '',
  google_vision_test_url: '',
  important_news_for_instagram: '',
})

const openai_news_sync_methods = [
  {label: 'Full News', value: 'full_news'},
  {label: 'Individual News Field', value: 'individual_field'},
]
const countries = computed(() => {
  let countries = [];
  for (const [key, value] of Object.entries(window.TeraPixelNewsGenerator.countries)) {
    countries.push({label: value, value: key});
  }
  return countries;
})
const languages = computed(() => {
  let languages = [];
  for (const [key, value] of Object.entries(window.TeraPixelNewsGenerator.languages)) {
    languages.push({label: value, value: key});
  }
  return languages;
})

function getSubtext(api_key) {
  let key = api_key.api_key, key_length = api_key.api_key.length,
      ending = key.substring((key_length - 4), key_length);
  return `Key: ...${ending}; Limit: ${api_key.limit_per_day}; Request Sent: ${api_key.request_sent}`;
}

function getSettingSubtext(setting: NewsSyncSettingsInterface) {
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
  return html;
}

function addNewKey() {
  state.news_api.push({"api_key": "", "limit_per_day": 200, "request_sent": 0});
}

const createUUID = (): string => {
  const pattern = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx";
  return pattern.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function addSyncSetting() {
  state.news_sync.push({
    option_id: createUUID(),
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
    enable_live_news: false,
    news_filtering_instruction: '',
    query_info: null,
  });
}

const addNewOpenAiApiKey = () => {
  state.openai_api.push({
    api_key: '',
    limit_per_day: 2000,
    organization: '',
    request_sent: 0,
  })
}

const removeOpenAiApiKey = (index) => {
  Dialog.confirm('Are you sure to delete?').then(confirmed => {
    if (confirmed) {
      state.openai_api.splice(index, 1);
    }
  })
}

function removeApiKey(index) {
  Dialog.confirm('Are you sure to delete?').then(confirmed => {
    if (confirmed) {
      state.news_api.splice(index, 1);
    }
  })
}

function removeSyncSetting(index) {
  Dialog.confirm('Are you sure to delete?').then(confirmed => {
    if (confirmed) {
      state.news_sync.splice(index, 1);
    }
  })
}

const duplicateSyncSetting = (settings: NewsSyncSettingsInterface) => {
  Dialog.confirm({
    message: 'New setting will append at bottom of current settings list.',
    title: 'Are you sure?'
  }).then(confirmed => {
    if (confirmed) {
      state.news_sync.push(JSON.parse(JSON.stringify(settings)));
    }
  })
}

function getSettings() {
  crud.getItems().then(data => {
    state.news_sync_query_info = data.news_sync_query_info as NewsSyncQueryInfoInterface[];
    state.news_sync_fields = data.news_sync_fields as ({
      label: string;
      value: string
    })[];
    state.blacklist_words = data.blacklist_words as ({
      id: number;
      phrase: string
    })[];
    state.google_vision_test_url = data.google_vision_test_url as string;
    state.important_news_for_instagram = data.important_news_for_instagram as string;
    const settings = data.settings as Record<string, any>;
    const primary_categories = settings.primary_categories as Record<string, string>;
    const _categories = [];
    for (const [value, label] of Object.entries(primary_categories)) {
      _categories.push({label, value});
    }
    state.news_api = settings.news_api;
    state.news_sync = settings.news_sync;
    state.google_maps_api = settings.google_maps_api;
    state.openai_api = settings.openai_api;
    state.instructions = settings.instructions;
    state.openai_auto_sync_enabled = settings.openai_auto_sync_enabled;
    state.use_linkedin_data_for_instagram = settings.use_linkedin_data_for_instagram;
    state.openai_news_country_enabled = settings.openai_news_country_enabled;
    state.important_news_for_tweets_enabled = settings.important_news_for_tweets_enabled;
    state.min_news_count_for_important_tweets = settings.min_news_count_for_important_tweets;
    state.newsapi_auto_sync_enabled = settings.newsapi_auto_sync_enabled;
    state.news_duplicate_checking_enabled = settings.news_duplicate_checking_enabled;
    state.similarity_in_percent = settings.similarity_in_percent;
    state.num_of_days_for_similarity = settings.num_of_days_for_similarity;
    state.news_sync_interval = settings.news_sync_interval;
    state.instagram_new_news_interval = settings.instagram_new_news_interval;
    state.news_not_before_in_minutes = settings.news_not_before_in_minutes;
    state.default_news_category = settings.default_news_category;
    state.openai_news_sync_method = settings.openai_news_sync_method;
    state.external_link_enabled = settings.external_link_enabled;
    state.google_vision_secret_key = settings.google_vision_secret_key;
    state.should_remove_image_with_text = settings.should_remove_image_with_text;
    state.sync_image_copy_setting_from_source = settings.sync_image_copy_setting_from_source;
    state.primary_categories = _categories;
  })
}

function saveSettings() {
  let data = {
    news_api: state.news_api,
    google_maps_api: state.google_maps_api,
    news_sync: state.news_sync,
    openai_api: state.openai_api,
    openai_auto_sync_enabled: state.openai_auto_sync_enabled,
    newsapi_auto_sync_enabled: state.newsapi_auto_sync_enabled,
    news_duplicate_checking_enabled: state.news_duplicate_checking_enabled,
    default_news_category: state.default_news_category,
    openai_news_country_enabled: state.openai_news_country_enabled,
    min_news_count_for_important_tweets: state.min_news_count_for_important_tweets,
    instructions: state.instructions,
    primary_categories: state.primary_categories,
    similarity_in_percent: state.similarity_in_percent,
    num_of_days_for_similarity: state.num_of_days_for_similarity,
    news_sync_interval: state.news_sync_interval,
    instagram_new_news_interval: state.instagram_new_news_interval,
    news_not_before_in_minutes: state.news_not_before_in_minutes,
    openai_news_sync_method: state.openai_news_sync_method,
    important_news_for_tweets_enabled: state.important_news_for_tweets_enabled,
    external_link_enabled: state.external_link_enabled,
    google_vision_secret_key: state.google_vision_secret_key,
    should_remove_image_with_text: state.should_remove_image_with_text,
    sync_image_copy_setting_from_source: state.sync_image_copy_setting_from_source,
    use_linkedin_data_for_instagram: state.use_linkedin_data_for_instagram,
  };
  crud.createItem(data).then(() => {
    getSettings();
  });
}

const searchLocation = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.locations = [];
    return;
  }
  state.loadingLocation = true;
  http
      .get('settings/locations', {params: {prefix: value}})
      .then(response => {
        state.locations = response.data.data;
      })
      .catch(() => {
        state.locations = [];
      })
      .finally(() => {
        state.loadingLocation = false;
      })
}
const searchCategory = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.categories = [];
    return;
  }
  state.loadingLocation = true;
  http
      .get('settings/categories', {params: {prefix: value}})
      .then(response => {
        state.categories = response.data.data;
      })
      .catch(() => {
        state.categories = [];
      })
      .finally(() => {
        state.loadingLocation = false;
      })
}

const chooseLocation = (location) => {
  state.location = location;
  state.openLocationModal = false;
  state.news_sync[state.active_news_sync_index].locations.push(location)
  state.news_sync[state.active_news_sync_index].locationUri.push(location.wikiUri);
}

const chooseCategory = (category) => {
  state.category = category;
  state.openCategoryModal = false;
  state.news_sync[state.active_news_sync_index].categories.push(category);
  state.news_sync[state.active_news_sync_index].categoryUri.push(category.uri);
}

const activateLocationModal = (settings, index) => {
  state.openLocationModal = true;
  state.active_news_sync = settings;
  state.active_news_sync_index = index;
}

const activateCategoryModal = (settings, index) => {
  state.openCategoryModal = true;
  state.active_news_sync = settings;
  state.active_news_sync_index = index;
}

const deactivateLocationModal = () => {
  state.openLocationModal = false;
  state.active_news_sync = {};
  state.active_news_sync_index = -1;
}

const addNewMapApiKey = () => {
  state.google_maps_api.push({"api_key": "", "limit_per_day": 200, "request_sent": 0});
}

const removeMapApiKey = (index) => {
  Dialog.confirm('Are you sure to delete?').then(confirmed => {
    if (confirmed) {
      state.google_maps_api.splice(index, 1);
    }
  })
}

onMounted(() => {
  getSettings();
})
</script>
