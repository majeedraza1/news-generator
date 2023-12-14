<template>
  <h1 class="wp-heading-inline">OpenAi Logs</h1>
  <hr class="wp-header-end">
  <div>
    <ShaplaTabs alignment="center">
      <ShaplaTab selected name="OpenAI Logs">
        <OpenAiLogs/>
      </ShaplaTab>
      <ShaplaTab name="News Filtering">
        <NewsFiltering/>
      </ShaplaTab>
      <ShaplaTab name="Instagram Logs">
        <InstagramAttemptLog/>
      </ShaplaTab>
      <ShaplaTab name="Linkedin Logs">
        <LinkeditLog/>
      </ShaplaTab>
      <ShaplaTab name="Twitter Logs">
        <TwitterLog/>
      </ShaplaTab>
    </ShaplaTabs>
  </div>
</template>

<script setup lang="ts">

import {ShaplaTab, ShaplaTabs} from "@shapla/vue-components";
import OpenAiLogs from "./OpenAiLogs.vue";
import NewsFiltering from "./NewsFiltering.vue";
import InstagramAttemptLog from "@/admin/news-api/components/InstagramAttemptLog.vue";
import LinkeditLog from "@/admin/news-api/components/LinkeditLog.vue";
import TwitterLog from "@/admin/news-api/components/TwitterLog.vue";
</script>