<template>
  <div class="stackonet-admin-app">
    <router-view/>
    <ShaplaConfirmContainer/>
    <ShaplaNotificationContainer/>
    <ShaplaSpinner :is-root-spinner="true"/>
  </div>
</template>

<script lang="ts" setup>
import {ShaplaConfirmContainer, ShaplaNotificationContainer, ShaplaSpinner} from '@shapla/vue-components';</script>

<style lang="scss">
.stackonet-admin-app {
  box-sizing: border-box;

  *, :after, :before {
    box-sizing: border-box;
  }
}
</style>
