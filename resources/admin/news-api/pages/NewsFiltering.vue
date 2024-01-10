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
				:actions="actions"
				:selected-items="state.selectedItems"
				@select:item="onItemSelect"
				@click:action="onActionClick"
			>
				<template v-slot:raw_news_ids="data">{{ data.row.raw_news_ids.length }}</template>
				<template v-slot:created_at="data">{{ formatISO8601Date(data.row.created_at) }}</template>
			</ShaplaTable>
			<div class="mt-4">
				<ShaplaTablePagination
					:per-page="state.pagination.per_page"
					:current-page="state.pagination.current_page"
					:total-items="state.pagination.total_items"
					@paginate="paginate"
				/>
			</div>
		</div>
	</div>
	<ShaplaModal v-if="state.showViewModel && state.activeItem" :active="state.showViewModel"
				 @close="state.showViewModel = false" :title="`Filter Log #${state.activeItem.id}`" content-size="full">
		<table class="form-table">
			<tr>
				<th>Instruction</th>
				<td v-html="state.activeItem.openai_api_instruction"></td>
			</tr>
			<tr>
				<th>Open AI Response</th>
				<td v-html="state.activeItem.openai_api_response"></td>
			</tr>
			<tr>
				<th>News</th>
				<td>
					<ShaplaTable
						:items="state.source_news"
						:columns="columns2"
						:show-cb="false"
						:selected-items="state.activeItem.suggested_news_ids"
					>
						<template v-slot:title="data">
							<div class="whitespace-nowrap overflow-ellipsis overflow-hidden max-w-sm"
								 :title="data.row.title">
								{{ data.row.title }}
							</div>
						</template>
					</ShaplaTable>
				</td>
			</tr>
			<tr>
				<th>OpenAI News</th>
				<td>
					<ShaplaTable
						:items="state.openai_news"
						:columns="columns2"
						:show-cb="false"
					>
						<template v-slot:title="data">
							<div class="whitespace-nowrap overflow-ellipsis overflow-hidden max-w-sm"
								 :title="data.row.title">
								{{ data.row.title }}
							</div>
						</template>
					</ShaplaTable>
				</td>
			</tr>
			<tr>
				<th>Category</th>
				<td>{{ state.activeItem.primary_category }}</td>
			</tr>
			<tr>
				<th>Sync Settings</th>
				<td>
					<div class="bg-gray-100 p-2">
						<div>
							<div v-for="(value,key) in state.activeItem.sync_settings" class="flex space-x-4 mb-2">
								<div class="font-bold lg:min-w-[180px]">{{ key }}</div>
								<div>{{ value }}</div>
							</div>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</ShaplaModal>
</template>

<script setup lang="ts">
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {computed, onMounted, reactive} from "vue";
import {Notify, ShaplaButton, ShaplaModal, ShaplaTable, ShaplaTablePagination} from "@shapla/vue-components";
import {formatISO8601Date} from "../../../utils/humanTimeDiff";
import {ArticleInterface, InterestingNewsFilterInterface, OpenAiNewsInterface} from "../../../utils/interfaces";

const crud = new CrudOperation('admin/news-filtering', http);
const state = reactive<{
	items: InterestingNewsFilterInterface[],
	selectedItems: number[],
	pagination: PaginationDataInterface,
	activeItem: InterestingNewsFilterInterface,
	source_news: ArticleInterface[],
	openai_news: OpenAiNewsInterface[],
	showViewModel: boolean,
}>({
	items: [],
	selectedItems: [],
	pagination: {total_items: 0, per_page: 20, current_page: 1, total_pages: 0},
	activeItem: undefined,
	source_news: [],
	openai_news: [],
	showViewModel: false,
})

const columns = [
	{label: 'Setting', key: 'setting_title'},
	{label: 'Created', key: 'created_at'},
	{label: 'Category', key: 'primary_category'},
	{label: 'Total News in Batch', key: 'raw_news_ids', numeric: true},
	{label: 'Total Suggested', key: 'total_suggested_news', numeric: true},
	{label: 'Total Re-Created', key: 'total_recreated_news', numeric: true},
];

const columns2 = [
	{label: 'News Title', key: 'title'},
	{label: 'Words Count', key: 'body_words_count', numeric: true},
];

const actions = [
	{key: 'view', label: 'View'},
	{key: 'recalculate', label: 'Re-Calculate'},
	{key: 'recreate', label: 'Re-Create (OpenAI)'},
	{key: 'debug', label: 'Debug'},
];

const ajaxUrl = computed<string>(() => window.StackonetNewsGenerator.ajaxUrl);

const onItemSelect = (ids: number[]) => {
	state.selectedItems = ids;
}

const getItemsCollection = () => {
	const params = {
		page: state.pagination.current_page,
		per_page: state.pagination.per_page,
	};
	crud.getItems(params).then(data => {
		state.items = data.items as InterestingNewsFilterInterface[];
		state.pagination = data.pagination;
	})
}

const reCalculateItem = (id: number) => {
	crud.post(`admin/news-filtering/${id}/recalculate`).then(() => {
		Notify.success('All information has been recalculated.', 'Success!');
		getItemsCollection();
	})
}

const reCreateItem = (id: number) => {
	crud.post(`admin/news-filtering/${id}/recreate`).then(() => {
		Notify.success('A background task is running to recreate news with OpenAI.', 'Success!');
		getItemsCollection();
	})
}

const onActionClick = (action: string, data) => {
	if ('view' === action) {
		crud.getItem(data.id).then((_data) => {
			state.activeItem = _data.item as InterestingNewsFilterInterface;
			state.source_news = _data.source_news as ArticleInterface[];
			state.openai_news = _data.openai_news as OpenAiNewsInterface[];
			state.showViewModel = true;
		})
	}
	if ('recalculate' === action) {
		reCalculateItem(data.id);
	}
	if ('recreate' === action) {
		reCreateItem(data.id);
	}
	if ('debug' === action) {
		const url = new URL(window.StackonetNewsGenerator.ajaxUrl);
		url.searchParams.append('action', 'debug_interesting_news');
		url.searchParams.append('news_id', data.id.toString());
		const aTag = document.createElement('a');
		aTag.target = '_blank';
		aTag.href = url.toString();
		aTag.click();
		aTag.remove();
	}
}

const paginate = (page: number) => {
	state.pagination.current_page = page;
	getItemsCollection();
}

onMounted(() => {
	getItemsCollection();
})
</script>
