<template>
	<div>
		<h1 class="wp-heading-inline">News</h1>
		<hr class="wp-header-end">
		<div>
			<div class="flex mb-4 space-x-4">
				<div class="flex-grow"></div>
				<ShaplaButton v-if="state.selectedItems.length" theme="secondary" size="small"
							  @click.prevent="addToInterestingFilter">
					News Filtering
				</ShaplaButton>
				<ShaplaButton v-if="state.selectedItems.length" theme="error" size="small" outline
							  @click.prevent="deleteSelectedNews">
					Delete Selected
				</ShaplaButton>
				<ShaplaButton v-if="state.selectedItems.length" theme="primary" size="small"
							  @click.prevent="filterData">
					Re-Create with OpenAI
				</ShaplaButton>
				<ShaplaButton size="small" @click="getNewsCollection">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current w-6 h-6">
						<path d="M0 0h24v24H0V0z" fill="none"/>
						<path
							d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
					</svg>
					<span>Refresh</span>
				</ShaplaButton>
			</div>
			<div class="mb-4 space-x-4 flex justify-end items-center">
				<ShaplaSearchForm
					@search="onSearch"
				/>
			</div>
			<div class="mb-4 space-x-4 flex justify-between items-center">
				<ShaplaSelect
					label="Category"
					:options="state.categories"
					v-model="state.filter.category"
					label-key="label"
					value-key="slug"
				/>
				<ShaplaButton theme="primary" outline v-if="state.has_filter" size="small" @click.prevent="clearFilter">
					Clear Filter
				</ShaplaButton>
				<div class="flex-grow"></div>
				<ShaplaTablePagination
					:total-items="state.total_items"
					:per-page="state.per_page"
					:current-page="state.current_page"
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
				<template v-slot:source_name="data">
					<a :href="data.row.source_uri" target="_blank">{{ data.row.source_title }}</a>
				</template>
				<template v-slot:category="data">
					<template v-for="_cat in state.categories">
						<template v-if="_cat.value === data.row.category">{{ _cat.label }}</template>
					</template>
				</template>
				<template v-slot:image="data">
					<ShaplaImage v-if="data.row.image" container-width="32px" container-height="32px">
						<img :src="data.row.image" alt="" loading="lazy">
					</ShaplaImage>
				</template>
				<template v-slot:title="data">
					<div class="whitespace-nowrap overflow-ellipsis overflow-hidden max-w-xs" :title="data.row.title">
						{{ data.row.title }}
					</div>
				</template>
				<template v-slot:news_datetime="data">
					{{ formatDate(data.row.news_datetime) }}
				</template>
				<template v-slot:news_filtering="data">
					{{ data.row.news_filtering ? 'yes' : '-' }}
				</template>
				<template v-slot:sync_datetime="data">
					{{ data.row.sync_datetime ? formatDate(data.row.sync_datetime) : '-' }}
				</template>
				<template v-slot:openai_news_id="data">
					<template v-if="data.row.openai_error.length">
						<span :title="data.row.openai_error" class="text-red-600 cursor-help">Error</span>
					</template>
					<template v-else>
						<template v-if="data.row.openai_news_id > 0">Sync</template>
						<template v-else>
							<template v-if="state.pending_openai_request.includes(data.row.id)">Pending</template>
							<template v-else>-</template>
						</template>
					</template>
				</template>
			</ShaplaTable>
			<div class="mt-4">
				<ShaplaTablePagination
					:total-items="state.total_items"
					:per-page="state.per_page"
					:current-page="state.current_page"
					@paginate="paginate"
				/>
			</div>
		</div>
	</div>
	<ShaplaModal v-if="state.showViewModal" :active="state.showViewModal" @close="state.showViewModal = false"
				 title="News Details" content-size="full">
		<ArticleDetails :article="state.activeItem"/>
	</ShaplaModal>
</template>

<script lang="ts" setup>
import {
	Dialog,
	Notify,
	ShaplaButton,
	ShaplaImage,
	ShaplaModal,
	ShaplaSearchForm,
	ShaplaSelect,
	ShaplaTable,
	ShaplaTablePagination,
	Spinner
} from '@shapla/vue-components'
import {formatISO8601Date} from "../../../utils/humanTimeDiff";
import {onMounted, reactive, watch} from "vue";
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import ArticleDetails from "../components/ArticleDetails.vue";
import {ArticleInterface} from "../../../utils/interfaces";


const crud = new CrudOperation('admin/news', http);
const state = reactive({
	categories: [],
	countries: [],
	pending_openai_request: [],
	items: [],
	per_page: 100,
	current_page: 1,
	total_items: 0,
	selectedItems: [],
	filter: {
		category: '',
		show_in_sync: false,
	},
	has_filter: false,
	showViewModal: false,
	activeItem: {},
	activeItemId: 0,
	showOpenAiModal: false,
	showAiCreateButton: false,
	search: '',
});
const columns = [
	{key: 'title', label: 'Title'},
	{key: 'category', label: 'Category'},
	{key: 'news_filtering', label: 'In Filter'},
	{key: 'body_words_count', label: 'Words Count'},
	{key: 'openai_news_id', label: 'OpenAI Status'},
	{key: 'image', label: 'Image'},
	{key: 'news_datetime', label: 'News Datetime'},
	{key: 'sync_datetime', label: 'Sync Datetime'},
	// {key: 'source_title', label: 'Source'},
];

const actions = [
	{key: 'view', label: 'View'},
	{key: 'sync_openai', label: 'Re-create with OpenAI'},
];

const handleOpenAiStatus = (_value: boolean) => {
	state.showAiCreateButton = _value
}

const saveAiNews = () => {
	Spinner.activate();
	http
		.post(`admin/news/${state.activeItemId}/openai`, {force: true})
		.then(response => {
			const data = response.data.data;
			if (data.openai_news_id && data.openai_news) {
				state.activeItem = {
					...state.activeItem,
					openai_news_id: data.openai_news_id,
					openai_news: data.openai_news
				}
				Notify.success('News sync with OpenAI', 'Success!');
			}
			if (data.pending_tasks) {
				state.pending_openai_request = data.pending_tasks;
				Notify.primary('A Background task is running to sync with OpenAI', 'Info!')
			}
			getNewsCollection();
		})
		.catch(() => {
			Notify.error('Something went wrong. Please try again.', 'Error!');
		})
		.finally(() => {
			Spinner.deactivate()
		})
}

const getNewsCollection = () => {
	const params = {
		page: state.current_page,
		per_page: state.per_page,
		in_sync: state.filter.show_in_sync ? 1 : 0,
		search: state.search
	};
	if (state.filter.category) {
		params['category'] = state.filter.category;
	}
	crud.getItems(params).then(data => {
		state.categories = data.categories as { label: string; value: string }[];
		state.items = data.items;
		state.pending_openai_request = data.pending_openai_request as number[];
		state.per_page = data.pagination.per_page;
		state.total_items = data.pagination.total_items;
	})
}

const getSingleItem = (id: number) => {
	crud.getItem(id).then(data => {
		state.activeItem = data;
	})
}

const deleteSelectedNews = () => {
	Dialog.confirm('Are you sure to delete selected news').then(confirmed => {
		if (confirmed) {
			http.post('admin/news/batch', {action: 'delete', ids: state.selectedItems}).then(() => {
				Notify.success('Selected news have been deleted.', 'Success!');
				state.selectedItems = [];
				getNewsCollection();
			}).catch(error => {
				if (error.response.data.message) {
					Notify.error(error.response.data.message, 'Error!');
				}
			}).finally(() => {
				Spinner.deactivate();
			})
		}
	})
}

const addToInterestingFilter = () => {
	Dialog.confirm('Are you sure to add selected news to interesting filtering?').then(confirmed => {
		if (confirmed) {
			http.post('admin/news/batch', {action: 'interesting-filter', ids: state.selectedItems}).then(() => {
				Notify.success('Selected news have been added to interesting filter.', 'Success!');
				state.selectedItems = [];
			}).catch(error => {
				if (error.response.data.message) {
					Notify.error(error.response.data.message, 'Error!');
				}
			}).finally(() => {
				Spinner.deactivate();
			})
		}
	})
}

function filterData() {
	Dialog.confirm('Are you sure to re-created with OpenAI?').then(confirmed => {
		if (confirmed) {
			Spinner.activate();
			http.post('admin/news/openai-recreate', {ids: state.selectedItems}).then(() => {
				Notify.success('Selected news have been added to background task to sync with OpenAI', 'Success');
				state.selectedItems = [];
			}).catch(error => {
				if (error.response.data.message) {
					Notify.error(error.response.data.message, 'Error!');
				}
			}).finally(() => {
				Spinner.deactivate();
			})
		}
	})
}

function clearFilter() {
	state.filter.category = '';
	state.has_filter = false;
	getNewsCollection();
}

const onItemSelect = (ids: number[]) => {
	state.selectedItems = ids;
}

function formatDate(from) {
	return formatISO8601Date(from);
}

function paginate(page) {
	state.current_page = page;
	getNewsCollection();
}

const onActionClick = (action: string, data: ArticleInterface) => {
	if ('view' === action) {
		onRowClick(data);
	}
	if ('sync_openai' === action) {
		if (data.openai_news_id) {
			Notify.error('Already in sync', 'Info!');
		} else if (state.pending_openai_request.includes(data.id)) {
			Notify.error('Already in sync queue', 'Info!');
		} else {
			Dialog.confirm('Are you to sync with OpenAI?').then(confirmed => {
				if (confirmed) {
					state.activeItemId = data.id;
					state.activeItem = data;
					saveAiNews();
				}
			})
		}
	}
	if ('openai' === action) {
		state.showOpenAiModal = true;
		state.activeItemId = data.id;
		state.activeItem = data;

		getSingleItem(data.id);
	}
}

const onSearch = (value: string) => {
	state.search = value;
	getNewsCollection();
}

const onRowClick = (data) => {
	state.showViewModal = true;
	state.activeItemId = data.id;
	state.activeItem = data;

	getSingleItem(data.id);
}

watch(() => state.filter.category, () => {
	getNewsCollection();
})

onMounted(() => {
	getNewsCollection();
})
</script>
