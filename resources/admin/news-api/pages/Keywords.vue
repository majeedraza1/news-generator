<script setup lang="ts">
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {Dialog, ShaplaButton, ShaplaTable} from "@shapla/vue-components";
import {ExistingKeywordInterface, KeywordInterface} from "../../../utils/interfaces";
import AddOrEditKeywordModal from "@/admin/news-api/components/AddOrEditKeywordModal.vue";

const crud = new CrudOperation('admin/keywords', http);

const state = reactive<{
  openAddNewModal: boolean;
  keyword: KeywordInterface;
  keywords: KeywordInterface[];
  pagination: PaginationDataInterface;
  selectedKeywords: number[];
  activeKeyword: ExistingKeywordInterface | null;
  openEditModal: boolean;
}>({
  openAddNewModal: false,
  keyword: {
    keyword: '',
    instruction: '',
  },
  keywords: [],
  selectedKeywords: [],
  pagination: {per_page: 20, current_page: 1, total_items: 0, total_pages: 1},
  activeKeyword: null,
  openEditModal: false,
})

const getItems = (page = 1) => {
  crud.getItems({
    per_page: state.pagination.per_page,
    page: page
  }).then(data => {
    state.keywords = data.items as KeywordInterface[];
    state.pagination = data.pagination;
  })
}

const submitNewKeyword = (keyword: KeywordInterface) => {
  crud.createItem(keyword).then(() => {
    getItems();
    closeAddNewModal();
  })
}

const updateKeyword = (keyword: ExistingKeywordInterface) => {
  crud.updateItem(keyword.id, keyword).then(() => {
    getItems();
    closeEditModal();
  })
}

const closeAddNewModal = () => {
  state.openAddNewModal = false;
}

const closeEditModal = () => {
  state.openEditModal = false;
  state.activeKeyword = null;
}

const onSelect = (selected: number[]) => {
  state.selectedKeywords = selected;
}

const deleteKeyword = (id: number) => {
  Dialog.confirm('Are you sure to delete?').then(confirmed => {
    if (confirmed) {
      crud.deleteItem(id).then(() => {
        getItems();
      })
    }
  })
}

const onClickAction = (action: string, row: ExistingKeywordInterface) => {
  if ('delete' === action) {
    deleteKeyword(row.id);
  }
  if ('edit' === action) {
    state.activeKeyword = row;
    state.openEditModal = true;
  }
}

const deleteSelectedKeywords = () => {
  Dialog.confirm('Are you sure to delete all selected keywords?').then(confirmed => {
    if (confirmed) {
      crud.batch('delete', state.selectedKeywords).then(() => {
        state.selectedKeywords = [];
        getItems();
      })
    }
  })
}

onMounted(() => {
  getItems();
})
</script>

<template>
  <h1 class="wp-heading-inline">Keywords</h1>
  <hr class="wp-header-end">
  <div class="all-border-box">
    <div class="flex mb-2 space-x-2">
      <div class="flex-grow"></div>
      <ShaplaButton v-if="state.selectedKeywords.length" theme="error" size="small" @click="deleteSelectedKeywords">
        Delete
      </ShaplaButton>
      <ShaplaButton theme="primary" size="small" outline @click="() => getItems()">Refresh</ShaplaButton>
    </div>
    <ShaplaTable
        :columns="[{key:'keyword',label:'Keyword'},{key:'instruction',label:'Instruction'}]"
        :actions="[{key:'edit',label:'Edit'},{key:'delete',label:'Delete'}]"
        :items="state.keywords"
        :selected-items="state.selectedKeywords"
        @click:action="onClickAction"
        @select:item="onSelect"
    >
      <template v-slot:instruction="data">
        <span v-if="data.row.instruction">{{ data.row.instruction }}</span>
        <span v-else class="text-gray-300">using global instruction</span>
      </template>
    </ShaplaTable>
  </div>
  <AddOrEditKeywordModal
      v-if="state.openAddNewModal"
      :active="true"
      :keyword="state.keyword"
      @close="closeAddNewModal"
      @submit="submitNewKeyword"
  />
  <AddOrEditKeywordModal
      v-if="state.openEditModal"
      :active="true"
      :keyword="state.activeKeyword"
      @close="closeEditModal"
      @submit="updateKeyword"
  />
  <div class="is-fixed bottom-4 right-4">
    <ShaplaButton fab theme="primary" size="large" @click="state.openAddNewModal = true">+</ShaplaButton>
  </div>
</template>
