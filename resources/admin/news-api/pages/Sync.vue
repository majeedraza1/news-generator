<template>
	<h1 class="wp-heading-inline">Manual Sync</h1>
	<hr class="wp-header-end">
	<div>
		<ShaplaTabs alignment="center">
			<ShaplaTab name="News API" :selected="true">
				<div class="mb-4 flex justify-end">
					<ShaplaButton theme="success" @click="syncAllNewsNow">Sync All Settings</ShaplaButton>
				</div>
				<div v-for="setting in state.news_sync" class="shadow-2 bg-white rounded p-2 flex space-x-4 mb-2">
					<div class="w-2/3">
						<template v-for="(setting_value,setting_key) in setting">
							<div v-if="!blacklistFields.includes(setting_key) && setting_value" class="flex mb-1">
								<div class="lg:min-w-[180px]">{{ setting_key }}</div>
								<div class="font-bold">{{ setting_value }}</div>
							</div>
						</template>
					</div>
					<div class="w-1/3 flex justify-center items-center flex-col space-y-2">
						<ShaplaButton @click="queryInfo(setting)" theme="default" size="small" outline>Query Info
						</ShaplaButton>
						<ShaplaButton @click="syncNow(setting)" theme="primary" size="small" outline>Sync Now
						</ShaplaButton>
					</div>
				</div>
			</ShaplaTab>
			<ShaplaTab name="OpenAI API">
				<div class="shadow-2 bg-white rounded p-2 flex space-x-4 mb-2">
					<table class="form-table">
						<tr>
							<th>OpenAI sync</th>
							<td>
								<ShaplaButton @click="syncOldNews">Sync All</ShaplaButton>
								<p class="description">Sync old news that are not sync yet. This is a background
									task.</p>
							</td>
						</tr>
						<tr>
							<th>Sync for specific date</th>
							<td>
								<div class="flex items-center space-x-2">
									<input type="date" v-model="state.openai_sync_date">
									<ShaplaButton size="small" theme="primary"
												  :disabled="!state.openai_sync_date.length"
												  @click="syncForADate">Sync
									</ShaplaButton>
								</div>
								<p class="description">Choose a date to sync news.</p>
							</td>
						</tr>
					</table>
				</div>
			</ShaplaTab>
			<ShaplaTab name="News Tags">
				<NewsTagSyncSettings/>
			</ShaplaTab>
		</ShaplaTabs>

	</div>
	<ShaplaModal :active="state.openQueryInfoModal" @close="state.openQueryInfoModal = false" :show-card-footer="false"
				 title="Query Info">
		<div v-if="state.query_info && Object.keys(state.query_info).length">
			<div>
				<h2>GET Request</h2>
				<table class="form-table">
					<tr>
						<th>URL</th>
						<td>
							<div>
                                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
										state.query_info.get.url
									}}</code></pre>
							</div>
							<a :href="state.query_info.get.url" target="_blank">Open</a>
						</td>
					</tr>
				</table>
			</div>
			<div>
				<h2>POST request</h2>
				<table class="form-table">
					<tr>
						<th>URL</th>
						<td>
							<div>
                                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
										state.query_info.post.url
									}}</code></pre>
							</div>
						</td>
					</tr>
					<tr>
						<th>Body</th>
						<td>
							<div>
                                <pre class="whitespace-pre-wrap overflow-x-auto overflow-y-hidden max-w-lg"><code>{{
										state.query_info.post.body
									}}</code></pre>
							</div>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</ShaplaModal>
</template>

<script lang="ts" setup>
import CrudOperation from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {onMounted, reactive} from "vue";
import {Dialog, Notify, ShaplaButton, ShaplaModal, ShaplaTab, ShaplaTabs, Spinner} from "@shapla/vue-components";
import {NewsSyncQueryInfoInterface, NewsSyncSettingsInterface} from "../../../utils/interfaces";
import NewsTagSyncSettings from "../components/NewsTagSyncSettings.vue";

const crud = new CrudOperation('settings', http);

const state = reactive<{
	news_sync: NewsSyncSettingsInterface[];
	openai_unsync_items_count: number;
	query_info: NewsSyncQueryInfoInterface,
	openQueryInfoModal: boolean;
	openai_sync_date: string;
}>({
	openQueryInfoModal: false,
	news_sync: [],
	query_info: null,
	openai_unsync_items_count: 0,
	openai_sync_date: '',
})

const blacklistFields = ['query_info', 'fields', 'categories', 'locations', 'concepts', 'sources', 'news_filtering_instruction'];

const getSettings = () => {
	crud.getItems().then(data => {
		const settings = data.settings as Record<string, any>;
		state.news_sync = settings.news_sync;
		state.openai_unsync_items_count = settings.openai_unsync_items_count;
	})
}

const syncAllNewsNow = () => {
	Dialog.confirm('Are you sure to run all settings?').then(confirmed => {
		if (confirmed) {
			Spinner.activate();
			http
				.post('settings/sync-all')
				.then((response) => {
					Notify.success('All sync settings are push to background tasks to sync news.', 'Success!');
				})
				.catch((error) => {
					const responseData = error.response.data;
					if (responseData.message) {
						Notify.error(responseData.message, 'Error!');
					}
				})
				.finally(() => {
					Spinner.deactivate();
				})
		}
	})
}

const syncNow = (setting: NewsSyncSettingsInterface) => {
	Spinner.activate();
	http
		.post('settings/sync', {option_id: setting.option_id})
		.then((response) => {
			const data = response.data.data;
			const existing_records_ids = data.existing_records_ids.length;
			const new_records_ids = data.new_records_ids.length;
			const records_ids = data.records_ids.length;
			state.news_sync = data.settings.news_sync;
			if (existing_records_ids || new_records_ids) {
				let message = `${records_ids} new articles from news api. ${existing_records_ids} records are already exists. ${new_records_ids} records are synced.`;
				Notify.success(message, 'Success!');
			} else {
				let message = `No new articles found from news api.`;
				Notify.success(message, 'Success!');
			}
		})
		.catch((error) => {
			const responseData = error.response.data;
			if (responseData.message) {
				Notify.error(responseData.message, 'Error!');
			}
		})
		.finally(() => {
			Spinner.deactivate();
		})
}

const syncForADate = () => {
	Dialog.confirm('Are you sure to sync all for date: ' + state.openai_sync_date).then(confirmed => {
		if (confirmed) {
			Spinner.activate();
			http
				.post('settings/sync-with-openai', {date: state.openai_sync_date})
				.then(() => {
					Notify.success('A background task is running to sync news with OpenAI.', 'Success!')
				})
				.catch(error => {
					if (error.response.data.message) {
						Notify.error(error.response.data.message, 'Error!');
					}
				})
				.finally(() => {
					Spinner.deactivate();
				})
		}
	})
}

const queryInfo = (setting: NewsSyncSettingsInterface) => {
	state.query_info = setting.query_info;
	state.openQueryInfoModal = true;
}

const syncOldNews = () => {
	Dialog.confirm('Are you sure to sync all old news? There are ' + state.openai_unsync_items_count + ' items to sync.').then(confirmed => {
		if (confirmed) {
			Spinner.activate();
			http
				.post('settings/sync-with-openai')
				.then(() => {
					Notify.success('A background task is running to sync news with OpenAI.', 'Success!')
				})
				.finally(() => {
					Spinner.deactivate();
				})
		}
	})
}

onMounted(() => {
	getSettings();
})
</script>
