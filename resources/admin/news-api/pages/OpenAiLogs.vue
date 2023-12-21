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
        <ShaplaSearchForm
            @search="searchItems"
        />
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
      <template v-slot:response_type="data">
        <div class="text-red-600" v-if="'error' === data.row.response_type">
          Error
        </div>
        <div v-else>Success</div>
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
      <tr>
        <th>ID</th>
        <td>{{ state.activeItem.id }}</td>
      </tr>
      <tr>
        <th>OpenAi Model</th>
        <td>{{ state.activeItem.model }}</td>
      </tr>
      <tr>
        <th>Success/Error</th>
        <td>{{ state.activeItem.response_type }}</td>
      </tr>
      <tr>
        <th>Total time</th>
        <td>{{ state.activeItem.total_time }}</td>
      </tr>
      <tr>
        <th>Token used</th>
        <td>{{ state.activeItem.total_tokens }}</td>
      </tr>
      <tr>
        <th>Group</th>
        <td>{{ state.activeItem.group }}</td>
      </tr>
      <tr>
        <th>Source Type</th>
        <td>{{ state.activeItem.source_type }}</td>
      </tr>
      <tr>
        <th>Source ID</th>
        <td>{{ state.activeItem.source_id }}</td>
      </tr>
      <tr>
        <th>Datetime</th>
        <td>{{ state.activeItem.created_at }}</td>
      </tr>
      <tr>
        <th>Instruction</th>
        <td>
          <pre><code>{{ state.activeItem.instruction }}</code></pre>
        </td>
      </tr>
      <tr>
        <th>Response from OpenAI</th>
        <td>
          <pre><code>{{ state.activeItem.api_response }}</code></pre>
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
import {OpenAiResponseInterface} from "../../../utils/interfaces";

const crud = new CrudOperation('openai-logs', http);
const state = reactive<{
  items: OpenAiResponseInterface[];
  pagination: PaginationDataInterface;
  activeItem: OpenAiResponseInterface;
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
  {label: 'Group', key: 'group'},
  {label: 'Error/Success', key: 'response_type'},
  {label: 'Source', key: 'source_type'},
  {label: 'Source ID', key: 'source_id'},
  {label: 'Response Time (seconds)', key: 'total_time'},
  {label: 'Token used', key: 'total_tokens'},
  {label: 'Datetime', key: 'created_at'},
];

const actions = [
  {label: 'View', key: 'view'},
  {label: 'Debug', key: 'debug'},
];


const decodeHtml = (html: string) => {
  const txt = document.createElement("textarea");
  txt.innerHTML = html;
  return txt.value;
}

const onActionClick = (action: string, item: OpenAiResponseInterface) => {
  if ('view' === action) {
    state.showViewItemModal = true;
    state.activeItem = item;
  }
  if ('debug' === action) {
    const a = document.createElement('a');
    a.setAttribute('target', '_blank');
    a.setAttribute('href', decodeHtml(item.debug_url));
    a.click();
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
    state.items = data.items as OpenAiResponseInterface[];
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
