<template>
	<div class="flex">
		<div class="w-1/3 p-2 font-bold">{{ label }}</div>
		<div class="w-2/3 p-2">
			<slot>
				<div v-if="'sync_settings' === label" class="whitespace-pre-wrap bg-gray-200">{{ value }}</div>
				<div v-else v-html="value"></div>
			</slot>
		</div>
	</div>
</template>

<script lang="ts" setup>
defineProps({
	label: {type: String},
	value: {type: [String, Number, Boolean, Object]}
})
</script>

<style scoped>

</style>
