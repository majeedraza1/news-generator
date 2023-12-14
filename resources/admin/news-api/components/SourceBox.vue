<template>
	<div class="shadow-2 p-2 mb-2 border border-solid border-gray-100 hover:border-gray-300 relative"
		 v-if="Object.keys(source).length && source.uri">
		<ShaplaCross v-if="deletable" @click="emitDelete" class="absolute top-1 right-1"/>
		<div>
			<div class="font-bold">{{ source.title }}</div>
			<div>Type: {{ source.dataType }}</div>
			<div>Source ID: {{ source.uri }}</div>
		</div>
		<div>
			<slot></slot>
		</div>
	</div>
</template>

<script lang="ts" setup>
import { PropType} from "vue";
import {SourceInterface} from "../../../utils/interfaces";
import {ShaplaCross} from "@shapla/vue-components";

const props = defineProps({
	source: {type: Object as PropType<SourceInterface>, required: true},
	deletable: {type: Boolean, default: false}
});

const emit = defineEmits(['delete']);

const emitDelete = () => {
	emit('delete', props.source);
}
</script>
