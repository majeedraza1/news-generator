<script setup lang="ts">
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {ShaplaButton, ShaplaInput, ShaplaModal, ShaplaTable} from "@shapla/vue-components";

const crud = new CrudOperation('admin/keywords', http);

interface KeywordInterface {
  keyword: string;
  instruction: string;
}

const state = reactive<{
  openAddNewModal: boolean;
  keyword: KeywordInterface;
  keywords: KeywordInterface[];
  pagination: PaginationDataInterface;
}>({
  openAddNewModal: false,
  keyword: {
    keyword: '',
    instruction: '',
  },
  keywords: [],
  pagination: {per_page: 20, current_page: 1, total_items: 0, total_pages: 1}
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

const submitNewKeyword = () => {
  crud.createItem(state.keyword).then(() => {
    getItems();
    closeAddNewModal();
  })
}

const closeAddNewModal = () => {
  state.openAddNewModal = false;
}

onMounted(() => {
  getItems();
})
</script>

<template>
  <h1 class="wp-heading-inline">Keywords</h1>
  <hr class="wp-header-end">
  <div class="all-border-box">
    <ShaplaTable
        :columns="[{key:'keyword',label:'Keyword'},{key:'instruction',label:'Instruction'}]"
        :items="state.keywords"
    >
      <template v-slot:instruction="data">
        <span v-if="data.row.instruction">{{ data.row.instruction }}</span>
        <span v-else class="text-gray-300">using global instruction</span>
      </template>
    </ShaplaTable>
  </div>
  <ShaplaModal :active="state.openAddNewModal" title="Add New Keyword" @close="closeAddNewModal">
    <div class="mb-2">
      <ShaplaInput label="Keyword" v-model="state.keyword.keyword"/>
    </div>
    <div class="mb-2">
      <ShaplaInput type="textarea" label="Instruction" v-model="state.keyword.instruction"/>
      <p class="description">
        <span>Leave it empty to use default/global instruction.</span><br>
        Remember to include the following line bottom of your instruction<br>
        Add [Title:], [Meta Description:] and [Content:] respectively when starting each section.
      </p>
    </div>
    <template v-slot:foot>
      <ShaplaButton theme="primary" @click="submitNewKeyword">Submit</ShaplaButton>
    </template>
  </ShaplaModal>
  <div class="is-fixed bottom-4 right-4">
    <ShaplaButton fab theme="primary" size="large" @click="state.openAddNewModal = true">+</ShaplaButton>
  </div>
</template>
