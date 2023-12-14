<template>
  <div class="shadow-2 p-2 mb-2 border border-solid border-gray-100 hover:border-gray-300 relative"
       v-if="Object.keys(category).length">
    <ShaplaCross v-if="deletable" @click="emitDelete" class="absolute top-1 right-1"/>
    <div class="font-bold">{{ category.label }}</div>
    <div>ID: {{ category.uri }}</div>
    <div>Parent ID: {{ category.parentUri }}</div>
  </div>
</template>

<script lang="ts" setup>
import { PropType} from "vue";
import {CategoryInterface} from "../../../utils/interfaces";
import {ShaplaCross} from "@shapla/vue-components";

const props = defineProps({
  category: {type: Object as PropType<CategoryInterface>, required: true},
  deletable: {type: Boolean, default: false}
});

const emit = defineEmits(['delete']);

const emitDelete = () => {
  emit('delete', props.category);
}
</script>
