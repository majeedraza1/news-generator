<template>
  <div class="shadow-2 p-2 mb-2 border border-solid border-gray-100 hover:border-gray-300 relative"
       v-if="Object.keys(location).length && location.wikiUri">
    <ShaplaCross v-if="deletable" @click="emitDelete" class="absolute top-1 right-1"/>
    <div class="font-bold">{{ location.label.eng }}</div>
    <div>Type: {{ location.type }}</div>
    <div>Location ID: {{ location.wikiUri }}</div>
  </div>
</template>

<script lang="ts" setup>
import { PropType} from "vue";
import {LocationInterfaces} from "../../../utils/interfaces";
import {ShaplaCross} from "@shapla/vue-components";

const props = defineProps({
  location: {type: Object as PropType<LocationInterfaces>, required: true},
  deletable: {type: Boolean, default: false}
});

const emit = defineEmits(['delete']);

const emitDelete = () => {
  emit('delete', props.location);
}
</script>
