<template>
  <div class="shadow-2 p-2 mb-2 border border-solid border-gray-100 hover:border-gray-300 relative"
       v-if="Object.keys(concept).length">
    <ShaplaCross v-if="deletable" @click="emitDelete" class="absolute top-1 right-1"/>
    <div class="font-bold">{{ concept.label.eng }}</div>
    <div>Type: {{ concept.type }}</div>
    <div>Concept ID: {{ concept.uri }}</div>
  </div>
</template>

<script lang="ts" setup>
import { PropType} from "vue";
import {ConceptInterfaces} from "../../../utils/interfaces";
import {ShaplaCross} from "@shapla/vue-components";

const props = defineProps({
  concept: {type: Object as PropType<ConceptInterfaces>, required: true},
  deletable: {type: Boolean, default: false}
});

const emit = defineEmits(['delete']);

const emitDelete = () => {
  emit('delete', props.concept);
}
</script>
