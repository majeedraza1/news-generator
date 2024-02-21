<template>
  <div>
    <div class="mb-4 bg-white p-2 rounded text-base">
      <table class="w-full border border-solid border-gray-100">
        <thead>
        <tr class="bg-gray-100">
          <th>Placeholder</th>
          <th>Description</th>
          <th>Not applicable for</th>
        </tr>
        </thead>
        <tr>
          <th v-html="`{{newsapi:title}}`"></th>
          <td>News title from newsapi.ai</td>
          <td class="text-xs">-</td>
        </tr>
        <tr>
          <th v-html="`{{newsapi:content}}`"></th>
          <td>News content from newsapi.ai</td>
          <td class="text-xs">-</td>
        </tr>
        <tr>
          <th v-html="`{{newsapi:links}}`"></th>
          <td>News references links from newsapi.ai</td>
          <td class="text-xs">-</td>
        </tr>
        <tr>
          <th v-html="`{{title}}`"></th>
          <td>OpenAi generated title. It will use
            <span v-html="`{{newsapi:title}}`" class="font-bold"></span> value if empty.
          </td>
          <td class="text-xs">title</td>
        </tr>
        <tr>
          <th v-html="`{{content}}`"></th>
          <td>
            OpenAi generated news body.
            It will use <span v-html="`{{newsapi:content}}`" class="font-bold"></span> value if empty.
          </td>
          <td class="text-xs">title, focus_keyphrase, body</td>
        </tr>
        <tr>
          <th v-html="`{{focus_keyphrase}}`"></th>
          <td>OpenAi generated focus keyphrase</td>
          <td class="text-xs">title, focus_keyphrase</td>
        </tr>
        <tr>
          <th v-html="`{{ig_heading}}`"></th>
          <td>OpenAi generated instagram heading</td>
          <td class="text-xs">instagram heading</td>
        </tr>
      </table>
    </div>
    <table class="form-table" v-if="Object.keys(props.instructions).length">
      <tr>
        <th><label>Instruction for title</label></th>
        <td>
          <textarea rows="4" class="w-full" v-model="state.instructions.title"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for focus key-phrase</label></th>
        <td>
          <textarea rows="6" class="w-full" v-model="state.instructions.focus_keyphrase"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for body</label></th>
        <td>
          <textarea rows="12" class="w-full" v-model="state.instructions.body"></textarea>
          <p class="description"
             v-html="'Remember to user placeholder {{title}}, {{newsapi:links}} {{openai:title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for meta description</label></th>
        <td>
          <textarea rows="4" class="w-full" v-model="state.instructions.meta"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for tweet</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.tweet"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for facebook</label></th>
        <td>
          <textarea rows="4" class="w-full" v-model="state.instructions.facebook"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for tags</label></th>
        <td>
          <textarea rows="4" class="w-full" v-model="state.instructions.tag"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for tags meta description</label></th>
        <td>
          <textarea rows="3" class="w-full" v-model="state.instructions.tag_meta"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{tag_name}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for news faqs</label></th>
        <td>
          <textarea rows="4" class="w-full" v-model="state.instructions.news_faq"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for tumblr</label></th>
        <td>
          <textarea rows="3" class="w-full" v-model="state.instructions.tumblr"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for medium</label></th>
        <td>
          <textarea rows="3" class="w-full" v-model="state.instructions.medium"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for linkedin</label></th>
        <td>
          <textarea rows="3" class="w-full" v-model="state.instructions.linkedin"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for news filtering</label></th>
        <td>
          <textarea rows="6" class="w-full" v-model="state.instructions.news_filtering"></textarea>
          <p class="description" v-html="'Remember to use placeholder {{news_titles_list}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for category filter</label></th>
        <td>
          <textarea rows="6" class="w-full" v-model="state.instructions.category_filter"></textarea>
          <p class="description"
             v-html="'Remember to user placeholder {{title}}, {{content}} and {{category_list}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for finding news country</label></th>
        <td>
          <textarea rows="10" class="w-full" v-model="state.instructions.news_country"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{title}} and {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for finding important news for tweet</label></th>
        <td>
          <textarea rows="10" class="w-full" v-model="state.instructions.important_news_for_tweet"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{news_titles_list}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for removing blacklist phrase from response</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.remove_blacklist_phrase"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}} and {{suspected_phrase}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for instagram heading</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.instagram_heading"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for instagram subheading</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.instagram_subheading"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}} and {{ig_heading}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for instagram body</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.instagram_body"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for instagram hashtag</label></th>
        <td>
          <textarea rows="5" class="w-full" v-model="state.instructions.instagram_hashtag"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction for finding important news for instagram</label></th>
        <td>
          <textarea rows="10" class="w-full" v-model="state.instructions.important_news_for_instagram"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{news_titles_list}}'"></p>
        </td>
      </tr>
      <tr>
        <th><label>Instruction to beautify news article</label></th>
        <td>
          <textarea rows="10" class="w-full" v-model="state.instructions.beautify_article"></textarea>
          <p class="description" v-html="'Remember to user placeholder {{content}}'"></p>
        </td>
      </tr>
    </table>
  </div>
</template>

<script lang="ts" setup>
import {onMounted, PropType, reactive, watch} from "vue";

const emit = defineEmits(['change:instruction']);
const props = defineProps({
  instructions: {type: Object as PropType<Record<string, string>>, required: true}
})

const state = reactive<{
  instructions: Record<string, string> | {
    title: string;
    body: string;
    meta: string;
    tweet: string;
    facebook: string;
    tag: string;
    tag_meta: string;
    news_faq: string;
    tumblr: string;
    medium: string;
    linkedin: string;
    news_filtering: string;
    focus_keyphrase: string;
    category_filter: string;
    news_country: string;
    important_news_for_tweet: string;
    interesting_tweets: string;
    remove_blacklist_phrase: string;
    instagram_heading: string;
    instagram_subheading: string;
    instagram_body: string;
    important_news_for_instagram: string;
    instagram_hashtag: string;
    beautify_article: string;
  }
}>({
  instructions: {
    title: '',
    body: '',
    meta: '',
    tweet: '',
    facebook: '',
    linkedin: '',
    tag: '',
    tag_meta: '',
    news_filtering: '',
    focus_keyphrase: '',
    category_filter: '',
    news_country: '',
    interesting_tweets: '',
    important_news_for_tweet: '',
    remove_blacklist_phrase: '',
    important_news_for_instagram: '',
    instagram_heading: '',
    instagram_subheading: '',
    instagram_body: '',
    instagram_hashtag: '',
    beautify_article: '',
  }
})

watch(() => props.instructions, newValue => {
  state.instructions = newValue;
}, {deep: true});

watch(
    () => state.instructions,
    newValue => {
      emit('change:instruction', newValue)
    },
    {deep: true}
)

onMounted(() => {
  state.instructions = props.instructions;
})
</script>
