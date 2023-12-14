<template>
	<ShaplaTable
		:show-cb="false"
		:show-expand="true"
		:items="logs"
		:columns="openAiLogsColumns"
	>
		<template v-slot:cellExpand="data">
			<div>
				<div class="mb-2 text-lg">Instruction</div>
				<div class="whitespace-pre-wrap bg-white p-2">{{ data.row.instruction }}</div>
			</div>
			<div class="mt-4">
				<div class="mb-2 text-lg">Api Response</div>
				<div class="whitespace-pre-wrap bg-white p-2">
					<code>{{ data.row.api_response }}</code>
				</div>
			</div>
		</template>
		<template v-slot:response_type="data">
			<span class="text-red-600" v-if="'error' === data.row.response_type">Error</span>
			<span v-else>Success</span>
		</template>
		<template v-slot:created_at="data">
			{{ formatISO8601DateTime(data.row.created_at) }}
		</template>
	</ShaplaTable>
</template>

<script setup lang="ts">
import {ShaplaTable} from "@shapla/vue-components";
import {formatISO8601DateTime} from "../../../utils/humanTimeDiff";
import {PropType} from "vue";

const props = defineProps({
	logs: {type: Array as PropType<Record<string, any>[]>, default: () => []}
});

const openAiLogsColumns = [
	{label: 'Group', key: 'group'},
	{label: 'Error/Success', key: 'response_type'},
	{label: 'Source', key: 'source_type'},
	{label: 'Source ID', key: 'source_id'},
	{label: 'Response Time (seconds)', key: 'total_time'},
	{label: 'Token used', key: 'total_tokens'},
	{label: 'Datetime', key: 'created_at'},
];
</script>
