<script setup lang="ts">
import {Dialog, Notify, ShaplaButton, Spinner} from "@shapla/vue-components";
import {reactive} from "vue";
import http from "../../../utils/axios";

const state = reactive({
  min_count_to_delete: 3,
  min_count_to_generate_meta: 10,
  limit_batch_generate: 20,
})

const runBatchOperation = (payload) => {
  return new Promise(resolve => {
    Spinner.show();
    http
      .post('openai/news-tags/batch', payload)
      .then(response => {
        const data = response.data.data;
        resolve(data);
      })
      .catch(error => {
        if (error.response.data.message) {
          Notify.error(error.data.message, 'Error!');
        }
      })
      .finally(() => {
        Spinner.hide();
      })
  })
}

const deleteNewsTags = () => {
  Dialog.confirm(`Are you sure to delete tags that do not have ${state.min_count_to_delete} linked news?`).then(confirmed => {
    if (confirmed) {
      runBatchOperation({action: 'delete', min_count: state.min_count_to_delete}).then((data: { count: number; }) => {
        if (data.count) {
          Notify.success(`${data.count} tags has been deleted.`, 'Success!');
        }
      })
    }
  })
}

const generateTagsFromNews = () => {
  Dialog.confirm(`Are you sure to regenerate tags from news?`).then(confirmed => {
    if (confirmed) {
      runBatchOperation({action: 'copy_from_existing_news', limit: state.limit_batch_generate}).then(() => {
        Notify.success(`A background task is running to generate tags from news.`, 'Success!');
      })
    }
  })
}

const generateMetaDescription = () => {
  Dialog.confirm(`Are you sure to regenerate meta description for tags?`).then(confirmed => {
    if (confirmed) {
      runBatchOperation({
        action: 'generate_meta_description',
        min_count: state.min_count_to_generate_meta
      }).then(() => {
        Notify.success(`A background task is running to generate tags meta description.`, 'Success!');
      })
    }
  })
}

const sendTagsToSites = () => {
  Dialog.confirm(`Are you sure to send tags to all sites?`).then(confirmed => {
    if (confirmed) {
      runBatchOperation({action: 'send_to_sites'}).then(() => {
        Notify.success(`A background task is running to send tags meta description to sites.`, 'Success!');
      })
    }
  })
}
</script>

<template>
    <div class="shadow-2 bg-white rounded p-2 flex space-x-4 mb-2">
        <table class="form-table">
            <tr>
                <th>Sync tags from News</th>
                <td>
                    <div class="flex items-center space-x-2">
                        <input type="number" v-model="state.limit_batch_generate" min="20" max="200">
                        <ShaplaButton size="small" theme="primary" @click="generateTagsFromNews">Generate</ShaplaButton>
                    </div>
                    <p class="description">Generate tags (if not generated yet) from existing news.</p>
                </td>
            </tr>
            <tr>
                <th>Delete News Tags</th>
                <td>
                    <div class="flex items-center space-x-2">
                        <input type="number" v-model="state.min_count_to_delete">
                        <ShaplaButton size="small" theme="primary" @click="deleteNewsTags">Delete</ShaplaButton>
                    </div>
                    <p class="description">Delete news tags that do not have minimum required news. Minimum value is 1
                        and maximum value is 10.</p>
                </td>
            </tr>
            <tr>
                <th>Generate Tags meta description (from OpenAI)</th>
                <td>
                    <div class="flex items-center space-x-2">
                        <input type="number" v-model="state.min_count_to_generate_meta" min="1">
                        <ShaplaButton size="small" theme="primary" @click="generateMetaDescription">Generate
                        </ShaplaButton>
                    </div>
                    <p class="description">Generate meta description for tags based on minimum count value of tags.</p>
                </td>
            </tr>
            <tr>
                <th>Send tags to sites</th>
                <td>
                    <div class="flex items-center space-x-2">
                        <ShaplaButton size="small" theme="primary" @click="sendTagsToSites">Start Sending</ShaplaButton>
                    </div>
                    <p class="description">Update meta description to all sites.</p>
                </td>
            </tr>
        </table>
    </div>
</template>

<style scoped lang="scss">

</style>