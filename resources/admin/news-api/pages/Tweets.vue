<template>
	<h1 class="wp-heading-inline">Tweets</h1>
	<hr class="wp-header-end">
	<div>
		<ShaplaTabs alignment="center">
			<ShaplaTab selected name="Tweets">
				<div class="flex justify-end">
					<ShaplaButton theme="primary" size="small"
								  @click="()=>getTweets(state.tweetsPagination.current_page)">Refresh
					</ShaplaButton>
				</div>
				<div class="my-4">
					<ShaplaTablePagination
						:total-items="state.tweetsPagination.total_items"
						:per-page="state.tweetsPagination.per_page"
						:current-page="state.tweetsPagination.current_page"
						@paginate="paginateTweets"
					/>
				</div>
				<div>
					<ShaplaTable
						:columns="tweetsColumns"
						:items="state.tweets"
						@click:action="onActionClick"
					>
						<template v-slot:tweet_text="data">
							<div class="max-w-xl">{{ data.row.tweet_text }}</div>
						</template>
						<template v-slot:tweet_datetime="data">
							{{ formatISO8601DateTime(data.row.tweet_datetime) }}
						</template>
					</ShaplaTable>
				</div>
				<div class="mt-4">
					<ShaplaTablePagination
						:total-items="state.tweetsPagination.total_items"
						:per-page="state.tweetsPagination.per_page"
						:current-page="state.tweetsPagination.current_page"
						@paginate="paginateTweets"
					/>
				</div>
			</ShaplaTab>
			<ShaplaTab name="Usernames">
				<div class="flex justify-end">
					<ShaplaButton theme="secondary" size="small" @click="state.openAddNewModal = true">Add New
					</ShaplaButton>
				</div>
				<div class="my-2">
					<ShaplaTable
						:columns="columns"
						:actions="[{key:'sync',label:'Sync Tweets'},{key:'delete',label:'Delete'}]"
						:items="state.items"
						@click:action="onActionClick"
					/>
				</div>
			</ShaplaTab>
			<ShaplaTab name="Settings">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="">Sync Interval</label>
						</th>
						<td>
							<input type="number" v-model="state.settings.sync_interval" min="15" max="360" step="5"/>
							<p class="description">Tweets sync interval in minutes. Minimum value is 15(minutes) and
								maximum value is 360(minutes).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							Batch Type
						</th>
						<td>
							<div class="max-w-sm">
								<ShaplaSelect
									:options="objectToSelectOption(state.batch_types)"
									v-model="state.settings.batch_type"
									:clearable="false"
								/>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							Languages
						</th>
						<td>
							<div class="max-w-sm">
								<ShaplaSelect
									:options="objectToSelectOption(state.supported_languages)"
									v-model="state.settings.supported_languages"
									:clearable="false"
									:multiple="true"
									:searchable="true"
								/>
							</div>
							<p class="description">Only tweets from the selected languages (from users) will be store to
								our system for further processing.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							Instruction for important tweets
						</th>
						<td>
							<textarea v-model="state.settings.instruction_for_important_tweets" rows="5"
									  class="w-full"></textarea>
							<p class="description">OpenAI instruction to find important tweets that will be used to
								write news article. Remember to use <span v-html="'{{list_of_tweets}}'"></span>
								placeholder</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							Instruction for tweet to article
						</th>
						<td>
							<textarea v-model="state.settings.instruction_for_tweet_to_article" rows="10"
									  class="w-full"></textarea>
							<p class="description">OpenAI instruction to generate news title and body from tweet.
								Remember to use <span v-html="'{{tweet}}, {{user:name}}, {{user:designation}}'"></span> placeholders</p>
						</td>
					</tr>
				</table>
				<div class="fixed bottom-8 right-8">
					<ShaplaButton fab theme="primary" size="large" @click="saveSettings">
						<ShaplaIcon>
							<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"
								 fill="#000000">
								<path d="M0 0h24v24H0V0z" fill="none"/>
								<path
									d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm2 16H5V5h11.17L19 7.83V19zm-7-7c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zM6 6h9v4H6z"/>
							</svg>
						</ShaplaIcon>
					</ShaplaButton>
				</div>
			</ShaplaTab>
		</ShaplaTabs>
	</div>
	<ShaplaModal title="Add New Username" :active="state.openAddNewModal" @close="closeAddNewModal">
		<ShaplaInput type="text" label="Twitter Username" v-model="state.username"/>
		<template v-slot:foot>
			<ShaplaButton theme="primary" @click="addNewUsername">Create</ShaplaButton>
		</template>
	</ShaplaModal>
</template>

<script setup lang="ts">
import {
	Dialog,
	Notify,
	ShaplaButton,
	ShaplaIcon,
	ShaplaInput,
	ShaplaModal,
	ShaplaSelect,
	ShaplaTab,
	ShaplaTable,
	ShaplaTablePagination,
	ShaplaTabs,
	Spinner
} from "@shapla/vue-components";
import {onMounted, reactive} from "vue";
import CrudOperation, {PaginationDataInterface} from "../../../utils/CrudOperation";
import http from "../../../utils/axios";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";


interface TwitterUsernameInterface {
	id: number;
	user_id: string;
	name: string;
	username: string;
}

interface TwitterTweetsInterface {
	id: number;
	username: string;
	twitter_id: string;
	tweet_text: string;
}

interface TwitterSettingInterface {
	sync_interval: number;
	batch_type: string;
	supported_languages: string[];
	instruction_for_important_tweets: string;
	instruction_for_tweet_to_article: string;
}

const state = reactive<{
	items: TwitterUsernameInterface[]
	tweets: TwitterTweetsInterface[]
	settings: TwitterSettingInterface
	batch_types: Record<string, string>;
	supported_languages: Record<string, string>;
	tweetsPagination: PaginationDataInterface
	openAddNewModal: boolean;
	activeItem: TwitterUsernameInterface;
	username: string;
}>({
	items: [],
	tweets: [],
	settings: {
		sync_interval: 0,
		batch_type: '',
		supported_languages: [],
		instruction_for_tweet_to_article: '',
		instruction_for_important_tweets: '',
	},
	tweetsPagination: {total_items: 0, per_page: 100, current_page: 1, total_pages: 1},
	openAddNewModal: false,
	activeItem: null,
	batch_types: {},
	supported_languages: {},
	username: '',
})

const columns = [
	{key: 'username', label: 'Username'},
	{key: 'user_id', label: 'Twitter ID'},
	{key: 'name', label: 'Name'},
];

const tweetsColumns = [
	{label: 'Tweet Text', key: 'tweet_text'},
	{label: 'Datetime', key: 'tweet_datetime'},
	{label: 'Username', key: 'username'},
];

const crud = new CrudOperation('twitter-usernames', http);
const crudTweet = new CrudOperation('twitter-tweets', http);
const crudSettings = new CrudOperation('twitter-settings', http);

const objectToSelectOption = (object: Record<string, string>) => {
	let _data = []
	if (object) {
		for (const [value, label] of Object.entries(object)) {
			_data.push({label, value})
		}
	}
	return _data;
}

const closeAddNewModal = () => {
	state.openAddNewModal = false;
	state.username = '';
}
const getSettings = () => {
	crudSettings.getItems().then((data) => {
		state.settings = data.settings as TwitterSettingInterface;
		state.batch_types = data.batch_types as Record<string, string>;
		state.supported_languages = data.supported_languages as Record<string, string>;
	})
}
const saveSettings = () => {
	crudSettings.createItem(state.settings).then(settings => {
		state.settings = settings as TwitterSettingInterface;
	})
}
const getItems = () => {
	crud.getItems().then((data) => {
		state.items = data.items as TwitterUsernameInterface[];
	})
}
const getTweets = (page: number = 1) => {
	crudTweet.getItems({
		page: page,
		per_page: state.tweetsPagination.per_page
	}).then((data) => {
		state.tweets = data.items as TwitterTweetsInterface[];
		state.tweetsPagination = data.pagination as PaginationDataInterface;
	})
}
const paginateTweets = (nextPage: number) => {
	getTweets(nextPage);
}
const addNewUsername = () => {
	crud.createItem({username: state.username}).then(() => {
		closeAddNewModal();
		getItems();
	})
}

const syncTweets = (id: number) => {
	Spinner.show();
	http
		.get(`twitter-usernames/${id}/sync`)
		.then(() => {
			Notify.success('Latest tweets has been sync.', 'Success!');
			getTweets(state.tweetsPagination.current_page);
		})
		.finally(() => {
			Spinner.hide();
		})
}
const deleteItem = (id: number) => {
	Dialog.confirm('Are you sure to delete?').then(confirmed => {
		if (confirmed) {
			crud.deleteItem(id).then(() => {
				getItems();
			});
		}
	})
}
const onActionClick = (action: string, item: TwitterUsernameInterface) => {
	if ('sync' === action) {
		syncTweets(item.id);
	}
	if ('delete' === action) {
		deleteItem(item.id);
	}
}

onMounted(() => {
	getTweets();
	getItems();
	getSettings();
})
</script>
