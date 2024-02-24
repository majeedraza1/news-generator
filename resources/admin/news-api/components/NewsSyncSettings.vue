<template>
  <table class="form-table" v-if="state.setting && Object.keys(state.setting).length">
    <tr>
      <th scope="row"><label :for="`title-${index}`">Admin Label</label></th>
      <td>
        <ShaplaInput v-model="state.setting.title"/>
      </td>
    </tr>
    <tr>
      <th scope="row"><label :for="`primary-category-${index}`">Primary Category</label></th>
      <td>
        <ShaplaSelect
            :options="categories"
            v-model="state.setting.primary_category"
            :searchable="true"
            :clearable="false"
            :multiple="false"
            help-text="Primary category will be used for internal category."
        />
      </td>
    </tr>
    <tr>
      <th scope="row"><label :for="`service_provider-${index}`">Service Provider</label></th>
      <td>
        <div class="flex space-x-2">
          <ShaplaRadio label="newsapi.ai" value="newsapi.ai" v-model="state.setting.service_provider"/>
          <ShaplaRadio label="naver.com" value="naver.com" v-model="state.setting.service_provider"/>
        </div>
      </td>
    </tr>
    <tr>
      <th scope="row"><label :for="`primary-category-${index}`">Copy News Image</label></th>
      <td>
        <ShaplaCheckbox
            v-model="state.setting.copy_news_image"
            label="Copy image from news site"
        />
        <p class="description" v-if="state.setting.service_provider === 'naver.com'">
          <strong>naver.com</strong> does not provide image directly. Image will be copied if it is available on
          <strong>newsapi.com extract article information</strong> or on <strong>News Crawl</strong> functionality.
        </p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label :for="`primary-category-${index}`">Rewrite news</label></th>
      <td>
        <div class="mb-2">
          <ShaplaCheckbox
              v-model="state.setting.use_actual_news"
              label="Use actual news"
          />
        </div>
        <p class="description">If you check this, NewsAPI news will be send to sites without rewriting from OpenAI</p>
      </td>
    </tr>
    <tr>
      <th scope="row"><label :for="`primary-category-${index}`">Live News</label></th>
      <td>
        <ShaplaCheckbox
            v-model="state.setting.enable_live_news"
            label="Enable Live News"
        />
        <p class="description">All news from this setting will be used as live news update.</p>
      </td>
    </tr>
    <template v-if="state.setting.service_provider === 'naver.com'">
      <tr>
        <th scope="row"><label :for="`keyword-${index}`">Keyword</label></th>
        <td>
          <ShaplaInput
              label="Keyword"
              v-model="state.setting.keyword"
          />
          <div>
            <label for="">Keyword Location</label>
            <div>
              <ShaplaRadio v-model="state.setting.keywordLoc" value="title">Title</ShaplaRadio>
              <ShaplaRadio v-model="state.setting.keywordLoc" value="body">Body</ShaplaRadio>
              <ShaplaRadio v-model="state.setting.keywordLoc" value="title-or-body">Title or Body</ShaplaRadio>
              <ShaplaRadio v-model="state.setting.keywordLoc" value="title-and-body">Both Title and Body</ShaplaRadio>
            </div>
          </div>
        </td>
      </tr>
      <tr>
        <th scope="row"><label :for="`primary-category-${index}`">News Filtering</label></th>
        <td>
          <ShaplaCheckbox
              v-model="state.setting.enable_news_filtering"
              label="Enable news filtering"
          />
          <div class="mt-4" v-show="state.setting.enable_news_filtering">
            <ShaplaInput
                type="textarea"
                label="Instruction for OpenAI"
                v-model="state.setting.news_filtering_instruction"
                rows="4"
            />
            <p class="description">Leave it empty to use global instruction.</p>
          </div>
        </td>
      </tr>
    </template>
    <template v-if="state.setting.service_provider === 'newsapi.ai'">
      <tr>
        <th scope="row"><label :for="`sync-fields-${index}`">Sync Fields</label></th>
        <td>
          <div class="flex flex-wrap space-x-2">
            <ShaplaCheckbox
                v-for="_field in news_sync_fields"
                :key="_field.value"
                :value="_field.value"
                :label="_field.label"
                v-model="state.setting.fields"
            />
          </div>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('keyword')">
        <th scope="row"><label :for="`keyword-${index}`">Keyword</label></th>
        <td>
          <div class="mb-2">
            <ShaplaInput
                label="Keyword"
                v-model="state.setting.keyword"
            />
            <div>
              <label for="">Keyword Location</label>
              <div>
                <ShaplaRadio v-model="state.setting.keywordLoc" value="title">Title</ShaplaRadio>
                <ShaplaRadio v-model="state.setting.keywordLoc" value="body">Body</ShaplaRadio>
                <ShaplaRadio v-model="state.setting.keywordLoc" value="title-or-body">Title or Body
                </ShaplaRadio>
                <ShaplaRadio v-model="state.setting.keywordLoc" value="title-and-body">Both Title and Body
                </ShaplaRadio>
              </div>
            </div>
          </div>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('locationUri')">
        <th scope="row"><label :for="`location-${index}`">Location</label></th>
        <td>
          <div class="mb-2">
            <LocationBox
                v-for="(_location, index) in state.setting.locations"
                :key="_location.wikiUri"
                :location="_location"
                :deletable="true"
                @delete="() => onLocationDelete(index)"
            />
          </div>
          <ShaplaButton @click="state.openLocationModal = true" size="small" theme="primary">
            {{ state.setting.locations.length ? 'Change Location' : 'Select Location' }}
          </ShaplaButton>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('categoryUri')">
        <th scope="row"><label :for="`category-${index}`">Category</label>
        </th>
        <td>
          <div class="mb-2">
            <CategoryBox
                v-for="(_category, index) in state.setting.categories"
                :key="_category.uri"
                :category="_category"
                :deletable="true"
                @delete="() => onCategoryDelete(index)"
            />
          </div>
          <ShaplaButton @click="state.openCategoryModal = true" size="small" theme="primary">
            {{ state.setting.categories.length ? 'Change Category' : 'Select Category' }}
          </ShaplaButton>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('conceptUri')">
        <th scope="row"><label :for="`concept-${index}`">Concept</label>
        </th>
        <td>
          <div class="mb-2">
            <ConceptBox
                v-for="(_concept, index) in state.setting.concepts"
                :key="_concept.uri"
                :concept="_concept"
                :deletable="true"
                @delete="() => onConceptDelete(index)"
            />
          </div>
          <ShaplaButton @click="state.openConceptModal = true" size="small" theme="primary">
            {{ state.setting.concepts.length ? 'Change Concept' : 'Select Concept' }}
          </ShaplaButton>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('sourceUri')">
        <th scope="row"><label :for="`source-${index}`">Source</label>
        </th>
        <td>
          <div class="mb-2">
            <SourceBox
                v-for="(_source,index) in state.setting.sources"
                :key="_source.uri"
                :source="_source"
                :deletable="true"
                @delete="() => onSourceDelete(index)"
            />
          </div>
          <ShaplaButton @click="state.openSourceModal = true" size="small" theme="primary">
            Select Source
          </ShaplaButton>
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('lang')">
        <th scope="row"><label :for="`languages-${index}`">Languages</label></th>
        <td>
          <ShaplaSelect
              :options="languages"
              v-model="state.setting.lang"
              :searchable="true"
              :clearable="false"
              :multiple="true"
          />
        </td>
      </tr>
      <tr v-show="state.setting.fields.includes('enable_news_filtering')">
        <th scope="row"><label :for="`primary-category-${index}`">Enable News Filtering</label></th>
        <td>
          <ShaplaCheckbox
              v-model="state.setting.enable_news_filtering"
              label="Enable news filtering"
          />
          <div class="mt-4" v-show="state.setting.enable_news_filtering">
            <ShaplaInput
                type="textarea"
                label="Instruction for OpenAI"
                v-model="state.setting.news_filtering_instruction"
                rows="4"
            />
            <p class="description">Leave it empty to use global instruction.</p>
          </div>
        </td>
      </tr>
    </template>
    <tr>
      <th>Status</th>
      <td>
        <div class="flex space-x-2">
          <label>
            <input type="radio" v-model="state.setting.status" value="publish">
            <span>Publish</span>
          </label>
          <label>
            <input type="radio" v-model="state.setting.status" value="draft">
            <span>Draft</span>
          </label>
        </div>
        <p class="description">Setting 'Draft' won't run automatically for news sync but you can still test all option
          manually.</p>
      </td>
    </tr>
    <tr>
      <th>Will be delivered to</th>
      <td>
        <template v-if="setting.to_sites">
          <div v-for="site in setting.to_sites">{{ site }}</div>
        </template>
        <template v-else>None</template>
      </td>
    </tr>
  </table>
  <ShaplaModal :active="state.openQueryInfoModal" @close="state.openQueryInfoModal = false" :show-card-footer="false"
               title="Query Info">
    <div v-if="setting.query_info && Object.keys(setting.query_info).length">
      <div>
        <h2>GET Request</h2>
        <table class="form-table">
          <tr>
            <th>URL</th>
            <td>
              <div>
                                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
                                    setting.query_info.get.url
                                  }}</code></pre>
              </div>
              <a :href="setting.query_info.get.url" target="_blank">Open</a>
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
                                    setting.query_info.post.url
                                  }}</code></pre>
              </div>
            </td>
          </tr>
          <tr>
            <th>Body</th>
            <td>
              <div>
                                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
                                    setting.query_info.post.body
                                  }}</code></pre>
              </div>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </ShaplaModal>
  <ShaplaModal :active="state.openLocationModal" @close="state.openLocationModal = false" :show-card-footer="false"
               title="Choose Location">
    <ShaplaColumns multiline>
      <ShaplaColumn :is="12">
        <input type="text" placeholder="Search location, country, city, etc..." @input="searchLocation"
               class="p-2 w-full mb-4">
      </ShaplaColumn>
      <ShaplaColumn :tablet="12">
        <div class="min-h-[300px]">
          <LocationBox
              v-for="location in state.locations"
              :key="location.wikiUri"
              :location="location"
              @click="chooseLocation(location)"
          />
        </div>
      </ShaplaColumn>
    </ShaplaColumns>
  </ShaplaModal>
  <ShaplaModal :active="state.openCategoryModal" @close="state.openCategoryModal = false" :show-card-footer="false"
               title="Choose Categories">
    <ShaplaColumns multiline>
      <ShaplaColumn :is="12">
        <input type="text" placeholder="Search category" @input="searchCategory"
               class="p-2 w-full mb-4">
      </ShaplaColumn>
      <ShaplaColumn :tablet="12">
        <div class="min-h-[300px]">
          <CategoryBox
              v-for="category in state.categories"
              :key="category.uri"
              :category="category"
              @click="chooseCategory(category)"
          />
        </div>
      </ShaplaColumn>
    </ShaplaColumns>
  </ShaplaModal>
  <ShaplaModal :active="state.openConceptModal" @close="state.openConceptModal = false" :show-card-footer="false"
               title="Choose Concepts">
    <ShaplaColumns multiline>
      <ShaplaColumn :is="12">
        <input type="text" placeholder="Search concept" @input="searchConcept"
               class="p-2 w-full mb-4">
      </ShaplaColumn>
      <ShaplaColumn :tablet="12">
        <div class="min-h-[300px]">
          <ConceptBox
              v-for="concept in state.concepts"
              :key="concept.uri"
              :concept="concept"
              @click="chooseConcept(concept)"
          />
        </div>
      </ShaplaColumn>
    </ShaplaColumns>
  </ShaplaModal>
  <ShaplaModal :active="state.openSourceModal" @close="state.openSourceModal = false" :show-card-footer="false"
               title="Choose Sources">
    <ShaplaColumns multiline>
      <ShaplaColumn :is="12">
        <input type="text" placeholder="Search source" @input="searchSource"
               class="p-2 w-full mb-4">
      </ShaplaColumn>
      <ShaplaColumn :tablet="12">
        <div class="min-h-[300px]">
          <SourceBox
              v-for="_source in state.sources"
              :key="_source.uri"
              :source="_source"
              @click="chooseSource(_source)"
          />
        </div>
      </ShaplaColumn>
    </ShaplaColumns>
  </ShaplaModal>
</template>

<script lang="ts" setup>
import {defineEmits, defineProps, onMounted, PropType, reactive, watch} from "vue";
import {
  CategoryInterface,
  ConceptInterfaces,
  LocationInterfaces,
  NewsSyncSettingsInterface,
  SourceInterface
} from "../../../utils/interfaces";
import LocationBox from "./LocationBox.vue";
import {
  Dialog,
  ShaplaButton,
  ShaplaCheckbox,
  ShaplaColumn,
  ShaplaColumns,
  ShaplaInput,
  ShaplaModal,
  ShaplaRadio,
  ShaplaSelect
} from "@shapla/vue-components";
import CategoryBox from "./CategoryBox.vue";
import http from "../../../utils/axios";
import ConceptBox from "./ConceptBox.vue";
import SourceBox from "./SourceBox.vue";

const props = defineProps({
  index: {type: [Number, String], default: 0},
  setting: {type: Object as PropType<NewsSyncSettingsInterface>, required: true},
  categories: {type: Array as PropType<{ label: string; value: string }[]>, required: true},
  countries: {type: Array as PropType<{ label: string; value: string }[]>, required: true},
  news_sync_fields: {type: Array as PropType<{ label: string; value: string }[]>, required: true},
  languages: {type: Array as PropType<{ label: string; value: string }[]>, required: true},
});
const state = reactive<{
  setting: NewsSyncSettingsInterface
  loading: boolean;
  openLocationModal: boolean;
  openQueryInfoModal: boolean;
  locations: LocationInterfaces[];
  openCategoryModal: boolean;
  categories: CategoryInterface[];
  openConceptModal: boolean;
  concepts: ConceptInterfaces[];
  openSourceModal: boolean;
  sources: SourceInterface[];
}>({
  setting: null,
  loading: false,
  openLocationModal: false,
  openQueryInfoModal: false,
  locations: [],
  openCategoryModal: false,
  categories: [],
  openConceptModal: false,
  concepts: [],
  openSourceModal: false,
  sources: [],
})
const emit = defineEmits(['remove', 'change:setting']);

const searchLocation = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.locations = [];
    return;
  }
  state.loading = true;
  http
      .get('settings/locations', {params: {prefix: value}})
      .then(response => {
        state.locations = response.data.data;
      })
      .catch(() => {
        state.locations = [];
      })
      .finally(() => {
        state.loading = false;
      })
}
const searchCategory = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.categories = [];
    return;
  }
  state.loading = true;
  http
      .get('settings/categories', {params: {prefix: value}})
      .then(response => {
        state.categories = response.data.data;
      })
      .catch(() => {
        state.categories = [];
      })
      .finally(() => {
        state.loading = false;
      })
}

const searchConcept = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.concepts = [];
    return;
  }
  state.loading = true;
  http
      .get('settings/concepts', {params: {prefix: value}})
      .then(response => {
        state.concepts = response.data.data;
      })
      .catch(() => {
        state.concepts = [];
      })
      .finally(() => {
        state.loading = false;
      })
}

const searchSource = (event: InputEvent) => {
  const value = (event.target as HTMLInputElement).value;
  if (value.length < 2) {
    state.sources = [];
    return;
  }
  state.loading = true;
  http
      .get('settings/sources', {params: {prefix: value}})
      .then(response => {
        state.sources = response.data.data;
      })
      .catch(() => {
        state.sources = [];
      })
      .finally(() => {
        state.loading = false;
      })
}

const chooseLocation = (location: LocationInterfaces) => {
  state.openLocationModal = false;
  state.setting.locations.push(location);
  state.setting.locationUri.push(location.wikiUri);
}

const chooseCategory = (category: CategoryInterface) => {
  state.openCategoryModal = false;
  state.setting.categories.push(category);
  state.setting.categoryUri.push(category.uri);
}

const chooseConcept = (concept: ConceptInterfaces) => {
  state.openConceptModal = false;
  state.setting.concepts.push(concept);
  state.setting.conceptUri.push(concept.uri);
}

const chooseSource = (source: SourceInterface) => {
  state.openSourceModal = false;
  state.setting.sources.push(source);
  state.setting.sourceUri.push(source.uri);
}

const onSourceDelete = (index) => {
  Dialog.confirm('Are you sure to remove the source?').then(confirmed => {
    if (confirmed) {
      state.setting.sources.splice(index, 1);
      state.setting.sourceUri.splice(index, 1);
    }
  })
}

const onLocationDelete = (index) => {
  Dialog.confirm('Are you sure remove the location?').then(confirmed => {
    if (confirmed) {
      state.setting.locations.splice(index, 1);
      state.setting.locationUri.splice(index, 1);
    }
  })
}

const onCategoryDelete = (index) => {
  Dialog.confirm('Are you sure remove the category?').then(confirmed => {
    if (confirmed) {
      state.setting.categories.splice(index, 1);
      state.setting.categoryUri.splice(index, 1);
    }
  })
}

const onConceptDelete = (index) => {
  Dialog.confirm('Are you sure remove the concept?').then(confirmed => {
    if (confirmed) {
      state.setting.concepts.splice(index, 1);
      state.setting.conceptUri.splice(index, 1);
    }
  })
}

watch(
    () => props.setting,
    (newValue) => {
      state.setting = newValue;
    },
    {deep: true}
)

watch(
    () => state.setting,
    (newValue) => {
      emit('change:setting', newValue);
    },
    {deep: true}
)

onMounted(() => {
  state.setting = props.setting;
})
</script>
