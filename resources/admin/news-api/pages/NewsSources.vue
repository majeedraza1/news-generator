<script setup lang="ts">
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {
	Dialog, ShaplaButton, ShaplaCheckbox, ShaplaColumn, ShaplaColumns, ShaplaModal, ShaplaTable, ShaplaTablePagination,
} from "@shapla/vue-components";
import {PaginationDataInterface} from "../../../utils/CrudOperation";
import {SourceInterface, NewsSourceInterface} from "../../../utils/interfaces";
import SourceBox from "../components/SourceBox.vue";
import CrudOperation from "../../../utils/CrudOperation";

const crud = new CrudOperation('admin/news-sources', http);

const state = reactive<{
	search_sources: SourceInterface[],
	sources: NewsSourceInterface[],
	pagination: PaginationDataInterface;
	selectedItems: number[],
	openAddNewModal: boolean;
	openEditModal: boolean;
	search: string;
	activeSource?: NewsSourceInterface;
	spinner: boolean;
}>({
	search_sources: [],
	sources: [],
	pagination: {total_items: 0, current_page: 1, per_page: 100, total_pages: 0,},
	selectedItems: [],
	openAddNewModal: false,
	openEditModal: false,
	search: '',
	activeSource: null,
	spinner: false
})

const tableColumns = [
	{label: 'Title', key: 'title'},
	{label: 'URI', key: 'uri'},
	{label: 'Copy Image', key: 'copy_image'},
];

const actions = [
	{label: 'Edit', key: 'edit'},
	{label: 'Delete', key: 'delete'},
];

const onItemSelect = (selectedItems: number[]) => {
	state.selectedItems = selectedItems;
}

const closeEditModel = () => {
	state.activeSource = null;
	state.openEditModal = false;
}

const onActionClick = (action: string, item: NewsSourceInterface) => {
	if ('edit' === action) {
		state.activeSource = item;
		state.openEditModal = true;
	}
	if ('delete' === action) {
		// Send http request to server to delete this item
		Dialog.confirm('Are you sure to delete the source?').then(confirmed => {
			if (confirmed) {
				crud.deleteItem(item.id).then(() => {
					state.sources.splice(state.sources.indexOf(item), 1);
				})
			}
		})
	}
}

const onUpdateSource = () => {
	crud.updateItem(state.activeSource.id, state.activeSource).then(data => {
		closeEditModel();
		getNewsSources();
	})
}

const chooseSource = (source: SourceInterface) => {
	crud.createItem({
		title: source.title,
		uri: source.uri,
		data_type: source.dataType,
	}).then((data: NewsSourceInterface) => {
		state.sources.unshift(data);
	});
}

const searchSource = () => {
	const value = state.search;
	if (value.length < 2) {
		state.search_sources = [];
		return;
	}
	state.spinner = true;
	http
		.get('settings/sources', {params: {prefix: value}})
		.then(response => {
			state.search_sources = response.data.data;
		})
		.catch(() => {
			state.search_sources = [];
		})
		.finally(() => {
			state.spinner = false;
		})
}
const getNewsSources = () => {
	const params = {
		page: state.pagination.current_page,
		per_page: state.pagination.per_page
	};
	crud.getItems(params).then(data => {
		state.sources = data.items as NewsSourceInterface[];
		state.pagination = data.pagination;
	})
}

const paginate = (page: number) => {
	state.pagination.current_page = page;
	getNewsSources();
}

const batchDelete = () => {
	Dialog.confirm('Are you sure to delete all selected news sources?').then(confirmed => {
		if (confirmed) {
			crud.batch('delete', state.selectedItems).then(() => {
				getNewsSources();
			})
		}
	})
}

const copyFromSyncSettings = () => {
	Dialog.confirm('Are you sure to copy news sources from sync settings?').then(confirmed => {
		if (confirmed) {
			crud.batch('copy').then(() => {
				getNewsSources();
			})
		}
	})
}

onMounted(() => {
	getNewsSources();
})
</script>

<template>
	<h1 class="wp-heading-inline">News Sources</h1>
	<hr class="wp-header-end">
	<div class="flex justify-end mb-2 space-x-2">
		<ShaplaButton theme="primary" size="small" outline @click="copyFromSyncSettings">
			Copy from Settings
		</ShaplaButton>
		<ShaplaButton :disabled="state.selectedItems.length < 1" theme="error" size="small" @click="batchDelete">
			Delete
		</ShaplaButton>
		<ShaplaButton theme="primary" size="small" @click="state.openAddNewModal = true">Add New</ShaplaButton>
	</div>
	<div>
		<div class="mb-2">
			<ShaplaTablePagination
				:total-items="state.pagination.total_items"
				:per-page="state.pagination.per_page"
				:current-page="state.pagination.current_page"
				@paginate="paginate"
			/>
		</div>
		<ShaplaTable
			:columns="tableColumns"
			:items="state.sources"
			:actions="actions"
			:selected-items="state.selectedItems"
			@select:item="onItemSelect"
			@click:action="onActionClick"
		>
			<template v-slot:copy_image="data">
				{{ data.row.copy_image ? 'Yes' : 'No' }}
			</template>
		</ShaplaTable>
		<div class="mt-4">
			<ShaplaTablePagination
				:total-items="state.pagination.total_items"
				:per-page="state.pagination.per_page"
				:current-page="state.pagination.current_page"
				@paginate="paginate"
			/>
		</div>
	</div>
	<ShaplaModal :active="state.openAddNewModal" @close="state.openAddNewModal = false" title="Add New Source">
		<ShaplaColumns multiline>
			<ShaplaColumn :tablet="10">
				<input type="search" v-model="state.search" class="w-full p-2">
			</ShaplaColumn>
			<ShaplaColumn :tablet="2">
				<ShaplaButton theme="primary" fullwidth @click="searchSource" :loading="state.spinner">Search
				</ShaplaButton>
			</ShaplaColumn>
			<ShaplaColumn :tablet="12">
				<div class="min-h-[300px]">
					<SourceBox
						v-for="_source in state.search_sources"
						:key="_source.uri"
						:source="_source"
					>
						<div class="mt-2">
							<ShaplaButton theme="primary" outline size="small" @click="chooseSource(_source)">Add to
								List
							</ShaplaButton>
						</div>
					</SourceBox>
				</div>
			</ShaplaColumn>
		</ShaplaColumns>
	</ShaplaModal>
	<ShaplaModal v-if="state.activeSource" :active="state.openEditModal" @close="closeEditModel" title="Edit Source">
		<ShaplaColumns multiline>
			<ShaplaColumn :tablet="12">
				<strong>{{ state.activeSource.title }}</strong>
			</ShaplaColumn>
			<ShaplaColumn :tablet="12">
				<strong>{{ state.activeSource.uri }}</strong>
			</ShaplaColumn>
			<ShaplaColumn>
				<ShaplaCheckbox label="Show Image" v-model="state.activeSource.copy_image"/>
			</ShaplaColumn>
		</ShaplaColumns>
		<template v-slot:foot>
			<ShaplaButton theme="primary" @click="onUpdateSource">Update</ShaplaButton>
		</template>
	</ShaplaModal>
</template>
