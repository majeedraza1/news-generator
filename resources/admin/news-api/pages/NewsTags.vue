<script setup lang="ts">
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {NewsTagInterface} from "../../../utils/interfaces";
import {ShaplaTable, ShaplaTablePagination} from "@shapla/vue-components";

const crud = new CrudOperation('openai/news-tags', http);

const state = reactive<{
  items: NewsTagInterface[];
  pagination: PaginationDataInterface;
  sortBy: string;
  sortOrder: 'asc' | 'desc';
}>({
  items: [],
  pagination: {total_items: 0, current_page: 1, per_page: 100, total_pages: 0,},
  sortBy: 'count',
  sortOrder: 'desc'
})

const columns = [
  {label: 'Name', key: 'name', sortable: true},
  {label: 'Meta Description', key: 'meta_description'},
  {label: 'Count', key: 'count', sortable: true, numeric: true},
];

const getNewsTags = () => {
  crud.getItems({
    page: state.pagination.current_page,
    per_page: state.pagination.per_page,
    sort: `${state.sortBy}+${state.sortOrder.toUpperCase()}`
  }).then(data => {
    state.items = data.items as NewsTagInterface[];
    state.pagination = data.pagination;
  })
}

const onPaginate = (page: number) => {
  state.pagination.current_page = page;
  getNewsTags();
}

const onSort = (column, order) => {
  state.sortBy = column;
  state.sortOrder = order;
  getNewsTags();
}

onMounted(() => {
  getNewsTags();
})

</script>

<template>
    <h1 class="wp-heading-inline">Tags</h1>
    <hr class="wp-header-end">
    <div>
        <div class="my-2">
            <ShaplaTablePagination
                    :current-page="state.pagination.current_page"
                    :total-items="state.pagination.total_items"
                    :per-page="state.pagination.per_page"
                    @paginate="onPaginate"
            />
        </div>
        <ShaplaTable
                :columns="columns"
                :items="state.items"
                :sort-by="state.sortBy"
                :sort-order="state.sortOrder"
                @click:sort="onSort"
        >
            <template v-slot:meta_description="data">
                <div class="lg:max-w-sm overflow-hidden overflow-ellipsis">{{ data.row.meta_description }}</div>
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
</template>

<style scoped lang="scss">

</style>