<template>
  <div>
    <h1 class="wp-heading-inline">External Links</h1>
    <hr class="wp-header-end">
    <div class="flex justify-end mb-2 space-x-2">
      <ShaplaButton theme="secondary" size="small" outline @click="state.showCsvUploadModal = true">Upload CSV
      </ShaplaButton>
      <ShaplaButton theme="primary" size="small" @click="state.showNewItemModal = true">Add New</ShaplaButton>
    </div>
    <div class="flex justify-end mb-2">
      <ShaplaSearchForm v-model="state.search" @search="onSearch"/>
    </div>
    <div class="flex justify-end mb-2">
      <ShaplaTablePagination
          :total-items="state.pagination.total_items"
          :per-page="state.pagination.per_page"
          :current-page="state.pagination.current_page"
      />
    </div>
    <div>
      <ShaplaTable
          :columns="columns"
          :items="state.items"
          :actions="actions"
          @click:action="onActionClick"
      />
    </div>
    <ShaplaModal :active="state.showNewItemModal" @close="state.showNewItemModal = false" title="Add New Link">
      <div class="mb-2">
        <ShaplaInput label="Title" v-model="state.newItem.name"/>
      </div>
      <div>
        <ShaplaInput
            label="Url"
            type="url"
            v-model="state.newItem.link"
        />
      </div>
      <template v-slot:foot>
        <ShaplaButton theme="primary" @click="saveNewLink">Save</ShaplaButton>
      </template>
    </ShaplaModal>
    <ShaplaModal v-if="state.editItem && Object.keys(state.editItem).length" :active="state.showEditItemModal"
                 @close="state.showEditItemModal = false" title="Edit Link">
      <div class="mb-2">
        <ShaplaInput label="Title" v-model="state.editItem.name"/>
      </div>
      <div>
        <ShaplaInput
            label="Url"
            type="url"
            v-model="state.editItem.link"
        />
      </div>
      <template v-slot:foot>
        <ShaplaButton theme="primary" @click="updateItem">Save</ShaplaButton>
      </template>
    </ShaplaModal>
    <ShaplaModal :active="state.showCsvUploadModal" @close="state.showCsvUploadModal = false" title="Upload CSV">
      <form @submit="onSubmitCsvForm" class="flex items-center space-x-4">
        <div class="w-2/3">
          <input type="file" id="csvFile" accept=".csv"/>
        </div>
        <div class="w-1/3">
          <input type="submit" value="Read File" class="button"/>
        </div>
      </form>
      <div v-if="state.csv_items.length" class="mt-4">
        <div class="shapla-data-table-container">
          <table class="shapla-data-table">
            <thead>
            <tr class="shapla-data-table__header-row">
              <th v-for="_name in Object.keys(state.csv_items[0])" class="shapla-data-table__header-cell">{{
                  _name
                }}
              </th>
              <th class="shapla-data-table__header-cell">Actions</th>
            </tr>
            </thead>
            <tbody class="shapla-data-table__content">
            <tr class="shapla-data-table__row is-selected">
              <td v-for="_name in Object.keys(state.csv_items[0])" class="shapla-data-table__row">
                <div class="w-full">
                  <select class="w-full" v-model="state.csv_field_map[_name]">
                    <option value="name">Title</option>
                    <option value="link">Link</option>
                  </select>
                </div>
              </td>
              <td class="shapla-data-table__row"></td>
            </tr>
            <tr v-for="(_items, index) in state.csv_items" class="shapla-data-table__row">
              <td v-for="_item in _items" class="shapla-data-table__cell">{{ _item }}</td>
              <td class="shapla-data-table__cell">
                <ShaplaCross @click="deleteCsvRow(index)"/>
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
      <template v-slot:foot>
        <ShaplaButton theme="primary" @click="uploadCsvToServer" :disabled="!canUploadCsv">Upload to Server
        </ShaplaButton>
      </template>
    </ShaplaModal>
  </div>
</template>

<script setup lang="ts">
import {computed, onMounted, reactive, ref} from "vue";
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {
  Dialog,
  ShaplaButton,
  ShaplaCross,
  ShaplaInput,
  ShaplaModal,
  ShaplaSearchForm,
  ShaplaTable,
  ShaplaTablePagination
} from "@shapla/vue-components";

const csv_form = ref(null);

const crud = new CrudOperation('external-links', http);

interface ExternalLinkInterface {
  id?: number;
  name: string;
  link: string
}

const state = reactive<{
  items: ExternalLinkInterface[],
  csv_items: Record<string, string>[],
  csv_field_map: Record<string, string>
  pagination: PaginationDataInterface,
  showNewItemModal: boolean,
  showCsvUploadModal: boolean,
  newItem: ExternalLinkInterface;
  showEditItemModal: boolean,
  editItem: null | ExternalLinkInterface;
  search: string;
}>({
  items: [],
  pagination: {per_page: 50, current_page: 1, total_items: 0, total_pages: 1},
  showNewItemModal: false,
  newItem: {
    name: '',
    link: ''
  },
  search: '',
  showEditItemModal: false,
  editItem: null,
  showCsvUploadModal: false,
  csv_items: [],
  csv_field_map: {}
})

const columns = [
  {label: 'Title', key: 'name'},
  {label: 'Link', key: 'link'},
];

const actions = [
  {label: 'Edit', key: 'edit'},
  {label: 'Delete', key: 'delete'},
];

const canUploadCsv = computed(() => {
  if (state.csv_items.length < 1) {
    return false
  }
  if (Object.keys(state.csv_field_map).length !== (Object.values(state.csv_field_map).filter(n => n.toString().length === 4)).length) {
    return false;
  }
  return true;
})

const onActionClick = (action: string, item: ExternalLinkInterface) => {
  if ('edit' === action) {
    state.editItem = item;
    state.showEditItemModal = true;
  }
  if ('delete' === action) {
    Dialog.confirm('Are you sure to delete this item?').then(confirmed => {
      if (confirmed) {
        crud.deleteItem(item.id).then(() => {
          getItems();
        });
      }
    })
  }
}

const getItems = () => {
  crud.getItems({
    page: state.pagination.current_page,
    per_page: state.pagination.per_page,
    search: state.search
  }).then((data) => {
    state.items = data.items as ExternalLinkInterface[];
    state.pagination = data.pagination as PaginationDataInterface;
  })
}

const onSearch = (value: string) => {
  state.search = value
  getItems();
}

const saveNewLink = () => {
  crud.createItem(state.newItem).then(response => {
    state.showNewItemModal = false;
    state.newItem = {name: '', link: ''}
    state.items.unshift(response as ExternalLinkInterface);
  })
}

const updateItem = () => {
  crud.updateItem(state.editItem.id, {name: state.editItem.name, link: state.editItem.link}).then(() => {
    state.showEditItemModal = false;
    state.editItem = null;
    getItems();
  })
}

const uploadCsvToServer = () => {
  if (state.csv_items.length < 1) {
    return;
  }

  const objectKeys = Object.keys(state.csv_field_map);
  const nameKey = objectKeys.find(key => state.csv_field_map[key] === 'name');
  const linkKey = objectKeys.find(key => state.csv_field_map[key] === 'link');
  const items = state.csv_items.map(item => ({name: item[nameKey], link: item[linkKey]}))

  crud.batch('create', items).then(() => {
    state.showCsvUploadModal = false;
    state.csv_field_map = {};
    state.csv_items = [];
    getItems();
  })
}

const deleteCsvRow = (index: number) => {
  if (state.csv_items) {
    Dialog.confirm('Are you sure to delete CSV row?').then(confirmed => {
      if (confirmed) {
        state.csv_items.splice(index, 1);
      }
    })
  }
}

const csvToArray = (str: string, delimiter: string = ",") => {
  // slice from start of text to the first \n index
  // use split to create an array from string by delimiter
  const headers = str.slice(0, str.indexOf("\n")).split(delimiter);

  // slice from \n index + 1 to the end of the text
  // use split to create an array of each csv value row
  const rows = str.slice(str.indexOf("\n") + 1).split("\n");

  // Map the rows
  // split values from each row into an array
  // use headers.reduce to create an object
  // object properties derived from headers:values
  // the object passed as an element of the array
  const arr = rows.map(function (row) {
    const values = row.split(delimiter);
    const el = headers.reduce(function (object, header, index) {
      object[header] = values[index];
      return object;
    }, {});
    return el;
  });

  // return the array
  return arr;
}

const onSubmitCsvForm = (event: SubmitEvent) => {
  event.preventDefault();
  const form = event.target as HTMLFormElement;
  const inputFile = form.querySelector('[type=file]') as HTMLInputElement;
  const reader = new FileReader();

  reader.onload = function (e) {
    const text = csvToArray(e.target.result as string);
    state.csv_items = text;
    state.csv_field_map = JSON.parse(JSON.stringify(text[0]));
  };

  reader.readAsText(inputFile.files[0]);
}

onMounted(() => {
  getItems();
})
</script>
