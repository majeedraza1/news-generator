<template>
	<table class="form-table">
		<tr v-for="(info,key) in article" :key="key">
			<th>{{ key }}</th>
			<td>
				<div v-if="'sync_settings' === key" class="whitespace-pre-wrap bg-gray-200">{{ info }}</div>
				<div v-else v-html="info"></div>
			</td>
		</tr>
	</table>
</template>

<script lang="ts" setup>
import {PropType} from "vue";
import {ArticleInterface} from "../../../utils/interfaces";

defineProps({
	article: {type: Object as PropType<ArticleInterface>, required: true},
	showAdditionalInformation: {type: Boolean, default: true}
});
</script>
