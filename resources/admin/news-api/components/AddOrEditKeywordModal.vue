<script setup lang="ts">
import {ShaplaButton, ShaplaInput, ShaplaModal} from "@shapla/vue-components";
import {PropType, reactive, watch} from "vue";
import {ExistingKeywordInterface, KeywordInterface} from "../../../utils/interfaces";

const props = defineProps({
  active: {type: Boolean, default: false},
  keyword: {type: Object as PropType<ExistingKeywordInterface | KeywordInterface>, default: false}
});

const state = reactive({
  keyword: {
    keyword: '',
    instruction: '',
  }
})

const emit = defineEmits<{
  (e: 'close'): void,
  (e: 'submit', value: KeywordInterface | ExistingKeywordInterface): void
}>()

const emitClose = () => emit('close');
const submitKeyword = () => emit('submit', state.keyword);

watch(() => props.keyword, (newValue) => {
  state.keyword = newValue
}, {deep: true})
</script>

<template>
  <ShaplaModal :active="active" title="Add New Keyword" @close="emitClose">
    <div class="mb-2">
      <ShaplaInput label="Keyword" v-model="keyword.keyword"/>
    </div>
    <div class="mb-2">
      <ShaplaInput type="textarea" label="Instruction" v-model="keyword.instruction"/>
      <p class="description">
        <span>Leave it empty to use default/global instruction.</span><br>
        <span v-html="`Remember to user placeholder {{keyword}} to get keyword`"></span><br>
        Remember to include the following line bottom of your instruction<br>
        Add [Title:], [Meta Description:] and [Content:] respectively when starting each section.
      </p>
    </div>
    <template v-slot:foot>
      <ShaplaButton theme="primary" @click="submitKeyword">Submit</ShaplaButton>
    </template>
  </ShaplaModal>
</template>
