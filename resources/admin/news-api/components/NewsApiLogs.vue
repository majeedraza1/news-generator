<template>
  <div>
    <div class="flex">
      <div class="flex-grow"></div>
      <div class="flex space-x-2 items-center">
        <ShaplaButton size="small" theme="primary" outline @click="()=>getItems(1)">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current w-6 h-6">
            <path d="M0 0h24v24H0V0z" fill="none"/>
            <path
                d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
          </svg>
          <span>Refresh</span>
        </ShaplaButton>
        <ShaplaSearchForm @search="searchItems"/>
      </div>
    </div>
    <div class="my-4">
      <ShaplaTablePagination
          :total-items="state.pagination.total_items"
          :per-page="state.pagination.per_page"
          :current-page="state.pagination.current_page"
          @paginate="getItems"
      />
    </div>
    <ShaplaTable
        :items="state.items"
        :columns="columns"
        :actions="actions"
        @click:action="onActionClick"
    >
      <template v-slot:existing_records_ids="data">
        {{ data.row.existing_records_ids.length }}
      </template>
      <template v-slot:new_records_ids="data">
        {{ data.row.new_records_ids.length }}
      </template>
      <template v-slot:created_at="data">
        {{ formatISO8601DateTime(data.row.created_at) }}
      </template>
    </ShaplaTable>
    <div class="mt-4">
      <ShaplaTablePagination
          :total-items="state.pagination.total_items"
          :per-page="state.pagination.per_page"
          :current-page="state.pagination.current_page"
          @paginate="getItems"
      />
    </div>
  </div>
  <ShaplaModal v-if="state.showViewItemModal && state.activeItem" :active="state.showViewItemModal" title="View Log"
               @close="closeViewItemModal" content-size="full">
    <table class="form-table">
      <template v-for="(value, key) in state.activeItem">
        <tr v-if="'news_articles' !== key">
          <th>{{ key }}</th>
          <td> {{ value }}</td>
        </tr>
      </template>
      <tr>
        <th>News Items</th>
        <td>
          <div v-for="article in state.activeItem.news_articles"
               class="bg-white shadow rounded mb-4 p-2 relative max-w-full">
            <div>
              <div v-for="(value, key) in article" class="flex">
                <span class="w-max lg:min-w-[180px]">{{ key }}</span>
                <span class="">{{ value }}</span>
              </div>
            </div>
            <span class="absolute top-1 right-1 rounded bg-green-600 text-white px-4 py-2">{{ article.type }}</span>
          </div>
        </td>
      </tr>
    </table>
  </ShaplaModal>
</template>
<script setup lang="ts">
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {ShaplaButton, ShaplaModal, ShaplaSearchForm, ShaplaTable, ShaplaTablePagination} from "@shapla/vue-components";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";
import {NewsApiResponseLogInterface} from "../../../utils/interfaces";

const crud = new CrudOperation('newsapi-logs', http);
const state = reactive<{
  items: NewsApiResponseLogInterface[];
  pagination: PaginationDataInterface;
  activeItem: NewsApiResponseLogInterface;
  showViewItemModal: boolean;
  search: string;
}>({
  items: [],
  pagination: {total_items: 0, current_page: 1, per_page: 100, total_pages: 1},
  activeItem: null,
  showViewItemModal: false,
  search: '',
});

const columns = [
  {label: 'Setting', key: 'sync_setting_title'},
  {label: 'New News', key: 'new_records_ids'},
  {label: 'Already Recorded', key: 'existing_records_ids'},
  {label: 'Datetime', key: 'created_at'},
];

const actions = [
  {label: 'View', key: 'view'},
];

const decodeHtml = (html: string) => {
  const txt = document.createElement("textarea");
  txt.innerHTML = html;
  return txt.value;
}

const onActionClick = (action: string, item: NewsApiResponseLogInterface) => {
  if ('view' === action) {
    state.showViewItemModal = true;
    state.activeItem = item;
  }
}

const closeViewItemModal = () => {
  state.showViewItemModal = false;
  state.activeItem = null;
}

const getItems = (page: number = 1) => {
  crud.getItems({
    page: page,
    per_page: state.pagination.per_page,
    search: state.search,
  }).then(data => {
    state.items = data.items as NewsApiResponseLogInterface[];
    state.pagination = data.pagination;
  })
}

const searchItems = (value: string) => {
  state.search = value;
  getItems();
}

onMounted(() => {
  getItems();
})
</script>
