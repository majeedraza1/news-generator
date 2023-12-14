<template>
  <div>
    <ShaplaColumns>
      <ShaplaColumn>
        <h2 class="font-bold mb-4">{{ article.title }}</h2>
      </ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for title"
              :modelValue="instructions.title"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-if="state.title">{{ state.title }}</div>
      </ShaplaColumn>
    </ShaplaColumns>
    <ShaplaColumns>
      <ShaplaColumn>
        <div v-html="article.body"/>
      </ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for content"
              :modelValue="instructions.body"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-html="state.body"/>
      </ShaplaColumn>
    </ShaplaColumns>
    <ShaplaColumns>
      <ShaplaColumn></ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for meta description"
              :modelValue="instructions.meta"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-html="state.meta"/>
      </ShaplaColumn>
    </ShaplaColumns>
    <ShaplaColumns>
      <ShaplaColumn></ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for tweet"
              :modelValue="instructions.tweet"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-html="state.tweet"/>
      </ShaplaColumn>
    </ShaplaColumns>
    <ShaplaColumns>
      <ShaplaColumn></ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for facebook"
              :modelValue="instructions.facebook"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-html="state.facebook"/>
      </ShaplaColumn>
    </ShaplaColumns>
    <ShaplaColumns>
      <ShaplaColumn></ShaplaColumn>
      <ShaplaColumn>
        <div class="mb-2">
          <ShaplaInput
              type="textarea"
              label="Instruction for tag"
              :modelValue="instructions.tag"
              :readonly="true"
              :rows="2"
          />
        </div>
        <div v-html="state.tag"/>
      </ShaplaColumn>
    </ShaplaColumns>
  </div>
</template>

<script lang="ts" setup>
import {computed, onMounted, PropType, reactive, watch} from "vue";
import {ArticleInterface, OpenAiNewsInterface} from "../../../utils/interfaces";
import {Notify, ShaplaButton, ShaplaColumn, ShaplaColumns, ShaplaInput, Spinner} from "@shapla/vue-components";
import http from "../../../utils/axios";

const emit = defineEmits(['open']);
const props = defineProps({
  article: {type: Object as PropType<ArticleInterface>, required: true},
  news: {type: Object as PropType<OpenAiNewsInterface>}
});

const state = reactive<Record<string, string>>({
  title: '',
  body: '',
  meta: '',
  tweet: '',
  facebook: '',
  tag: '',
})

const canCreateAiNews = computed(() => {
  return (state.title.length > 5 && state.body.length > 100 && state.meta.length > 10 && state.tweet.length > 10 &&
      state.facebook.length > 10 && state.tag.length > 10)
})

const instructions = computed(() => window.TeraPixelNewsGenerator.instructions)

watch(() => canCreateAiNews, newValue => {
  emit('open', newValue)
})

watch(() => props.article, () => {
  if (props.article && props.article.openai_news) {
    state.title = props.article.openai_news.title;
    state.body = props.article.openai_news.content;
    state.meta = props.article.openai_news.meta_description;
    state.tweet = props.article.openai_news.tweet;
    state.facebook = props.article.openai_news.facebook_text;
    state.tag = props.article.openai_news.tags.toString();
  }
}, {deep: true})

onMounted(() => {
  if (props.article && props.article.openai_news) {
    state.title = props.article.openai_news.title;
    state.body = props.article.openai_news.content;
    state.meta = props.article.openai_news.meta_description;
    state.tweet = props.article.openai_news.tweet;
    state.facebook = props.article.openai_news.facebook_text;
    state.tag = props.article.openai_news.tags.toString();
  }
})

const checkOpenApi = (field: string) => {
  Spinner.activate();
  http
      .post('openai/completions', {
        id: props.article.id,
        field: field
      })
      .then(response => {
        state[field] = response.data.data.output;
      })
      .catch(error => {
        if (error.response.data.message) {
          Notify.error(error.response.data.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.deactivate();
      })
}
</script>