<script setup lang="ts">
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";
import {onMounted, reactive} from "vue";
import {ShaplaButton, ShaplaModal, ShaplaTable, ShaplaTablePagination} from "@shapla/vue-components";
import NewsCrawlerLog from "@/admin/news-api/components/NewsCrawlerLog.vue";

const crud = new CrudOperation('news-crawler-logs', http);

const state = reactive({
  items: [],
  pagination: {
    total_items: 0,
    per_page: 50,
    current_page: 1
  },
  showViewModal: false,
  activeItem: null,
})

const columns = [
  {key: 'source_url', label: 'URL'}
];
const actions = [
  {key: 'view', label: 'View'}
];

const getItems = (page) => {
  crud.getItems({status: 'fail', page: page}).then(data => {
    state.items = data.items;
    state.pagination = data.pagination;
  })
}

const closeViewModal = () => {
  state.showViewModal = false;
  state.activeItem = null;
}

const onActionClick = (action: string, item) => {
  if ('view' === action) {
    state.activeItem = item;
    state.showViewModal = true;
  }
}

onMounted(() => {
  getItems(1)
})
</script>

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
  <ShaplaModal :active="state.showViewModal && state.activeItem" @close="closeViewModal" title="View Details"
               content-size="large">
    <NewsCrawlerLog :crawler-log="state.activeItem"/>
  </ShaplaModal>
</template>
