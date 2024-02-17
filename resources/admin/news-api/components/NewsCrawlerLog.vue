<script setup lang="ts">
defineProps({
  crawlerLog: {type: [Object, Boolean], default: false}
})
</script>

<template>
  <div v-if="crawlerLog">
    <table class="form-table">
      <tr v-for="(info,key) in crawlerLog" :key="key">
        <th>{{ key }}</th>
        <td>
          <div v-html="info"></div>
        </td>
      </tr>
    </table>
  </div>
</template>
