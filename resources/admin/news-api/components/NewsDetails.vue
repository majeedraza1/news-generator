<template>
	<table class="form-table">
		<template v-for="(value,label) in props.news">
			<tr v-if="!blacklist.includes(label)" :key="`key-${label}`">
				<th>{{ label }}</th>
				<td>
					<template v-if="['category','openai_category'].includes(label)">
						<ShaplaChip v-if="news.category">{{ news.category.name }}</ShaplaChip>
					</template>
					<template v-else-if="'tags' === label">
						<ShaplaChip v-for="tag in news.tags">{{ tag }}</ShaplaChip>
					</template>
					<template v-else-if="'image' === label">
						<div v-if="news.image_id" style="max-width: 300px;">
							<ShaplaImage :width-ratio="news.image.width" :height-ratio="news.image.height">
								<img :src="news.image.url" alt="">
							</ShaplaImage>
						</div>
						<div v-else>No image found</div>
					</template>
					<template v-else-if="pre.includes(label)">
						<div style="white-space: pre-line">{{ value }}</div>
					</template>
					<template v-else-if="'country' === label">
						<pre><code>{{ value }}</code></pre>
					</template>
					<template v-else-if="'remote_log' === label">
						<div v-for="log in remote_log" :key="log.id"
							 class="shadow rounded border border-solid border-gray-300 p-2">
							<div>
								<span>Remote Site URL:</span>
								<strong>{{ log.remote_site_url }}</strong>
							</div>
							<div>
								<span>News URL:</span>
								<strong><a target="_blank" :href="log.remote_news_url">{{ log.remote_news_url }}</a></strong>
							</div>
							<div>
								<span>Sent on:</span>
								<strong>{{ formatISO8601DateTime(log.created_at) }}</strong>
							</div>
						</div>
					</template>
					<template v-else>
						{{ value }}
					</template>
				</td>
			</tr>
		</template>
	</table>
</template>
<script lang="ts" setup>
import {computed, PropType} from "vue";
import {NewsToSiteLogInterface, OpenAiNewsInterface} from "../../../utils/interfaces";
import {ShaplaChip, ShaplaImage} from "@shapla/vue-components";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";

const props = defineProps({
	news: {type: Object as PropType<OpenAiNewsInterface>, default: () => ({})}
});
const blacklist = ['sync_step_done', 'source_uri'];
const pre = ['content', 'facebook_text', 'tweet', 'tumblr_text', 'medium_text'];
const remote_log = computed<NewsToSiteLogInterface[]>(() => props.news.remote_log)
</script>
