<template>
  <div>
    <h1 class="wp-heading-inline">News</h1>
    <hr class="wp-header-end">
    <div>
      <div class="mb-2 flex space-x-2">
        <div class="flex-grow"></div>
        <ShaplaIcon size="medium" hoverable @click="state.openScreenOptionsModal = true">
          <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
            <path
                d="M440-280h80l12-60q12-5 22.5-10.5T576-364l58 18 40-68-46-40q2-14 2-26t-2-26l46-40-40-68-58 18q-11-8-21.5-13.5T532-620l-12-60h-80l-12 60q-12 5-22.5 10.5T384-596l-58-18-40 68 46 40q-2 14-2 26t2 26l-46 40 40 68 58-18q11 8 21.5 13.5T428-340l12 60Zm40-120q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/>
          </svg>
        </ShaplaIcon>
        <ShaplaButton v-if=" state.status === 'in-progress' && state.selectedItems.length" size="small"
                      @click="markAsFail" theme="primary" outline>
          Mark as Fail
        </ShaplaButton>
        <ShaplaButton v-if=" state.status === 'in-progress' && state.selectedItems.length" size="small"
                      @click="markAsComplete" theme="secondary" outline>
          Mark as Complete
        </ShaplaButton>
        <ShaplaButton v-if=" state.status === 'fail'" size="small" @click="deleteFailNews" theme="error"
                      outline :disabled="state.items.length < 1">
          Delete All Fail
        </ShaplaButton>
        <ShaplaButton size="small" @click="getNews">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current w-6 h-6">
            <path d="M0 0h24v24H0V0z" fill="none"/>
            <path
                d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
          </svg>
          <span>Refresh</span>
        </ShaplaButton>
        <ShaplaButton v-if="state.status === 'complete'" size="small" theme="secondary"
                      :disabled="state.selectedItems.length < 1" @click="sendBatchNewsToSites">
          Send News to site
        </ShaplaButton>
        <ShaplaButton size="small" @click="state.openAddNewModal = true" theme="primary">Add new</ShaplaButton>
      </div>
      <div>
        <div class="mb-4 space-x-4 flex justify-end items-center">
          <select v-model="state.filter_by" class="py-1">
            <option value="">Filter By</option>
            <option value="use_for_instagram">for Instagram</option>
            <option value="important_for_tweet">for Twitter</option>
            <option value="has_image_id">Has Image</option>
          </select>
          <ShaplaSearchForm
              @search="onSearch"
          />
        </div>
        <div class="mb-2 flex items-center justify-between">
          <ShaplaTableStatusList
              :statuses="state.statuses"
              @change="changeStatus"
          />
          <ShaplaTablePagination
              :current-page="state.pagination.current_page"
              :total-items="state.pagination.total_items"
              :per-page="state.pagination.per_page"
              @paginate="onPaginate"
          />
        </div>
        <ShaplaTable
            :columns="columns.filter(column => !state.excludedColumns.includes(column.key))"
            :items="state.items"
            :selected-items="state.selectedItems"
            @select:item="onSelectItem"
            :actions="actions"
            @click:action="onClickAction"
        >
          <template v-slot:important_for_instagram="data">{{ data.row.important_for_instagram ? 'yes' : '' }}</template>
          <template v-slot:image="data">
            <template v-if="data.row.image && Object.keys(data.row.image).length">
              <ShaplaImage container-width="32px" container-height="32px">
                <img :src="data.row.image.url" :width="data.row.image.width"
                     :height="data.row.image.height"/>
              </ShaplaImage>
            </template>
            <template v-else>&nbsp;</template>
          </template>
          <template v-slot:remote_log="data">
            {{ data.row.remote_log.length ? data.row.remote_log.length : '' }}
          </template>
          <template v-slot:created_via="data">
            <span>{{ data.row.created_via }}</span>
            <div v-if="data.row.sync_setting_id">
              <span v-if="'keyword' === data.row.created_via && !!data.row.sync_setting.keyword" class="text-green-600">
                {{ data.row.sync_setting.keyword }}
              </span>
              <a v-if="'newsapi.ai' === data.row.created_via" href="#" @click.prevent="() => showSyncSetting(data.row)">
                {{ state.syncSettingsOptions[data.row.sync_setting_id] ?? data.row.sync_setting_id }}
              </a>
            </div>
          </template>
          <template v-slot:category="data">
            <span>{{ data.row.category.name }}</span><br>
            <span v-if="data.row.category.name !== data.row.openai_category.name" class="text-green-600">
							{{ data.row.openai_category.name }}
						</span>
            <span v-if="data.row.openai_category_response" class="text-red-600">
							{{ data.row.openai_category_response }}
						</span>
          </template>
          <template v-slot:title="data">
            <div class="w-auto text-ellipsis whitespace-nowrap overflow-hidden max-w-xl"
                 :title="data.row.title">
              {{ data.row.title }}
            </div>
          </template>
          <template v-slot:updated="data">
            {{ formatISO8601Date(data.row.updated) }}
          </template>
          <template v-slot:country="data">
            {{ data.row.country.name }}
          </template>
          <template v-slot:important_for_tweet="data">
            {{ data.row.important_for_tweet ? 'yes' : '' }}
          </template>
          <template v-slot:sync_status="data">
            <template v-if="state.status === 'in-progress' || state.status === 'fail'">
              {{ data.row.sync_step_done }} of {{ data.row.total_sync_step }}
            </template>
            <template v-else>{{ data.row.sync_status }}</template>
          </template>
        </ShaplaTable>
        <div class="mt-2">
          <ShaplaTablePagination
              :current-page="state.pagination.current_page"
              :total-items="state.pagination.total_items"
              :per-page="state.pagination.per_page"
              @paginate="onPaginate"
          />
        </div>
      </div>
    </div>
  </div>
  <ShaplaModal :active="state.showModal" @close="state.showModal = false" title="News Details" content-size="full">
    <ShaplaTabs alignment="center">
      <ShaplaTab name="News" selected>
        <NewsDetails v-if="state.item" :news="state.item"/>
      </ShaplaTab>
      <ShaplaTab name="Original News">
        <ArticleDetails v-if="state.originalNews" :article="state.originalNews"/>
      </ShaplaTab>
      <ShaplaTab name="News Crawler">
        <NewsCrawlerLog v-if="state.crawler_log" :crawler-log="state.crawler_log"/>
      </ShaplaTab>
      <ShaplaTab name="OpenAI Logs">
        <OpenAiLogs v-if="state.openAiLogs" :logs="state.openAiLogs"/>
      </ShaplaTab>
      <ShaplaTab name="Sent to Sites">
        <div v-for="log in state.newsToSitesLogs" :key="log.id"
             class="shadow rounded border border-solid border-gray-300 p-2">
          <div>
            <span>Remote Site URL:</span>
            <strong>{{ log.remote_site_url }}</strong>
          </div>
          <div>
            <span>News URL:</span>
            <strong><a target="_blank" :href="log.remote_news_url">{{ log.remote_news_url }}</a></strong>
          </div>
          <div>
            <span>Sent on:</span>
            <strong>{{ formatISO8601DateTime(log.created_at) }}</strong>
          </div>
        </div>
      </ShaplaTab>
    </ShaplaTabs>
  </ShaplaModal>
  <ShaplaModal :active="state.showNewsSendToSitesModal" @close="closeModal" title="Send news to Sites">
    <div class="font-bold">{{ state.newsToSend.title }}</div>
    <br>
    <div>This news is going to be sent to the sites.</div>
    <br>
    <ol>
      <li v-for="site in state.sitesToSend" :key="site.id">{{ site.site_url }}</li>
    </ol>
    <template v-slot:foot>
      <ShaplaButton theme="primary" @click="onConfirmSendToSites">Confirm Send</ShaplaButton>
    </template>
  </ShaplaModal>
  <AddNewNewsModal
      :active="state.openAddNewModal"
      :categories="state.categories"
      @close="state.openAddNewModal = false"
      @submit="addNewNews"
  />
  <ShaplaModal v-if="state.openAiLogs.length" :active="state.showItemLogsModal" @close="closeOpenAiLogModal"
               title="OpenAi Logs" content-size="full">
    <OpenAiLogs :logs="state.openAiLogs"/>
  </ShaplaModal>
  <ShaplaModal :active="state.showSyncSettingModal" @close="state.showSyncSettingModal = false" title="Sync Setting">
    <template v-for="(setting_value,setting_key) in state.item.sync_setting">
      <div class="flex mb-1">
        <div class="lg:min-w-[180px]">{{ setting_key }}</div>
        <div class="font-bold">{{ setting_value }}</div>
      </div>
    </template>
  </ShaplaModal>
  <ModalScreenOption
      v-if="state.openScreenOptionsModal"
      :active="state.openScreenOptionsModal"
      :columns="columns"
      :excluded-columns="state.excludedColumns"
      :per-page="state.userPerPage"
      @close="state.openScreenOptionsModal = false"
      @update="updateUserOption"
  />
</template>

<script lang="ts" setup>
import CrudOperation, {PaginationDataInterface, ScreenOptionDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive, ref, watch} from "vue";
import {
  Dialog,
  Notify,
  ShaplaButton,
  ShaplaIcon,
  ShaplaImage,
  ShaplaModal,
  ShaplaSearchForm,
  ShaplaTab,
  ShaplaTable,
  ShaplaTablePagination,
  ShaplaTableStatusList,
  ShaplaTabs,
  Spinner
} from "@shapla/vue-components";
import {ArticleInterface, OpenAiNewsInterface, StatusInterface} from "../../../utils/interfaces";
import {formatISO8601Date, formatISO8601DateTime} from "../../../utils/humanTimeDiff";
import NewsDetails from "../components/NewsDetails.vue";
import ArticleDetails from "../components/ArticleDetails.vue";
import OpenAiLogs from "../components/OpenAiLogs.vue";
import AddNewNewsModal from "@/admin/news-api/components/AddNewNewsModal.vue";
import NewsCrawlerLog from "@/admin/news-api/components/NewsCrawlerLog.vue";
import ModalScreenOption from "@/admin/news-api/components/ModalScreenOption.vue";

const crud = new CrudOperation('openai/news', http);

const state = reactive<{
  items: OpenAiNewsInterface[];
  syncSettingsOptions: Record<string, string> | null;
  item: OpenAiNewsInterface;
  originalNews: ArticleInterface;
  showModal: boolean;
  showItemLogsModal: boolean;
  openAddNewModal: boolean;
  showSyncSettingModal: boolean;
  pagination: PaginationDataInterface;
  selectedItems: number[]
  status: 'complete' | 'in-progress' | 'fail' | string;
  search: string;
  filter_by: string;
  statuses: StatusInterface[];
  showNewsSendToSitesModal: boolean,
  newsToSend: OpenAiNewsInterface,
  sitesToSend: { id: number; site_url: string }[],
  categories: Record<string, string>;
  default_category: string;
  openAiLogs: Record<string, any>[];
  newsToSitesLogs: Record<string, any>[];
  crawler_log: false | Record<string, any>;
  important_news_for_tweets_enabled: boolean;
  openScreenOptionsModal: boolean;
  excludedColumns: string[];
  userPerPage: number;
}>({
  items: [],
  syncSettingsOptions: null,
  item: {
    id: 0
  },
  originalNews: null,
  showModal: false,
  showSyncSettingModal: false,
  openAddNewModal: false,
  showItemLogsModal: false,
  openAiLogs: [],
  newsToSitesLogs: [],
  pagination: {total_items: 0, current_page: 1, per_page: 100, total_pages: 0,},
  selectedItems: [],
  status: 'complete',
  search: '',
  filter_by: '',
  statuses: [],
  showNewsSendToSitesModal: false,
  important_news_for_tweets_enabled: false,
  newsToSend: {id: 0},
  sitesToSend: [],
  categories: null,
  default_category: '',
  crawler_log: false,
  openScreenOptionsModal: false,
  excludedColumns: [],
  userPerPage: 20,
})

const columns = ref([{label: 'Title', key: 'title'}]);
const actions = ref([{label: 'View', key: 'view'}]);

const onChangeExcludedColumns = (value: string[]) => {
  state.excludedColumns = value;
}

const updateUserOption = (value: Record<string, any>) => {
  Spinner.show();
  http
      .post(`openai/news/screen-options`, {
        status: state.status,
        ...value
      })
      .then(response => {
        const data = response.data.data as ScreenOptionDataInterface;
        state.excludedColumns = data.excluded_columns as string[];
        state.userPerPage = data.per_page as number;
        state.openScreenOptionsModal = false;
      })
      .finally(() => {
        Spinner.hide();
      })
}

const showSyncSetting = (news: OpenAiNewsInterface) => {
  state.item = news;
  state.showSyncSettingModal = true;
}

const getSingleArticle = (newsId: number) => {
  Spinner.show();
  http
      .get(`openai/news/${newsId}`)
      .then(response => {
        const data = response.data.data;
        state.item = data.news;
        state.originalNews = data.source_news;
        state.openAiLogs = data.logs;
        state.newsToSitesLogs = data.news_to_sites;
        state.crawler_log = data.crawler_log;
      })
      .finally(() => {
        Spinner.hide();
      })
}

const getNews = () => {
  crud.getItems({
    page: state.pagination.current_page,
    per_page: state.pagination.per_page,
    status: state.status,
    filter_by: state.filter_by,
    search: state.search
  }).then(data => {
    state.items = data.items as OpenAiNewsInterface[];
    state.pagination = data.pagination;
    state.statuses = data.statuses as StatusInterface[];
    state.categories = data.categories as Record<string, string>;
    state.syncSettingsOptions = data.sync_settings_options as Record<string, string>;
    state.default_category = data.default_category as string;
    state.important_news_for_tweets_enabled = data.important_news_for_tweets_enabled as boolean;
    state.excludedColumns = data.screen_options.excluded_columns;
    state.userPerPage = data.screen_options.per_page;
    columns.value = data.screen_options.columns;
    actions.value = data.screen_options.actions;
  })
}

const onPaginate = (page: number) => {
  state.pagination.current_page = page;
  getNews();
}

const changeStatus = (status: StatusInterface) => {
  state.status = status.key;
  getNews();
}

const markAsFail = () => {
  Dialog.confirm('Are you sure to mark selected news as failed?').then(confirmed => {
    if (confirmed) {
      Spinner.activate();
      http.post('openai/news/batch', {action: 'mark-fail', ids: state.selectedItems}).then(() => {
        state.selectedItems = [];
        getNews();
      }).catch(error => {
        if (error.response.data) {
          Notify.error(error.response.data.message, 'Error!');
        }
      }).finally(() => {
        Spinner.deactivate();
      })
    }
  })
}

const markAsComplete = () => {
  Dialog.confirm('Are you sure to mark selected news as completed?').then(confirmed => {
    if (confirmed) {
      Spinner.activate();
      http.post('openai/news/batch', {action: 'mark-complete', ids: state.selectedItems}).then(() => {
        state.selectedItems = [];
        getNews();
      }).catch(error => {
        if (error.response.data) {
          Notify.error(error.response.data.message, 'Error!');
        }
      }).finally(() => {
        Spinner.deactivate();
      })
    }
  })
}

const deleteFailNews = () => {
  Dialog.confirm('Are you sure to delete all failed news?').then(confirmed => {
    if (confirmed) {
      Spinner.activate();
      http.post('openai/news/batch', {action: 'delete-fail'}).then(() => {
        Notify.success('All failed news have been deleted.', 'Success!');
        getNews();
      }).catch(error => {
        if (error.response.data) {
          Notify.error(error.response.data.message, 'Error!');
        }
      }).finally(() => {
        Spinner.deactivate();
      })
    }
  })
}

const closeOpenAiLogModal = () => {
  state.openAiLogs = [];
  state.showItemLogsModal = true;
}

const getOpenAiLog = (news: OpenAiNewsInterface) => {
  Spinner.activate();
  http
      .get(`openai-logs/news-logs/${news.source_id}`)
      .then((response) => {
        state.openAiLogs = response.data.data.items;
        state.showItemLogsModal = true;
      })
      .finally(() => {
        Spinner.deactivate();
      })
}

const sendToSites = (news: OpenAiNewsInterface) => {
  Spinner.activate();
  http
      .post(`admin/news-sites/send-news-to-sites`, {force: true, news_ids: [news.id]})
      .then(() => {
        Notify.success('News has been send to sites.', 'Success');
      })
      .finally(() => {
        Spinner.deactivate();
      })
}
const sendBatchNewsToSites = () => {
  Spinner.activate();
  http
      .post(`admin/news-sites/send-news-to-sites`, {force: false, news_ids: state.selectedItems})
      .then(() => {
        Notify.success('A background task is running to send news to sites.', 'Success');
        state.selectedItems = [];
      })
      .finally(() => {
        Spinner.deactivate();
      })
}

const onSelectItem = (ids: number[]) => {
  state.selectedItems = ids;
}

const copyImage = (news: OpenAiNewsInterface) => {
  Spinner.activate();
  http
      .post(`admin/news-sites/copy-image`, {id: news.id})
      .then(() => {
        Notify.success('News has been copy from remote site.', 'Success');
        getNews();
      })
      .catch(error => {
        const responseData = error.response.data;
        if (responseData.message) {
          Notify.error(responseData.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.deactivate();
      })
}

const syncNow = (news: OpenAiNewsInterface) => {
  Spinner.activate();
  http
      .post(`openai/news/${news.id}/sync`)
      .then(() => {
        Notify.success('News has been re-created from OpenAI.', 'Success');
        getNews();
      })
      .catch(error => {
        const responseData = error.response.data;
        if (responseData.message) {
          Notify.error(responseData.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.deactivate();
      })
}
const markImportantForInstagram = (news: OpenAiNewsInterface) => {
  Spinner.activate();
  http
      .post(`openai/news/${news.id}/instagram`)
      .then(() => {
        Notify.success('News has been marked for instagram feed. A background task is running to handle it.', 'Success');
        getNews();
      })
      .catch(error => {
        const responseData = error.response.data;
        if (responseData.message) {
          Notify.error(responseData.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.deactivate();
      })
}

const onClickAction = (action: string, news: OpenAiNewsInterface) => {
  if ('mark-for-ig' === action) {
    Dialog.confirm('Are you sure to use this news for instagram feed?').then(confirmed => {
      if (confirmed) {
        markImportantForInstagram(news);
      }
    })
  }
  if ('view-openai-logs' === action) {
    getOpenAiLog(news);
  }
  if ('view' === action) {
    state.item = news;
    state.showModal = true;
    getSingleArticle(news.id);
  }
  if ('sync-now' === action) {
    syncNow(news);
  }
  if ('copy-image' === action) {
    copyImage(news);
  }
  if ('send-to-sites' === action) {
    crud.getItem(news.id).then(data => {
      state.showNewsSendToSitesModal = true;
      state.newsToSend = data.news as OpenAiNewsInterface;
      state.sitesToSend = data.sites as { id: number; site_url: string }[];
    })
  }
}

const closeModal = () => {
  state.showNewsSendToSitesModal = false;
  state.newsToSend = {id: 0}
  state.sitesToSend = [];
}

const closeAddNewModal = () => {
  state.openAddNewModal = false;
}

const addNewNews = (event: SubmitEvent) => {
  getNews();
  closeAddNewModal();
}

const onConfirmSendToSites = () => {
  sendToSites(state.newsToSend);
}

const onSearch = (search: string) => {
  state.search = search;
  getNews();
}

watch(() => state.filter_by, () => {
  getNews();
})

onMounted(() => {
  getNews();
})
</script>
