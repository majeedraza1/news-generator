<script setup lang="ts">
import {ShaplaButton, ShaplaTable, ShaplaTablePagination} from "@shapla/vue-components";
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {InstagramAttemptLogInterface} from "../../../utils/interfaces";
import {onMounted, reactive} from "vue";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";

const crud = new CrudOperation('admin/instagram-log', http)

const state = reactive({
  items: [],
  selectedItems: [],
  pagination: {total_items: 0, per_page: 50, current_page: 1, total_pages: 0},
})

const columns = [
  {label: 'Message', key: 'message'},
  {label: 'Type', key: 'type'},
  {label: 'Suggestion', key: 'suggestion'},
  {label: 'Datetime', key: 'created_at'},
];

const getItemsCollection = () => {
  const params = {
    page: state.pagination.current_page,
    per_page: state.pagination.per_page,
    log_for: 'twitter'
  };
  crud.getItems(params).then(data => {
    state.items = data.items as InstagramAttemptLogInterface[];
    state.pagination = data.pagination;
  })
}

const onItemSelect = (ids: number[]) => {
  state.selectedItems = ids;
}

const paginate = (page: number) => {
  state.pagination.current_page = page;
  getItemsCollection();
}

onMounted(() => {
  getItemsCollection();
})
</script>

<template>
  <div>
    <div class="my-2 flex justify-end">
      <ShaplaButton size="small" @click="getItemsCollection">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current w-6 h-6">
          <path d="M0 0h24v24H0V0z" fill="none"/>
          <path
              d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
        </svg>
        <span>Refresh</span>
      </ShaplaButton>
    </div>
    <div>
      <div class="mb-4">
        <ShaplaTablePagination
            :per-page="state.pagination.per_page"
            :current-page="state.pagination.current_page"
            :total-items="state.pagination.total_items"
            @paginate="paginate"
        />
      </div>
      <ShaplaTable
          :items="state.items"
          :columns="columns"
          :selected-items="state.selectedItems"
          :show-expand="true"
          @select:item="onItemSelect"
      >
        <template v-slot:created_at="data">
          {{ formatISO8601DateTime(data.row.created_at) }}
        </template>
        <template v-slot:cellExpand="data">
          <div v-for="(value, label) in data.row" class="flex mb-1">
            <div class="w-[100px]">{{ label }}</div>
            <div class="flex-grow">{{ value }}</div>
          </div>
        </template>
      </ShaplaTable>
    </div>
  </div>
</template>
